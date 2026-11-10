<?php
/**
 * AuthService - Gestor Integral de Autenticación de Clientes
 * Compatible con Login Tradicional (Email/Pass) y Google Identity Services (GIS/OAuth)
 * SinNotario.cl - Keystone Advisors
 */
require_once __DIR__ . '/../../database/Database.php';

class AuthService {
    private static $pdo = null;

    private static function getDb() {
        if (self::$pdo === null) {
            self::$pdo = Database::getInstance();
        }
        return self::$pdo;
    }

    /**
     * Iniciar sesión segura de PHP
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            session_start();
        }
    }

    /**
     * Obtener el usuario autenticado actual o null
     */
    public static function getCurrentUser() {
        self::startSession();

        // 1. Verificar sesión nativa PHP
        if (isset($_SESSION['sinnotario_user_id'])) {
            $stmt = self::getDb()->prepare("SELECT id, rut, nombre, apellido, email, telefono, tipo_persona, auth_provider, avatar_url, empresa_razon_social, empresa_rut, email_verificado, created_at FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['sinnotario_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return $user;
            }
        }

        // 2. Verificar token de cookie persistente (Remember Me / Session token)
        $cookieToken = $_COOKIE['sn_auth_token'] ?? null;
        if ($cookieToken) {
            $stmt = self::getDb()->prepare("
                SELECT u.id, u.rut, u.nombre, u.apellido, u.email, u.telefono, u.tipo_persona, u.auth_provider, u.avatar_url, u.empresa_razon_social, u.empresa_rut, u.email_verificado, u.created_at
                FROM user_sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.token = ? AND s.expires_at > datetime('now')
            ");
            $stmt->execute([$cookieToken]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['sinnotario_user_id'] = $user['id'];
                $_SESSION['sinnotario_user_email'] = $user['email'];
                return $user;
            }
        }

        return null;
    }

    /**
     * Iniciar sesión con email y contraseña (Paso 1 del 2FA WhatsApp OTP - SOL-0123)
     */
    public static function loginWithPassword($email, $password, $remember = true) {
        $email = strtolower(trim($email));
        if (empty($email) || empty($password)) {
            throw new Exception("Debes ingresar correo electrónico y contraseña.");
        }

        $stmt = self::getDb()->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("No existe una cuenta asociada a este correo electrónico.");
        }

        if (empty($user['password_hash'])) {
            if ($user['auth_provider'] === 'GOOGLE') {
                throw new Exception("Esta cuenta fue creada con Google. Por favor, haz clic en 'Continuar con Google' para ingresar.");
            }
            throw new Exception("La cuenta no posee contraseña configurada.");
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception("Contraseña incorrecta. Por favor verifica tus credenciales.");
        }

        // Si el usuario tiene teléfono celular registrado, iniciar desafío 2FA vía WhatsApp OTP
        $userPhone = !empty($user['telefono']) ? trim($user['telefono']) : '';
        if (!empty($userPhone)) {
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = password_hash($otp, PASSWORD_BCRYPT);

            $stmtOtp = self::getDb()->prepare("UPDATE users SET otp_code = ?, otp_expires_at = datetime('now', '+5 minutes') WHERE id = ?");
            $stmtOtp->execute([$otpHash, $user['id']]);

            // Despachar OTP vía WhatsApp
            try {
                require_once __DIR__ . '/WhatsAppService.php';
                $wa = new WhatsAppService();
                $nombreCompleto = trim($user['nombre'] . ' ' . $user['apellido']);
                $msg = "🛡️ *SinNotario.cl Security (2FA)*\n\nHola {$nombreCompleto},\nTu código de verificación para ingresar a tu Bóveda es:\n\n👉 *{$otp}*\n\nEste código es válido por 5 minutos.";
                $wa->sendTextMessage($userPhone, $msg, 'keystone-general', null, $nombreCompleto, '2FA_OTP');
            } catch (\Throwable $eWa) {
                error_log("Error despachando 2FA WhatsApp en SinNotario: " . $eWa->getMessage());
            }

            // Guardar estado pendiente en sesión temporal
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['2fa_pending_uid'] = $user['id'];
            $_SESSION['2fa_pending_email'] = $user['email'];
            $_SESSION['2fa_pending_remember'] = $remember;

            $maskedPhone = substr($userPhone, 0, 4) . '***' . substr($userPhone, -4);

            return [
                'requires_2fa' => true,
                'email' => $user['email'],
                'masked_phone' => $maskedPhone,
                'message' => "Código de 6 dígitos enviado a tu WhatsApp ({$maskedPhone})"
            ];
        }

        // Si no tiene teléfono configurado aún, conceder sesión directa
        return self::establishSession($user, $remember);
    }

