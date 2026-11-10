<?php
/**
 * Recuperar y Restablecer Contraseña — SinNotario.cl
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Services/AuthService.php';

$token = trim($_GET['token'] ?? '');
$tokenValid = false;
$tokenUser = null;

if (!empty($token)) {
    $tokenUser = AuthService::validatePasswordResetToken($token);
    if ($tokenUser) {
        $tokenValid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | SinNotario.cl</title>
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Principales -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_3">

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
            padding: 2rem;
            text-align: center;
            color: #FFFFFF;
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
        .auth-body {
            padding: 2rem;
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
                <a href="login.php" class="btn-cta" style="background:#F1F5F9; color:#0F2A4A; border:1px solid #CBD5E1;">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
            </div>
        </div>
    </header>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?= $tokenValid ? 'Nueva Contraseña' : 'Recuperar Contraseña' ?></h1>
                <p><?= $tokenValid ? 'Ingresa tu nueva clave de acceso para tu cuenta' : 'Te enviaremos un enlace seguro para restablecerla' ?></p>
            </div>

            <div class="auth-body">
                <div id="authAlert" class="auth-alert"></div>

                <?php if ($token && !$tokenValid): ?>
                    <!-- Token Inválido o Expirado -->
                    <div style="text-align:center; padding:1.5rem 0;">
                        <div style="width:64px; height:64px; background:#FEF2F2; color:#DC2626; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; font-size:1.75rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 style="font-size:1.15rem; font-weight:700; color:#0F2A4A; margin-bottom:0.5rem;">Enlace Inválido o Expirado</h3>
                        <p style="font-size:0.9rem; color:#64748B; margin-bottom:1.5rem;">
                            El enlace que utilizaste ya no es válido o superó el tiempo límite de seguridad (1 hora).
                        </p>
                        <a href="recuperar.php" class="btn-submit-auth" style="text-decoration:none;">
                            <i class="fas fa-redo"></i> Solicitar Nuevo Enlace
                        </a>
                    </div>

                <?php elseif ($tokenValid): ?>
                    <!-- Formulario de Restablecimiento de Contraseña (Con Token Válido) -->
                    <form id="formResetPass" onsubmit="submitResetPassword(event)">
                        <input type="hidden" id="resetToken" value="<?= htmlspecialchars($token) ?>">
                        
                        <div style="background:#F0F9FF; border:1px solid #BAE6FD; border-radius:8px; padding:12px; margin-bottom:1.25rem; font-size:0.85rem; color:#0369A1;">
                            <i class="fas fa-user-check"></i> Restableciendo contraseña para: <br>
                            <strong><?= htmlspecialchars($tokenUser['nombre'] . ' ' . $tokenUser['apellido']) ?></strong> (<?= htmlspecialchars($tokenUser['email']) ?>)
                        </div>

                        <div class="form-floating-custom">
                            <label for="newPass"><i class="fas fa-lock"></i> Nueva Contraseña *</label>
                            <input type="password" id="newPass" required placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password">
                        </div>

                        <div class="form-floating-custom">
                            <label for="confirmPass"><i class="fas fa-lock"></i> Confirmar Nueva Contraseña *</label>
                            <input type="password" id="confirmPass" required placeholder="Repite tu nueva contraseña" minlength="6" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn-submit-auth" id="btnResetSubmit">
                            <i class="fas fa-check-circle"></i> Guardar Nueva Contraseña
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Formulario de Solicitud de Recuperación (Sin Token) -->
                    <form id="formForgotPass" onsubmit="submitForgotPassword(event)">
                        <p style="font-size:0.9rem; color:#475569; line-height:1.5; margin-bottom:1.25rem;">
                            Ingresa el correo electrónico asociado a tu cuenta de SinNotario. Te enviaremos un correo con las instrucciones y el enlace para crear una nueva clave.
                        </p>

                        <div class="form-floating-custom">
                            <label for="forgotEmail"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                            <input type="email" id="forgotEmail" required placeholder="tu@email.com" autocomplete="email">
                        </div>

                        <button type="submit" class="btn-submit-auth" id="btnForgotSubmit">
                            <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                        </button>
                    </form>

                    <div style="text-align:center; margin-top:1.5rem;">
                        <a href="login.php" style="font-size:0.85rem; color:#0066CC; text-decoration:none; font-weight:600;">
                            <i class="fas fa-arrow-left"></i> Volver a Iniciar Sesión
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
        function showAlert(msg, isSuccess = false) {
            const el = document.getElementById('authAlert');
            el.className = 'auth-alert ' + (isSuccess ? 'success' : 'error');
            el.innerHTML = (isSuccess ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-exclamation-circle"></i> ') + msg;
            el.style.display = 'block';
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        async function submitForgotPassword(e) {
            e.preventDefault();
            const btn = document.getElementById('btnForgotSubmit');
            const email = document.getElementById('forgotEmail').value.trim();

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

            try {
                const res = await fetch('api/index.php?action=auth_forgot_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert(data.data.message || 'Si tu correo está registrado, recibirás un enlace en breve.', true);
                    document.getElementById('formForgotPass').reset();
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Enlace Enviado';
                } else {
                    showAlert(data.error || 'Ocurrió un error al procesar la solicitud.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación';
                }
            } catch (err) {
                showAlert('Error de conexión: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación';
            }
        }

        async function submitResetPassword(e) {
            e.preventDefault();
            const btn = document.getElementById('btnResetSubmit');
            const token = document.getElementById('resetToken').value;
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;

            if (newPass !== confirmPass) {
                showAlert('Las contraseñas no coinciden. Por favor verifícalas.');
                return;
            }

            if (newPass.length < 6) {
                showAlert('La contraseña debe tener un mínimo de 6 caracteres.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                const res = await fetch('api/index.php?action=auth_reset_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, password: newPass })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert(data.data.message || 'Contraseña actualizada con éxito. Redirigiendo...', true);
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    showAlert(data.error || 'No fue posible restablecer la contraseña.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Guardar Nueva Contraseña';
                }
            } catch (err) {
                showAlert('Error de conexión: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Guardar Nueva Contraseña';
            }
        }
    </script>
</body>
</html>
