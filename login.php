<?php
/**
 * SinNotario.cl - Portal de Acceso y Registro de Clientes
 * Compatible con Login Google One Tap / GIS y Credenciales Tradicionales
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/database/Database.php';

$currentUser = AuthService::getCurrentUser();
if ($currentUser) {
    header("Location: mi-cuenta.php");
    exit;
}

$tab = isset($_GET['tab']) && $_GET['tab'] === 'registro' ? 'registro' : 'login';
$redirectUrl = isset($_GET['redirect']) ? htmlspecialchars($_GET['redirect']) : 'mi-cuenta.php';
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// Consultar Google Client ID configurado (en BD o .env)
$pdo = Database::getInstance();
$stmtG = $pdo->query("SELECT value FROM system_settings WHERE key = 'google_oauth_client_id'");
$googleClientId = $stmtG ? $stmtG->fetchColumn() : '';
if (empty($googleClientId)) {
    $googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión o Crear Cuenta | SinNotario.cl</title>
    <meta name="description" content="Accede a tu bóveda personal de contratos y documentos con firma digital certificada en SinNotario.cl.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Principales -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_3">
    
    <!-- Google Identity Services (GIS) para Google Sign-In oficial -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        .auth-wrapper {
            min-height: calc(100vh - 120px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
        }
        .auth-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 42, 74, 0.08);
            border: 1px solid #E2E8F0;
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        .auth-header {
            background: #0F2A4A;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            color: #FFFFFF;
        }
        .auth-header img {
            height: 48px;
            margin-bottom: 0.75rem;
            filter: brightness(0) invert(1);
        }
        .auth-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: #FFFFFF;
        }
        .auth-header p {
            font-size: 0.85rem;
            color: #94A3B8;
            margin: 0;
        }
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid #E2E8F0;
            background: #F8FAFC;
        }
        .auth-tab-btn {
            flex: 1;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            color: #64748B;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
        }
        .auth-tab-btn.active {
            color: #0066CC;
            background: #FFFFFF;
            border-bottom-color: #0066CC;
        }
        .auth-body {
            padding: 2rem;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            background: #FFFFFF;
            color: #1E293B;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-google:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .btn-google svg {
            width: 20px;
            height: 20px;
        }
        .auth-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #94A3B8;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .auth-divider::before, .auth-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E2E8F0;
        }
        .auth-divider:not(:empty)::before {
            margin-right: .75em;
        }
        .auth-divider:not(:empty)::after {
            margin-left: .75em;
        }
        .form-floating-custom {
            margin-bottom: 1.25rem;
        }
        .form-floating-custom label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .form-floating-custom input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1E293B;
            box-sizing: border-box;
            transition: border-color 0.2s ease;
        }
        .form-floating-custom input:focus {
            outline: none;
            border-color: #0066CC;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.12);
        }
        .btn-submit-auth {
            width: 100%;
            padding: 0.85rem 1.5rem;
            background: #0066CC;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-submit-auth:hover {
            background: #004C99;
            transform: translateY(-1px);
        }
        .auth-alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: none;
        }
        .auth-alert.error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #F87171;
        }
        .auth-alert.success {
            background: #F0FDF4;
            color: #166534;
            border: 1px solid #86EFAC;
        }
    </style>
</head>
<body>

    <!-- Header Limpio -->
    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Trámites</a></li>
                <li><a href="subir-documento.php"><i class="fas fa-file-upload"></i> Subir Documento</a></li>
                <li><a href="verificar.php">Verificar</a></li>
            </ul>
            <div>
                <a href="index.php" class="btn-cta" style="background:#F1F5F9; color:#0F2A4A; border:1px solid #CBD5E1;">
                    <i class="fas fa-arrow-left"></i> Volver al Portal
                </a>
            </div>
        </div>
    </header>

    <div class="auth-wrapper">
        <div class="auth-card">
            <?php if ($msg === 'b2b_auth_required'): ?>
                <div style="background:#EFF6FF; border:1px solid #93C5FD; color:#1E40AF; padding:0.85rem 1rem; border-radius:8px; font-size:0.88rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-lock fa-lg" style="color:#2563EB;"></i>
                    <div>
                        <strong>Acceso Corporativo Protegido:</strong>
                        <div>Inicia sesión con tu cuenta corporativa autorizada para acceder a la Suite B2B de Empresas y consultar tu plan y firmas.</div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="auth-header">
                <h1>Zona Privada de Clientes</h1>
                <p>Accede a tu bóveda de contratos, tracker de firmas y documentos oficiales</p>
            </div>

            <!-- Selector de Pestañas -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab-btn <?= $tab === 'login' ? 'active' : '' ?>" id="tabBtnLogin" onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
                <button type="button" class="auth-tab-btn <?= $tab === 'registro' ? 'active' : '' ?>" id="tabBtnRegister" onclick="switchTab('registro')">
                    <i class="fas fa-user-plus"></i> Crear Cuenta
                </button>
            </div>

            <div class="auth-body">
                <!-- Alerta Dinámica -->
                <div id="authAlert" class="auth-alert"></div>

                <!-- Botón de Google OAuth Oficial -->
                <button type="button" class="btn-google" id="btnGoogleAuth" onclick="handleGoogleSignIn()">
                    <svg viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Continuar con Google
                </button>

                <?php if (!empty($googleClientId)): ?>
                <!-- Contenedor oficial de Google GIS -->
                <div id="g_id_onload"
                     data-client_id="<?= htmlspecialchars($googleClientId) ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="onGoogleTokenReceived"
                     data-auto_prompt="false">
                </div>
                <?php endif; ?>

                <div class="auth-divider">o ingresa con tu correo</div>

                <!-- FORMULARIO 1: INICIAR SESIÓN -->
                <form id="formLogin" style="<?= $tab === 'login' ? 'display:block;' : 'display:none;' ?>" onsubmit="submitLogin(event)">
                    <div class="form-floating-custom">
                        <label for="loginEmail"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                        <input type="email" id="loginEmail" required placeholder="tu@email.com" autocomplete="email">
                    </div>

                    <div class="form-floating-custom">
                        <label for="loginPassword"><i class="fas fa-lock"></i> Contraseña</label>
                        <input type="password" id="loginPassword" required placeholder="••••••••" autocomplete="current-password">
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; font-size:0.85rem;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; color:#475569;">
                            <input type="checkbox" id="loginRemember" checked> Recordarme en este equipo
                        </label>
                        <a href="recuperar.php" style="color:#0066CC; text-decoration:none; font-weight:600;">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn-submit-auth" id="btnLoginSubmit">
                        <i class="fas fa-sign-in-alt"></i> Entrar a mi Bóveda
                    </button>
                </form>

                <!-- FORMULARIO 2FA DUAL (WHATSAPP + FALLBACK EMAIL - SOL-0127) -->
                <form id="form2FA" style="display:none;" onsubmit="submit2FA(event)">
                    <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:1.25rem 1rem; margin-bottom:1.5rem; text-align:center;">
                        <div style="font-size:1.8rem; margin-bottom:0.35rem;" id="icon2FaBadge">🛡️</div>
                        <div style="font-weight:800; color:#166534; font-size:1rem; margin-bottom:0.25rem;">Doble Factor Activo (2FA)</div>
                        <div style="font-size:0.86rem; color:#15803D;" id="txt2FaTargetPhone">Código de seguridad enviado a tu WhatsApp</div>
                    </div>

                    <div class="form-floating-custom" style="text-align:center;">
                        <label for="input2FaCode" style="text-align:center; display:block; margin-bottom:0.6rem; font-weight:700; color:#0A1C33;"><i class="fas fa-key"></i> Ingrese el Código de 6 Dígitos:</label>
                        <input type="text" id="input2FaCode" maxlength="6" pattern="[0-9]{6}" required placeholder="••••••" style="text-align:center; font-size:1.8rem; letter-spacing:0.5rem; font-weight:800; color:#0A1C33; background:#F8FAFC;" autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn-submit-auth" id="btn2FaSubmit" style="background:linear-gradient(135deg, #15803D 0%, #16A34A 100%);">
                        <i class="fas fa-shield-alt"></i> Verificar y Entrar ➜
                    </button>

                    <!-- Acciones Alternativas de Canal -->
                    <div style="margin-top:1.2rem; text-align:center; padding:0.6rem; background:#F8FAFC; border-radius:8px; font-size:0.82rem;" id="boxSwitchChannel">
                        <a href="javascript:void(0)" onclick="resend2FaCode('email')" style="color:#0066CC; text-decoration:none; font-weight:600;" id="lnkSwitchToEmail">
                            <i class="fas fa-envelope"></i> ¿No tienes acceso a tu WhatsApp? Recibir por correo
                        </a>
                        <a href="javascript:void(0)" onclick="resend2FaCode('whatsapp')" style="color:#16A34A; text-decoration:none; font-weight:600; display:none;" id="lnkSwitchToWa">
                            <i class="fab fa-whatsapp"></i> ¿Prefieres recibirlo en WhatsApp? Enviar al celular
                        </a>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.25rem; font-size:0.85rem;">
                        <a href="javascript:void(0)" onclick="resend2FaCode()" style="color:#0066CC; text-decoration:none; font-weight:700;"><i class="fas fa-sync-alt"></i> Reenviar Código</a>
                        <a href="javascript:void(0)" onclick="cancel2Fa()" style="color:#DC2626; text-decoration:none; font-weight:700;"><i class="fas fa-times"></i> Cancelar</a>
                    </div>
                </form>

                <!-- FORMULARIO 2: CREAR CUENTA -->
                <form id="formRegister" style="<?= $tab === 'registro' ? 'display:block;' : 'display:none;' ?>" onsubmit="submitRegister(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-floating-custom">
                            <label for="regNombre">Nombre *</label>
                            <input type="text" id="regNombre" required placeholder="Ej: Rodrigo">
                        </div>
                        <div class="form-floating-custom">
                            <label for="regApellido">Apellidos *</label>
                            <input type="text" id="regApellido" required placeholder="Ej: Díaz Castro">
                        </div>
                    </div>

                    <div class="form-floating-custom">
                        <label for="regEmail"><i class="fas fa-envelope"></i> Correo Electrónico *</label>
                        <input type="email" id="regEmail" required placeholder="tu@email.com" autocomplete="email">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-floating-custom">
                            <label for="regRut">RUT Personal (Opcional)</label>
                            <input type="text" id="regRut" placeholder="12.345.678-9" maxlength="12">
                        </div>
                        <div class="form-floating-custom">
                            <label for="regTelefono">WhatsApp (Opcional)</label>
                            <input type="text" id="regTelefono" placeholder="+56 9 1234 5678">
                        </div>
                    </div>

                    <!-- Campos Corporativos para Empresas B2B -->
                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px; margin-bottom:14px;">
                        <span style="font-size:0.75rem; font-weight:800; color:#0A1C33; text-transform:uppercase; display:block; margin-bottom:6px;">
                            🏢 ¿Representas a una Empresa? (Opcional)
                        </span>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div class="form-floating-custom" style="margin-bottom:0;">
                                <label for="regEmpresaNombre" style="font-size:0.75rem;">Razón Social Empresa</label>
                                <input type="text" id="regEmpresaNombre" placeholder="Ej. Constructora del Norte SpA">
                            </div>
                            <div class="form-floating-custom" style="margin-bottom:0;">
                                <label for="regEmpresaRut" style="font-size:0.75rem;">RUT Empresa</label>
                                <input type="text" id="regEmpresaRut" placeholder="78.089.686-8">
                            </div>
                        </div>
                        <small style="color:#64748B; font-size:0.72rem; display:block; margin-top:4px;">
                            Activa acceso inmediato a la Suite B2B, expediente digital y bolsas de prepago.
                        </small>
                    </div>

                    <div class="form-floating-custom">
                        <label for="regPassword"><i class="fas fa-lock"></i> Crear Contraseña *</label>
                        <input type="password" id="regPassword" required placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password">
                    </div>

                    <p style="font-size:0.75rem; color:#64748B; margin-bottom:1.25rem;">
                        Al registrarte aceptas los <a href="terminos-condiciones.php" target="_blank" style="color:#0066CC;">Términos y Condiciones</a> y la <a href="politica-privacidad.php" target="_blank" style="color:#0066CC;">Política de Privacidad</a> de SinNotario.cl.
                    </p>

                    <button type="submit" class="btn-submit-auth" id="btnRegisterSubmit">
                        <i class="fas fa-user-check"></i> Crear mi Cuenta Gratis
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Script de Gestión de Autenticación -->
    <script src="assets/js/app.js?v=20261107_3"></script>
    <script>
        const redirectTarget = <?= json_encode($redirectUrl) ?>;

        function switchTab(tab) {
            const btnLogin = document.getElementById('tabBtnLogin');
            const btnReg = document.getElementById('tabBtnRegister');
            const formLogin = document.getElementById('formLogin');
            const formReg = document.getElementById('formRegister');
            const alertEl = document.getElementById('authAlert');
            alertEl.style.display = 'none';

            if (tab === 'login') {
                btnLogin.classList.add('active');
                btnReg.classList.remove('active');
                formLogin.style.display = 'block';
                formReg.style.display = 'none';
            } else {
                btnReg.classList.add('active');
                btnLogin.classList.remove('active');
                formReg.style.display = 'block';
                formLogin.style.display = 'none';
            }
        }

        function showAlert(msg, isSuccess = false) {
            const el = document.getElementById('authAlert');
            el.className = 'auth-alert ' + (isSuccess ? 'success' : 'error');
            el.innerHTML = (isSuccess ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-exclamation-triangle"></i> ') + msg;
            el.style.display = 'block';
        }

        let active2FaEmail = '';

        // 1. Enviar Login
        async function submitLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('btnLoginSubmit');
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            const remember = document.getElementById('loginRemember').checked;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';

            try {
                const res = await fetch('api/index.php?action=auth_login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password, remember })
                });
                const data = await res.json();

                if (data.success) {
                    if (data.data && data.data.requires_2fa) {
                        // Activar pantalla de Desafío 2FA Dual (WhatsApp / Email)
                        active2FaEmail = data.data.email;
                        document.getElementById('formLogin').style.display = 'none';
                        document.getElementById('btnGoogleAuth').style.display = 'none';
                        document.querySelector('.auth-divider').style.display = 'none';
                        document.querySelector('.auth-tabs').style.display = 'none';

                        const isEmail = data.data.channel === 'email';
                        const targetText = isEmail 
                            ? `Hemos enviado un código a tu correo <strong>${data.data.masked_destination}</strong>`
                            : `Hemos enviado un código a tu WhatsApp <strong>${data.data.masked_destination}</strong>`;
                        document.getElementById('txt2FaTargetPhone').innerHTML = targetText;
                        document.getElementById('icon2FaBadge').textContent = isEmail ? '✉️' : '🛡️';

                        // Conmutadores de canal
                        if (isEmail) {
                            document.getElementById('lnkSwitchToEmail').style.display = 'none';
                            document.getElementById('lnkSwitchToWa').style.display = data.data.has_phone ? 'inline-block' : 'none';
                        } else {
                            document.getElementById('lnkSwitchToEmail').style.display = 'inline-block';
                            document.getElementById('lnkSwitchToWa').style.display = 'none';
                        }

                        document.getElementById('form2FA').style.display = 'block';
                        document.getElementById('input2FaCode').focus();
                        showAlert(data.data.message || 'Código de seguridad enviado.', true);
                    } else {
                        showAlert('¡Bienvenido! Redirigiendo a tu bóveda...', true);
                        setTimeout(() => {
                            window.location.href = redirectTarget;
                        }, 800);
                    }
                } else {
                    showAlert(data.error || 'Credenciales incorrectas');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar a mi Bóveda';
                }
            } catch (err) {
                showAlert('Error de conexión con el servidor: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar a mi Bóveda';
            }
        }

        // 1b. Enviar Desafío 2FA (SOL-0123 / SOL-0127)
        async function submit2FA(e) {
            e.preventDefault();
            const btn = document.getElementById('btn2FaSubmit');
            const otpCode = document.getElementById('input2FaCode').value.trim();

            if (otpCode.length !== 6) {
                showAlert('Por favor ingresa los 6 dígitos del código.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';

            try {
                const res = await fetch('api/index.php?action=auth_verify_otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: active2FaEmail, otp_code: otpCode })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('¡Identidad verificada exitosamente! Entrando a tu bóveda...', true);
                    setTimeout(() => {
                        window.location.href = redirectTarget;
                    }, 800);
                } else {
                    showAlert(data.error || 'Código incorrecto o expirado.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-shield-alt"></i> Verificar y Entrar ➜';
                    document.getElementById('input2FaCode').select();
                }
            } catch (err) {
                showAlert('Error al verificar: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt"></i> Verificar y Entrar ➜';
            }
        }

        // 1c. Reenviar Código 2FA (WhatsApp o Email)
        async function resend2FaCode(forceChannel = null) {
            if (!active2FaEmail) return;
            const targetName = forceChannel === 'email' ? 'tu correo...' : (forceChannel === 'whatsapp' ? 'tu WhatsApp...' : 'tu destino...');
            showAlert(`Reenviando código a ${targetName}`, true);

            try {
                const payload = { email: active2FaEmail };
                if (forceChannel) payload.channel = forceChannel;

                const res = await fetch('api/index.php?action=auth_resend_otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    const info = data.data || {};
                    showAlert(info.message || 'Nuevo código despachado exitosamente.', true);
                    if (info.masked_destination) {
                        const isEmail = info.channel === 'email';
                        document.getElementById('txt2FaTargetPhone').innerHTML = isEmail
                            ? `Hemos enviado un código a tu correo <strong>${info.masked_destination}</strong>`
                            : `Hemos enviado un código a tu WhatsApp <strong>${info.masked_destination}</strong>`;
                        document.getElementById('icon2FaBadge').textContent = isEmail ? '✉️' : '🛡️';

                        if (isEmail) {
                            document.getElementById('lnkSwitchToEmail').style.display = 'none';
                            document.getElementById('lnkSwitchToWa').style.display = 'inline-block';
                        } else {
                            document.getElementById('lnkSwitchToEmail').style.display = 'inline-block';
                            document.getElementById('lnkSwitchToWa').style.display = 'none';
                        }
                    }
                    document.getElementById('input2FaCode').value = '';
                    document.getElementById('input2FaCode').focus();
                } else {
                    showAlert(data.error || 'No fue posible reenviar el código.');
                }
            } catch (err) {
                showAlert('Error al reenviar código: ' + err.message);
            }
        }

        // 1d. Cancelar 2FA
        function cancel2Fa() {
            active2FaEmail = '';
            document.getElementById('form2FA').style.display = 'none';
            document.getElementById('formLogin').style.display = 'block';
            document.getElementById('btnGoogleAuth').style.display = 'flex';
            document.querySelector('.auth-divider').style.display = 'flex';
            document.querySelector('.auth-tabs').style.display = 'grid';
            document.getElementById('input2FaCode').value = '';
            const btn = document.getElementById('btnLoginSubmit');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar a mi Bóveda';
            document.getElementById('authAlert').style.display = 'none';
        }

        // 2. Enviar Registro
        async function submitRegister(e) {
            e.preventDefault();
            const btn = document.getElementById('btnRegisterSubmit');
            const nombre = document.getElementById('regNombre').value.trim();
            const apellido = document.getElementById('regApellido').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const rut = document.getElementById('regRut').value.trim();
            const telefono = document.getElementById('regTelefono').value.trim();
            const empresaRazon = document.getElementById('regEmpresaNombre') ? document.getElementById('regEmpresaNombre').value.trim() : '';
            const empresaRut = document.getElementById('regEmpresaRut') ? document.getElementById('regEmpresaRut').value.trim() : '';
            const password = document.getElementById('regPassword').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando cuenta...';

            try {
                const res = await fetch('api/index.php?action=auth_register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nombre, apellido, email, rut, telefono, password,
                        empresa_razon_social: empresaRazon,
                        empresa_rut: empresaRut
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('¡Cuenta creada exitosamente! Ingresando a tu zona privada...', true);
                    setTimeout(() => {
                        window.location.href = redirectTarget;
                    }, 900);
                } else {
                    showAlert(data.error || 'No fue posible crear la cuenta');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-user-check"></i> Crear mi Cuenta Gratis';
                }
            } catch (err) {
                showAlert('Error de conexión: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-check"></i> Crear mi Cuenta Gratis';
            }
        }

        // 3. Callback de Google Identity Services
        async function onGoogleTokenReceived(response) {
            if (!response || !response.credential) {
                showAlert('No se recibió la credencial de Google.');
                return;
            }

            showAlert('Verificando cuenta con Google...', true);
            try {
                const res = await fetch('api/index.php?action=auth_google', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ credential: response.credential })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('¡Identidad confirmada! Redirigiendo...', true);
                    setTimeout(() => {
                        window.location.href = redirectTarget;
                    }, 800);
                } else {
                    showAlert(data.error || 'Error al autenticar con Google');
                }
            } catch (err) {
                showAlert('Fallo de red al comunicar con Google: ' + err.message);
            }
        }

        const hasGoogleClientId = <?= json_encode(!empty($googleClientId)) ?>;

        function handleGoogleSignIn() {
            if (!hasGoogleClientId) {
                showAlert('El acceso con Google se encuentra en fase de configuración de credenciales. Por favor utiliza tu correo y contraseña o crea tu cuenta en la pestaña "Crear Cuenta".');
                return;
            }
            if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                google.accounts.id.prompt();
            } else {
                showAlert('Inicializando servicios de Google, por favor reintenta en un momento.');
            }
        }

        // Auto-formateo del RUT en registro si está disponible RutHelper
        document.addEventListener('DOMContentLoaded', () => {
            const rutInp = document.getElementById('regRut');
            if (rutInp && typeof RutHelper !== 'undefined') {
                RutHelper.attachToInput(rutInp);
            }
        });
    </script>
</body>
</html>
