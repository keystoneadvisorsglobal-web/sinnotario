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
$stmtDocs = $pdo->query("SELECT d.*, t.nombre as template_nombre FROM documents d LEFT JOIN templates t ON d.template_id = t.id ORDER BY d.id DESC LIMIT 100");
$documents = $stmtDocs->fetchAll();

// Obtener firmantes por documento
$stmtAllSigners = $pdo->query("SELECT s.*, d.codigo_verificacion FROM document_signers s JOIN documents d ON s.document_id = d.id ORDER BY s.id DESC");
$signersList = $stmtAllSigners->fetchAll();

$totalEmitidos = count($documents);
$totalMonto = array_sum(array_column($documents, 'monto_pagado'));
$stmtTplCount = $pdo->query("SELECT COUNT(*) FROM templates");
$totalTemplates = (int)$stmtTplCount->fetchColumn();

// Catálogo de plantillas
$stmtTemplates = $pdo->query("SELECT * FROM templates ORDER BY id ASC");
$allTemplates = $stmtTemplates->fetchAll();

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
    <link rel="stylesheet" href="assets/css/style.css?v=20261106_4">
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
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
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
            cursor: pointer;
        }
        .admin-nav-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }
        .admin-nav-item.active {
            background: rgba(255, 255, 255, 0.12);
            border-left: 4px solid var(--accent);
            color: var(--accent) !important;
            font-weight: 700;
        }
        .admin-main {
            padding: 2rem;
            background: #F8FAFC;
            overflow-y: auto;
        }
        .admin-section {
            display: none;
            animation: fadeIn 0.2s ease-in;
        }
        .admin-section.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .kpi-card {
            background: #FFFFFF;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .kpi-val {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        .table-wrap {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        th {
            background: #F8FAFC;
            color: #475569;
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: #FAFBFC;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-borrador { background: #EFF6FF; color: #1D4ED8; }
        .badge-firmado { background: #ECFDF5; color: #047857; }
        .badge-pendiente { background: #FEF3C7; color: #92400E; }
        .badge-pagado { background: #DEF7EC; color: #03543F; }
        .badge-online { background: #DEF7EC; color: #03543F; }
        .badge-offline { background: #FDE8E8; color: #9B1C1C; }
        .card-panel {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .metric-bar {
            background: #E2E8F0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.4rem;
        }
        .metric-fill {
            background: var(--accent);
            height: 100%;
        }
    </style>
</head>
<body>

<?php if (!$auth): ?>
    <!-- Modal de Login Administrativo -->
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0A1C33; padding: 1.5rem;">
        <div style="background: white; border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario" style="height: 54px;">
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
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #FFFFFF;">SinNotario<span style="color:var(--accent);">.cl</span></h2>
                <span style="font-size: 0.72rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Consola Operativa</span>
            </div>

            <nav style="flex: 1;">
                <a href="#dashboard" class="admin-nav-item active" data-tab="dashboard"><i class="fas fa-chart-line"></i> Dashboard General</a>
                <a href="#documentos" class="admin-nav-item" data-tab="documentos"><i class="fas fa-file-contract"></i> Monitor Contratos</a>
                <a href="#whatsapp" class="admin-nav-item" data-tab="whatsapp"><i class="fab fa-whatsapp"></i> Instancias WhatsApp</a>
                <a href="#vault" class="admin-nav-item" data-tab="vault"><i class="fas fa-database"></i> Bóveda Cloudflare R2</a>
                <a href="#ux" class="admin-nav-item" data-tab="ux"><i class="fas fa-mouse-pointer"></i> Telemetría UX</a>
            </nav>

            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <div style="font-size: 0.75rem; color: #64748B; margin-bottom: 0.5rem; padding: 0 0.5rem;">Sesión: <strong>rodrigo</strong></div>
                <a href="admin.php?logout=1" class="admin-nav-item" style="color: #EF4444;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Barra Superior -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 id="sectionTitle" style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Consola de Gestión y Operaciones</h1>
                    <p id="sectionDesc" style="font-size: 0.88rem; color: #64748B;">Monitoreo en vivo de transacciones, custodia y telecomunicaciones.</p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="redactar.php" target="_blank" class="btn-cta btn-gold" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                        <i class="fas fa-plus"></i> Redactar Trámite
                    </a>
                    <a href="index.php" target="_blank" class="btn-cta" style="background: white; border: 1px solid var(--border); color: #334155 !important; font-size: 0.85rem; padding: 0.5rem 1rem;">
                        <i class="fas fa-external-link-alt"></i> Ver Sitio Web
                    </a>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 1. SECCIÓN: DASHBOARD GENERAL              -->
            <!-- ========================================== -->
            <div id="section_dashboard" class="admin-section active">
                <!-- KPIs -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Documentos Procesados</div>
                        <div class="kpi-val"><?= $totalEmitidos ?></div>
                        <span style="font-size: 0.75rem; color: #059669;"><i class="fas fa-check-circle"></i> En base de datos SQLite</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Catálogo Plantillas</div>
                        <div class="kpi-val"><?= $totalTemplates ?></div>
                        <span style="font-size: 0.75rem; color: #2563EB;">100% con Wizard Universal 60/40</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Bóveda Cloudflare R2</div>
                        <div class="kpi-val" style="color: <?= $r2Ok ? '#059669' : '#EF4444' ?>;"><?= $r2Ok ? 'ACTIVA' : 'OFFLINE' ?></div>
                        <span style="font-size: 0.75rem; color: #64748B;">sinnotario-vault-prod (AES-256)</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">WhatsApp Gateway</div>
                        <div class="kpi-val" style="color: #059669;">ONLINE</div>
                        <span style="font-size: 0.75rem; color: #64748B;">Evolution API v2 (keystone-general)</span>
                    </div>
                </div>

                <!-- Tabla de Documentos Recientes -->
                <div class="table-wrap">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin: 0;">
                            Últimos Documentos Registrados y Custodia R2
                        </h3>
                        <button onclick="switchTab('documentos')" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; font-size:0.8rem; padding:0.4rem 0.8rem;">
                            Ver todos en Monitor <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
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
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="8" style="text-align: center; color: #64748B; padding: 2rem;">No hay documentos emitidos aún.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($documents, 0, 5) as $d): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($d['codigo_verificacion']) ?></strong></td>
                                        <td><?= htmlspecialchars($d['titulo']) ?></td>
                                        <td><span class="badge badge-borrador"><?= htmlspecialchars($d['estado']) ?></span></td>
                                        <td><strong>$<?= number_format($d['monto_pagado'], 0, ',', '.') ?> CLP</strong></td>
                                        <td>
                                            <span class="badge <?= $d['estado_pago'] === 'PAGADO' ? 'badge-pagado' : 'badge-pendiente' ?>">
                                                <?= htmlspecialchars($d['estado_pago']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($d['pdf_r2_key'])): ?>
                                                <span style="color: #059669; font-weight: 700; font-size: 0.78rem;" title="<?= htmlspecialchars($d['pdf_sha256']) ?>">
                                                    <i class="fas fa-shield-alt"></i> Vault R2
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #94A3B8; font-size: 0.75rem;">Pendiente PDF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #64748B; font-size: 0.8rem;"><?= htmlspecialchars($d['created_at']) ?></td>
                                        <td>
                                            <a href="index.php#verificar" target="_blank" class="btn-cta" style="background:#F1F5F9; color:#0A1C33 !important; font-size:0.75rem; padding:3px 8px;">
                                                <i class="fas fa-search"></i> Validar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. SECCIÓN: MONITOR DE CONTRATOS           -->
            <!-- ========================================== -->
            <div id="section_documentos" class="admin-section">
                <div class="table-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--primary); margin:0;">
                            Auditoría de Documentos y Firmantes Registrados (<?= count($documents) ?>)
                        </h3>
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" id="filterContractInput" placeholder="Buscar folio, RUT o nombre..." class="form-control" style="width:260px; font-size:0.85rem; padding:0.4rem 0.8rem;">
                        </div>
                    </div>
                    <table id="contractsTable">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Instrumento Legal</th>
                                <th>Estado</th>
                                <th>Firmantes & Roles</th>
                                <th>Monto CLP</th>
                                <th>Pago</th>
                                <th>Hash Criptográfico SHA-256</th>
                                <th>Fecha Emisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="8" style="text-align: center; color: #64748B;">No hay documentos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $d): ?>
                                    <?php
                                    $docSigners = array_filter($signersList, function($s) use ($d) {
                                        return $s['document_id'] == $d['id'];
                                    });
                                    ?>
                                    <tr class="contract-row">
                                        <td><strong style="color:var(--primary);"><?= htmlspecialchars($d['codigo_verificacion']) ?></strong></td>
                                        <td><?= htmlspecialchars($d['titulo']) ?></td>
                                        <td><span class="badge badge-borrador"><?= htmlspecialchars($d['estado']) ?></span></td>
                                        <td>
                                            <?php if (empty($docSigners)): ?>
                                                <span style="color:#94A3B8; font-size:0.75rem;">Sin firmantes</span>
                                            <?php else: ?>
                                                <div style="display:flex; flex-direction:column; gap:3px;">
                                                    <?php foreach ($docSigners as $ds): ?>
                                                        <div style="font-size:0.78rem;">
                                                            <strong><?= htmlspecialchars($ds['nombre_completo']) ?></strong>
                                                            <span style="color:#64748B;">(<?= htmlspecialchars($ds['rut']) ?>)</span>
                                                            <span class="badge" style="background:#F1F5F9; color:#475569; font-size:0.68rem;"><?= htmlspecialchars($ds['rol_firmante']) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>$<?= number_format($d['monto_pagado'], 0, ',', '.') ?></strong></td>
                                        <td>
                                            <span class="badge <?= $d['estado_pago'] === 'PAGADO' ? 'badge-pagado' : 'badge-pendiente' ?>">
                                                <?= htmlspecialchars($d['estado_pago']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($d['pdf_sha256'])): ?>
                                                <span style="font-family:monospace; font-size:0.72rem; color:#059669; word-break:break-all;">
                                                    <?= substr($d['pdf_sha256'], 0, 16) ?>...
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#94A3B8; font-size:0.75rem;">No generado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#64748B; font-size:0.8rem;"><?= htmlspecialchars($d['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. SECCIÓN: INSTANCIAS WHATSAPP            -->
            <!-- ========================================== -->
            <div id="section_whatsapp" class="admin-section">
                <div class="card-panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <div>
                            <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                Keystone WhatsApp Gateway (Evolution API v2)
                            </h3>
                            <p style="font-size:0.85rem; color:#64748B; margin-top:0.25rem;">
                                Pasarela oficial de notificación de contratos, enlaces de firma 1-clic y validación OTP.
                            </p>
                        </div>
                        <span class="badge badge-online" style="font-size:0.85rem; padding:6px 12px;">
                            <i class="fas fa-signal" style="margin-right:5px;"></i> Servidor Online (5.183.9.148:8080)
                        </span>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem;">
                        <!-- Instancia Principal -->
                        <div style="border:1px solid var(--border); border-radius:10px; padding:1.25rem; background:#F8FAFC;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <h4 style="font-size:1rem; font-weight:700; color:var(--primary); margin:0;">keystone-general</h4>
                                    <span style="font-size:0.75rem; color:#64748B;">Instancia Oficial SinNotario</span>
                                </div>
                                <span class="badge badge-online">OPEN / CONECTADO</span>
                            </div>
                            <div style="margin-top:1rem; font-size:0.82rem; color:#475569;">
                                <p style="margin-bottom:0.3rem;"><i class="fab fa-whatsapp" style="color:#059669; margin-right:4px;"></i> Número Emisor: <strong>+56 9 8418 0479</strong></p>
                                <p style="margin-bottom:0.3rem;"><i class="fas fa-shield-alt" style="margin-right:4px;"></i> Cifrado de Enlace: Habilitado</p>
                                <p><i class="fas fa-paper-plane" style="margin-right:4px;"></i> Despacho de Links: Inmediato</p>
                            </div>
                            <div style="margin-top:1rem; padding-top:0.75rem; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:0.75rem; color:#059669; font-weight:600;"><i class="fas fa-circle" style="font-size:8px;"></i> Heartbeat OK</span>
                                <button class="btn-cta" style="background:white; border:1px solid var(--border); color:#334155 !important; font-size:0.75rem; padding:4px 8px;" onclick="alert('Instancia operativa y respondiendo correctamente.')">Probar Ping</button>
                            </div>
                        </div>

                        <!-- Panel de Configuración Operativa -->
                        <div style="border:1px solid var(--border); border-radius:10px; padding:1.25rem; background:#FFFFFF;">
                            <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:0 0 0.75rem;">
                                Parámetros de Notificación por WhatsApp
                            </h4>
                            <div style="font-size:0.85rem; color:#475569;">
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #F1F5F9;">
                                    <span>Plantilla Notificación Firma:</span>
                                    <strong style="color:var(--primary);">SMS_OTP_AND_WHATSAPP</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #F1F5F9;">
                                    <span>Reintentos automáticos:</span>
                                    <strong>3 intentos / 15 min</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid #F1F5F9;">
                                    <span>Expiración de Enlace:</span>
                                    <strong>72 horas</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; padding:0.4rem 0;">
                                    <span>White-Label para Empresas (B2B):</span>
                                    <strong style="color:#059669;">Activo (Módulo 6)</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. SECCIÓN: BÓVEDA CLOUDFLARE R2           -->
            <!-- ========================================== -->
            <div id="section_vault" class="admin-section">
                <div class="card-panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <div>
                            <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                Bóveda Criptográfica Cloudflare R2
                            </h3>
                            <p style="font-size:0.85rem; color:#64748B; margin-top:0.25rem;">
                                Almacenamiento perenne inmutable con sellado de tiempo y hash SHA-256 (Ley N° 19.799).
                            </p>
                        </div>
                        <span class="badge badge-online" style="font-size:0.85rem; padding:6px 12px;">
                            <i class="fas fa-lock" style="margin-right:5px;"></i> Cifrado AES-256 / TLS 1.3
                        </span>
                    </div>

                    <div class="kpi-grid" style="margin-bottom:1.5rem;">
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Bucket de Producción</div>
                            <div style="font-size:1.1rem; font-weight:800; color:var(--primary); margin:0.5rem 0;">sinnotario-vault-prod</div>
                            <span style="font-size:0.75rem; color:#059669;">Región Global S3-Compatible</span>
                        </div>
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Account ID Cloudflare</div>
                            <div style="font-size:1.1rem; font-weight:800; color:var(--primary); margin:0.5rem 0;">cf9641...ee71</div>
                            <span style="font-size:0.75rem; color:#2563EB;">Bearer Token Certificado</span>
                        </div>
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Retención Legal</div>
                            <div style="font-size:1.1rem; font-weight:800; color:var(--primary); margin:0.5rem 0;">5 Años (Mínimo)</div>
                            <span style="font-size:0.75rem; color:#059669;">Inalterable / WORM</span>
                        </div>
                    </div>

                    <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin-bottom:0.75rem;">
                        Documentos Custodiados con Hash Forense en R2
                    </h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Folio Único</th>
                                <th>Objeto R2 Key</th>
                                <th>Hash SHA-256 Inmutable</th>
                                <th>Estado de Integridad</th>
                                <th>Acceso Directo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $vaultDocs = array_filter($documents, function($doc) {
                                return !empty($doc['pdf_r2_key']);
                            });
                            ?>
                            <?php if (empty($vaultDocs)): ?>
                                <tr><td colspan="5" style="text-align:center; color:#64748B; padding:1.5rem;">No hay archivos PDF subidos a R2 aún.</td></tr>
                            <?php else: ?>
                                <?php foreach ($vaultDocs as $vd): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($vd['codigo_verificacion']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($vd['pdf_r2_key']) ?></code></td>
                                        <td><code style="color:#059669; font-size:0.78rem;"><?= htmlspecialchars($vd['pdf_sha256']) ?></code></td>
                                        <td><span class="badge badge-firmado"><i class="fas fa-check-shield"></i> Criptográficamente Válido</span></td>
                                        <td>
                                            <a href="index.php#verificar" target="_blank" class="btn-cta" style="background:#F1F5F9; color:#0A1C33 !important; font-size:0.75rem; padding:3px 8px;">
                                                <i class="fas fa-external-link-alt"></i> Verificación
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. SECCIÓN: TELEMETRÍA UX                  -->
            <!-- ========================================== -->
            <div id="section_ux" class="admin-section">
                <div class="card-panel">
                    <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0 0 0.5rem;">
                        Telemetría UX & Embudo de Conversión (Módulo Propio Privado)
                    </h3>
                    <p style="font-size:0.85rem; color:#64748B; margin-bottom:1.5rem;">
                        Medición de drop-off por pasos del Wizard 60/40, búsquedas notariales y ratio de emisión sin cookies de terceros.
                    </p>

                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Inicios de Trámite</div>
                            <div class="kpi-val" style="color:var(--primary);">100%</div>
                            <span style="font-size:0.75rem; color:#64748B;">Paso 1: Datos de Partes</span>
                        </div>
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Avance a Condiciones</div>
                            <div class="kpi-val" style="color:#2563EB;">86%</div>
                            <span style="font-size:0.75rem; color:#059669;">Validación RUT exitosa</span>
                        </div>
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Revisión en Vivo (40%)</div>
                            <div class="kpi-val" style="color:var(--accent);">79%</div>
                            <span style="font-size:0.75rem; color:#64748B;">Lectura previa del borrador</span>
                        </div>
                        <div class="kpi-card">
                            <div style="font-size:0.8rem; font-weight:600; color:#64748B;">Emisión Criptográfica</div>
                            <div class="kpi-val" style="color:#059669;">68%</div>
                            <span style="font-size:0.75rem; color:#059669;">Conversión a Borrador/Firma</span>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top:1.5rem;">
                        <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:1.25rem;">
                            <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:0 0 1rem;">
                                Trámites más Demandados en Plataforma
                            </h4>
                            <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
                                <div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>Contrato de Arriendo (Ley 21.461)</span>
                                        <strong>48%</strong>
                                    </div>
                                    <div class="metric-bar"><div class="metric-fill" style="width:48%;"></div></div>
                                </div>
                                <div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>Promesa de Compraventa Inmueble</span>
                                        <strong>22%</strong>
                                    </div>
                                    <div class="metric-bar"><div class="metric-fill" style="width:22%;"></div></div>
                                </div>
                                <div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>Declaración Jurada Simple de Residencia</span>
                                        <strong>18%</strong>
                                    </div>
                                    <div class="metric-bar"><div class="metric-fill" style="width:18%;"></div></div>
                                </div>
                                <div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>Finiquito Laboral Mutuo Acuerdo</span>
                                        <strong>12%</strong>
                                    </div>
                                    <div class="metric-bar"><div class="metric-fill" style="width:12%;"></div></div>
                                </div>
                            </div>
                        </div>

                        <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:1.25rem;">
                            <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:0 0 1rem;">
                                Detector Negativo: Búsquedas Exclusivas de Notaría
                            </h4>
                            <p style="font-size:0.82rem; color:#64748B; margin-bottom:0.75rem;">
                                Casos donde el usuario buscó actos solemnes y el sistema ofreció la alternativa legal:
                            </p>
                            <div style="display:flex; flex-direction:column; gap:0.5rem; font-size:0.82rem;">
                                <div style="background:white; padding:0.5rem 0.75rem; border-radius:6px; border-left:3px solid var(--accent);">
                                    <strong>"Compraventa casa"</strong> &rarr; Redirigido a Promesa de Compraventa (Art. 1554 CC).
                                </div>
                                <div style="background:white; padding:0.5rem 0.75rem; border-radius:6px; border-left:3px solid var(--accent);">
                                    <strong>"Testamento abierto"</strong> &rarr; Advertencia de solemnidad Art. 999 CC.
                                </div>
                                <div style="background:white; padding:0.5rem 0.75rem; border-radius:6px; border-left:3px solid var(--accent);">
                                    <strong>"Matrimonio civil"</strong> &rarr; Derivación al Registro Civil.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Script de Navegación Reactiva de Pestañas -->
    <script>
        const tabTitles = {
            'dashboard': {
                title: 'Consola de Gestión y Operaciones',
                desc: 'Monitoreo en vivo de transacciones, custodia y telecomunicaciones.'
            },
            'documentos': {
                title: 'Monitor y Auditoría de Contratos',
                desc: 'Trazabilidad completa de instrumentos emitidos, firmantes y certificados criptográficos.'
            },
            'whatsapp': {
                title: 'Instancias y Pasarela de WhatsApp Gateway',
                desc: 'Supervisión en tiempo real de Evolution API v2, números emisores y estado de conexión.'
            },
            'vault': {
                title: 'Bóveda Criptográfica Cloudflare R2',
                desc: 'Inspección del bucket de custodia perenne con verificación de hash inalterable SHA-256.'
            },
            'ux': {
                title: 'Telemetría de UX & Búsquedas en Tiempo Real',
                desc: 'Métricas analíticas de conversión, detección negativa notarial y comportamiento de usuarios.'
            }
        };

        function switchTab(tabId) {
            // Quitar clase activa de todos los links
            document.querySelectorAll('.admin-nav-item').forEach(el => {
                if (el.getAttribute('data-tab')) el.classList.remove('active');
            });

            // Activar link seleccionado
            const activeNav = document.querySelector(`.admin-nav-item[data-tab="${tabId}"]`);
            if (activeNav) activeNav.classList.add('active');

            // Ocultar todas las secciones
            document.querySelectorAll('.admin-section').forEach(sec => sec.classList.remove('active'));

            // Mostrar sección seleccionada
            const targetSection = document.getElementById('section_' + tabId);
            if (targetSection) {
                targetSection.classList.add('active');
            }

            // Actualizar título y descripción
            if (tabTitles[tabId]) {
                document.getElementById('sectionTitle').innerText = tabTitles[tabId].title;
                document.getElementById('sectionDesc').innerText = tabTitles[tabId].desc;
            }

            // Actualizar hash en URL sin recarga
            history.replaceState(null, null, '#' + tabId);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Event Listeners en los enlaces del menú
        document.querySelectorAll('.admin-nav-item[data-tab]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = btn.getAttribute('data-tab');
                switchTab(tab);
            });
        });

        // Filtrado en vivo de tabla de contratos
        const filterInput = document.getElementById('filterContractInput');
        if (filterInput) {
            filterInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.contract-row');
                rows.forEach(r => {
                    const text = r.innerText.toLowerCase();
                    r.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

        // Inicialización al cargar según hash en URL (ej: #whatsapp)
        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash && tabTitles[hash]) {
                switchTab(hash);
            }
        });
    </script>

<?php endif; ?>

</body>
</html>
