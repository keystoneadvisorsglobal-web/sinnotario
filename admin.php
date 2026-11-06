<?php
/**
 * SinNotario.cl - Consola de Administración Operativa (/admin)
 * Keystone Advisors
 */
session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/api/adapters/StorageR2Adapter.php';

// Control de Sesión Administrativa Básica
$auth = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

if (isset($_POST['login_admin'])) {
    $user = $_POST['admin_user'] ?? '';
    $pass = $_POST['admin_pass'] ?? '';
    if ($user === 'rodrigo' && $pass === 'Keystone2026#SinNotario') {
        $_SESSION['admin_auth'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $errorLogin = 'Credenciales administrativas inválidas';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$pdo = Database::getInstance();

// Consultas de métricas y datos
$stmtDocs = $pdo->query("SELECT d.*, t.nombre as template_nombre FROM documents d LEFT JOIN templates t ON d.template_id = t.id ORDER BY d.id DESC LIMIT 50");
$documents = $stmtDocs->fetchAll();

$totalEmitidos = count($documents);
$totalMonto = array_sum(array_column($documents, 'monto_pagado'));
$stmtTplCount = $pdo->query("SELECT COUNT(*) FROM templates");
$totalTemplates = (int)$stmtTplCount->fetchColumn();

// Estado de Cloudflare R2
$r2 = new StorageR2Adapter();
$r2Ok = !empty($r2->accountId) && !empty($r2->apiToken);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consola de Administración | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            background: #0A1C33;
            color: #94A3B8;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #94A3B8;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
            transition: all 0.2s;
        }
        .admin-nav-item:hover, .admin-nav-item.active {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }
        .admin-nav-item.active {
            border-left: 3px solid var(--accent);
            color: var(--accent);
            font-weight: 600;
        }
        .admin-main {
            padding: 2rem;
            background: #F8FAFC;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .kpi-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        .kpi-val {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 0.35rem;
        }
        .table-wrap {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #F8FAFC;
            color: #64748B;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
        }
    </style>
</head>
<body>

<?php if (!$auth): ?>
    <!-- Modal de Login Administrativo -->
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0A1C33; padding: 1.5rem;">
        <div style="background: white; border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario" style="height: 48px;">
                <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-top: 0.75rem;">Consola de Administración</h2>
                <p style="font-size: 0.85rem; color: #64748B;">Keystone Advisors — Acceso Restringido</p>
            </div>

            <?php if (!empty($errorLogin)): ?>
                <div style="background: #FEF2F2; color: #991B1B; padding: 0.65rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; text-align: center;">
                    <?= htmlspecialchars($errorLogin) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Usuario Administrador</label>
                    <input type="text" name="admin_user" class="form-control" required placeholder="rodrigo" value="rodrigo">
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>Contraseña Maestra</label>
                    <input type="password" name="admin_pass" class="form-control" required placeholder="••••••••••••">
                </div>
                <button type="submit" name="login_admin" class="btn-cta btn-gold" style="width: 100%; margin-top: 1.5rem; justify-content: center;">
                    <i class="fas fa-lock"></i> Iniciar Sesión Segura
                </button>
            </form>
        </div>
    </div>
<?php else: ?>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div style="margin-bottom: 2rem; padding: 0 0.5rem;">
                <h2 style="font-size: 1.15rem; font-weight: 800; color: #FFFFFF;">SinNotario<span style="color:var(--accent);">.cl</span></h2>
                <span style="font-size: 0.72rem; color: #64748B; text-transform: uppercase;">Consola Operativa</span>
            </div>

            <nav style="flex: 1;">
                <a href="#dashboard" class="admin-nav-item active"><i class="fas fa-chart-line"></i> Dashboard General</a>
                <a href="#documentos" class="admin-nav-item"><i class="fas fa-file-contract"></i> Monitor Contratos</a>
                <a href="#whatsapp" class="admin-nav-item"><i class="fab fa-whatsapp"></i> Instancias WhatsApp</a>
                <a href="#vault" class="admin-nav-item"><i class="fas fa-database"></i> Bóveda Cloudflare R2</a>
                <a href="#ux" class="admin-nav-item"><i class="fas fa-mouse-pointer"></i> Telemetría UX</a>
            </nav>

            <div>
                <a href="admin.php?logout=1" class="admin-nav-item" style="color: #EF4444;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Consola de Gestión y Operaciones</h1>
                    <p style="font-size: 0.88rem; color: #64748B;">Monitoreo en vivo de transacciones, custodia y telecomunicaciones.</p>
                </div>
                <div>
                    <a href="index.php" target="_blank" class="btn-cta" style="background: white; border: 1px solid var(--border); color: #334155 !important;">
                        <i class="fas fa-external-link-alt"></i> Ver Sitio Web
                    </a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Documentos Procesados</div>
                    <div class="kpi-val"><?= $totalEmitidos ?></div>
                    <span style="font-size: 0.75rem; color: #059669;"><i class="fas fa-check-circle"></i> En base de datos</span>
                </div>
                <div class="kpi-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Catálogo Plantillas</div>
                    <div class="kpi-val"><?= $totalTemplates ?></div>
                    <span style="font-size: 0.75rem; color: #2563EB;">100% con Wizard 60/40</span>
                </div>
                <div class="kpi-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Bóveda Cloudflare R2</div>
                    <div class="kpi-val" style="color: <?= $r2Ok ? '#059669' : '#EF4444' ?>;"><?= $r2Ok ? 'ACTIVA' : 'OFFLINE' ?></div>
                    <span style="font-size: 0.75rem; color: #64748B;">sinnotario-vault-prod</span>
                </div>
                <div class="kpi-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">WhatsApp Gateway</div>
                    <div class="kpi-val" style="color: #059669;">ONLINE</div>
                    <span style="font-size: 0.75rem; color: #64748B;">Evolution API v2</span>
                </div>
            </div>

            <!-- Tabla de Documentos -->
            <div class="table-wrap">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-bottom: 1.25rem;">
                    Últimos Documentos Registrados y Custodia R2
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Folio Único</th>
                            <th>Instrumento</th>
                            <th>Estado Documento</th>
                            <th>Monto</th>
                            <th>Estado Pago</th>
                            <th>Bóveda R2 / SHA-256</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr><td colspan="7" style="text-align: center; color: #64748B;">No hay documentos emitidos aún.</td></tr>
                        <?php else: ?>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($d['codigo_verificacion']) ?></strong></td>
                                    <td><?= htmlspecialchars($d['titulo']) ?></td>
                                    <td>
                                        <span style="background: #EFF6FF; color: #1D4ED8; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                            <?= htmlspecialchars($d['estado']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($d['monto_pagado'], 0, ',', '.') ?> CLP</td>
                                    <td>
                                        <span style="background: <?= $d['estado_pago'] === 'PAGADO' ? '#DCFCE7' : '#FEF3C7' ?>; color: <?= $d['estado_pago'] === 'PAGADO' ? '#15803D' : '#92400E' ?>; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                                            <?= htmlspecialchars($d['estado_pago']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($d['pdf_r2_key'])): ?>
                                            <span style="color: #059669; font-weight: 600; font-size: 0.78rem;" title="<?= htmlspecialchars($d['pdf_sha256']) ?>">
                                                <i class="fas fa-check-shield"></i> Vault R2 (SHA-256)
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94A3B8; font-size: 0.75rem;">Pendiente PDF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #64748B; font-size: 0.8rem;"><?= htmlspecialchars($d['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

<?php endif; ?>

</body>
</html>