    /**
     * Verificar código 2FA WhatsApp OTP (Paso 2 - SOL-0123)
     */
    public static function verifyOtp($email, $otpEntered) {
        $email = strtolower(trim($email));
        $otpEntered = trim($otpEntered);

        if (empty($email) || empty($otpEntered)) {
            throw new Exception("Debes ingresar el código de 6 dígitos.");
        }

        $stmt = self::getDb()->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['otp_code'])) {
            throw new Exception("Sesión de verificación expirada o inválida. Inicia sesión nuevamente.");
        }

        // Verificar expiración
        $stmtExp = self::getDb()->prepare("SELECT datetime('now') > ?");
        $stmtExp->execute([$user['otp_expires_at']]);
        if ((int)$stmtExp->fetchColumn() === 1) {
            throw new Exception("El código de 6 dígitos ha expirado (validez de 5 minutos). Solicita uno nuevo.");
        }

        if (!password_verify($otpEntered, $user['otp_code'])) {
            throw new Exception("Código de verificación incorrecto. Revisa tu WhatsApp.");
        }

        // Limpiar OTP en base de datos
        $stmtClean = self::getDb()->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
        $stmtClean->execute([$user['id']]);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $remember = !empty($_SESSION['2fa_pending_remember']);
        unset($_SESSION['2fa_pending_uid'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_pending_remember']);

        return self::establishSession($user, $remember);
    }

    /**
     * Reenviar código OTP 2FA por WhatsApp
     */
    public static function resendOtp($email) {
        $email = strtolower(trim($email));
        $stmt = self::getDb()->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['telefono'])) {
            throw new Exception("Usuario no encontrado o sin celular registrado.");
        }

        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);

        $stmtOtp = self::getDb()->prepare("UPDATE users SET otp_code = ?, otp_expires_at = datetime('now', '+5 minutes') WHERE id = ?");
        $stmtOtp->execute([$otpHash, $user['id']]);

        require_once __DIR__ . '/WhatsAppService.php';
        $wa = new WhatsAppService();
        $nombreCompleto = trim($user['nombre'] . ' ' . $user['apellido']);
        $msg = "🛡️ *SinNotario.cl Security (2FA)*\n\nHola {$nombreCompleto},\nTu nuevo código de verificación es:\n\n👉 *{$otp}*\n\nExpira en 5 minutos.";
        $wa->sendTextMessage($user['telefono'], $msg, 'keystone-general', null, $nombreCompleto, '2FA_OTP');

        return true;
    }

    /**
     * Registro con correo y contraseña
     */
    public static function registerUser($nombre, $apellido, $email, $password, $rut = null, $telefono = null, $empresaRazonSocial = null, $empresaRut = null) {
        $email = strtolower(trim($email));
        $nombre = trim($nombre);
        $apellido = trim($apellido);
        $rut = $rut ? trim($rut) : null;
        $telefono = $telefono ? trim($telefono) : null;
        $empresaRazonSocial = $empresaRazonSocial ? trim($empresaRazonSocial) : null;
        $empresaRut = $empresaRut ? trim($empresaRut) : null;

        if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
            throw new Exception("Por favor completa los campos obligatorios (Nombre, Apellidos, Correo y Contraseña).");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico es inválido.");
        }

        if (strlen($password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        $stmtCheck = self::getDb()->prepare("SELECT id, auth_provider FROM users WHERE LOWER(email) = ?");
        $stmtCheck->execute([$email]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            throw new Exception("Ya existe una cuenta registrada con el correo {$email}. Por favor inicia sesión.");
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);

        $tipoPersona = (!empty($empresaRazonSocial) || !empty($empresaRut)) ? 'JURIDICA' : 'NATURAL';

        $stmt = self::getDb()->prepare("
            INSERT INTO users (nombre, apellido, email, rut, telefono, password_hash, tipo_persona, empresa_razon_social, empresa_rut, auth_provider, created_at, updated_at, ultimo_login)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'LOCAL', datetime('now'), datetime('now'), datetime('now'))
        ");
        $stmt->execute([$nombre, $apellido, $email, $rut, $telefono, $passwordHash, $tipoPersona, $empresaRazonSocial, $empresaRut]);
        $userId = self::getDb()->lastInsertId();

        // Vincular contratos preexistentes donde figure este correo
        self::linkExistingDocumentsToUser($userId, $email);

        // Auto-crear empresa B2B con bolsa prepagada inicial de bienvenida
        try {
            require_once __DIR__ . '/B2BService.php';
            require_once __DIR__ . '/MailService.php';

            $razon = $empresaRazonSocial ?: ($nombre . ' ' . $apellido . ' SpA');
            $rutEmp = $empresaRut ?: ($rut ?: '78.089.686-8');

            $comp = B2BService::getOrCreateCompany($userId, [
                'rut' => $rutEmp,
                'razon_social' => $razon,
                'rep_legal_nombre' => $nombre . ' ' . $apellido,
                'rep_legal_rut' => $rut ?: '17.297.004-4',
                'rep_legal_email' => $email
            ]);

            // Enviar correo de bienvenida institucional con enlace al manual PDF
            MailService::sendB2BWelcome($email, $nombre . ' ' . $apellido, $comp['razon_social'], $comp['rut']);
        } catch (Exception $eB2b) {
            error_log("Error en auto-registro B2B: " . $eB2b->getMessage());
        }

        $stmtUser = self::getDb()->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $newUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        return self::establishSession($newUser, true);
    }

    /**
     * Autenticación con Google (Token JWT credential de Google Identity Services)
     */
    public static function loginWithGoogle($idToken) {
        if (empty($idToken)) {
            throw new Exception("Token de Google no recibido.");
        }

        // Decodificar el token JWT de Google
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new Exception("Token de Google malformado.");
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        if (!$payload || !isset($payload['email'])) {
            throw new Exception("No fue posible validar la identidad con Google.");
        }

        // Verificar expiración del token
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception("La sesión de Google ha expirado. Por favor reintenta.");
        }

        $email = strtolower(trim($payload['email']));
        $googleId = $payload['sub'] ?? null;
        $nombreCompleto = $payload['name'] ?? 'Usuario';
        $nombre = $payload['given_name'] ?? $nombreCompleto;
        $apellido = $payload['family_name'] ?? '';
        $avatarUrl = $payload['picture'] ?? null;
        $emailVerified = !empty($payload['email_verified']);

        // Buscar si el usuario ya existe por email
        $stmt = self::getDb()->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Actualizar credencial de Google y avatar
            $stmtUpd = self::getDb()->prepare("
                UPDATE users 
                SET google_id = COALESCE(google_id, ?),
                    avatar_url = COALESCE(?, avatar_url),
                    ultimo_login = datetime('now'),
                    email_verificado = 1
                WHERE id = ?
            ");
            $stmtUpd->execute([$googleId, $avatarUrl, $user['id']]);

            // Recargar datos
            $stmtReload = self::getDb()->prepare("SELECT * FROM users WHERE id = ?");
            $stmtReload->execute([$user['id']]);
            $user = $stmtReload->fetch(PDO::FETCH_ASSOC);
        } else {
            // Crear nuevo usuario desde Google
            $stmtIns = self::getDb()->prepare("
                INSERT INTO users (nombre, apellido, email, google_id, avatar_url, auth_provider, email_verificado, created_at, updated_at, ultimo_login)
                VALUES (?, ?, ?, ?, ?, 'GOOGLE', 1, datetime('now'), datetime('now'), datetime('now'))
            ");
            $stmtIns->execute([$nombre, $apellido, $email, $googleId, $avatarUrl]);
            $userId = self::getDb()->lastInsertId();

            $stmtUser = self::getDb()->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        }

        // Vincular documentos preexistentes creados con este email
        self::linkExistingDocumentsToUser($user['id'], $email);

        return self::establishSession($user, true);
    }

    /**
     * Solicitar recuperación de contraseña (Genera token seguro y envía email)
     */
    public static function requestPasswordReset($email) {
        $email = strtolower(trim($email));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Por favor ingresa un correo electrónico válido.");
        }

        $stmt = self::getDb()->prepare("SELECT id, nombre, apellido, email, auth_provider FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Por seguridad, retornamos éxito genérico para evitar enumeración de correos
            return true;
        }

        if ($user['auth_provider'] === 'GOOGLE') {
            throw new Exception("Esta cuenta utiliza autenticación con Google. Por favor ingresa haciendo clic en 'Continuar con Google'.");
        }

        // Invalidar tokens previos de este tipo
        $stmtDel = self::getDb()->prepare("DELETE FROM user_tokens WHERE user_id = ? AND tipo = 'PASSWORD_RESET'");
        $stmtDel->execute([$user['id']]);

        // Generar nuevo token seguro y código
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtToken = self::getDb()->prepare("
            INSERT INTO user_tokens (user_id, tipo, token, expires_at, utilizado, created_at)
            VALUES (?, 'PASSWORD_RESET', ?, ?, 0, datetime('now'))
        ");
        $stmtToken->execute([$user['id'], $token, $expires]);

        // Construir link de recuperación
        $baseUrl = getenv('APP_URL') ?: 'https://sinnotario.cl';
        $resetLink = rtrim($baseUrl, '/') . "/recuperar.php?token=" . urlencode($token);

        require_once __DIR__ . '/MailService.php';
        MailService::sendPasswordReset($user['email'], $user['nombre'], $resetLink, $token);

        return true;
    }

    /**
     * Validar token de recuperación de contraseña
     */
    public static function validatePasswordResetToken($token) {
        if (empty($token)) {
            return false;
        }

        $stmt = self::getDb()->prepare("
            SELECT t.*, u.email, u.nombre, u.apellido
            FROM user_tokens t
            JOIN users u ON u.id = t.user_id
            WHERE t.token = ? AND t.tipo = 'PASSWORD_RESET' AND t.utilizado = 0 AND t.expires_at > datetime('now')
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Restablecer contraseña con token válido
     */
    public static function resetPasswordWithToken($token, $newPassword) {
        if (empty($newPassword) || strlen($newPassword) < 6) {
            throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
        }

        $tokenData = self::validatePasswordResetToken($token);
        if (!$tokenData) {
            throw new Exception("El enlace de recuperación es inválido o ha expirado. Por favor solicita uno nuevo.");
        }

        $userId = $tokenData['user_id'];
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 11]);

        self::getDb()->beginTransaction();
        try {
            // Actualizar contraseña
            $stmtUpd = self::getDb()->prepare("
                UPDATE users 
                SET password_hash = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmtUpd->execute([$newHash, $userId]);

            // Marcar token como utilizado
            $stmtToken = self::getDb()->prepare("UPDATE user_tokens SET utilizado = 1 WHERE id = ?");
            $stmtToken->execute([$tokenData['id']]);

            // Revocar sesiones activas previas por seguridad
            $stmtSess = self::getDb()->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmtSess->execute([$userId]);

            self::getDb()->commit();
            return true;
        } catch (Exception $e) {
            self::getDb()->rollBack();
            throw new Exception("Error al actualizar la contraseña: " . $e->getMessage());
        }
    }

    /**
     * Establecer sesión local y generar token persistente
     */
    private static function establishSession($user, $remember = true) {
        self::startSession();
        $_SESSION['sinnotario_user_id'] = $user['id'];
        $_SESSION['sinnotario_user_email'] = $user['email'];
        $_SESSION['sinnotario_user_nombre'] = $user['nombre'] . ' ' . $user['apellido'];

        // Token persistente en base de datos
        $sessionToken = bin2hex(random_bytes(32));
        $expires = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+1 day'));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $stmtSess = self::getDb()->prepare("
            INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtSess->execute([$user['id'], $sessionToken, $ip, $ua, $expires]);

        if ($remember) {
            $cookieExpires = time() + (86400 * 30);
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('sn_auth_token', $sessionToken, $cookieExpires, '/', '', $secure, true);
        }

        // Registrar timestamp de último login
        $stmtLogin = self::getDb()->prepare("UPDATE users SET ultimo_login = datetime('now') WHERE id = ?");
        $stmtLogin->execute([$user['id']]);

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'avatar_url' => $user['avatar_url'] ?? null,
            'rut' => $user['rut'] ?? null
        ];
    }

    /**
     * Vincular contratos que se crearon con el correo del usuario antes de que se registrara
     */
    public static function linkExistingDocumentsToUser($userId, $email) {
        $email = strtolower(trim($email));
        if (empty($email) || empty($userId)) return;

        try {
            // Documentos donde este email sea un firmante
            $stmtFind = self::getDb()->prepare("
                SELECT DISTINCT document_id FROM document_signers WHERE LOWER(email) = ?
            ");
            $stmtFind->execute([$email]);
            $docIds = $stmtFind->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($docIds)) {
                $inQuery = implode(',', array_map('intval', $docIds));
                self::getDb()->exec("UPDATE documents SET user_id = {$userId} WHERE id IN ({$inQuery}) AND (user_id IS NULL OR user_id = 0)");
            }
        } catch (Exception $e) {
            error_log("Error linking documents to user: " . $e->getMessage());
        }
    }

    /**
     * Cerrar sesión
     */
    public static function logout() {
        self::startSession();

        if (isset($_COOKIE['sn_auth_token'])) {
            $token = $_COOKIE['sn_auth_token'];
            $stmt = self::getDb()->prepare("DELETE FROM user_sessions WHERE token = ?");
            $stmt->execute([$token]);
            setcookie('sn_auth_token', '', time() - 3600, '/');
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
