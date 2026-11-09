<?php
/**
 * AuthGate - Candado de Acceso Seguro para Pre-Lanzamiento / Mantención
 * Bloquea el acceso público a todas las páginas mientras el sitio esté en construcción.
 * Permite desbloquear el sitio completo introduciendo usuario y contraseña autorizados.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Credenciales Maestras de Validación Interna
define('GATE_USER', 'rodrigo');
define('GATE_PASS', 'Keystone2026#SinNotario');

// Cierre de sesión de mantención
if (isset($_GET['exit_preview'])) {
    unset($_SESSION['sinnotario_preview_auth']);
    unset($_SESSION['sinnotario_preview_user']);
    setcookie('sn_preview_pass', '', time() - 3600, '/');
    header("Location: index.php");
    exit;
}

// Validación de cookie persistente de mantención
if (empty($_SESSION['sinnotario_preview_auth']) && isset($_COOKIE['sn_preview_pass'])) {
    if ($_COOKIE['sn_preview_pass'] === md5(GATE_USER . GATE_PASS . 'sinnotario_salt_2026')) {
        $_SESSION['sinnotario_preview_auth'] = true;
        $_SESSION['sinnotario_preview_user'] = GATE_USER;
    }
}

// Procesar formulario de login de mantención
$gateError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gate_action']) && $_POST['gate_action'] === 'unlock') {
    $u = trim($_POST['gate_username'] ?? '');
    $p = trim($_POST['gate_password'] ?? '');

    if ($u === GATE_USER && $p === GATE_PASS) {
        $_SESSION['sinnotario_preview_auth'] = true;
        $_SESSION['sinnotario_preview_user'] = $u;
        // Cookie válida por 30 días para no pedir clave a cada clic
        setcookie('sn_preview_pass', md5(GATE_USER . GATE_PASS . 'sinnotario_salt_2026'), time() + (86400 * 30), '/');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $gateError = 'Credenciales de acceso de pre-lanzamiento incorrectas.';
    }
}

// Si está autenticado, permitir continuar con la carga normal de la página
if (!empty($_SESSION['sinnotario_preview_auth'])) {
    return;
}

// Si no está autenticado, interceptar y mostrar la pantalla elegante de mantención y acceso exclusivo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SinNotario.cl — Plataforma LegalTech en Construcción</title>
    <meta name="robots" content="noindex, nofollow">
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="G9FGSpS5XFq1EUAuNHjU851fTtlZTa__hJJ34rW1kyY" />
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at top right, #1E3A8A 0%, #0F2A4A 40%, #081628 100%);
            min-height: 100vh;
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .gate-header {
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        .gate-header img {
            height: 42px;
            filter: brightness(0) invert(1);
        }
        .badge-dev {
            background: rgba(30, 58, 138, 0.6);
            border: 1px solid rgba(147, 197, 253, 0.3);
            color: #93C5FD;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22C55E;
            box-shadow: 0 0 10px #22C55E;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .gate-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
        }
        .gate-container {
            max-width: 800px;
            width: 100%;
            text-align: center;
        }
        .gate-title {
            font-size: 2.5rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 1.25rem;
            letter-spacing: -0.5px;
        }
        .gate-title span {
            background: linear-gradient(135deg, #60A5FA 0%, #34D399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gate-desc {
            font-size: 1.1rem;
            color: #94A3B8;
            line-height: 1.6;
            max-width: 620px;
            margin: 0 auto 2.5rem;
        }
        .gate-pillars {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
            text-align: left;
        }
        .pillar-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            backdrop-filter: blur(8px);
        }
        .pillar-card i {
            font-size: 1.5rem;
            color: #60A5FA;
            margin-bottom: 0.75rem;
            display: block;
        }
        .pillar-card h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 0.35rem;
        }
        .pillar-card p {
            font-size: 0.8rem;
            color: #94A3B8;
            line-height: 1.4;
        }
        .btn-unlock-modal {
            background: #FFFFFF;
            color: #0F2A4A;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.15);
        }
        .btn-unlock-modal:hover {
            transform: translateY(-2px);
            background: #F8FAFC;
        }

        /* Modal de Clave */
        .gate-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 1000;
        }
        .gate-modal {
            background: #0B192C;
            border: 1px solid #1E3A8A;
            border-radius: 16px;
            max-width: 420px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            text-align: left;
        }
        .modal-input {
            width: 100%;
            background: #06101E;
            border: 1px solid #1E3A8A;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #FFFFFF;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            box-sizing: border-box;
        }
        .modal-input:focus {
            outline: none;
            border-color: #60A5FA;
        }
        .btn-login-gate {
            width: 100%;
            background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            padding: 0.85rem;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-login-gate:hover {
            background: #0284C7;
        }
        .gate-footer {
            padding: 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: #64748B;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="gate-header">
        <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl">
        <div class="badge-dev">
            <span class="badge-pulse"></span>
            <span>Acceso Privado &bull; Próximo Lanzamiento</span>
        </div>
    </header>

    <!-- Main -->
    <main class="gate-main">
        <div class="gate-container">
            <div class="badge-dev" style="margin-bottom: 1.5rem;">
                <i class="fas fa-lock"></i> Entorno de Pre-Lanzamiento Restringido
            </div>

            <h1 class="gate-title">
                Contratos Legales y Firma Digital <br>
                <span>Sin Filas, Sin Esperas y Sin Notaría.</span>
            </h1>

            <p class="gate-desc">
                Estamos finalizando los preparativos para el lanzamiento oficial de <strong>SinNotario.cl</strong> bajo la <strong>Ley N° 19.799</strong> y <strong>Ley N° 21.461</strong> con validación criptográfica, Bóveda Cloudflare R2 y redención de trámites con plena fuerza legal.
            </p>

            <div class="gate-pillars">
                <div class="pillar-card">
                    <i class="fas fa-file-signature"></i>
                    <h4>Contratos Inteligentes</h4>
                    <p>Arriendos, finiquitos laborales, poderes y declaraciones con mérito ejecutivo.</p>
                </div>
                <div class="pillar-card">
                    <i class="fas fa-shield-alt"></i>
                    <h4>Bóveda de Custodia</h4>
                    <p>Sello de tiempo y hash forense SHA-256 inalterable durante 5 años.</p>
                </div>
                <div class="pillar-card">
                    <i class="fas fa-fingerprint"></i>
                    <h4>Verificación Oficial</h4>
                    <p>Cotejo de integridad al instante con código QR y Folio nacional.</p>
                </div>
            </div>

            <div>
                <button type="button" class="btn-unlock-modal" onclick="document.getElementById('gateModal').style.display='flex'">
                    <i class="fas fa-key"></i> Acceso de Validación Autorizado
                </button>
            </div>
        </div>
    </main>

    <!-- Modal Login de Validación -->
    <div id="gateModal" class="gate-modal-backdrop" style="<?= empty($gateError) ? 'display:none;' : 'display:flex;' ?>">
        <div class="gate-modal">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h3 style="font-size:1.15rem; font-weight:800; color:#FFFFFF; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-user-lock" style="color:#60A5FA;"></i> Acceso Privado
                </h3>
                <button type="button" onclick="document.getElementById('gateModal').style.display='none'" style="background:none; border:none; color:#64748B; font-size:1.25rem; cursor:pointer;">&times;</button>
            </div>

            <?php if (!empty($gateError)): ?>
                <div style="background:rgba(239, 68, 68, 0.15); border:1px solid #EF4444; color:#FCA5A5; padding:0.65rem 0.85rem; border-radius:8px; font-size:0.85rem; margin-bottom:1rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($gateError) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="gate_action" value="unlock">
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#94A3B8; margin-bottom:4px;">Usuario</label>
                    <input type="text" name="gate_username" class="modal-input" placeholder="Ingresa usuario" required autofocus>
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:600; color:#94A3B8; margin-bottom:4px;">Contraseña de Acceso</label>
                    <input type="password" name="gate_password" class="modal-input" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login-gate">
                    <i class="fas fa-sign-in-alt"></i> Desbloquear y Ver Sitio
                </button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="gate-footer">
        SinNotario.cl &bull; Keystone Advisors SpA &bull; Todos los derechos reservados.
    </footer>

</body>
</html>
<?php
exit;
