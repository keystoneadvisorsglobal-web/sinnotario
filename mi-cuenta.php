<?php
/**
 * SinNotario.cl - Portal Privado de Clientes (/mi-cuenta.php)
 * Bóveda Criptográfica Personal, Tracker de Firmas y Perfil
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Services/AuthService.php';

$currentUser = AuthService::getCurrentUser();
if (!$currentUser) {
    header("Location: login.php?redirect=mi-cuenta.php");
    exit;
}

$userDisplayName = htmlspecialchars($currentUser['nombre'] . ' ' . $currentUser['apellido']);
$userEmail = htmlspecialchars($currentUser['email']);
$userAvatar = !empty($currentUser['avatar_url']) ? htmlspecialchars($currentUser['avatar_url']) : null;
$userRut = htmlspecialchars($currentUser['rut'] ?? '');
$userTel = htmlspecialchars($currentUser['telefono'] ?? '');
$userEmpresaNombre = htmlspecialchars($currentUser['empresa_razon_social'] ?? '');
$userEmpresaRut = htmlspecialchars($currentUser['empresa_rut'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta | Bóveda de Contratos & Firmas - SinNotario.cl</title>
    <meta name="description" content="Gestiona tus contratos legales, revisa el estado de firmas en vivo y descarga copias oficiales selladas.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Principales -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_2">

    <style>
        :root {
            --portal-navy: #0F2A4A;
            --portal-blue: #0066CC;
            --portal-green: #059669;
            --portal-bg: #F8FAFC;
        }
        body {
            background-color: var(--portal-bg);
            color: #1E293B;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .portal-header {
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .portal-header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .portal-header-brand img {
            height: 42px;
        }
        .portal-user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .portal-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0066CC;
            color: #FFFFFF;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            font-size: 1rem;
            border: 2px solid #E2E8F0;
        }
        .portal-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .portal-user-info {
            text-align: right;
            line-height: 1.2;
        }
        .portal-user-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0F2A4A;
        }
        .portal-user-email {
            font-size: 0.8rem;
            color: #64748B;
        }
        .btn-logout {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FCA5A5;
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-logout:hover {
            background: #FEE2E2;
        }

        /* Hero / Welcome Banner */
        .portal-hero {
            background: linear-gradient(135deg, #0F2A4A 0%, #1E3A8A 100%);
            color: #FFFFFF;
            padding: 2.5rem 1.5rem;
        }
        .portal-hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .portal-hero-text h1 {
            font-size: 1.85rem;
            font-weight: 800;
            margin: 0 0 0.4rem 0;
            color: #FFFFFF;
        }
        .portal-hero-text p {
            color: #94A3B8;
            margin: 0;
            font-size: 0.95rem;
        }
        .portal-hero-actions {
            display: flex;
            gap: 10px;
        }
        .btn-new-doc {
            background: #22C55E;
            color: #FFFFFF;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
            transition: all 0.2s ease;
        }
        .btn-new-doc:hover {
            background: #16A34A;
            transform: translateY(-2px);
        }
        .btn-upload-doc {
            background: #FFFFFF;
            color: #0F2A4A;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-upload-doc:hover {
            background: #F1F5F9;
            transform: translateY(-2px);
        }

        /* Métricas Rápidas */
        .portal-metrics {
            max-width: 1200px;
            margin: -1.5rem auto 2rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }
        .metric-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .metric-icon.blue { background: #EFF6FF; color: #0066CC; }
        .metric-icon.green { background: #ECFDF5; color: #059669; }
        .metric-icon.amber { background: #FFFBEB; color: #D97706; }
        .metric-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0F2A4A;
            line-height: 1;
        }
        .metric-lbl {
            font-size: 0.8rem;
            color: #64748B;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Contenido Principal */
        .portal-main {
            max-width: 1200px;
            margin: 0 auto 4rem;
            padding: 0 1.5rem;
        }
        .portal-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            font-weight: 700;
            color: #64748B;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn.active {
            color: #0066CC;
            border-bottom-color: #0066CC;
        }

        /* Tabla de Documentos */
        .doc-card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            margin-bottom: 1.25rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .doc-card:hover {
            box-shadow: 0 8px 16px rgba(15, 42, 74, 0.08);
            border-color: #CBD5E1;
        }
        .doc-card-header {
            padding: 1rem 1.25rem;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .doc-folio {
            font-family: monospace;
            font-weight: 700;
            font-size: 0.9rem;
            color: #0066CC;
            background: #EFF6FF;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #BFDBFE;
        }
        .doc-card-body {
            padding: 1.25rem;
        }
        .doc-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0F2A4A;
            margin: 0 0 0.5rem 0;
        }
        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 0.85rem;
            color: #64748B;
            margin-bottom: 1rem;
        }

        /* Tracker de Firmantes */
        .signers-tracker {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            border: 1px solid #E2E8F0;
            margin-top: 0.75rem;
        }
        .signers-tracker-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 0.65rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .signer-pill {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #E2E8F0;
            font-size: 0.85rem;
        }
        .signer-pill:last-child {
            border-bottom: none;
        }
        .badge-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-firmado { background: #DCFCE7; color: #166534; }
        .badge-pendiente { background: #FEF3C7; color: #92400E; }
        .badge-completado { background: #DBEAFE; color: #1E40AF; }

        .doc-actions {
            display: flex;
            gap: 8px;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #F1F5F9;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 0.5rem 0.95rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .btn-action-primary {
            background: #0066CC;
            color: #FFFFFF;
        }
        .btn-action-primary:hover {
            background: #004C99;
        }
        .btn-action-secondary {
            background: #F1F5F9;
            color: #334155;
            border-color: #CBD5E1;
        }
        .btn-action-secondary:hover {
            background: #E2E8F0;
        }
        .empty-boveda {
            background: #FFFFFF;
            border-radius: 12px;
            border: 2px dashed #CBD5E1;
            padding: 4rem 2rem;
            text-align: center;
        }
        .empty-boveda i {
            font-size: 3.5rem;
            color: #94A3B8;
            margin-bottom: 1rem;
        }

        /* Formulario de Perfil */
        .profile-card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            padding: 2rem;
            max-width: 680px;
        }
    </style>
</head>
<body>

    <!-- Header Portal -->
    <header class="portal-header">
        <div class="portal-header-container">
            <a href="index.php" class="portal-header-brand">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>

            <!-- Navegación Rápida Portal Clientes & Empresas -->
            <nav style="display:flex; align-items:center; gap:1.25rem;">
                <a href="index.php" style="color:#64748B; text-decoration:none; font-size:0.9rem; font-weight:600; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <a href="mi-cuenta.php" style="color:var(--portal-blue); text-decoration:none; font-size:0.9rem; font-weight:700; border-bottom:2px solid var(--portal-blue); padding-bottom:4px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-archive"></i> Mi Bóveda
                </a>
                <a href="empresas.php" class="btn-cta btn-gold" style="font-size:0.85rem; padding:0.45rem 1rem; text-decoration:none; border-radius:6px; font-weight:700; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(0,0,0,0.08);">
                    <i class="fas fa-building"></i> Ir a Suite Empresas B2B
                </a>
            </nav>

            <div class="portal-user-badge">
                <div class="portal-avatar">
                    <?php if ($userAvatar): ?>
                        <img src="<?= $userAvatar ?>" alt="Avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($currentUser['nombre'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="portal-user-info">
                    <div class="portal-user-name"><?= $userDisplayName ?></div>
                    <div class="portal-user-email"><?= $userEmail ?></div>
                </div>
                <a href="javascript:void(0)" onclick="logoutPortal()" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Hero / Bienvenida -->
    <div class="portal-hero">
        <div class="portal-hero-container">
            <div class="portal-hero-text">
                <h1>Bóveda Privada de Contratos</h1>
                <p>Monitorea tus trámites, el estado de tus firmantes y descarga copias certificadas con validez plena (Ley 19.799).</p>
            </div>
            <div class="portal-hero-actions">
                <a href="empresas.php" class="btn-cta btn-gold" style="font-size:0.92rem; padding:0.65rem 1.25rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-building"></i> Portal Empresas B2B
                </a>
                <a href="index.php#tramites" class="btn-new-doc">
                    <i class="fas fa-pen-nib"></i> Redactar Trámite
                </a>
                <a href="subir-documento.php" class="btn-upload-doc">
                    <i class="fas fa-file-upload"></i> Subir PDF Propio
                </a>
            </div>
        </div>
    </div>

    <!-- Métricas -->
    <div class="portal-metrics">
        <div class="metric-card">
            <div class="metric-icon blue"><i class="fas fa-folder-open"></i></div>
            <div>
                <div class="metric-val" id="metricTotal">0</div>
                <div class="metric-lbl">Total Documentos</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon amber"><i class="fas fa-clock"></i></div>
            <div>
                <div class="metric-val" id="metricPendientes">0</div>
                <div class="metric-lbl">Pendientes de Firma</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon green"><i class="fas fa-check-double"></i></div>
            <div>
                <div class="metric-val" id="metricListos">0</div>
                <div class="metric-lbl">Firmados & Sellados</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="portal-main">
        <div class="portal-tabs">
            <button type="button" class="tab-btn active" id="tabBtnDocs" onclick="showPortalTab('docs')">
                <i class="fas fa-file-contract"></i> Mis Documentos (<span id="tabCountDocs">0</span>)
            </button>
            <button type="button" class="tab-btn" id="tabBtnProfile" onclick="showPortalTab('profile')">
                <i class="fas fa-user-cog"></i> Mis Datos & Empresa
            </button>
        </div>

        <!-- TAB 1: LISTA DE DOCUMENTOS -->
        <div id="portalSectionDocs">
            <div id="docsLoading" style="text-align:center; padding:3rem; color:#64748B;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top:1rem;">Cargando tu bóveda personal...</p>
            </div>

            <div id="docsContainer" style="display:none;"></div>

            <!-- Empty State -->
            <div id="docsEmpty" class="empty-boveda" style="display:none;">
                <i class="fas fa-folder-open"></i>
                <h3 style="color:#0F2A4A; margin-bottom:0.5rem;">Aún no tienes documentos en tu bóveda</h3>
                <p style="color:#64748B; max-width:480px; margin:0 auto 1.5rem;">
                    Redacta tu primer contrato inteligente o sube un documento PDF para enviarlo a firma digital con plena validez legal.
                </p>
                <div style="display:flex; justify-content:center; gap:10px;">
                    <a href="index.php#tramites" class="btn-new-doc"><i class="fas fa-plus"></i> Iniciar Trámite</a>
                    <a href="subir-documento.php" class="btn-upload-doc" style="border:1px solid #CBD5E1;"><i class="fas fa-file-upload"></i> Subir Documento</a>
                </div>
            </div>
        </div>

        <!-- TAB 2: PERFIL & DATOS PRE-LLENADO -->
        <div id="portalSectionProfile" style="display:none;">
            <div class="profile-card">
                <h2 style="font-size:1.35rem; color:#0F2A4A; margin-top:0; margin-bottom:0.4rem;">Datos Personales & Empresa</h2>
                <p style="color:#64748B; font-size:0.85rem; margin-bottom:1.5rem;">
                    Estos datos se usarán para pre-completar automáticamente tus datos en cualquier contrato que redactes o subas a firmar.
                </p>

                <div id="profileAlert" style="display:none; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:0.85rem;"></div>

                <form onsubmit="saveProfile(event)">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:1rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">Nombre *</label>
                            <input type="text" id="profNombre" class="form-control" value="<?= $currentUser['nombre'] ?>" required style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">Apellidos *</label>
                            <input type="text" id="profApellido" class="form-control" value="<?= $currentUser['apellido'] ?>" required style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:1rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">RUT Personal</label>
                            <input type="text" id="profRut" class="form-control" value="<?= $userRut ?>" placeholder="12.345.678-9" maxlength="12" style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">Teléfono WhatsApp</label>
                            <input type="text" id="profTel" class="form-control" value="<?= $userTel ?>" placeholder="+56 9 1234 5678" style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">Correo Electrónico (Identificador)</label>
                        <input type="email" class="form-control" value="<?= $userEmail ?>" disabled style="width:100%; padding:0.7rem; background:#F1F5F9; border:1.5px solid #E2E8F0; border-radius:6px; color:#64748B;">
                    </div>

                    <hr style="border:none; border-top:1px solid #E2E8F0; margin:1.5rem 0;">

                    <h3 style="font-size:1.1rem; color:#0F2A4A; margin-bottom:0.5rem;"><i class="fas fa-building"></i> Datos de Empresa Representada (Opcional)</h3>
                    <p style="color:#64748B; font-size:0.8rem; margin-bottom:1rem;">
                        Si habitualmente firmas a nombre de una persona jurídica, guárdala aquí para acelerar tus trámites.
                    </p>

                    <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:12px; margin-bottom:1.5rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">Razón Social</label>
                            <input type="text" id="profEmpresaNombre" class="form-control" value="<?= $userEmpresaNombre ?>" placeholder="Ej: Inversiones Globales SpA" style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:4px;">RUT Empresa</label>
                            <input type="text" id="profEmpresaRut" class="form-control" value="<?= $userEmpresaRut ?>" placeholder="76.123.456-7" maxlength="12" style="width:100%; padding:0.7rem; border:1.5px solid #CBD5E1; border-radius:6px;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <button type="submit" id="btnSaveProfile" class="btn-action-primary" style="padding:0.75rem 1.5rem; font-size:0.95rem; border:none; cursor:pointer;">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <a href="empresas.php" class="btn-cta btn-gold" style="font-size:0.9rem; padding:0.65rem 1.25rem; text-decoration:none; border-radius:6px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-building"></i> Abrir Suite Empresas B2B
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/app.js?v=20261107_3"></script>
    <script>
        function showPortalTab(tab) {
            const btnDocs = document.getElementById('tabBtnDocs');
            const btnProfile = document.getElementById('tabBtnProfile');
            const secDocs = document.getElementById('portalSectionDocs');
            const secProfile = document.getElementById('portalSectionProfile');

            if (tab === 'docs') {
                btnDocs.classList.add('active');
                btnProfile.classList.remove('active');
                secDocs.style.display = 'block';
                secProfile.style.display = 'none';
            } else {
                btnProfile.classList.add('active');
                btnDocs.classList.remove('active');
                secProfile.style.display = 'block';
                secDocs.style.display = 'none';
            }
        }

        async function loadDocuments() {
            const loading = document.getElementById('docsLoading');
            const container = document.getElementById('docsContainer');
            const empty = document.getElementById('docsEmpty');

            try {
                const res = await fetch('api/index.php?action=client_get_documents');
                const data = await res.json();

                loading.style.display = 'none';

                if (data.success && data.data && data.data.documents) {
                    const docs = data.data.documents;
                    document.getElementById('metricTotal').textContent = docs.length;
                    document.getElementById('tabCountDocs').textContent = docs.length;

                    let countPendientes = 0;
                    let countListos = 0;

                    if (docs.length === 0) {
                        empty.style.display = 'block';
                        container.style.display = 'none';
                        return;
                    }

                    empty.style.display = 'none';
                    container.style.display = 'block';
                    container.innerHTML = '';

                    docs.forEach(doc => {
                        const isCompletado = doc.estado === 'COMPLETADO';
                        if (isCompletado) countListos++;
                        else countPendientes++;

                        const card = document.createElement('div');
                        card.className = 'doc-card';

                        let statusBadge = '';
                        if (isCompletado) {
                            statusBadge = '<span class="badge-status badge-completado"><i class="fas fa-check-circle"></i> Completado</span>';
                        } else if (doc.estado === 'PENDIENTE_FIRMAS') {
                            statusBadge = '<span class="badge-status badge-pendiente"><i class="fas fa-clock"></i> Pendiente de Firmas</span>';
                        } else {
                            statusBadge = '<span class="badge-status badge-pendiente"><i class="fas fa-file-edit"></i> ' + (doc.estado || 'Borrador') + '</span>';
                        }

                        // Firmantes HTML
                        let signersHtml = '';
                        if (doc.signers && doc.signers.length > 0) {
                            doc.signers.forEach(sig => {
                                const sigFirmado = sig.estado_firma === 'FIRMADO';
                                const sigBadge = sigFirmado 
                                    ? '<span class="badge-status badge-firmado"><i class="fas fa-check"></i> Firmado</span>'
                                    : '<span class="badge-status badge-pendiente"><i class="fas fa-hourglass-half"></i> Pendiente</span>';
                                
                                const empresaText = sig.es_representante && sig.empresa_razon_social 
                                    ? `<span style="font-size:0.75rem; color:#64748B;"> - Rep. ${sig.empresa_razon_social}</span>` 
                                    : '';

                                signersHtml += `
                                    <div class="signer-pill">
                                        <div>
                                            <strong>${sig.nombre_completo}</strong> (${sig.rol_firmante}) ${empresaText}
                                            <div style="font-size:0.75rem; color:#94A3B8;">${sig.email} &bull; ${sig.rut}</div>
                                        </div>
                                        <div>${sigBadge}</div>
                                    </div>
                                `;
                            });
                        }

                        // Botones de acción
                        let actionButtons = `
                            <a href="verificar.php?code=${encodeURIComponent(doc.codigo_verificacion)}" target="_blank" class="btn-action btn-action-secondary">
                                <i class="fas fa-shield-alt"></i> Verificación Pública
                            </a>
                        `;

                        if (doc.pdf_r2_key || isCompletado) {
                            actionButtons += `
                                <a href="api/index.php?action=download_pdf&code=${encodeURIComponent(doc.codigo_verificacion)}" class="btn-action btn-action-primary" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Descargar Documento Oficial
                                </a>
                            `;
                        }

                        card.innerHTML = `
                            <div class="doc-card-header">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span class="doc-folio"><i class="fas fa-hashtag"></i> ${doc.codigo_verificacion}</span>
                                    <span style="font-size:0.8rem; color:#64748B;"><i class="far fa-calendar-alt"></i> ${doc.created_at || ''}</span>
                                </div>
                                <div>${statusBadge}</div>
                            </div>
                            <div class="doc-card-body">
                                <h3 class="doc-title">${doc.titulo}</h3>
                                <div class="doc-meta">
                                    ${doc.template_nombre ? `<span><i class="fas fa-layer-group"></i> Plantilla: ${doc.template_nombre}</span>` : '<span><i class="fas fa-file-upload"></i> Documento Externo Subido</span>'}
                                    ${doc.pdf_sha256 ? `<span style="font-family:monospace; font-size:0.75rem;"><i class="fas fa-fingerprint"></i> Hash: ${doc.pdf_sha256.substring(0, 16)}...</span>` : ''}
                                </div>

                                <div class="signers-tracker">
                                    <div class="signers-tracker-title">
                                        <span><i class="fas fa-users"></i> Cadena de Firmantes (${doc.signers ? doc.signers.length : 0})</span>
                                    </div>
                                    ${signersHtml || '<div style="font-size:0.8rem; color:#94A3B8;">Sin firmantes registrados</div>'}
                                </div>

                                <div class="doc-actions">
                                    ${actionButtons}
                                </div>
                            </div>
                        `;
                        container.appendChild(card);
                    });

                    document.getElementById('metricPendientes').textContent = countPendientes;
                    document.getElementById('metricListos').textContent = countListos;
                } else {
                    empty.style.display = 'block';
                }
            } catch (err) {
                loading.innerHTML = '<p style="color:#DC2626;">Error al cargar documentos: ' + err.message + '</p>';
            }
        }

        async function saveProfile(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProfile');
            const alertEl = document.getElementById('profileAlert');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            const payload = {
                nombre: document.getElementById('profNombre').value.trim(),
                apellido: document.getElementById('profApellido').value.trim(),
                rut: document.getElementById('profRut').value.trim(),
                telefono: document.getElementById('profTel').value.trim(),
                empresa_razon_social: document.getElementById('profEmpresaNombre').value.trim(),
                empresa_rut: document.getElementById('profEmpresaRut').value.trim(),
            };

            try {
                const res = await fetch('api/index.php?action=client_update_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    alertEl.style.cssText = 'display:block; background:#F0FDF4; color:#166534; border:1px solid #86EFAC;';
                    alertEl.innerHTML = '<i class="fas fa-check-circle"></i> Perfil y datos de empresa guardados correctamente.';
                } else {
                    alertEl.style.cssText = 'display:block; background:#FEF2F2; color:#991B1B; border:1px solid #F87171;';
                    alertEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'No fue posible guardar');
                }
            } catch (err) {
                alertEl.style.cssText = 'display:block; background:#FEF2F2; color:#991B1B; border:1px solid #F87171;';
                alertEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Fallo de conexión: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            }
        }

        async function logoutPortal() {
            try {
                await fetch('api/index.php?action=auth_logout');
            } catch (e) {}
            window.location.href = 'index.php';
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadDocuments();

            // Auto-formateo en inputs de RUT del perfil
            const profRut = document.getElementById('profRut');
            if (profRut && typeof RutHelper !== 'undefined') {
                RutHelper.attachToInput(profRut);
            }
            const empRut = document.getElementById('profEmpresaRut');
            if (empRut && typeof RutHelper !== 'undefined') {
                RutHelper.attachToInput(empRut);
            }
        });
    </script>
</body>
</html>
