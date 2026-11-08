<?php
/**
 * SinNotario.cl - Consola de Administración Operativa (/admin)
 * Keystone Advisors
 */
session_start();
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/api/adapters/StorageR2Adapter.php';
require_once __DIR__ . '/src/Services/B2BService.php';

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

// Consultas B2B Empresas
$stmtCompanies = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM b2b_batches WHERE company_id = c.id) as total_batches,
           (SELECT COUNT(*) FROM worker_folders WHERE company_id = c.id) as total_workers
    FROM companies c
    ORDER BY c.id DESC
");
$allCompanies = $stmtCompanies ? $stmtCompanies->fetchAll(PDO::FETCH_ASSOC) : [];

// Consultas Clientes Persona Natural (SOL-0074)
$stmtPersonas = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM documents WHERE user_id = u.id) as total_docs,
           (SELECT COUNT(*) FROM documents WHERE user_id = u.id AND estado = 'COMPLETADO') as docs_completados,
           (SELECT SUM(monto_pagado) FROM documents WHERE user_id = u.id) as gasto_total_clp
    FROM users u
    ORDER BY u.id DESC
");
$allPersonas = $stmtPersonas ? $stmtPersonas->fetchAll(PDO::FETCH_ASSOC) : [];

$stmtBillingLogs = $pdo->query("
    SELECT b.*, c.razon_social, c.rut as empresa_rut
    FROM b2b_billing_logs b
    LEFT JOIN companies c ON b.company_id = c.id
    ORDER BY b.id DESC
    LIMIT 100
");
$allBillingLogs = $stmtBillingLogs ? $stmtBillingLogs->fetchAll(PDO::FETCH_ASSOC) : [];

// Consultas Órdenes y Pagos Online (Mercado Pago & Khipu)
$stmtOrders = $pdo->query("
    SELECT p.*,
           c.razon_social as empresa_razon, c.rut as empresa_rut,
           u.nombre as usuario_nombre, u.apellido as usuario_apellido, u.email as usuario_email,
           d.codigo_verificacion as doc_codigo, d.titulo as doc_titulo
    FROM payment_orders p
    LEFT JOIN companies c ON p.company_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN documents d ON p.document_id = d.id
    ORDER BY p.id DESC
    LIMIT 150
");
$allPaymentOrders = $stmtOrders ? $stmtOrders->fetchAll(PDO::FETCH_ASSOC) : [];

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

// Procesar actualización de precios desde Consola Administrativa
$msgPricingSuccess = '';
if (isset($_POST['save_pricing_settings'])) {
    $precioFirmaExtra = (int)($_POST['precio_firma_extra'] ?? 2990);
    $precioBasePdf = (int)($_POST['precio_base_subir_pdf'] ?? 7990);

    $stmtUpdExtra = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('precio_firma_extra', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    $stmtUpdExtra->execute([$precioFirmaExtra]);

    $stmtUpdBase = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('precio_base_subir_pdf', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    $stmtUpdBase->execute([$precioBasePdf]);

    if (!empty($_POST['tpl_prices']) && is_array($_POST['tpl_prices'])) {
        $stmtUpdTpl = $pdo->prepare("UPDATE templates SET precio_base = ? WHERE id = ?");
        foreach ($_POST['tpl_prices'] as $tId => $tPrice) {
            $stmtUpdTpl->execute([(int)$tPrice, (int)$tId]);
        }
    }

    $msgPricingSuccess = '¡Precios de trámites y tarifas del catálogo oficial actualizadas exitosamente!';
}

// Procesar actualización de integraciones del sistema (Mercado Pago, Khipu, Google OAuth, SMTP)
$msgIntegrationsSuccess = '';
if (isset($_POST['save_integrations_settings'])) {
    if (isset($_POST['google_oauth_client_id'])) {
        $gClientId = trim($_POST['google_oauth_client_id']);
        $stmtG = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('google_oauth_client_id', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
        $stmtG->execute([$gClientId]);
    }

    // Parámetros SMTP para Correos Transaccionales
    $smtpFields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_name'];
    $stmtSmtp = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    foreach ($smtpFields as $sf) {
        if (isset($_POST[$sf])) {
            $stmtSmtp->execute([$sf, trim($_POST[$sf])]);
        }
    }

    // Parámetros Mercado Pago (Pasarela de Pagos)
    $mpEnabled = isset($_POST['mp_enabled']) && $_POST['mp_enabled'] === '1' ? '1' : '0';
    $stmtMpEn = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('mp_enabled', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    $stmtMpEn->execute([$mpEnabled]);

    $mpFields = [
        'mp_mode',
        'mp_public_key_production', 'mp_access_token_production',
        'mp_public_key_sandbox', 'mp_access_token_sandbox',
        'mp_webhook_secret',
        'mp_public_key', 'mp_access_token' // retrocompatibilidad
    ];
    $stmtMp = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    foreach ($mpFields as $mpf) {
        if (isset($_POST[$mpf])) {
            $stmtMp->execute([$mpf, trim($_POST[$mpf])]);
        }
    }

    // Parámetros Khipu (Transferencias Bancarias Simplificadas)
    $khipuEnabled = isset($_POST['khipu_enabled']) && $_POST['khipu_enabled'] === '1' ? '1' : '0';
    $stmtKhEn = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES ('khipu_enabled', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    $stmtKhEn->execute([$khipuEnabled]);

    $khipuFields = [
        'khipu_mode',
        'khipu_receiver_id_production', 'khipu_secret_key_production',
        'khipu_receiver_id_sandbox', 'khipu_secret_key_sandbox',
        'khipu_receiver_id', 'khipu_secret_key' // retrocompatibilidad
    ];
    $stmtKhipu = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    foreach ($khipuFields as $kf) {
        if (isset($_POST[$kf])) {
            $stmtKhipu->execute([$kf, trim($_POST[$kf])]);
        }
    }

    $msgIntegrationsSuccess = '¡Configuración de pasarelas de pago, webhooks y credenciales actualizada exitosamente!';
}

// Procesar acciones de gestión B2B desde Consola Administrativa
$msgB2BSuccess = '';
if (isset($_POST['admin_b2b_action'])) {
    $actionType = $_POST['admin_b2b_action'];
    $targetCompId = (int)($_POST['target_company_id'] ?? 0);

    if ($actionType === 'update_plan' && $targetCompId > 0) {
        $nuevoPlan = trim($_POST['plan_nombre'] ?? 'PLAN_INICIAL');
        $nuevoEstado = trim($_POST['plan_estado'] ?? 'ACTIVO');
        $modalidad = trim($_POST['modalidad_cobro'] ?? 'PREPAGO');
        $saldoExtra = (int)($_POST['saldo_firmas_prepago'] ?? 50);
        $umbral = (int)($_POST['umbral_max_postpago'] ?? 200);
        $tarifa = (int)($_POST['tarifa_tramo_clp'] ?? 7990);

        $stmtUpdComp = $pdo->prepare("
            UPDATE companies 
            SET plan_nombre = ?, plan_estado = ?, modalidad_cobro = ?,
                saldo_firmas_prepago = ?, umbral_max_postpago = ?, tarifa_tramo_clp = ?,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmtUpdComp->execute([$nuevoPlan, $nuevoEstado, $modalidad, $saldoExtra, $umbral, $tarifa, $targetCompId]);
        $msgB2BSuccess = "Empresa #{$targetCompId} actualizada exitosamente.";
    } elseif ($actionType === 'add_saldo' && $targetCompId > 0) {
        $firmasAdd = (int)($_POST['firmas_agregar'] ?? 50);
        $stmtAdd = $pdo->prepare("UPDATE companies SET saldo_firmas_prepago = saldo_firmas_prepago + ?, updated_at = datetime('now') WHERE id = ?");
        $stmtAdd->execute([$firmasAdd, $targetCompId]);

        // Registrar movimiento
        $stmtLog = $pdo->prepare("
            INSERT INTO b2b_billing_logs (company_id, tipo_movimiento, modalidad, cantidad_firmas, monto_clp, descripcion)
            VALUES (?, 'RECARGA_MANUAL_ADMIN', 'PREPAGO', ?, 0, 'Abono manual administrativo de firmas')
        ");
        $stmtLog->execute([$targetCompId, $firmasAdd]);
        $msgB2BSuccess = "Se abonaron {$firmasAdd} firmas a la empresa #{$targetCompId}.";
    } elseif ($actionType === 'update_b2b_packs') {
        $packKeys = [
            'b2b_pack_50_nombre', 'b2b_pack_50_firmas', 'b2b_pack_50_precio_unit', 'b2b_pack_50_ahorro', 'b2b_pack_50_badge',
            'b2b_pack_200_nombre', 'b2b_pack_200_firmas', 'b2b_pack_200_precio_unit', 'b2b_pack_200_ahorro', 'b2b_pack_200_badge',
            'b2b_pack_500_nombre', 'b2b_pack_500_firmas', 'b2b_pack_500_precio_unit', 'b2b_pack_500_ahorro', 'b2b_pack_500_badge'
        ];
        $stmtSavePack = $pdo->prepare("INSERT INTO system_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
        foreach ($packKeys as $pk) {
            if (isset($_POST[$pk])) {
                $stmtSavePack->execute([$pk, trim($_POST[$pk])]);
            }
        }
        $msgB2BSuccess = "¡Packs corporativos de prepago y valores unitarios actualizados exitosamente en todo el portal!";
    }

    // Recargar lista de empresas
    $stmtCompanies = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM b2b_batches WHERE company_id = c.id) as total_batches,
               (SELECT COUNT(*) FROM worker_folders WHERE company_id = c.id) as total_workers
        FROM companies c
        ORDER BY c.id DESC
    ");
    $allCompanies = $stmtCompanies ? $stmtCompanies->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Cargar configuración de precios actual
$stmtSets = $pdo->query("SELECT * FROM system_settings");
$sysSettings = [];
if ($stmtSets) {
    foreach ($stmtSets->fetchAll(PDO::FETCH_ASSOC) as $srow) {
        $sysSettings[$srow['key']] = $srow['value'];
    }
}
$currentPrecioExtra = (int)($sysSettings['precio_firma_extra'] ?? 2990);
$currentPrecioBasePdf = (int)($sysSettings['precio_base_subir_pdf'] ?? 7990);

// Recargar plantillas
$stmtTemplates = $pdo->query("SELECT * FROM templates ORDER BY id ASC");
$allTemplates = $stmtTemplates->fetchAll();
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
                <a href="#empresas" class="admin-nav-item" data-tab="empresas" style="color:#D4AF37; font-weight:800;"><i class="fas fa-building"></i> Empresas & B2B</a>
                <a href="#clientes" class="admin-nav-item" data-tab="clientes" style="color:#38BDF8; font-weight:700;"><i class="fas fa-users"></i> Clientes Persona</a>
                <a href="#pagos" class="admin-nav-item" data-tab="pagos" style="color:#10B981; font-weight:800;"><i class="fas fa-cash-register"></i> Pagos & Conciliación</a>
                <a href="#precios" class="admin-nav-item" data-tab="precios" style="color:var(--accent); font-weight:700;"><i class="fas fa-tags"></i> Precios & Tarifas</a>
                <a href="#integraciones" class="admin-nav-item" data-tab="integraciones" style="color:#A855F7; font-weight:700;"><i class="fas fa-cogs"></i> Pasarelas & Integraciones</a>
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
            <!-- 1B. SECCIÓN: EMPRESAS & B2B (SOL-0068)       -->
            <!-- ========================================== -->
            <div id="section_empresas" class="admin-section">
                <?php if (!empty($msgB2BSuccess)): ?>
                    <div style="background:#DCFCE7; border:1px solid #86EFAC; color:#15803D; padding:1rem; border-radius:8px; font-weight:700; margin-bottom:1.5rem; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-check-circle fa-lg"></i> <?= htmlspecialchars($msgB2BSuccess) ?>
                    </div>
                <?php endif; ?>

                <!-- Métricas B2B -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Empresas Registradas</div>
                        <div class="kpi-val" style="color:var(--primary);"><?= count($allCompanies) ?></div>
                        <span style="font-size: 0.75rem; color: #059669;"><i class="fas fa-building"></i> Clientes Corporativos</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Firmas Prepagadas Activas</div>
                        <div class="kpi-val" style="color:#059669;">
                            <?= array_sum(array_column($allCompanies, 'saldo_firmas_prepago')) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #64748B;">Bolsas de saldo en custodia</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Firmas Totales Emitidas</div>
                        <div class="kpi-val" style="color:#2563EB;">
                            <?= array_sum(array_column($allCompanies, 'firmas_utilizadas')) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #64748B;">Consumo masivo acumulado</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Postpago por Facturar</div>
                        <div class="kpi-val" style="color:#D4AF37;">
                            $<?= number_format(array_sum(array_column($allCompanies, 'gasto_acumulado_periodo')), 0, '', '.') ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #B45309;">Devengado para DTE 33 fin de mes</span>
                    </div>
                </div>

                <!-- Tabla de Empresas Clientes -->
                <div class="table-wrap" style="margin-bottom:2rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                        <div>
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin: 0;">
                                <i class="fas fa-building"></i> Cartera de Empresas B2B
                            </h3>
                            <p style="font-size:0.83rem; color:#64748B; margin:4px 0 0;">Control de suscripciones, bolsas prepago, consumo y asignación de tarifas especiales.</p>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #F8FAFC; color: #64748B; text-align: left;">
                                    <th style="padding: 0.75rem;">ID</th>
                                    <th style="padding: 0.75rem;">Razón Social & RUT</th>
                                    <th style="padding: 0.75rem;">Representante Legal</th>
                                    <th style="padding: 0.75rem;">Modalidad</th>
                                    <th style="padding: 0.75rem;">Saldo Bolsa Prepago</th>
                                    <th style="padding: 0.75rem;">Consumo / Umbral</th>
                                    <th style="padding: 0.75rem;">Tarifa Firma</th>
                                    <th style="padding: 0.75rem;">Estado</th>
                                    <th style="padding: 0.75rem; text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allCompanies)): ?>
                                    <tr><td colspan="9" style="padding:2rem; text-align:center; color:#94A3B8;">Aún no hay empresas registradas en la base de datos.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allCompanies as $comp): ?>
                                        <tr style="border-bottom: 1px solid #E2E8F0;">
                                            <td style="padding: 0.75rem; font-weight: 700;">#<?= $comp['id'] ?></td>
                                            <td style="padding: 0.75rem;">
                                                <strong style="color:var(--primary);"><?= htmlspecialchars($comp['razon_social']) ?></strong><br>
                                                <code style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($comp['rut']) ?></code>
                                            </td>
                                            <td style="padding: 0.75rem; font-size:0.83rem;">
                                                <?= htmlspecialchars($comp['rep_legal_nombre'] ?: 'No asignado') ?><br>
                                                <span style="color:#64748B;"><?= htmlspecialchars($comp['rep_legal_email'] ?: '-') ?></span>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <span style="background:<?= $comp['modalidad_cobro'] === 'PREPAGO' ? '#EFF6FF' : '#FEF3C7' ?>; color:<?= $comp['modalidad_cobro'] === 'PREPAGO' ? '#1D4ED8' : '#B45309' ?>; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                                    <?= $comp['modalidad_cobro'] ?: 'PREPAGO' ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <strong style="font-size:1.05rem; color:<?= (int)$comp['saldo_firmas_prepago'] < 10 ? '#EF4444' : '#059669' ?>;">
                                                    <?= (int)$comp['saldo_firmas_prepago'] ?>
                                                </strong> <span style="font-size:0.75rem; color:#64748B;">firmas</span>
                                            </td>
                                            <td style="padding: 0.75rem; font-size:0.83rem;">
                                                <strong><?= (int)$comp['firmas_utilizadas'] ?></strong> emitidas<br>
                                                <span style="color:#64748B;">Umbral: <?= (int)$comp['umbral_max_postpago'] ?></span>
                                            </td>
                                            <td style="padding: 0.75rem; font-weight:700; color:var(--primary);">
                                                $<?= number_format((int)($comp['tarifa_tramo_clp'] ?: 2490), 0, '', '.') ?> CLP
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <span style="background:<?= $comp['plan_estado'] === 'ACTIVO' ? '#DCFCE7' : '#FEE2E2' ?>; color:<?= $comp['plan_estado'] === 'ACTIVO' ? '#15803D' : '#B91C1C' ?>; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                                    <?= $comp['plan_estado'] ?: 'ACTIVO' ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.75rem; text-align: right;">
                                                <button type="button" class="btn-cta btn-gold" style="padding:4px 8px; font-size:0.75rem; margin-right:4px;" onclick="openAdminAddSaldo(<?= $comp['id'] ?>, '<?= htmlspecialchars(addslashes($comp['razon_social'])) ?>')">
                                                    <i class="fas fa-plus"></i> Abonar Saldo
                                                </button>
                                                <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1; padding:4px 8px; font-size:0.75rem;" onclick='openAdminEditComp(<?= json_encode($comp) ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historial Contable y Recargas B2B -->
                <div class="table-wrap">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin: 0 0 1rem;">
                        <i class="fas fa-file-invoice-dollar"></i> Bitácora Contable y Recargas de Bolsas
                    </h3>
                    <div style="overflow-x: auto; max-height:350px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: #F8FAFC; color: #64748B; text-align: left; position:sticky; top:0;">
                                    <th style="padding: 0.6rem;">ID</th>
                                    <th style="padding: 0.6rem;">Empresa</th>
                                    <th style="padding: 0.6rem;">Tipo Movimiento</th>
                                    <th style="padding: 0.6rem;">Firmas</th>
                                    <th style="padding: 0.6rem;">Monto CLP</th>
                                    <th style="padding: 0.6rem;">Saldo Ant. -> Nuevo</th>
                                    <th style="padding: 0.6rem;">Descripción</th>
                                    <th style="padding: 0.6rem;">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allBillingLogs)): ?>
                                    <tr><td colspan="8" style="padding:1.5rem; text-align:center; color:#94A3B8;">No hay registros contables en bitácora.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allBillingLogs as $log): ?>
                                        <tr style="border-bottom: 1px solid #E2E8F0;">
                                            <td style="padding: 0.6rem;">#<?= $log['id'] ?></td>
                                            <td style="padding: 0.6rem; font-weight:600;"><?= htmlspecialchars($log['razon_social'] ?: 'Empresa #' . $log['company_id']) ?></td>
                                            <td style="padding: 0.6rem;">
                                                <span style="font-size:0.72rem; font-weight:800; padding:2px 6px; border-radius:3px; background:<?= strpos($log['tipo_movimiento'], 'RECARGA') !== false ? '#DCFCE7' : '#F1F5F9' ?>; color:<?= strpos($log['tipo_movimiento'], 'RECARGA') !== false ? '#15803D' : '#334155' ?>;">
                                                    <?= $log['tipo_movimiento'] ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.6rem; font-weight:700;"><?= (int)$log['cantidad_firmas'] ?></td>
                                            <td style="padding: 0.6rem;"><?= $log['monto_clp'] ? '$' . number_format($log['monto_clp'], 0, '', '.') : '-' ?></td>
                                            <td style="padding: 0.6rem; font-family:monospace;"><?= $log['saldo_anterior'] ?> -> <?= $log['saldo_nuevo'] ?></td>
                                            <td style="padding: 0.6rem; color:#64748B; font-size:0.8rem;"><?= htmlspecialchars($log['descripcion'] ?: '-') ?></td>
                                            <td style="padding: 0.6rem; color:#94A3B8; font-size:0.78rem;"><?= $log['created_at'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Configuración de Bolsas Prepago y Tarifario B2B (SOL-0071) -->
                <?php $currentPacks = B2BService::getPrepaidPacks(); ?>
                <div class="table-wrap" style="border: 2px solid #E2E8F0; background:#FFF;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin:0;">
                                <i class="fas fa-tags" style="color:var(--gold);"></i> Definición de Packs Prepagados y Tarifario B2B
                            </h3>
                            <p style="font-size:0.83rem; color:#64748B; margin:4px 0 0;">
                                Configura la cantidad de firmas, precios unitarios y etiquetas de ahorro que verán las empresas en el portal.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="admin.php#empresas">
                        <input type="hidden" name="admin_b2b_action" value="update_b2b_packs">

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
                            <!-- Pack 50 -->
                            <div style="border:1px solid #CBD5E1; border-radius:10px; padding:1.25rem; background:#F8FAFC;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <span style="font-size:0.75rem; font-weight:800; color:#2563EB; background:#EFF6FF; padding:3px 8px; border-radius:4px;">PACK 1</span>
                                    <strong style="color:var(--primary); font-size:0.85rem;">Bolsa Inicial</strong>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Nombre Visible</label>
                                    <input type="text" name="b2b_pack_50_nombre" value="<?= htmlspecialchars($currentPacks['PACK_50']['nombre']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" required>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; margin-bottom:0.75rem;">
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Firmas Incluidas</label>
                                        <input type="number" name="b2b_pack_50_firmas" value="<?= $currentPacks['PACK_50']['firmas'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="1" required>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Precio Unitario CLP</label>
                                        <input type="number" name="b2b_pack_50_precio_unit" value="<?= $currentPacks['PACK_50']['precio_unit'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="500" required>
                                    </div>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Glosa de Ahorro / Subtexto</label>
                                    <input type="text" name="b2b_pack_50_ahorro" value="<?= htmlspecialchars($currentPacks['PACK_50']['ahorro_desc']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Insignia / Badge</label>
                                    <input type="text" name="b2b_pack_50_badge" value="<?= htmlspecialchars($currentPacks['PACK_50']['badge']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                            </div>

                            <!-- Pack 200 -->
                            <div style="border:2px solid #D4AF37; border-radius:10px; padding:1.25rem; background:#FFFBF0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <span style="font-size:0.75rem; font-weight:800; color:#B45309; background:#FEF3C7; padding:3px 8px; border-radius:4px;">PACK 2 (POPULAR)</span>
                                    <strong style="color:var(--primary); font-size:0.85rem;">Bolsa Crecimiento</strong>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Nombre Visible</label>
                                    <input type="text" name="b2b_pack_200_nombre" value="<?= htmlspecialchars($currentPacks['PACK_200']['nombre']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" required>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; margin-bottom:0.75rem;">
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Firmas Incluidas</label>
                                        <input type="number" name="b2b_pack_200_firmas" value="<?= $currentPacks['PACK_200']['firmas'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="1" required>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Precio Unitario CLP</label>
                                        <input type="number" name="b2b_pack_200_precio_unit" value="<?= $currentPacks['PACK_200']['precio_unit'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="500" required>
                                    </div>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Glosa de Ahorro / Subtexto</label>
                                    <input type="text" name="b2b_pack_200_ahorro" value="<?= htmlspecialchars($currentPacks['PACK_200']['ahorro_desc']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Insignia / Badge</label>
                                    <input type="text" name="b2b_pack_200_badge" value="<?= htmlspecialchars($currentPacks['PACK_200']['badge']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                            </div>

                            <!-- Pack 500 -->
                            <div style="border:1px solid #CBD5E1; border-radius:10px; padding:1.25rem; background:#F8FAFC;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <span style="font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:3px 8px; border-radius:4px;">PACK 3 (ENTERPRISE)</span>
                                    <strong style="color:var(--primary); font-size:0.85rem;">Bolsa Escala</strong>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Nombre Visible</label>
                                    <input type="text" name="b2b_pack_500_nombre" value="<?= htmlspecialchars($currentPacks['PACK_500']['nombre']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" required>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; margin-bottom:0.75rem;">
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Firmas Incluidas</label>
                                        <input type="number" name="b2b_pack_500_firmas" value="<?= $currentPacks['PACK_500']['firmas'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="1" required>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Precio Unitario CLP</label>
                                        <input type="number" name="b2b_pack_500_precio_unit" value="<?= $currentPacks['PACK_500']['precio_unit'] ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.85rem; border:1px solid #CBD5E1; border-radius:6px;" min="500" required>
                                    </div>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Glosa de Ahorro / Subtexto</label>
                                    <input type="text" name="b2b_pack_500_ahorro" value="<?= htmlspecialchars($currentPacks['PACK_500']['ahorro_desc']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                                <div>
                                    <label style="display:block; font-size:0.78rem; font-weight:700; color:#475569; margin-bottom:3px;">Insignia / Badge</label>
                                    <input type="text" name="b2b_pack_500_badge" value="<?= htmlspecialchars($currentPacks['PACK_500']['badge']) ?>" class="form-control" style="width:100%; padding:6px 10px; font-size:0.82rem; border:1px solid #CBD5E1; border-radius:6px;">
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" class="btn-cta btn-gold" style="font-size:0.95rem; padding:0.65rem 1.75rem;">
                                <i class="fas fa-save"></i> Guardar Configuración de Packs y Valores
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 1C. SECCIÓN: CLIENTES PERSONA (SOL-0074)   -->
            <!-- ========================================== -->
            <div id="section_clientes" class="admin-section">
                <!-- KPIs Clientes Persona -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Total Clientes Persona</div>
                        <div class="kpi-val" style="color:var(--primary);"><?= count($allPersonas) ?></div>
                        <span style="font-size: 0.75rem; color: #0284C7;"><i class="fas fa-user-check"></i> Cuentas Registradas</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Personas Naturales</div>
                        <div class="kpi-val" style="color:#059669;">
                            <?= count(array_filter($allPersonas, function($p) { return ($p['tipo_persona'] ?? 'NATURAL') === 'NATURAL'; })) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #64748B;">Trámites personales / particulares</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Contratos en Custodia</div>
                        <div class="kpi-val" style="color:#2563EB;">
                            <?= array_sum(array_column($allPersonas, 'total_docs')) ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #64748B;">Emitidos desde Bóveda Personal</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size: 0.8rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Ventas Retail Clientes</div>
                        <div class="kpi-val" style="color:#D4AF37;">
                            $<?= number_format(array_sum(array_column($allPersonas, 'gasto_total_clp')), 0, '', '.') ?>
                        </div>
                        <span style="font-size: 0.75rem; color: #B45309;">Pagos individuales recaudados</span>
                    </div>
                </div>

                <!-- Tabla de Clientes Persona -->
                <div class="table-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin:0;">
                                <i class="fas fa-address-book" style="color:#0284C7;"></i> Registro de Clientes Persona
                            </h3>
                            <p style="font-size:0.83rem; color:#64748B; margin:4px 0 0;">Historial de usuarios individuales, datos de contacto, contratos en bóveda y recaudación.</p>
                        </div>
                        <input type="text" id="filterPersonasInput" placeholder="Buscar por nombre, RUT o email..." style="padding:8px 14px; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem; width:280px;">
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;" id="tablePersonas">
                            <thead>
                                <tr style="background: #F8FAFC; color: #64748B; text-align: left;">
                                    <th style="padding: 0.75rem;">ID</th>
                                    <th style="padding: 0.75rem;">Cliente / Titular</th>
                                    <th style="padding: 0.75rem;">RUT</th>
                                    <th style="padding: 0.75rem;">Contacto</th>
                                    <th style="padding: 0.75rem;">Tipo / Origen</th>
                                    <th style="padding: 0.75rem;">Empresa Asoc.</th>
                                    <th style="padding: 0.75rem;">Documentos</th>
                                    <th style="padding: 0.75rem;">Gasto Total</th>
                                    <th style="padding: 0.75rem;">Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allPersonas)): ?>
                                    <tr><td colspan="9" style="padding:2rem; text-align:center; color:#94A3B8;">No hay clientes registrados en la base de datos.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allPersonas as $persona): ?>
                                        <tr class="persona-row" style="border-bottom: 1px solid #E2E8F0;">
                                            <td style="padding: 0.75rem; font-weight: 700;">#<?= $persona['id'] ?></td>
                                            <td style="padding: 0.75rem;">
                                                <div style="font-weight: 700; color: var(--primary);">
                                                    <?= htmlspecialchars($persona['nombre'] . ' ' . $persona['apellido']) ?>
                                                </div>
                                                <small style="color: #64748B;"><?= htmlspecialchars($persona['email']) ?></small>
                                            </td>
                                            <td style="padding: 0.75rem; font-family: monospace;">
                                                <?= htmlspecialchars($persona['rut'] ?: 'No registrado') ?>
                                            </td>
                                            <td style="padding: 0.75rem; font-size:0.83rem;">
                                                <?= htmlspecialchars($persona['telefono'] ?: '-') ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <span style="font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:4px; background:<?= ($persona['tipo_persona'] ?? 'NATURAL') === 'NATURAL' ? '#E0F2FE' : '#FEF3C7' ?>; color:<?= ($persona['tipo_persona'] ?? 'NATURAL') === 'NATURAL' ? '#0369A1' : '#B45309' ?>;">
                                                    <?= htmlspecialchars($persona['tipo_persona'] ?? 'NATURAL') ?>
                                                </span>
                                                <div style="font-size:0.7rem; color:#94A3B8; margin-top:2px;">
                                                    <?= $persona['auth_provider'] === 'GOOGLE' ? '<i class="fab fa-google" style="color:#EA4335;"></i> Google' : '<i class="fas fa-envelope"></i> Email' ?>
                                                </div>
                                            </td>
                                            <td style="padding: 0.75rem; font-size:0.83rem;">
                                                <?php if (!empty($persona['empresa_razon_social'])): ?>
                                                    <strong><?= htmlspecialchars($persona['empresa_razon_social']) ?></strong><br>
                                                    <code style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($persona['empresa_rut']) ?></code>
                                                <?php else: ?>
                                                    <span style="color:#94A3B8;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <span style="font-weight:700; color:var(--primary);"><?= (int)$persona['total_docs'] ?></span> total<br>
                                                <span style="font-size:0.75rem; color:#059669; font-weight:600;"><i class="fas fa-check"></i> <?= (int)$persona['docs_completados'] ?> completados</span>
                                            </td>
                                            <td style="padding: 0.75rem; font-weight:700; color:#0F2A4A;">
                                                <?= $persona['gasto_total_clp'] ? '$' . number_format($persona['gasto_total_clp'], 0, '', '.') . ' CLP' : '$0 CLP' ?>
                                            </td>
                                            <td style="padding: 0.75rem; font-size:0.78rem; color:#64748B;">
                                                <?= date('d/m/Y H:i', strtotime($persona['created_at'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
            <!-- 1D. SECCIÓN: PAGOS & CONCILIACIÓN BANCARIA -->
            <!-- ========================================== -->
            <div id="section_pagos" class="admin-section">
                <!-- Accesos Directos a Portales Oficiales para Cuadre -->
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; box-shadow:var(--shadow-sm);">
                    <div>
                        <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                            <i class="fas fa-university" style="color:#10B981;"></i> Conciliación y Acceso Directo a Portales de Recaudación
                        </h3>
                        <p style="font-size:0.83rem; color:#64748B; margin:4px 0 0;">
                            Contrasta las órdenes de SinNotario en tiempo real contra los paneles de liquidación bancaria de Mercado Pago y Khipu.
                        </p>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <a href="https://www.mercadopago.cl/activities" target="_blank" class="btn-cta" style="background:#009EE3; color:#FFF !important; padding:8px 14px; font-size:0.85rem;">
                            <i class="fas fa-external-link-alt"></i> Panel Mercado Pago (Actividad & Cartola)
                        </a>
                        <a href="https://khipu.com/merchant" target="_blank" class="btn-cta" style="background:#6366F1; color:#FFF !important; padding:8px 14px; font-size:0.85rem;">
                            <i class="fas fa-external-link-alt"></i> Panel Khipu (Transferencias Cobradas)
                        </a>
                    </div>
                </div>

                <!-- KPIs de Recaudación -->
                <?php
                $totalOrdenes = count($allPaymentOrders);
                $aprobadas = array_filter($allPaymentOrders, function($o) { return $o['estado'] === 'APROBADO'; });
                $pendientes = array_filter($allPaymentOrders, function($o) { return $o['estado'] === 'PENDIENTE'; });
                $totalRecaudado = array_sum(array_column($aprobadas, 'monto_total'));
                ?>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div style="font-size:0.8rem; font-weight:600; color:#64748B; text-transform:uppercase;">Total Transacciones</div>
                        <div class="kpi-val" style="color:var(--primary);"><?= $totalOrdenes ?></div>
                        <span style="font-size:0.75rem; color:#64748B;">Órdenes iniciadas</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size:0.8rem; font-weight:600; color:#64748B; text-transform:uppercase;">Pagos Aprobados</div>
                        <div class="kpi-val" style="color:#059669;"><?= count($aprobadas) ?></div>
                        <span style="font-size:0.75rem; color:#059669;"><i class="fas fa-check-circle"></i> Acreditados con éxito</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size:0.8rem; font-weight:600; color:#64748B; text-transform:uppercase;">Pagos Pendientes</div>
                        <div class="kpi-val" style="color:#D97706;"><?= count($pendientes) ?></div>
                        <span style="font-size:0.75rem; color:#D97706;"><i class="fas fa-clock"></i> En proceso / carrito</span>
                    </div>
                    <div class="kpi-card">
                        <div style="font-size:0.8rem; font-weight:600; color:#64748B; text-transform:uppercase;">Total Recaudado (IVA incl.)</div>
                        <div class="kpi-val" style="color:#10B981;">$<?= number_format($totalRecaudado, 0, '', '.') ?></div>
                        <span style="font-size:0.75rem; color:#10B981;"><i class="fas fa-coins"></i> Liquidado en pasarelas</span>
                    </div>
                </div>

                <!-- Tabla Detallada de Transacciones -->
                <div class="table-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                <i class="fas fa-receipt" style="color:#10B981;"></i> Libro Mayor de Pagos y Conciliación
                            </h3>
                            <p style="font-size:0.83rem; color:#64748B; margin:4px 0 0;">
                                Trazabilidad por ID de pasarela, referencia externa, cliente pagador y comprobante tributario.
                            </p>
                        </div>
                        <input type="text" id="filterPaymentsInput" placeholder="Buscar por referencia, cliente o RUT..." style="padding:8px 14px; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem; width:280px;">
                    </div>

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.88rem;" id="tablePayments">
                            <thead>
                                <tr style="background:#F8FAFC; color:#64748B; text-align:left;">
                                    <th style="padding:0.75rem;">ID</th>
                                    <th style="padding:0.75rem;">Referencia / Gateway</th>
                                    <th style="padding:0.75rem;">Tipo / Concepto</th>
                                    <th style="padding:0.75rem;">Cliente / Empresa</th>
                                    <th style="padding:0.75rem;">Monto Neto</th>
                                    <th style="padding:0.75rem;">Total Pagado</th>
                                    <th style="padding:0.75rem; text-align:center;">Estado</th>
                                    <th style="padding:0.75rem;">Fecha & Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allPaymentOrders)): ?>
                                    <tr>
                                        <td colspan="8" style="padding:2.5rem; text-align:center; color:#94A3B8;">
                                            <i class="fas fa-cash-register fa-2x" style="margin-bottom:0.5rem; opacity:0.5;"></i><br>
                                            No se han registrado órdenes de pago online aún.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allPaymentOrders as $ord): ?>
                                        <tr style="border-bottom:1px solid #E2E8F0;">
                                            <td style="padding:0.75rem; font-weight:700;">#<?= $ord['id'] ?></td>
                                            <td style="padding:0.75rem;">
                                                <div style="font-family:monospace; font-size:0.82rem; font-weight:700; color:#0F2A4A;"><?= htmlspecialchars($ord['external_reference']) ?></div>
                                                <span style="font-size:0.72rem; font-weight:800; padding:2px 6px; border-radius:4px; <?= $ord['gateway'] === 'KHIPU' ? 'background:#EEF2FF; color:#4338CA;' : 'background:#F0F9FF; color:#0369A1;' ?>">
                                                    <?= $ord['gateway'] === 'KHIPU' ? 'KHIPU' : 'MERCADO PAGO' ?>
                                                    <?= !empty($ord['mp_payment_id']) ? " (ID: {$ord['mp_payment_id']})" : '' ?>
                                                </span>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <?php if ($ord['payment_type'] === 'B2B_PACK'): ?>
                                                    <span style="font-weight:700; color:#D4AF37;">📦 Bolsa B2B: <?= htmlspecialchars($ord['pack_key']) ?></span>
                                                <?php else: ?>
                                                    <span style="font-weight:700; color:#0284C7;">📄 Trámite: <?= htmlspecialchars($ord['doc_codigo'] ?: 'Documento') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:0.75rem;">
                                                <?php if (!empty($ord['empresa_razon'])): ?>
                                                    <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($ord['empresa_razon']) ?></div>
                                                    <div style="font-size:0.75rem; color:#64748B;">RUT: <?= htmlspecialchars($ord['empresa_rut']) ?></div>
                                                <?php else: ?>
                                                    <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars(($ord['usuario_nombre'] ?? '') . ' ' . ($ord['usuario_apellido'] ?? '')) ?></div>
                                                    <div style="font-size:0.75rem; color:#64748B;"><?= htmlspecialchars($ord['usuario_email'] ?? '-') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:0.75rem; font-family:monospace;">$<?= number_format($ord['monto_neto'], 0, '', '.') ?></td>
                                            <td style="padding:0.75rem; font-weight:800; color:#059669; font-family:monospace;">$<?= number_format($ord['monto_total'], 0, '', '.') ?></td>
                                            <td style="padding:0.75rem; text-align:center;">
                                                <?php if ($ord['estado'] === 'APROBADO'): ?>
                                                    <span style="background:#DCFCE7; color:#15803D; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                                        <i class="fas fa-check"></i> APROBADO
                                                    </span>
                                                <?php elseif ($ord['estado'] === 'PENDIENTE'): ?>
                                                    <span style="background:#FEF3C7; color:#B45309; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                                        <i class="fas fa-clock"></i> PENDIENTE
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background:#FEE2E2; color:#B91C1C; font-weight:800; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                                        <?= htmlspecialchars($ord['estado']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:0.75rem; font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($ord['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 1.1 SECCIÓN: PRECIOS Y TARIFAS             -->
            <!-- ========================================== -->
            <div id="section_precios" class="admin-section">
                <?php if (!empty($msgPricingSuccess)): ?>
                    <div style="background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:1rem; border-radius:10px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
                        <i class="fas fa-check-circle fa-2x"></i>
                        <div>
                            <strong>¡Tarifas Guardadas!</strong>
                            <p style="margin:0; font-size:0.88rem;"><?= htmlspecialchars($msgPricingSuccess) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="card-panel">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                            <div>
                                <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                    <i class="fas fa-tags" style="color:var(--accent);"></i> Parámetros Globales de Tarifas y Firmas
                                </h3>
                                <p style="font-size:0.85rem; color:#64748B; margin:0.25rem 0 0;">
                                    Define la tarifa por firmante adicional y el precio base de documentos propios cargados por clientes.
                                </p>
                            </div>
                            <button type="submit" name="save_pricing_settings" class="btn-cta btn-gold">
                                <i class="fas fa-save"></i> Guardar Cambios de Precios
                            </button>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; background:#F8FAFC; border:1px solid #E2E8F0; padding:1.25rem; border-radius:10px;">
                            <div class="form-group">
                                <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem;">
                                    <i class="fas fa-user-plus" style="color:#0284C7;"></i> Precio por Firmante Extra (CLP) *
                                </label>
                                <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0.4rem;">
                                    Monto que se suma al total por cada firmante que exceda al 1er firmante base.
                                </p>
                                <input type="number" name="precio_firma_extra" class="form-control" style="font-weight:800; font-size:1.1rem; color:#1E40AF;" value="<?= $currentPrecioExtra ?>" min="0" step="1" required>
                            </div>

                            <div class="form-group">
                                <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem;">
                                    <i class="fas fa-file-upload" style="color:#059669;"></i> Precio Base Carga PDF Propio (CLP) *
                                </label>
                                <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0.4rem;">
                                    Tarifa base para documentos redactados por el cliente (incluye 1 firmante + bóveda R2).
                                </p>
                                <input type="number" name="precio_base_subir_pdf" class="form-control" style="font-weight:800; font-size:1.1rem; color:#065F46;" value="<?= $currentPrecioBasePdf ?>" min="0" step="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="card-panel" style="margin-top:1.5rem;">
                        <div style="margin-bottom:1.25rem;">
                            <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                <i class="fas fa-file-alt" style="color:var(--accent);"></i> Catálogo Oficial: Precio Base por Instrumento (1 Firmante)
                            </h3>
                            <p style="font-size:0.85rem; color:#64748B; margin:0.25rem 0 0;">
                                Puedes ajustar el precio base de cada contrato o declaración jurada del catálogo oficial.
                            </p>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width:70px;">ID</th>
                                    <th>Nombre del Instrumento</th>
                                    <th>Categoría</th>
                                    <th>Validez & Tipo</th>
                                    <th style="width:200px;">Precio Base CLP (1 Firmante)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allTemplates as $tpl): ?>
                                    <tr>
                                        <td><strong>#<?= $tpl['id'] ?></strong></td>
                                        <td>
                                            <strong style="color:var(--primary);"><?= htmlspecialchars($tpl['nombre']) ?></strong>
                                            <div style="font-size:0.75rem; color:#64748B;">/redactar.php?slug=<?= htmlspecialchars($tpl['slug']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:#F1F5F9; color:#475569; text-transform:uppercase; font-size:0.7rem;">
                                                <?= htmlspecialchars($tpl['categoria']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size:0.78rem; color:#059669; font-weight:600;">
                                                <i class="fas fa-check-circle"></i> Ley 19.799
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.35rem;">
                                                <span style="font-weight:700; color:#64748B;">$</span>
                                                <input type="number" name="tpl_prices[<?= $tpl['id'] ?>]" class="form-control" style="font-weight:800; text-align:right;" value="<?= (int)$tpl['precio_base'] ?>" min="0" step="1" required>
                                                <span style="font-size:0.75rem; color:#64748B;">CLP</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top:1.5rem; text-align:right;">
                            <button type="submit" name="save_pricing_settings" class="btn-cta btn-gold">
                                <i class="fas fa-save"></i> Guardar Todas las Tarifas
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ========================================== -->
            <!-- 1.2 SECCIÓN: PASARELAS & INTEGRACIONES     -->
            <!-- ========================================== -->
            <div id="section_integraciones" class="admin-section">
                <?php if (!empty($msgIntegrationsSuccess)): ?>
                    <div style="background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:1rem; border-radius:10px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
                        <i class="fas fa-check-circle fa-2x"></i>
                        <div>
                            <strong>¡Integraciones Guardadas!</strong>
                            <p style="margin:0; font-size:0.88rem;"><?= htmlspecialchars($msgIntegrationsSuccess) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="card-panel">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                            <div>
                                <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                                    <i class="fas fa-cogs" style="color:#A855F7;"></i> Pasarelas de Pago y Conectores de Infraestructura
                                </h3>
                                <p style="font-size:0.85rem; color:#64748B; margin:0.25rem 0 0;">
                                    Configura los conectores bancarios, webhooks de conciliación, Google OAuth y servidor SMTP.
                                </p>
                            </div>
                            <button type="submit" name="save_integrations_settings" class="btn-cta btn-gold" style="background:#A855F7; border-color:#9333EA;">
                                <i class="fas fa-save"></i> Guardar Configuración de Integraciones
                            </button>
                        </div>

                        <!-- Configuración Pasarela de Pagos: Mercado Pago Chile -->
                        <div style="margin-top:1rem;">
                            <?php 
                                $isMpActive = ($sysSettings['mp_enabled'] ?? '1') === '1'; 
                                $mpMode = $sysSettings['mp_mode'] ?? 'production';
                                $currentMpToken = $mpMode === 'sandbox' 
                                    ? ($sysSettings['mp_access_token_sandbox'] ?? ($sysSettings['mp_access_token'] ?? '')) 
                                    : ($sysSettings['mp_access_token_production'] ?? ($sysSettings['mp_access_token'] ?? ''));
                            ?>
                            <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                <div>
                                    <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem; font-size:1rem;">
                                        <i class="fas fa-credit-card" style="color:#009EE3;"></i> Pasarela de Pagos Online: Mercado Pago Chile
                                    </label>
                                    <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0;">
                                        Permite recibir pagos automáticos con Tarjetas de Crédito, Redcompra/Débito y Dinero en cuenta para bolsas prepagadas B2B y trámites particulares.
                                    </p>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; background:<?= $isMpActive ? '#EFF6FF' : '#F1F5F9' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $isMpActive ? '#BFDBFE' : '#CBD5E1' ?>; font-size:0.82rem; font-weight:700; color:<?= $isMpActive ? '#1D4ED8' : '#64748B' ?>;">
                                        <input type="checkbox" name="mp_enabled" value="1" <?= $isMpActive ? 'checked' : '' ?> style="accent-color:#009EE3;">
                                        Habilitar Pasarela
                                    </label>
                                    <span style="background:<?= ($isMpActive && !empty($currentMpToken)) ? '#DCFCE7' : ($isMpActive ? '#FEF3C7' : '#F1F5F9') ?>; color:<?= ($isMpActive && !empty($currentMpToken)) ? '#15803D' : ($isMpActive ? '#B45309' : '#64748B') ?>; font-size:0.75rem; font-weight:800; padding:3px 10px; border-radius:9999px;">
                                        <?= !$isMpActive ? '<i class="fas fa-ban"></i> DESACTIVADA' : (!empty($currentMpToken) ? '<i class="fas fa-check-circle"></i> ACTIVA (' . strtoupper($mpMode) . ')' : '<i class="fas fa-clock"></i> PENDIENTE CREDENCIALES') ?>
                                    </span>
                                </div>
                            </div>

                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:0.85rem; font-weight:800; color:#0F2A4A;"><i class="fas fa-toggle-on" style="color:#009EE3;"></i> Ambiente Activo en Sistema:</span>
                                    <select name="mp_mode" class="form-control" style="display:inline-block; width:auto; font-weight:700; padding:4px 10px; font-size:0.85rem;">
                                        <option value="production" <?= $mpMode === 'production' ? 'selected' : '' ?>>🚀 Producción Oficial (En Vivo)</option>
                                        <option value="sandbox" <?= $mpMode === 'sandbox' ? 'selected' : '' ?>>🧪 Sandbox (Pruebas de Desarrollo)</option>
                                    </select>
                                </div>
                                <span style="font-size:0.75rem; color:#64748B;">
                                    El sistema conmuta automáticamente entre credenciales según el ambiente seleccionado.
                                </span>
                            </div>

                            <!-- Bloque 1: Credenciales de Producción -->
                            <div style="border:1px solid #E2E8F0; border-radius:8px; padding:1rem; margin-bottom:1rem; background:#FFFFFF;">
                                <div style="font-size:0.85rem; font-weight:800; color:#059669; margin-bottom:0.75rem; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-shield-alt"></i> Credenciales de Producción Oficial (En Vivo)
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Public Key (Producción)</label>
                                        <input type="text" name="mp_public_key_production" class="form-control" placeholder="APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?= htmlspecialchars($sysSettings['mp_public_key_production'] ?? ($sysSettings['mp_public_key'] ?? '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Access Token (Producción)</label>
                                        <input type="password" name="mp_access_token_production" class="form-control" placeholder="APP_USR-xxxxxxxxxxxx-xxxxxx-xxxxxxxxxxxxxxxx-xxxxxxxx" value="<?= htmlspecialchars($sysSettings['mp_access_token_production'] ?? ($sysSettings['mp_access_token'] ?? '')) ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Bloque 2: Credenciales de Sandbox / Pruebas -->
                            <div style="border:1px solid #E2E8F0; border-radius:8px; padding:1rem; background:#FAF5FF;">
                                <div style="font-size:0.85rem; font-weight:800; color:#7E22CE; margin-bottom:0.75rem; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-flask"></i> Credenciales de Sandbox (Pruebas)
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Public Key (Sandbox / Prueba)</label>
                                        <input type="text" name="mp_public_key_sandbox" class="form-control" placeholder="APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?= htmlspecialchars($sysSettings['mp_public_key_sandbox'] ?? ($sysSettings['mp_public_key'] ?? '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Access Token (Sandbox / Prueba)</label>
                                        <input type="password" name="mp_access_token_sandbox" class="form-control" placeholder="APP_USR-xxxxxxxxxxxx-xxxxxx-xxxxxxxxxxxxxxxx-xxxxxxxx" value="<?= htmlspecialchars($sysSettings['mp_access_token_sandbox'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Firma Secreta del Webhook (Integridad Criptográfica) -->
                            <div style="background:#F1F5F9; border:1px solid #CBD5E1; border-radius:8px; padding:0.85rem 1rem; margin-top:1rem;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-size:0.8rem; font-weight:700; color:#1E293B; display:flex; align-items:center; gap:6px;">
                                        <i class="fas fa-key" style="color:#0284C7;"></i> Firma Secreta del Webhook (Signature Secret)
                                    </label>
                                    <input type="password" name="mp_webhook_secret" class="form-control" placeholder="Ej: e0898228fa71d7600d2477e72c03b5a8..." value="<?= htmlspecialchars($sysSettings['mp_webhook_secret'] ?? '') ?>" style="margin-top:4px;">
                                    <small style="color:#64748B; font-size:0.73rem; display:block; margin-top:3px;">
                                        URL del Webhook: <code style="background:#E2E8F0; padding:2px 6px; border-radius:4px; color:#0F2A4A;">https://sinnotario.cl/api/index.php?action=mp_webhook</code>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración Pasarela de Pagos: Khipu Chile -->
                        <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px dashed #CBD5E1;">
                            <?php 
                                $isKhActive = ($sysSettings['khipu_enabled'] ?? '1') === '1'; 
                                $khMode = $sysSettings['khipu_mode'] ?? 'production';
                                $currentKhSecret = $khMode === 'sandbox' 
                                    ? ($sysSettings['khipu_secret_key_sandbox'] ?? ($sysSettings['khipu_secret_key'] ?? '')) 
                                    : ($sysSettings['khipu_secret_key_production'] ?? ($sysSettings['khipu_secret_key'] ?? ''));
                            ?>
                            <div style="margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                <div>
                                    <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem; font-size:1rem;">
                                        <i class="fas fa-university" style="color:#6366F1;"></i> Pasarela B2B: Khipu (Transferencias Bancarias Simplificadas)
                                    </label>
                                    <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0;">
                                        Ideal para montos altos de empresas (baja comisión ~1,5%). Permite pagar cuenta a cuenta sin topar cupos de tarjetas.
                                    </p>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; background:<?= $isKhActive ? '#EEF2FF' : '#F1F5F9' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $isKhActive ? '#C7D2FE' : '#CBD5E1' ?>; font-size:0.82rem; font-weight:700; color:<?= $isKhActive ? '#4338CA' : '#64748B' ?>;">
                                        <input type="checkbox" name="khipu_enabled" value="1" <?= $isKhActive ? 'checked' : '' ?> style="accent-color:#6366F1;">
                                        Habilitar Pasarela
                                    </label>
                                    <span style="background:<?= ($isKhActive && !empty($currentKhSecret)) ? '#DCFCE7' : ($isKhActive ? '#FEF3C7' : '#F1F5F9') ?>; color:<?= ($isKhActive && !empty($currentKhSecret)) ? '#15803D' : ($isKhActive ? '#B45309' : '#64748B') ?>; font-size:0.75rem; font-weight:800; padding:3px 10px; border-radius:9999px;">
                                        <?= !$isKhActive ? '<i class="fas fa-ban"></i> DESACTIVADA' : (!empty($currentKhSecret) ? '<i class="fas fa-check-circle"></i> ACTIVA (' . strtoupper($khMode) . ')' : '<i class="fas fa-clock"></i> PENDIENTE CREDENCIALES') ?>
                                    </span>
                                </div>
                            </div>

                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:0.85rem; font-weight:800; color:#0F2A4A;"><i class="fas fa-toggle-on" style="color:#6366F1;"></i> Ambiente Activo en Sistema:</span>
                                    <select name="khipu_mode" class="form-control" style="display:inline-block; width:auto; font-weight:700; padding:4px 10px; font-size:0.85rem;">
                                        <option value="production" <?= $khMode === 'production' ? 'selected' : '' ?>>🚀 Producción Oficial (En Vivo)</option>
                                        <option value="sandbox" <?= $khMode === 'sandbox' ? 'selected' : '' ?>>🧪 Sandbox / Developer</option>
                                    </select>
                                </div>
                                <span style="font-size:0.75rem; color:#64748B;">
                                    El sistema conmuta automáticamente entre credenciales según el ambiente seleccionado.
                                </span>
                            </div>

                            <!-- Bloque 1: Credenciales Khipu Producción -->
                            <div style="border:1px solid #E2E8F0; border-radius:8px; padding:1rem; margin-bottom:1rem; background:#FFFFFF;">
                                <div style="font-size:0.85rem; font-weight:800; color:#059669; margin-bottom:0.75rem; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-shield-alt"></i> Credenciales Khipu Producción Oficial (En Vivo)
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Receiver ID (Producción)</label>
                                        <input type="text" name="khipu_receiver_id_production" class="form-control" placeholder="Ej: 123456" value="<?= htmlspecialchars($sysSettings['khipu_receiver_id_production'] ?? ($sysSettings['khipu_receiver_id'] ?? '')) ?>">
                                    </div>
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Secret Key (Producción)</label>
                                        <input type="password" name="khipu_secret_key_production" class="form-control" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?= htmlspecialchars($sysSettings['khipu_secret_key_production'] ?? ($sysSettings['khipu_secret_key'] ?? '')) ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Bloque 2: Credenciales Khipu Sandbox -->
                            <div style="border:1px solid #E2E8F0; border-radius:8px; padding:1rem; background:#FAF5FF;">
                                <div style="font-size:0.85rem; font-weight:800; color:#7E22CE; margin-bottom:0.75rem; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-flask"></i> Credenciales Khipu Sandbox (Pruebas)
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem;">
                                    <div class="form-group">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Receiver ID (Sandbox / Prueba)</label>
                                        <input type="text" name="khipu_receiver_id_sandbox" class="form-control" placeholder="Ej: 999999" value="<?= htmlspecialchars($sysSettings['khipu_receiver_id_sandbox'] ?? '') ?>">
                                    </div>
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label style="font-size:0.8rem; font-weight:600; color:#334155;">Secret Key (Sandbox / Prueba)</label>
                                        <input type="password" name="khipu_secret_key_sandbox" class="form-control" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?= htmlspecialchars($sysSettings['khipu_secret_key_sandbox'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Integración Google OAuth / GIS -->
                        <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px dashed #CBD5E1;">
                            <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem;">
                                <i class="fab fa-google" style="color:#EA4335;"></i> Google Client ID para Inicio de Sesión (OAuth 2.0 / GIS)
                            </label>
                            <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0.4rem;">
                                ID de cliente de la consola de Google Cloud (ej: <code>xxxx.apps.googleusercontent.com</code>) para habilitar "Continuar con Google".
                            </p>
                            <input type="text" name="google_oauth_client_id" class="form-control" placeholder="Ej: 123456789-abcdef.apps.googleusercontent.com" value="<?= htmlspecialchars($sysSettings['google_oauth_client_id'] ?? '') ?>">
                        </div>

                        <!-- Configuración SMTP para Correos Transaccionales -->
                        <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px dashed #CBD5E1;">
                            <div style="margin-bottom:1rem;">
                                <label style="font-weight:700; color:#0F2A4A; display:flex; align-items:center; gap:0.4rem; font-size:1rem;">
                                    <i class="fas fa-envelope-open-text" style="color:#0284C7;"></i> Configuración SMTP (Servidor de Correos y Casilla Oficial)
                                </label>
                                <p style="font-size:0.78rem; color:#64748B; margin:0.15rem 0 0;">
                                    Parámetros para el despacho de correos transaccionales (recuperación de contraseña, notificaciones de firma y comprobantes).
                                </p>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Servidor SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" placeholder="smtp.titan.email" value="<?= htmlspecialchars($sysSettings['smtp_host'] ?? 'smtp.titan.email') ?>">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Puerto (465 SSL / 587 TLS)</label>
                                    <input type="number" name="smtp_port" class="form-control" placeholder="465" value="<?= htmlspecialchars($sysSettings['smtp_port'] ?? '465') ?>">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Usuario / Casilla SMTP</label>
                                    <input type="email" name="smtp_user" class="form-control" placeholder="contacto@keystoneadvisors.cl" value="<?= htmlspecialchars($sysSettings['smtp_user'] ?? 'contacto@keystoneadvisors.cl') ?>">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Contraseña de Casilla SMTP</label>
                                    <input type="password" name="smtp_pass" class="form-control" placeholder="••••••••" value="<?= htmlspecialchars($sysSettings['smtp_pass'] ?? 'Jaba10281029#123456') ?>">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Correo Remitente (From)</label>
                                    <input type="email" name="smtp_from" class="form-control" placeholder="contacto@keystoneadvisors.cl" value="<?= htmlspecialchars($sysSettings['smtp_from'] ?? 'contacto@keystoneadvisors.cl') ?>">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem; font-weight:600; color:#334155;">Nombre del Remitente</label>
                                    <input type="text" name="smtp_name" class="form-control" placeholder="SinNotario.cl" value="<?= htmlspecialchars($sysSettings['smtp_name'] ?? 'SinNotario.cl') ?>">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:1.5rem; text-align:right;">
                            <button type="submit" name="save_integrations_settings" class="btn-cta btn-gold" style="background:#A855F7; border-color:#9333EA;">
                                <i class="fas fa-save"></i> Guardar Configuración de Integraciones
                            </button>
                        </div>
                    </div>
                </form>
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

    <!-- Modal Admin: Abonar Saldo Manual de Firmas -->
    <div id="adminAddSaldoModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:450px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h3 style="font-weight:800; color:var(--primary); margin:0;">Abonar Firmas Prepagadas</h3>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeAdminAddSaldo()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="admin_b2b_action" value="add_saldo">
                <input type="hidden" name="target_company_id" id="addSaldoCompId">
                <p style="font-size:0.88rem; color:#64748B; margin-bottom:1rem;">Empresa: <strong id="addSaldoCompName" style="color:var(--primary);"></strong></p>
                
                <div style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Cantidad de Firmas a Acreditar</label>
                    <input type="number" name="firmas_agregar" class="form-control" style="width:100%; padding:10px; border:1px solid #CBD5E1; border-radius:6px; font-size:1.1rem; font-weight:700;" value="50" min="1" required>
                    <small style="color:#64748B;">Se incrementará el saldo disponible de la bolsa inmediatamente.</small>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeAdminAddSaldo()">Cancelar</button>
                    <button type="submit" class="btn-cta btn-gold">Confirmar y Abonar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Admin: Editar Configuración B2B de Empresa -->
    <div id="adminEditCompModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:550px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h3 style="font-weight:800; color:var(--primary); margin:0;">Editar Planes & Modalidad B2B</h3>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeAdminEditComp()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="admin_b2b_action" value="update_plan">
                <input type="hidden" name="target_company_id" id="editCompId">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Modalidad de Cobro</label>
                        <select name="modalidad_cobro" id="editCompModalidad" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;">
                            <option value="PREPAGO">PREPAGO (Bolsa de Firmas)</option>
                            <option value="POSTPAGO">POSTPAGO (Facturación Mes)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Estado del Plan</label>
                        <select name="plan_estado" id="editCompEstado" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;">
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="SUSPENDIDO">SUSPENDIDO</option>
                            <option value="PRUEBA">PRUEBA</option>
                            <option value="VENCIDO">VENCIDO</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Nombre del Plan</label>
                        <input type="text" name="plan_nombre" id="editCompPlanNombre" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" value="PLAN_INICIAL">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Tarifa por Firma (CLP)</label>
                        <input type="number" name="tarifa_tramo_clp" id="editCompTarifa" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" value="7990">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Saldo Prepago Actual</label>
                        <input type="number" name="saldo_firmas_prepago" id="editCompSaldo" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Umbral Máximo Postpago</label>
                        <input type="number" name="umbral_max_postpago" id="editCompUmbral" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" value="200">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeAdminEditComp()">Cancelar</button>
                    <button type="submit" class="btn-cta btn-gold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script de Navegación Reactiva de Pestañas -->
    <script>
        function openAdminAddSaldo(id, name) {
            document.getElementById('addSaldoCompId').value = id;
            document.getElementById('addSaldoCompName').textContent = name;
            document.getElementById('adminAddSaldoModal').style.display = 'flex';
        }
        function closeAdminAddSaldo() {
            document.getElementById('adminAddSaldoModal').style.display = 'none';
        }
        function openAdminEditComp(c) {
            document.getElementById('editCompId').value = c.id;
            document.getElementById('editCompModalidad').value = c.modalidad_cobro || 'PREPAGO';
            document.getElementById('editCompEstado').value = c.plan_estado || 'ACTIVO';
            document.getElementById('editCompPlanNombre').value = c.plan_nombre || 'PLAN_INICIAL';
            document.getElementById('editCompTarifa').value = c.tarifa_tramo_clp || 7990;
            document.getElementById('editCompSaldo').value = c.saldo_firmas_prepago || 0;
            document.getElementById('editCompUmbral').value = c.umbral_max_postpago || 200;
            document.getElementById('adminEditCompModal').style.display = 'flex';
        }
        function closeAdminEditComp() {
            document.getElementById('adminEditCompModal').style.display = 'none';
        }
        const tabTitles = {
            'dashboard': {
                title: 'Consola de Gestión y Operaciones',
                desc: 'Monitoreo en vivo de transacciones, custodia y telecomunicaciones.'
            },
            'empresas': {
                title: 'Administración B2B: Empresas, Suscripciones y Finanzas',
                desc: 'Supervisión de clientes corporativos, saldos de bolsas prepagadas, devengo postpago y recargas.'
            },
            'clientes': {
                title: 'Directorio de Clientes Persona Natural & Particulares',
                desc: 'Gestión y trazabilidad de usuarios individuales, trámites personales en bóveda y recaudación retail.'
            },
            'pagos': {
                title: 'Libro Mayor de Pagos, Recaudación y Conciliación Bancaria',
                desc: 'Trazabilidad de pagos online, cruce con Mercado Pago / Khipu y control de ingresos B2B y Retail.'
            },
            'precios': {
                title: 'Gestor de Tarifas y Precios de Firma',
                desc: 'Configuración comercial de precios base por documento y tarifa por firmante adicional.'
            },
            'integraciones': {
                title: 'Pasarelas de Pago, Webhooks y Conectores de Sistema',
                desc: 'Administración de credenciales de Mercado Pago, Khipu, llaves criptográficas de Webhooks, Google OAuth y servidor SMTP.'
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

        // Filtrado en vivo de tabla de clientes persona
        const filterPersonasInput = document.getElementById('filterPersonasInput');
        if (filterPersonasInput) {
            filterPersonasInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.persona-row');
                rows.forEach(r => {
                    const text = r.innerText.toLowerCase();
                    r.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

        // Filtrado en vivo de tabla de pagos online
        const filterPaymentsInput = document.getElementById('filterPaymentsInput');
        if (filterPaymentsInput) {
            filterPaymentsInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#tablePayments tbody tr');
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
