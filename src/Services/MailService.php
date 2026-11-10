<?php
/**
 * MailService - Servicio Centralizado de Notificaciones y Correos Transaccionales
 * Con despacho vía SMTP Autenticado (Titan Mail) y fallback resiliente.
 * SinNotario.cl - Keystone Advisors
 */
require_once __DIR__ . '/KeystoneSmtpClient.php';

class MailService {

    /**
     * Obtener configuración SMTP (Prioriza base de datos system_settings, luego variables de entorno)
     */
    private static function getSmtpConfig() {
        $dbConfig = [];
        try {
            require_once __DIR__ . '/../../database/Database.php';
            $pdo = Database::getInstance();
            $stmt = $pdo->query("SELECT key, value FROM system_settings WHERE key LIKE 'smtp_%'");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $dbConfig[$row['key']] = $row['value'];
                }
            }
        } catch (Exception $e) {
            // Silencioso
        }

        return [
            'smtp_host' => $dbConfig['smtp_host'] ?? (getenv('SMTP_HOST') ?: 'smtp.titan.email'),
            'smtp_port' => (int)($dbConfig['smtp_port'] ?? (getenv('SMTP_PORT') ?: 465)),
            'smtp_user' => $dbConfig['smtp_user'] ?? (getenv('SMTP_USER') ?: 'contacto@keystoneadvisors.cl'),
            'smtp_pass' => $dbConfig['smtp_pass'] ?? (getenv('SMTP_PASS') ?: 'Jaba10281029#123456'),
            'smtp_from' => $dbConfig['smtp_from'] ?? (getenv('SMTP_FROM') ?: 'contacto@keystoneadvisors.cl'),
            'smtp_name' => $dbConfig['smtp_name'] ?? (getenv('SMTP_NAME') ?: 'SinNotario.cl')
        ];
    }

    /**
     * Enviar correo HTML transaccional
     */
    public static function send($to, $subject, $htmlBody, $replyTo = null) {
        $config = self::getSmtpConfig();

        // 1. Envolver en plantilla corporativa
        $fullHtml = self::wrapTemplate($subject, $htmlBody);

        // 2. Intento principal: SMTP Socket con SSL
        if (!empty($config['smtp_pass'])) {
            try {
                $client = new KeystoneSmtpClient($config);
                $sent = $client->send($to, $subject, $fullHtml);
                if ($sent) {
                    return true;
                }
            } catch (Exception $e) {
                error_log("Error SMTP en SinNotario MailService: " . $e->getMessage());
            }
        }

        // 3. Fallback: mail() nativo del servidor
        $fromEmail = $config['smtp_from'] ?? 'contacto@keystoneadvisors.cl';
        $fromName  = 'SinNotario.cl';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: " . ($replyTo ?: $fromEmail);
        $headers[] = "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $fullHtml, implode("\r\n", $headers), "-f{$fromEmail}");
    }

    /**
     * Alias de compatibilidad para send()
     */
    public static function sendEmail($to, $subject, $htmlBody, $replyTo = null) {
        return self::send($to, $subject, $htmlBody, $replyTo);
    }

    /**
     * Enviar correo con enlace y código para restablecer contraseña
     */
    public static function sendPasswordReset($toEmail, $nombre, $resetLink, $tokenCode) {
        $subject = "Recuperación de Contraseña — SinNotario.cl";
        
        $body = '
            <p style="font-size:16px; color:#1E293B; margin-bottom:16px;">
                Hola <strong>' . htmlspecialchars($nombre) . '</strong>,
            </p>
            <p style="font-size:14px; color:#475569; line-height:1.6; margin-bottom:20px;">
                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>SinNotario.cl</strong>. Si realizaste esta solicitud, puedes crear una nueva clave haciendo clic en el siguiente botón:
            </p>
            <div style="text-align:center; margin:30px 0;">
                <a href="' . htmlspecialchars($resetLink) . '" style="background:#0066CC; color:#FFFFFF; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:700; font-size:15px; display:inline-block; box-shadow:0 4px 12px rgba(0,102,204,0.25);">
                    Restablecer mi Contraseña
                </a>
            </div>
            <p style="font-size:13px; color:#64748B; line-height:1.5; margin-bottom:12px;">
                O si prefieres, copia y pega el siguiente enlace directo en tu navegador:<br>
                <a href="' . htmlspecialchars($resetLink) . '" style="color:#0066CC; word-break:break-all;">' . htmlspecialchars($resetLink) . '</a>
            </p>
            <div style="background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; padding:14px; margin-top:20px; text-align:center;">
                <span style="font-size:12px; color:#64748B; display:block; margin-bottom:4px;">Tu código de seguridad temporal:</span>
                <strong style="font-size:18px; letter-spacing:3px; color:#0F2A4A;">' . htmlspecialchars(substr($tokenCode, 0, 8)) . '</strong>
            </div>
            <p style="font-size:12px; color:#94A3B8; line-height:1.5; margin-top:24px; border-top:1px solid #E2E8F0; padding-top:14px;">
                ⚠️ Este enlace expirará en <strong>1 hora</strong> por motivos de seguridad. Si no solicitaste restablecer tu contraseña, puedes ignorar este correo de forma segura. Tu clave actual no sufrirá ningún cambio.
            </p>
        ';

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Enviar correo de bienvenida institucional para Empresas B2B con enlace al instructivo
     */
    public static function sendB2BWelcome($toEmail, $nombreRep, $razonSocial, $rutEmpresa) {
        $subject = "¡Bienvenido a SinNotario Empresas! — Suite B2B Activada";
        
        $body = '
            <div style="text-align:center; margin-bottom:24px;">
                <span style="background:#DCFCE7; color:#15803D; font-size:12px; font-weight:800; padding:4px 12px; border-radius:20px; text-transform:uppercase;">
                    ✓ Cuenta Corporativa Prepago Activada
                </span>
            </div>
            <p style="font-size:16px; color:#1E293B; margin-bottom:12px;">
                Estimado(a) <strong>' . htmlspecialchars($nombreRep) . '</strong>,
            </p>
            <p style="font-size:14px; color:#475569; line-height:1.6; margin-bottom:16px;">
                Le damos una cordial bienvenida a la <strong>Suite Corporativa B2B de SinNotario.cl</strong> para <strong>' . htmlspecialchars($razonSocial) . '</strong> (RUT: ' . htmlspecialchars($rutEmpresa) . ').
            </p>
            <p style="font-size:14px; color:#475569; line-height:1.6; margin-bottom:20px;">
                Su empresa ya puede autogestionar la emisión y firma masiva de contratos bajo la <strong>Ley N° 19.799</strong> y la <strong>Ley N° 21.327 (Dirección del Trabajo)</strong>, sin amarras ni contratos forzosos mensuales: adquiere bolsas de firmas prepagadas con descuento y úsalas a tu propio ritmo.
            </p>
            
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:18px; margin:20px 0;">
                <h4 style="margin:0 0 10px; color:#0F2A4A; font-size:14px; font-weight:800;">🚀 ¿Cómo comenzar a operar?</h4>
                <ul style="margin:0; padding-left:20px; font-size:13px; color:#475569; line-height:1.7;">
                    <li><strong>1. Carga Masiva:</strong> Sube tu planilla Excel/CSV y valida RUTs en 1 clic.</li>
                    <li><strong>2. Plantillas Propias:</strong> Pega tu propio formato contractual con variables <code>{{NOMBRE}}</code>, <code>{{CARGO}}</code>, etc.</li>
                    <li><strong>3. Firma Paralela:</strong> Firma todo el lote con una sola ClaveÚnica.</li>
                    <li><strong>4. Libro Digital DT:</strong> Descarga la nómina codificada para el portal Mi DT.</li>
                </ul>
            </div>

            <div style="text-align:center; margin:30px 0; display:flex; gap:12px; justify-content:center;">
                <a href="https://sinnotario.cl/empresas.php" style="background:#0F2A4A; color:#FFFFFF; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:700; font-size:14px; display:inline-block;">
                    Ir a Mi Portal Empresas
                </a>
                <a href="https://sinnotario.cl/docs/manual-operativo-empresas.php" style="background:#D4AF37; color:#0A1C33; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:800; font-size:14px; display:inline-block;">
                    📖 Descargar Instructivo Operativo (PDF)
                </a>
            </div>

            <p style="font-size:12px; color:#64748B; line-height:1.5; margin-top:20px; border-top:1px solid #E2E8F0; padding-top:14px;">
                💡 <em>Soporte Corporativo:</em> Si necesitas asistencia para integrar la API o habilitar el WhatsApp oficial de tu empresa, puedes escribirnos directamente a <a href="mailto:contacto@sinnotario.cl" style="color:#0066CC;">contacto@sinnotario.cl</a> o vía WhatsApp al +56 9 8418 0479.
            </p>
        ';

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Plantilla base corporativa HTML responsiva
     */
    private static function wrapTemplate($title, $contentHtml) {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#F1F5F9; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F1F5F9; padding:30px 15px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px; background-color:#FFFFFF; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1px solid #E2E8F0;">
                    <!-- Cabecera -->
                    <tr>
                        <td align="center" style="background:#0F2A4A; padding:24px 20px;">
                            <span style="color:#FFFFFF; font-size:22px; font-weight:800; letter-spacing:-0.5px;">Sin<span style="color:#60A5FA;">Notario</span>.cl</span>
                            <span style="display:block; color:#94A3B8; font-size:11px; margin-top:4px; text-transform:uppercase; letter-spacing:1px;">LegalTech &bull; Firma Electrónica Ley 19.799</span>
                        </td>
                    </tr>
                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:32px 28px;">
                            ' . $contentHtml . '
                        </td>
                    </tr>
                    <!-- Pie de página -->
                    <tr>
                        <td align="center" style="background:#F8FAFC; border-top:1px solid #E2E8F0; padding:20px; font-size:11px; color:#94A3B8; line-height:1.5;">
                            SinNotario.cl &bull; Keystone Advisors SpA<br>
                            Plataforma de Firma Electrónica Avanzada y Contratos Legales Inteligentes.<br>
                            Santiago de Chile &bull; Todos los derechos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
