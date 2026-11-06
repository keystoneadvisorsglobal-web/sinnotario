<?php
/**
 * SinNotario.cl - Página Oficial de Construcción con Acceso Restringido
 * Permite visualizar el avance del nuevo portal únicamente a usuarios autorizados.
 */

session_start();

// Credenciales Maestras de Acceso de Validación
define('AUTH_USER', 'rodrigo');
define('AUTH_PASS', 'Keystone2026#SinNotario');

// Manejo de Cerrar Sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Manejo de Login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === AUTH_USER && $pass === AUTH_PASS) {
        $_SESSION['sinnotario_preview_auth'] = true;
        $_SESSION['sinnotario_user'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $loginError = 'Usuario o contraseña incorrectos.';
    }
}

$isAuthenticated = !empty($_SESSION['sinnotario_preview_auth']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SinNotario.cl — Plataforma en Construcción</title>
    <link rel="icon" type="image/png" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .gradient-brand { background: linear-gradient(135deg, #003366 0%, #006699 100%); }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur-md px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl" class="h-10 w-auto bg-white/10 rounded p-1">
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded bg-blue-900/60 text-blue-300 border border-blue-700/50">Plataforma v1.0 en Desarrollo</span>
            </div>
            <div>
                <?php if ($isAuthenticated): ?>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-slate-400">Sesión: <strong class="text-emerald-400"><?= htmlspecialchars($_SESSION['sinnotario_user']) ?></strong></span>
                        <a href="?logout=1" class="text-xs bg-rose-900/60 hover:bg-rose-800 text-rose-200 px-3 py-1.5 rounded transition">Cerrar Sesión</a>
                    </div>
                <?php else: ?>
                    <button onclick="document.getElementById('modalLogin').classList.remove('hidden')" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-3.5 py-1.5 rounded border border-slate-700 transition flex items-center space-x-1.5">
                        <span>🔒</span> <span>Acceso de Validación</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="max-w-3xl w-full text-center space-x-0">

            <?php if (!$isAuthenticated): ?>
                <!-- Vista Pública: En Construcción Oficial -->
                <div class="inline-flex items-center space-x-2 bg-blue-950/60 border border-blue-800/60 px-4 py-1.5 rounded-full text-blue-300 text-sm mb-6">
                    <span class="inline-block w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <span>Revolucionando la firma de documentos en Chile</span>
                </div>

                <h1 class="text-4xl md:text-5xl font-black tracking-tight text-white mb-6 leading-tight">
                    Firma tus contratos y trámites <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">100% legal, sin filas ni notarías.</span>
                </h1>

                <p class="text-base md:text-lg text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Estamos preparando el lanzamiento de la nueva plataforma de <strong>SinNotario.cl</strong> bajo la <strong>Ley N° 19.799</strong> y <strong>Ley N° 21.461</strong> con validación oficial de ClaveÚnica, redacción inteligente asistida y red de atención presencial en sucursales.
                </p>

                <!-- Cards Resumen de Pilares -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-left max-w-2xl mx-auto mb-10">
                    <div class="bg-slate-900/90 border border-slate-800 p-4 rounded-xl">
                        <div class="text-2xl mb-2">🏠</div>
                        <h3 class="font-bold text-slate-200 text-sm mb-1">Arriendos & Contratos</h3>
                        <p class="text-xs text-slate-400">Con mérito ejecutivo y procedimiento monitorio de la Ley Devuélveme mi casa.</p>
                    </div>
                    <div class="bg-slate-900/90 border border-slate-800 p-4 rounded-xl">
                        <div class="text-2xl mb-2">🔑</div>
                        <h3 class="font-bold text-slate-200 text-sm mb-1">Firma con ClaveÚnica</h3>
                        <p class="text-xs text-slate-400">Firma Electrónica Avanzada (FEA) acreditada ante el Ministerio de Economía.</p>
                    </div>
                    <div class="bg-slate-900/90 border border-slate-800 p-4 rounded-xl">
                        <div class="text-2xl mb-2">🏢</div>
                        <h3 class="font-bold text-slate-200 text-sm mb-1">Modelo Híbrido</h3>
                        <p class="text-xs text-slate-400">Autoatención web 24/7 y oficinas presenciales con cabinas de firma y asesoría.</p>
                    </div>
                </div>

                <div class="text-xs text-slate-500">
                    Una iniciativa tecnológica de <strong>Keystone Advisors SpA</strong>. Todos los derechos reservados.
                </div>

            <?php else: ?>
                <!-- Vista Privada Autorizada: Panel de Validación del Nuevo Sitio -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-left shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
                        <div>
                            <span class="text-xs font-mono uppercase px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800">Modo Validación Activo</span>
                            <h2 class="text-xl font-bold text-white mt-1">Ambiente de Validación del Nuevo Sitio Web</h2>
                        </div>
                        <span class="text-xs text-slate-400 font-mono">Build v1.0.0</span>
                    </div>

                    <p class="text-sm text-slate-300 mb-6 leading-relaxed">
                        Bienvenido, <strong><?= htmlspecialchars($_SESSION['sinnotario_user']) ?></strong>. El sitio de producción anterior ha sido respaldado de manera íntegra y el hosting se encuentra listo para el despliegue del nuevo frontend y backend modular de SinNotario.cl.
                    </p>

                    <div class="space-y-3 mb-6 text-sm">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="font-semibold text-slate-200">1. Respaldo WordPress Anterior</span>
                            <span class="text-xs px-2.5 py-1 bg-emerald-900/60 text-emerald-300 rounded font-mono">✅ Completado y Descargado</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="font-semibold text-slate-200">2. Candado de Acceso Seguro (Auth Gate)</span>
                            <span class="text-xs px-2.5 py-1 bg-emerald-900/60 text-emerald-300 rounded font-mono">✅ Activo en index.php</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="font-semibold text-slate-200">3. Próximo Paso: Cloudflare R2 Vault</span>
                            <span class="text-xs px-2.5 py-1 bg-amber-900/60 text-amber-300 rounded font-mono">⏳ Pendiente Configuración</span>
                        </div>
                    </div>

                    <div class="text-xs text-slate-400 bg-slate-950 p-4 rounded-lg font-mono border border-slate-800">
                        Estado Servidor: LiteSpeed PHP 8.2 | Hostinger | Dominio: sinnotario.cl
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Modal Login de Acceso -->
    <div id="modalLogin" class="<?= $loginError ? '' : 'hidden' ?> fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 max-w-md w-full rounded-2xl p-6 shadow-2xl text-left">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white flex items-center space-x-2">
                    <span>🔒</span> <span>Acceso de Validación</span>
                </h3>
                <button onclick="document.getElementById('modalLogin').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
            </div>
            
            <?php if ($loginError): ?>
                <div class="mb-4 p-3 rounded bg-rose-950/80 border border-rose-800 text-rose-200 text-xs">
                    <?= htmlspecialchars($loginError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Usuario</label>
                    <input type="text" name="username" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Contraseña</label>
                    <input type="password" name="password" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg font-semibold text-sm bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white transition shadow-lg shadow-blue-900/30">
                    Ingresar a Validar Sitio
                </button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-900/40 px-6 py-4 text-center text-xs text-slate-500">
        SinNotario.cl &bull; Certificación bajo Ley N° 19.799 &bull; República de Chile
    </footer>

</body>
</html>
