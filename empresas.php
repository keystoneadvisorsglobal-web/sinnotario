<?php
/**
 * SinNotario.cl - Suite B2B Empresas & Carga Masiva Excel
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/B2BService.php';

$currentUser = AuthService::getCurrentUser();
if (!$currentUser) {
    // Si no ha iniciado sesión, redirigir al portal de acceso institucional
    header('Location: login.php?redirect=' . urlencode('empresas.php') . '&msg=b2b_auth_required');
    exit;
}

$pdo = Database::getInstance();
$stmtSets = $pdo->query("SELECT * FROM system_settings");
$settingsRows = $stmtSets ? $stmtSets->fetchAll(PDO::FETCH_ASSOC) : [];
$b2bSettings = [];
foreach ($settingsRows as $sr) {
    $b2bSettings[$sr['key']] = $sr['value'];
}
$mpB2bActive = ($b2bSettings['mp_enabled'] ?? '1') === '1';
$khipuB2bActive = ($b2bSettings['khipu_enabled'] ?? '1') === '1';
$defaultB2bGateway = $mpB2bActive ? 'MERCADO_PAGO' : ($khipuB2bActive ? 'KHIPU' : 'NONE');

// Sincronización y Backup de Notificaciones ante eventual retraso del Webhook
$externalRef = isset($_GET['external_ref']) ? trim($_GET['external_ref']) : '';
$paymentStatus = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$orderIdParam = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';

if ($externalRef || $orderIdParam) {
    try {
        require_once __DIR__ . '/src/Services/MercadoPagoService.php';
        $mpSync = new MercadoPagoService();
        if ($orderIdParam) {
            $orderData = $mpSync->getOrder($orderIdParam);
            if (!empty($orderData['success']) && ($orderData['data']['status'] ?? '') === 'processed') {
                $extRefOrder = $orderData['data']['external_reference'] ?? $externalRef;
                if ($extRefOrder) {
                    MercadoPagoService::processApprovedPayment($extRefOrder, $orderIdParam, $orderData['data']);
                    $paymentStatus = 'success';
                }
            }
        } elseif ($externalRef && $paymentStatus === 'success') {
            MercadoPagoService::processApprovedPayment($externalRef, $orderIdParam ?: null);
        }
    } catch (\Throwable $e) {
        error_log("Error en sincronización backup de empresas.php: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suite B2B Empresas & Emisión Masiva | SinNotario.cl</title>
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/sinnotario-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_3">
    <!-- PapaParse para procesamiento cliente de alta velocidad -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
    <style>
        .b2b-layout {
            max-width: 1200px;
            margin: 2rem auto 4rem;
            padding: 0 1.5rem;
        }
        .company-badge-bar {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        .upload-dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: #FFFFFF;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-dropzone:hover {
            border-color: var(--primary);
            background: #F8FAFC;
        }
        .parsed-table-wrap {
            display: none;
            margin-top: 2rem;
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            animation: fadeIn 0.3s ease;
        }
        .metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .metric-valid { background: #DCFCE7; color: #15803D; }
        .metric-invalid { background: #FEE2E2; color: #B91C1C; }
        .history-card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2.5rem;
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>

    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Portal Público</a></li>
                <li><a href="empresas.php" class="active">Suite Empresas B2B</a></li>
                <li><a href="mi-cuenta.php">Mi Bóveda</a></li>
            </ul>
            <div>
                <a href="docs/manual-operativo-empresas.php" target="_blank" class="btn-cta" style="background:#F1F5F9; color:#0A1C33 !important; border:1px solid #CBD5E1; margin-right:8px;">
                    <i class="fas fa-file-pdf"></i> Instructivo PDF
                </a>
                <a href="https://wa.me/56984180479?text=Hola,%20solicito%20soporte%20corporativo%20B2B%20en%20SinNotario.cl" target="_blank" class="btn-cta btn-gold">
                    <i class="fab fa-whatsapp"></i> Soporte Empresas
                </a>
            </div>
        </div>
    </header>

    <main class="b2b-layout">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <span class="card-category laboral">Recursos Humanos & Corredoras</span>
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem; letter-spacing: -0.02em;">
                Suite B2B: Emisión Masiva y Firma en 1 Clic
            </h1>
            <p style="color: #64748B; font-size: 1rem; max-width: 750px; margin: 0 auto;">
                Sube tu planilla Excel o CSV de trabajadores o arrendatarios. Nuestro motor valida los RUTs con Módulo 11, emite contratos individuales certificados bajo la Ley N° 19.799 y Ley N° 21.327, y convoca a firmar automáticamente.
            </p>
        </div>

        <!-- Barra de Empresa y Métricas de Plan / Suscripción -->
        <div class="company-badge-bar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 10px; background: #0A1C33; color: #D4AF37; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-weight: 800; font-size: 1.1rem; color: var(--primary);" id="dispRazonSocial">Keystone Advisors SpA</span>
                        <span style="background:#DCFCE7; color:#15803D; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:12px;" id="dispPlanEstado">PLAN ACTIVO</span>
                        <span style="background:#EFF6FF; color:#1D4ED8; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:12px;" id="dispModalidadCobro">PREPAGO</span>
                    </div>
                    <div style="font-size: 0.85rem; color: #64748B; display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:2px;">
                        <span>RUT: <strong id="dispRutEmpresa">78.089.686-8</strong> | Tarifa por Firma: <strong id="dispTarifaTramo">$7.990 CLP</strong></span>
                        <a href="https://www.keystoneadvisors.cl/trust/?domain=sinnotario.cl" target="_blank" rel="noopener" title="Ver Telemetría en Vivo de Ciberseguridad TrustZero" style="display:inline-flex; align-items:center;">
                            <img src="https://www.keystoneadvisors.cl/api/trust/badge.php?domain=sinnotario.cl" alt="TrustZero Verified" style="height:20px; border-radius:3px; vertical-align:middle;">
                        </a>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <!-- Widget Financiero: Bolsa Prepago o Facturación Postpago (Dual FES / FEA SOL-0116) -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.5rem 1rem; text-align: right;" id="widgetFinanciero">
                    <div style="font-size: 0.72rem; color: #64748B; font-weight: 700; text-transform: uppercase;" id="lblSaldoModalidad">BOLSAS DISPONIBLES</div>
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; margin-top:2px;" id="valSaldoModalidad">
                        <span style="font-size: 0.95rem; font-weight: 800; color: #16A34A; background:#DCFCE7; padding:2px 8px; border-radius:6px;" title="Firmas Electrónicas Simples Disponibles">
                            <i class="fas fa-file-signature"></i> FES: <strong id="dispSaldoPrepago">0</strong>
                        </span>
                        <span style="font-size: 0.95rem; font-weight: 800; color: #1D4ED8; background:#DBEAFE; padding:2px 8px; border-radius:6px;" title="Firmas Avanzadas ClaveÚnica Disponibles">
                            <i class="fas fa-shield-alt"></i> FEA: <strong id="dispSaldoFea">0</strong>
                        </span>
                    </div>
                    <div style="font-size: 0.72rem; color: #64748B; margin-top:2px;" id="subSaldoModalidad">Total histórico emitido: <strong id="dispHistoricoUsadas">0</strong></div>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:4px;">
                    <button type="button" class="btn-cta btn-gold" style="padding:6px 12px; font-size:0.82rem;" onclick="openRechargeModal()">
                        <i class="fas fa-shopping-cart"></i> Comprar Bolsa
                    </button>
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1; padding:4px 10px; font-size:0.8rem;" onclick="openCompanyModal()">
                        <i class="fas fa-cog"></i> Configurar
                    </button>
                </div>
            </div>
        </div>

        <!-- Banner de Selección de Bolsas con Descuento (Dual FES vs FEA SOL-0116) -->
        <?php 
        $activePacks = B2BService::getPrepaidPacks(); 
        $fes10 = $activePacks['PACK_FES_10'];
        $fes50 = $activePacks['PACK_FES_50'];
        $fes200 = $activePacks['PACK_FES_200'];
        $fes500 = $activePacks['PACK_FES_500'];

        $fea10 = $activePacks['PACK_FEA_10'];
        $fea50 = $activePacks['PACK_FEA_50'];
        $fea200 = $activePacks['PACK_FEA_200'];
        $fea500 = $activePacks['PACK_FEA_500'];
        ?>
        <div style="margin-bottom: 2rem;">
            <!-- Selector de Tipo de Firma para Packs -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem;">
                <div>
                    <h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin:0;">
                        <i class="fas fa-tags" style="color:var(--gold);"></i> Bolsas de Firmas Prepagadas con Descuento por Volumen
                    </h3>
                    <p style="font-size:0.83rem; color:#64748B; margin:2px 0 0;">
                        Escoge la línea que mejor se adapte a tus operaciones: Firma Simple (FES) o Firma Avanzada ClaveÚnica (FEA).
                    </p>
                </div>
                <div style="display:inline-flex; background:#E2E8F0; padding:4px; border-radius:10px; gap:4px;">
                    <button type="button" id="tabPackFesBtn" onclick="togglePackLine('FES')" style="padding:6px 14px; border-radius:8px; font-size:0.82rem; font-weight:800; border:none; cursor:pointer; background:#16A34A; color:#FFFFFF; transition:all 0.2s;">
                        <i class="fas fa-file-signature"></i> Firma Simple (FES)
                    </button>
                    <button type="button" id="tabPackFeaBtn" onclick="togglePackLine('FEA')" style="padding:6px 14px; border-radius:8px; font-size:0.82rem; font-weight:800; border:none; cursor:pointer; background:transparent; color:#475569; transition:all 0.2s;">
                        <i class="fas fa-shield-alt"></i> Avanzada ClaveÚnica (FEA)
                    </button>
                </div>
            </div>

            <!-- GRILLA PACKS FES -->
            <div id="gridPacksFES" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <!-- FES Pack 10 Starter -->
                <div style="background:#FFFFFF; border:1px solid #BBF7D0; border-top:3px solid #16A34A; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.72rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fes10['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fes10['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.76rem; color:#64748B;"><?= htmlspecialchars($fes10['nombre']) ?> ($<?= number_format($fes10['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#16A34A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FES_10')">
                        Probar
                    </button>
                </div>

                <!-- FES Pack 50 -->
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.72rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fes50['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fes50['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.76rem; color:#64748B;"><?= htmlspecialchars($fes50['ahorro_desc']) ?> ($<?= number_format($fes50['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FES_50')">
                        Recargar
                    </button>
                </div>

                <!-- FES Pack 200 Popular -->
                <div style="background:#FFFFFF; border:2px solid #16A34A; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm); position:relative;">
                    <span style="position:absolute; top:-10px; right:15px; background:#16A34A; color:#FFF; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:10px;"><?= htmlspecialchars($fes200['badge']) ?></span>
                    <div>
                        <span style="font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fes200['nombre']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fes200['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes200['ahorro_desc']) ?> ($<?= number_format($fes200['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#16A34A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FES_200')">
                        Recargar
                    </button>
                </div>

                <!-- FES Pack 500 Enterprise -->
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fes500['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fes500['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes500['ahorro_desc']) ?> ($<?= number_format($fes500['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FES_500')">
                        Recargar
                    </button>
                </div>
            </div>

            <!-- GRILLA PACKS FEA (INICIALMENTE OCULTA O ALTERNABLE) -->
            <div id="gridPacksFEA" style="display: none; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <!-- FEA Pack 10 Starter -->
                <div style="background:#FFFFFF; border:1px solid #BFDBFE; border-top:3px solid #2563EB; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.72rem; font-weight:800; color:#1D4ED8; background:#DBEAFE; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fea10['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fea10['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.76rem; color:#64748B;"><?= htmlspecialchars($fea10['nombre']) ?> ($<?= number_format($fea10['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#2563EB; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FEA_10')">
                        Probar
                    </button>
                </div>

                <!-- FEA Pack 50 -->
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.72rem; font-weight:800; color:#1D4ED8; background:#DBEAFE; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fea50['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fea50['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.76rem; color:#64748B;"><?= htmlspecialchars($fea50['ahorro_desc']) ?> ($<?= number_format($fea50['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FEA_50')">
                        Recargar
                    </button>
                </div>

                <!-- FEA Pack 200 Popular -->
                <div style="background:#FFFFFF; border:2px solid #D4AF37; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm); position:relative;">
                    <span style="position:absolute; top:-10px; right:15px; background:#D4AF37; color:#0A1C33; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:10px;"><?= htmlspecialchars($fea200['badge']) ?></span>
                    <div>
                        <span style="font-size:0.75rem; font-weight:800; color:#B45309; background:#FEF3C7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fea200['nombre']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fea200['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea200['ahorro_desc']) ?> ($<?= number_format($fea200['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta btn-gold" style="padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FEA_200')">
                        Recargar
                    </button>
                </div>

                <!-- FEA Pack 500 Enterprise -->
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                    <div>
                        <span style="font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;"><?= htmlspecialchars($fea500['badge']) ?></span>
                        <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$<?= number_format($fea500['precio_unit'], 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                        <p style="margin:0; font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea500['ahorro_desc']) ?> ($<?= number_format($fea500['total'], 0, '', '.') ?> CLP)</p>
                    </div>
                    <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_FEA_500')">
                        Recargar
                    </button>
                </div>
            </div>
        </div>

        <!-- Navegación por Pestañas Corporativas B2B -->
        <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; border-bottom:2px solid #E2E8F0; padding-bottom:0.5rem;">
            <button type="button" class="tab-btn-b2b active" onclick="switchB2BTab('emision', this)">
                <i class="fas fa-file-excel"></i> 1. Smart Bulk & Emisión
            </button>
            <button type="button" class="tab-btn-b2b" onclick="switchB2BTab('plantillas', this)">
                <i class="fas fa-layer-group"></i> 2. Mis Plantillas Propias
            </button>
            <button type="button" class="tab-btn-b2b" onclick="switchB2BTab('expediente', this)">
                <i class="fas fa-folder-open"></i> 3. Expediente del Trabajador
            </button>
            <button type="button" class="tab-btn-b2b" onclick="switchB2BTab('whatsapp', this)">
                <i class="fab fa-whatsapp"></i> 4. WhatsApp Oficial
            </button>
        </div>

        <style>
            .tab-btn-b2b {
                background: none;
                border: none;
                font-size: 0.95rem;
                font-weight: 700;
                color: #64748B;
                padding: 8px 16px;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .tab-btn-b2b:hover {
                color: var(--primary);
                background: #F1F5F9;
            }
            .tab-btn-b2b.active {
                color: #0A1C33;
                background: #FEF3C7;
                border: 1px solid #D4AF37;
            }
            .b2b-tab-pane {
                display: none;
            }
            .b2b-tab-pane.active {
                display: block;
            }
        </style>

        <!-- PESTAÑA 1: SMART BULK & EMISIÓN -->
        <div id="pane_emision" class="b2b-tab-pane active">

            <!-- Selector de Modo de Emisión: Planilla vs PDF Único -->
            <div style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px; padding:0.5rem; margin-bottom:1.5rem; display:flex; gap:0.5rem; max-width:650px;">
                <button type="button" id="btnModeSpreadsheet" class="tab-btn-b2b active" style="flex:1; justify-content:center; padding:10px 14px; font-size:0.92rem;" onclick="switchEmisionMode('spreadsheet')">
                    <i class="fas fa-file-excel" style="color:#10B981;"></i> Opción A: Contratos con Variables (Planilla Excel)
                </button>
                <button type="button" id="btnModeBroadcast" class="tab-btn-b2b" style="flex:1; justify-content:center; padding:10px 14px; font-size:0.92rem;" onclick="switchEmisionMode('broadcast')">
                    <i class="fas fa-bullhorn" style="color:#2563EB;"></i> Opción B: Difusión Masiva de PDF Único
                </button>
            </div>

            <!-- CONTENEDOR MODO A: PLANILLA DINÁMICA -->
            <div id="modeSpreadsheetWrap">
                <!-- Barra Superior de Selección de Plantilla y Tipo de Certificación (Dual FES vs FEA) -->
                <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="flex:1; min-width:280px;">
                        <label style="display:block; font-size:0.85rem; font-weight:800; color:var(--primary); margin-bottom:4px;">
                            <i class="fas fa-file-signature" style="color:var(--gold);"></i> 1. Selecciona la Plantilla para Emisión Masiva:
                        </label>
                        <select id="selectBatchTemplate" class="form-control" style="width:100%; padding:9px 12px; font-size:0.92rem; font-weight:700; color:#0A1C33; border:2px solid #D4AF37; border-radius:8px; background:#FFFBF0;" onchange="handleBatchTemplateChange()">
                            <optgroup label="📋 Plantillas Oficiales SinNotario (Wizard Universal)">
                                <option value="contrato-individual-trabajo" data-type="official" selected>Contrato Individual de Trabajo (Ley 40 Horas N° 21.561)</option>
                                <option value="finiquito-laboral-mutuo-acuerdo" data-type="official">Finiquito Laboral por Mutuo Acuerdo (Código del Trabajo)</option>
                                <option value="prestacion-servicios-honorarios" data-type="official">Contrato de Prestación de Servicios Profesionales / Honorarios</option>
                                <option value="acuerdo-confidencialidad-nda" data-type="official">Acuerdo de Confidencialidad y No Divulgación (NDA)</option>
                                <option value="contrato-arriendo-ley-21461" data-type="official">Contrato de Arriendo Residencial (Ley Devuélveme Mi Casa)</option>
                            </optgroup>
                            <optgroup label="🏢 Mis Plantillas Propias Personalizadas" id="optgroupCustomTemplates">
                                <option value="" disabled>Cargando plantillas propias...</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Selector de Certificación Legal (FES vs FEA) -->
                    <div style="min-width:260px;">
                        <label style="display:block; font-size:0.85rem; font-weight:800; color:var(--primary); margin-bottom:4px;">
                            <i class="fas fa-shield-alt" style="color:#2563EB;"></i> 2. Tipo de Firma y Certificación:
                        </label>
                        <div style="display:flex; background:#F1F5F9; padding:3px; border-radius:8px; gap:4px; border:1px solid #CBD5E1;">
                            <button type="button" id="btnBatchCertFes" onclick="selectBatchCertType('FES')" style="flex:1; padding:7px 10px; font-size:0.8rem; font-weight:800; border:none; border-radius:6px; cursor:pointer; background:#16A34A; color:#FFF; transition:all 0.2s;">
                                <i class="fas fa-file-signature"></i> Simple (FES)
                            </button>
                            <button type="button" id="btnBatchCertFea" onclick="selectBatchCertType('FEA')" style="flex:1; padding:7px 10px; font-size:0.8rem; font-weight:800; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#475569; transition:all 0.2s;">
                                <i class="fas fa-shield-alt"></i> ClaveÚnica (FEA)
                            </button>
                        </div>
                        <div id="batchCertHint" style="font-size:0.74rem; color:#15803D; margin-top:4px; font-weight:600;">
                            🟢 Bolsa FES: <strong id="hintSaldoFes">0</strong> disponibles
                        </div>
                    </div>

                    <div style="display:flex; align-items:flex-end; gap:0.75rem;">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#0A1C33 !important; border:1px solid #CBD5E1; padding:9px 14px; font-size:0.85rem;" onclick="downloadSampleCsv()">
                            <i class="fas fa-download"></i> Planilla (.csv)
                        </button>
                        <a href="javascript:void(0)" onclick="openTemplateWizardPreview()" class="btn-cta" style="background:#FFF; color:#2563EB !important; border:1px solid #BFDBFE; padding:9px 12px; font-size:0.85rem;" title="Ver estructura y redacción de la plantilla">
                            <i class="fas fa-eye"></i> Ver Plantilla
                        </a>
                    </div>
                </div>

                <!-- Zona de Carga Masiva Drag-and-Drop -->
                <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('excelFileInput').click()">
                    <input type="file" id="excelFileInput" accept=".csv, .txt, .xlsx" style="display: none;" onchange="handleExcelUpload(event)">
                    <i class="fas fa-file-excel fa-3x" style="color: #10B981; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">Arrastra tu planilla CSV/Excel o haz clic para cargar</h3>
                    <p style="color: #64748B; font-size: 0.88rem; margin-top: 0.35rem;">
                        Formatos admitidos: <code>.csv</code> (separado por coma o punto y coma). Columnas recomendadas: <code>nombre</code>, <code>rut</code>, <code>email</code>, <code>telefono</code>, <code>cargo</code>, <code>sueldo</code>.
                    </p>
                    <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1;" onclick="event.stopPropagation(); downloadSampleCsv();">
                            <i class="fas fa-download"></i> Descargar Planilla Modelo (.csv)
                        </button>
                        <button type="button" class="btn-cta btn-gold" onclick="event.stopPropagation(); openSimulationModal();">
                            <i class="fas fa-flask"></i> Simular Emisión Piloto (Prueba Sin Consumo)
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR MODO B: DIFUSIÓN Y FIRMA MASIVA DE PDF ÚNICO -->
            <div id="modeBroadcastWrap" style="display: none;">
                <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <h3 style="font-size:1.2rem; font-weight:800; color:var(--primary); margin:0;">
                                <i class="fas fa-bullhorn" style="color:#2563EB;"></i> Difusión y Acuse de Recibo Masivo de PDF Único
                            </h3>
                            <p style="color:#64748B; font-size:0.88rem; margin:4px 0 0;">
                                Sube un documento común (Reglamento Interno, Protocolo Ley Karin, Entrega de EPP) y desdrápalo a toda tu nómina con certificación y archivo en el Expediente Digital de cada colaborador.
                            </p>
                        </div>
                        <span style="background:#EFF6FF; color:#1D4ED8; font-weight:700; font-size:0.8rem; padding:4px 12px; border-radius:9999px; border:1px solid #BFDBFE;">
                            <i class="fas fa-balance-scale"></i> Plena Validez Ley N° 19.799
                        </span>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                                Título del Documento Corporativo:
                            </label>
                            <input type="text" id="broadcastDocTitle" class="form-control" placeholder="Ej: Reglamento Interno de Orden, Higiene y Seguridad 2026" style="width:100%; padding:9px 12px; font-size:0.9rem;" value="Reglamento Interno de Orden, Higiene y Seguridad">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                                Tipo / Categoría de Documento:
                            </label>
                            <select id="broadcastDocCategory" class="form-control" style="width:100%; padding:9px 12px; font-size:0.9rem;">
                                <option value="REGLAMENTO_INTERNO" selected>Reglamento Interno (RIOHS - Código del Trabajo)</option>
                                <option value="LEY_KARIN">Protocolo de Acoso y Violencia (Ley Karin N° 21.643)</option>
                                <option value="EPP">Entrega de Equipos de Protección Personal (EPP)</option>
                                <option value="POLITICA_INTERNA">Política de Seguridad de la Información / Privacidad</option>
                                <option value="COMUNICADO_OFICIAL">Circular / Comunicado General de Empresa</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                                Tipo de Firma y Certificación:
                            </label>
                            <div style="display:flex; background:#F1F5F9; padding:3px; border-radius:8px; gap:4px; border:1px solid #CBD5E1;">
                                <button type="button" id="btnBroadcastCertFes" onclick="selectBroadcastCertType('FES')" style="flex:1; padding:7px 10px; font-size:0.8rem; font-weight:800; border:none; border-radius:6px; cursor:pointer; background:#16A34A; color:#FFF; transition:all 0.2s;">
                                    <i class="fas fa-file-signature"></i> Simple (FES)
                                </button>
                                <button type="button" id="btnBroadcastCertFea" onclick="selectBroadcastCertType('FEA')" style="flex:1; padding:7px 10px; font-size:0.8rem; font-weight:800; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#475569; transition:all 0.2s;">
                                    <i class="fas fa-shield-alt"></i> ClaveÚnica (FEA)
                                </button>
                            </div>
                            <div id="broadcastCertHint" style="font-size:0.74rem; color:#15803D; margin-top:4px; font-weight:600;">
                                🟢 Bolsa FES: <strong id="hintBroadcastSaldoFes">0</strong> disponibles
                            </div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;" class="broadcast-dual-grid">
                        <!-- Columna 1: Subida de PDF Único -->
                        <div style="border:2px dashed #93C5FD; background:#F8FAFC; border-radius:10px; padding:1.5rem; text-align:center; cursor:pointer;" onclick="document.getElementById('broadcastPdfInput').click()">
                            <input type="file" id="broadcastPdfInput" accept=".pdf" style="display:none;" onchange="handleBroadcastPdfUpload(event)">
                            <i class="fas fa-file-pdf fa-3x" style="color:#DC2626; margin-bottom:0.75rem;"></i>
                            <h4 style="font-size:1.05rem; font-weight:700; color:var(--primary); margin:0 0 4px;" id="broadcastPdfNameText">
                                Haz clic o arrastra el archivo PDF Único
                            </h4>
                            <p style="color:#64748B; font-size:0.82rem; margin:0;" id="broadcastPdfInfoText">
                                Archivo en formato PDF estándar (máx. 25 MB).
                            </p>
                        </div>

                        <!-- Columna 2: Subida de Nómina de Trabajadores -->
                        <div style="border:2px dashed #86EFAC; background:#F8FAFC; border-radius:10px; padding:1.5rem; text-align:center; cursor:pointer;" onclick="document.getElementById('broadcastCsvInput').click()">
                            <input type="file" id="broadcastCsvInput" accept=".csv, .txt, .xlsx" style="display:none;" onchange="handleBroadcastCsvUpload(event)">
                            <i class="fas fa-users fa-3x" style="color:#10B981; margin-bottom:0.75rem;"></i>
                            <h4 style="font-size:1.05rem; font-weight:700; color:var(--primary); margin:0 0 4px;" id="broadcastCsvNameText">
                                Carga la Nómina de Trabajadores (.csv)
                            </h4>
                            <p style="color:#64748B; font-size:0.82rem; margin:0;" id="broadcastCsvInfoText">
                                Columnas: <code>nombre, rut, email, telefono, cargo</code>
                            </p>
                            <div style="margin-top:0.75rem;">
                                <button type="button" class="btn-cta" style="background:#FFF; color:#15803D !important; border:1px solid #86EFAC; padding:5px 10px; font-size:0.78rem;" onclick="event.stopPropagation(); downloadBroadcastSampleCsv();">
                                    <i class="fas fa-download"></i> Descargar Planilla Nómina (.csv)
                                </button>
                                <button type="button" class="btn-cta" style="background:#FFF; color:#2563EB !important; border:1px solid #BFDBFE; padding:5px 10px; font-size:0.78rem; margin-left:4px;" onclick="event.stopPropagation(); loadBroadcastSampleData();">
                                    <i class="fas fa-magic"></i> Cargar Muestra (4)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen Previo de Convocatoria -->
                    <div id="broadcastPreviewWrap" style="display:none; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid #E2E8F0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <span style="font-weight:800; color:var(--primary); font-size:1rem;" id="broadcastTotalWorkersHeader">0 Colaboradores Listos</span>
                                <span style="font-size:0.82rem; color:#64748B; margin-left:8px;" id="broadcastPdfHashPreview">Hash: -</span>
                            </div>
                            <div>
                                <button type="button" class="btn-cta btn-gold" id="btnEmitBroadcast" onclick="submitBroadcastBatch()" style="padding:10px 18px; font-size:0.95rem;">
                                    <i class="fas fa-paper-plane"></i> Emitir, Difundir y Notificar por WhatsApp
                                </button>
                            </div>
                        </div>
                        <div style="max-height:220px; overflow-y:auto; border:1px solid #E2E8F0; border-radius:8px;">
                            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                                <thead>
                                    <tr style="background:#F1F5F9; color:#475569; position:sticky; top:0;">
                                        <th style="padding:6px 10px; text-align:left;">#</th>
                                        <th style="padding:6px 10px; text-align:left;">Nombre</th>
                                        <th style="padding:6px 10px; text-align:left;">RUT</th>
                                        <th style="padding:6px 10px; text-align:left;">Email</th>
                                        <th style="padding:6px 10px; text-align:left;">WhatsApp</th>
                                        <th style="padding:6px 10px; text-align:left;">Cargo</th>
                                    </tr>
                                </thead>
                                <tbody id="broadcastWorkersTbody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Mapeo y Validación Previa -->
            <div id="parsedWrap" class="parsed-table-wrap">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--primary); margin:0;">
                            Lote Procesado: <span id="batchTitleHeader">Nómina de Contratos</span>
                        </h3>
                        <div style="display: flex; gap: 10px; margin-top: 6px;">
                            <span class="metric-pill metric-valid" id="validCountPill"><i class="fas fa-check-circle"></i> 0 Válidos</span>
                            <span class="metric-pill metric-invalid" id="invalidCountPill"><i class="fas fa-exclamation-triangle"></i> 0 Inválidos</span>
                            <span style="font-size: 0.85rem; color: #64748B; align-self: center;" id="totalRecordsText">Total: 0 registros</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.75rem;">
                        <button class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" id="btnExportDt" onclick="exportCurrentBatchDt()" disabled>
                            <i class="fas fa-file-export"></i> Exportar Libro DT (Ley 21.327)
                        </button>
                        <button class="btn-cta btn-gold" id="btnSignBatch" onclick="signBatchConfirm()">
                            <i class="fas fa-signature"></i> Firmar Lote con 1 Clic
                        </button>
                    </div>
                </div>

                <div style="overflow-x: auto; max-height: 400px; border: 1px solid var(--border); border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background:#F8FAFC; color:#64748B; text-align:left; position: sticky; top:0; z-index: 10;">
                                <th style="padding:0.75rem;">#</th>
                                <th style="padding:0.75rem;">Nombre Colaborador</th>
                                <th style="padding:0.75rem;">RUT</th>
                                <th style="padding:0.75rem;">Correo</th>
                                <th style="padding:0.75rem;">Teléfono / WhatsApp</th>
                                <th style="padding:0.75rem;">Cargo</th>
                                <th style="padding:0.75rem;">Sueldo</th>
                                <th style="padding:0.75rem; text-align:center;">Estado / Validación</th>
                            </tr>
                        </thead>
                        <tbody id="parsedTableBody">
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #64748B;">
                    <div>
                        <i class="fas fa-shield-alt" style="color: var(--primary);"></i> Certificación criptográfica con hash SHA-256 e integración con la Dirección del Trabajo.
                    </div>
                    <div id="batchStatusFooter">Estado: Listo para emisión</div>
                </div>
            </div>

            <!-- Historial de Lotes Emitidos -->
            <div class="history-card">
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;">
                    <i class="fas fa-history"></i> Historial de Lotes y Contratos Masivos
                </h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background:#F8FAFC; color:#64748B; text-align:left;">
                                <th style="padding:0.75rem;">ID Lote</th>
                                <th style="padding:0.75rem;">Título</th>
                                <th style="padding:0.75rem;">Fecha Creación</th>
                                <th style="padding:0.75rem;">Registros</th>
                                <th style="padding:0.75rem;">Estado</th>
                                <th style="padding:0.75rem; text-align:right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="batchesHistoryBody">
                            <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: #94A3B8;">Cargando historial de lotes...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 2: PLANTILLAS PROPIAS DE LA EMPRESA -->
        <div id="pane_plantillas" class="b2b-tab-pane">
            <div style="background:#FFFFFF; border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; box-shadow:var(--shadow-sm);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h3 style="font-size:1.2rem; font-weight:800; color:var(--primary); margin:0;">
                            <i class="fas fa-layer-group" style="color:#D4AF37;"></i> Diseña y Carga tus Propios Contratos
                        </h3>
                        <p style="color:#64748B; font-size:0.85rem; margin:4px 0 0;">
                            Pega el texto legal de tu empresa y utiliza variables entre dobles llaves como <code>{{NOMBRE}}</code>, <code>{{RUT}}</code>, <code>{{CARGO}}</code>, <code>{{SUELDO}}</code>.
                        </p>
                    </div>
                    <button type="button" class="btn-cta btn-gold" onclick="openTemplateEditor()">
                        <i class="fas fa-plus"></i> Crear Nueva Plantilla Propia
                    </button>
                </div>

                <div id="companyTemplatesList" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1rem;">
                    <div style="padding:2rem; text-align:center; color:#94A3B8; grid-column:1/-1;">Cargando plantillas de la empresa...</div>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 3: EXPEDIENTE DIGITAL DEL TRABAJADOR -->
        <div id="pane_expediente" class="b2b-tab-pane">
            <div style="background:#FFFFFF; border:1px solid var(--border); border-radius:12px; padding:1.5rem; box-shadow:var(--shadow-sm);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                    <div>
                        <h3 style="font-size:1.2rem; font-weight:800; color:var(--primary); margin:0;">
                            <i class="fas fa-folder-open" style="color:#2563EB;"></i> Carpetas Digitales por Colaborador
                        </h3>
                        <p style="color:#64748B; font-size:0.85rem; margin:4px 0 0;">
                            Historial centralizado de contratos primitivos, anexos de 40 Horas, comprobantes y finiquitos bajo custodia certificada.
                        </p>
                    </div>
                    <input type="text" id="workerSearchInput" placeholder="Buscar por nombre o RUT..." style="padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px; font-size:0.85rem; width:250px;" oninput="filterWorkerFolders()">
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background:#F8FAFC; color:#64748B; text-align:left;">
                                <th style="padding:0.75rem;">Colaborador</th>
                                <th style="padding:0.75rem;">RUT</th>
                                <th style="padding:0.75rem;">Cargo Actual</th>
                                <th style="padding:0.75rem;">Documentos</th>
                                <th style="padding:0.75rem;">Estado</th>
                                <th style="padding:0.75rem; text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="workerFoldersTableBody">
                            <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: #94A3B8;">Cargando expedientes de colaboradores...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PESTAÑA 4: WHATSAPP OFICIAL DEDICADO -->
        <div id="pane_whatsapp" class="b2b-tab-pane">
            <div style="background:#FFFFFF; border:1px solid var(--border); border-radius:12px; padding:2rem; box-shadow:var(--shadow-sm); max-width:850px; margin:0 auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <div style="width:54px; height:54px; border-radius:12px; background:#DCFCE7; color:#15803D; display:flex; align-items:center; justify-content:center; font-size:1.8rem;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div>
                            <h3 style="font-size:1.3rem; font-weight:800; color:var(--primary); margin:0;">WhatsApp Oficial de tu Propia Empresa</h3>
                            <p style="color:#64748B; font-size:0.88rem; margin:4px 0 0;">
                                Conecta el número corporativo de tu empresa escaneando el código QR. Modelo $0 costo de plantillas Meta.
                            </p>
                        </div>
                    </div>
                    <div id="waStatusBadgeHeader">
                        <span style="background:#FEF3C7; color:#B45309; font-weight:800; font-size:0.82rem; padding:6px 14px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-circle-notch fa-spin"></i> Verificando conexión...
                        </span>
                    </div>
                </div>

                <!-- Tarjeta Interactiva de Escaneo de QR (Estilo KeyHub) -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.75rem; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:1.5rem;" id="waQrBoxGrid">
                    <!-- Columna Izquierda: Visualizador QR -->
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; background:#FFF; border:2px dashed #CBD5E1; border-radius:12px; padding:1.5rem; min-height:280px; text-align:center;">
                        <div id="waQrLoading" style="color:#64748B; font-size:0.88rem;">
                            <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary); margin-bottom:0.75rem;"></i><br>
                            Consultando Gateway WhatsApp...
                        </div>
                        <img id="imgWaQrCode" src="" alt="Código QR WhatsApp" style="display:none; max-width:220px; width:100%; border-radius:8px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:0.75rem;" />
                        <div id="waConnectedNotice" style="display:none; text-align:center; padding:1rem;">
                            <div style="width:64px; height:64px; border-radius:50%; background:#DCFCE7; color:#15803D; font-size:2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                                <i class="fas fa-check"></i>
                            </div>
                            <h4 style="font-weight:800; color:#15803D; margin:0 0 4px;">¡WhatsApp Vinculado y Operativo!</h4>
                            <p style="font-size:0.82rem; color:#64748B; margin:0;" id="waConnectedSubtext">Tu instancia está transmitiendo en vivo.</p>
                        </div>
                        <div style="margin-top:0.5rem; display:flex; gap:0.5rem;" id="waQrActionButtons">
                            <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; padding:4px 10px; font-size:0.75rem;" onclick="loadCompanyWhatsAppQr()">
                                <i class="fas fa-sync-alt"></i> Regenerar QR
                            </button>
                            <button type="button" id="btnDisconnectWa" class="btn-cta" style="background:#FEE2E2; color:#B91C1C !important; padding:4px 10px; font-size:0.75rem; display:none;" onclick="disconnectCompanyWhatsApp()">
                                <i class="fas fa-power-off"></i> Desvincular
                            </button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Pasos de Vinculación -->
                    <div style="display:flex; flex-direction:column; justify-content:center;">
                        <h4 style="font-size:0.95rem; font-weight:800; color:var(--primary); margin:0 0 0.75rem;">
                            <i class="fas fa-qrcode" style="color:var(--gold);"></i> ¿Cómo vincular tu WhatsApp corporativo?
                        </h4>
                        <ol style="font-size:0.82rem; color:#475569; padding-left:1.2rem; line-height:1.6; margin:0 0 1rem;">
                            <li>Abre <strong>WhatsApp</strong> o <strong>WhatsApp Business</strong> en el teléfono celular de la empresa.</li>
                            <li>Ve a <strong>Menú (o Configuración)</strong> > <strong>Dispositivos vinculados</strong>.</li>
                            <li>Toca <strong>Vincular un dispositivo</strong> y apunta tu cámara al código QR de la izquierda.</li>
                            <li>¡Listo! Los contratos y notificaciones saldrán con el <strong>nombre y logotipo</strong> de tu propia empresa.</li>
                        </ol>
                        <div style="background:#EFF6FF; border-left:4px solid #3B82F6; padding:0.65rem 0.85rem; border-radius:4px; font-size:0.78rem; color:#1E40AF;">
                            <strong>Ahorro Total:</strong> Sin costos por mensaje ni validaciones engorrosas de Meta. Utiliza la infraestructura de alta disponibilidad Evolution API de Keystone Advisors.
                        </div>
                    </div>
                </div>

                <!-- Formulario de Metadatos y Teléfono -->
                <form onsubmit="saveCompanyWhatsApp(event)">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Número de WhatsApp Corporativo</label>
                            <input type="text" id="cfgWaPhone" class="form-control" style="width:100%; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px;" placeholder="+56912345678" value="+56984180479">
                            <small style="color:#64748B;">Identificador visible para los trabajadores que reciben los contratos.</small>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Identificador de Instancia Dedicada (Evolution API)</label>
                            <input type="text" id="cfgWaInstance" class="form-control" style="width:100%; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px; font-family:monospace; background:#F8FAFC;" placeholder="sn-empresa-instance" readonly>
                            <small style="color:#64748B;">Instancia aislada asignada a la empresa.</small>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:1.25rem;">
                        <span id="waStatusBottomLabel" style="font-size:0.85rem; color:#64748B; font-weight:600;">
                            <i class="fas fa-info-circle"></i> Puedes cambiar el número telefónico visible en cualquier momento.
                        </span>
                        <button type="submit" class="btn-cta btn-gold">
                            <i class="fas fa-save"></i> Guardar Número WhatsApp
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <!-- Modal de Configuración de Empresa y Plan -->
    <div id="companyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:600px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="font-weight:800; color:var(--primary); margin:0;">Perfil de Empresa & Plan Contratado</h3>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeCompanyModal()">&times;</button>
            </div>
            
            <!-- Resumen de Plan y Costos -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-weight:700; color:#334155; font-size:0.9rem;">Suscripción Corporativa Actual:</span>
                    <span style="background:#DCFCE7; color:#15803D; font-weight:800; font-size:0.78rem; padding:2px 8px; border-radius:10px;" id="modalPlanBadge">PLAN PYME ACTIVO</span>
                </div>
                <div style="font-size:0.83rem; color:#64748B; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div>Cupo Base: <strong id="modalPlanCupo">50 firmas / mes</strong></div>
                    <div>Costo Base: <strong id="modalPlanCosto">2.50 UF + IVA / mes</strong></div>
                    <div>Consumo Actual: <strong id="modalPlanConsumo">0 firmas</strong></div>
                    <div>Firma Adicional: <strong id="modalPlanExtra">$2.490 CLP</strong></div>
                </div>
            </div>

            <form id="companyForm" onsubmit="saveCompanyData(event)">
                <div style="margin-bottom:1rem;">
                    <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Razón Social Empresa</label>
                    <input type="text" id="cfgRazonSocial" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" required value="Keystone Advisors SpA">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div>
                        <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">RUT Empresa</label>
                        <input type="text" id="cfgRutEmpresa" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" required value="78.089.686-8">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Giro Comercial</label>
                        <input type="text" id="cfgGiro" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" value="Servicios Legales y Tecnológicos">
                    </div>
                </div>
                <div style="margin-bottom:1rem;">
                    <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Nombre Representante Legal</label>
                    <input type="text" id="cfgRepNombre" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" required value="Maritza Vásquez">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                    <div>
                        <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">RUT Rep. Legal</label>
                        <input type="text" id="cfgRepRut" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" required value="17.297.004-4">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Email Rep. Legal</label>
                        <input type="email" id="cfgRepEmail" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" required value="contacto@sinnotario.cl">
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <a href="https://wa.me/56984180479?text=Hola,%20solicito%20ampliar%20el%20cupo%20de%20firmas%20de%20mi%20plan%20empresas%20en%20SinNotario.cl" target="_blank" style="font-size:0.85rem; color:var(--primary); font-weight:700; text-decoration:underline;">
                        <i class="fas fa-arrow-up"></i> Ampliar Plan o Cupo
                    </a>
                    <div style="display:flex; gap:0.5rem;">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeCompanyModal()">Cancelar</button>
                        <button type="submit" class="btn-cta btn-gold">Guardar Configuración</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Creación / Edición de Plantilla Propia -->
    <div id="templateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:750px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg); max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h3 style="font-weight:800; color:var(--primary); margin:0;">Diseñar Plantilla Propia de Contrato</h3>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeTemplateModal()">&times;</button>
            </div>

            <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; padding:0.85rem; font-size:0.82rem; color:#1E40AF; margin-bottom:1.25rem;">
                <strong>Instrucciones:</strong> Escribe o pega el texto legal de tu empresa. Inserta variables como <code>{{NOMBRE}}</code>, <code>{{RUT}}</code>, <code>{{CARGO}}</code>, <code>{{SUELDO}}</code>, <code>{{FECHA_INICIO}}</code>. El sistema las detectará automáticamente para armar tu planilla Excel personalizada.
            </div>

            <form onsubmit="saveCustomTemplate(event)">
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Título de la Plantilla</label>
                    <input type="text" id="tplTitleInput" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;" placeholder="Ej. Contrato Faena Minera Antofagasta" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Categoría</label>
                    <select id="tplCategorySelect" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #CBD5E1; border-radius:6px;">
                        <option value="laboral">Laboral & RRHH (Contratos y Anexos)</option>
                        <option value="comercial">Comercial & Servicios</option>
                        <option value="arriendo">Inmobiliario & Arriendos</option>
                    </select>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Cuerpo del Contrato con Variables</label>
                    <textarea id="tplBodyInput" rows="12" class="form-control" style="width:100%; padding:10px; border:1px solid #CBD5E1; border-radius:6px; font-family:monospace; font-size:0.85rem; line-height:1.4;" placeholder="En Santiago de Chile, comparecen por una parte {{EMPRESA_RAZON_SOCIAL}}, RUT {{EMPRESA_RUT}}, y por la otra don/doña {{NOMBRE}}, cédula nacional de identidad N° {{RUT}}..." required></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeTemplateModal()">Cancelar</button>
                    <button type="submit" class="btn-cta btn-gold">Guardar Plantilla y Mapear Variables</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalle Expediente del Trabajador -->
    <div id="workerDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:700px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <div>
                    <h3 style="font-weight:800; color:var(--primary); margin:0;" id="workerModalName">Patricio Alarcón Soto</h3>
                    <p style="color:#64748B; font-size:0.85rem; margin:2px 0 0;" id="workerModalRut">RUT: 14.234.567-6 | Cargo: Operador Técnico</p>
                </div>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeWorkerDetailModal()">&times;</button>
            </div>

            <div style="border-top:1px solid #E2E8F0; padding-top:1rem;">
                <h4 style="font-size:0.95rem; font-weight:800; color:#334155; margin-bottom:0.75rem;">Documentos en Custodia del Trabajador</h4>
                <div id="workerDocsContainer">
                    <p style="color:#94A3B8; font-size:0.85rem;">Cargando documentos del expediente...</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-bottom">
            <div>SinNotario.cl — Suite Corporativa B2B de Recursos Humanos y Contratos Keystone Advisors.</div>
            <div>WhatsApp White-Label vía Evolution API v2 | Conector Dirección del Trabajo Ley N° 21.327</div>
        </div>
    </footer>

    <!-- Modal de Checkout / Compra de Bolsas Prepagadas con Pasarelas de Pago -->
    <div id="rechargeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(3px);">
        <div style="background:#FFF; max-width:650px; width:92%; border-radius:16px; padding:2rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <div>
                    <h3 style="font-weight:800; color:var(--primary); margin:0; font-size:1.3rem;">
                        <i class="fas fa-shopping-cart" style="color:var(--gold);"></i> Comprar Bolsa Prepagada de Firmas
                    </h3>
                    <p style="font-size:0.85rem; color:#64748B; margin:4px 0 0;">
                        Selecciona el volumen de firmas y el medio de pago electrónico preferido.
                    </p>
                </div>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#64748B;" onclick="closeRechargeModal()">&times;</button>
            </div>

            <!-- Paso 1: Elegir Línea y Pack de Firmas -->
            <div style="margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label style="font-size:0.85rem; font-weight:800; color:#1E293B; margin:0;">
                        1. Selecciona el Tipo y Pack de Firmas:
                    </label>
                    <div style="display:inline-flex; background:#E2E8F0; padding:2px; border-radius:8px; gap:2px;">
                        <button type="button" id="modalLineFesBtn" onclick="switchModalPackLine('FES')" style="padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:800; border:none; cursor:pointer; background:#16A34A; color:#FFFFFF;">
                            FES (Simple)
                        </button>
                        <button type="button" id="modalLineFeaBtn" onclick="switchModalPackLine('FEA')" style="padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:800; border:none; cursor:pointer; background:transparent; color:#475569;">
                            FEA (ClaveÚnica)
                        </button>
                    </div>
                </div>

                <!-- PACKS FES EN MODAL -->
                <div id="modalPacksFES" style="display:grid; grid-template-columns:1fr; gap:0.75rem;">
                    <!-- FES 10 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #BBF7D0; background:#F0FDF4; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFes10" onclick="selectCheckoutPack('PACK_FES_10')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FES_10" checked style="accent-color:#16A34A; transform:scale(1.2);">
                            <div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fes10['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fes10['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes10['ahorro_desc']) ?> ($<?= number_format($fes10['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#166534; font-size:1.1rem;">$<?= number_format($fes10['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fes10['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FES 50 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #E2E8F0; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFes50" onclick="selectCheckoutPack('PACK_FES_50')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FES_50" style="accent-color:#16A34A; transform:scale(1.2);">
                            <div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fes50['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fes50['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes50['ahorro_desc']) ?> ($<?= number_format($fes50['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fes50['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fes50['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FES 200 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #16A34A; background:#F0FDF4; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFes200" onclick="selectCheckoutPack('PACK_FES_200')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FES_200" style="accent-color:#16A34A; transform:scale(1.2);">
                            <div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fes200['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fes200['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes200['ahorro_desc']) ?> ($<?= number_format($fes200['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fes200['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fes200['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FES 500 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #E2E8F0; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFes500" onclick="selectCheckoutPack('PACK_FES_500')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FES_500" style="accent-color:#16A34A; transform:scale(1.2);">
                            <div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fes500['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fes500['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fes500['ahorro_desc']) ?> ($<?= number_format($fes500['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fes500['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fes500['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>
                </div>

                <!-- PACKS FEA EN MODAL -->
                <div id="modalPacksFEA" style="display:none; grid-template-columns:1fr; gap:0.75rem;">
                    <!-- FEA 10 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #BFDBFE; background:#F8FAFC; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFea10" onclick="selectCheckoutPack('PACK_FEA_10')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FEA_10" style="accent-color:#2563EB; transform:scale(1.2);">
                            <div>
                                <span style="background:#DBEAFE; color:#1D4ED8; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fea10['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fea10['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea10['ahorro_desc']) ?> ($<?= number_format($fea10['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#1D4ED8; font-size:1.1rem;">$<?= number_format($fea10['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fea10['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FEA 50 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #E2E8F0; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFea50" onclick="selectCheckoutPack('PACK_FEA_50')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FEA_50" style="accent-color:#2563EB; transform:scale(1.2);">
                            <div>
                                <span style="background:#DBEAFE; color:#1D4ED8; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fea50['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fea50['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea50['ahorro_desc']) ?> ($<?= number_format($fea50['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fea50['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fea50['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FEA 200 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #D4AF37; background:#FFFDF5; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFea200" onclick="selectCheckoutPack('PACK_FEA_200')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FEA_200" style="accent-color:#D4AF37; transform:scale(1.2);">
                            <div>
                                <span style="background:#FEF3C7; color:#B45309; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fea200['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fea200['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea200['ahorro_desc']) ?> ($<?= number_format($fea200['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fea200['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fea200['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>

                    <!-- FEA 500 -->
                    <label style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:2px solid #E2E8F0; border-radius:10px; cursor:pointer; transition:all 0.2s;" id="labelPackFea500" onclick="selectCheckoutPack('PACK_FEA_500')">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="radio" name="checkout_pack" value="PACK_FEA_500" style="accent-color:#2563EB; transform:scale(1.2);">
                            <div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($fea500['badge']) ?></span>
                                <div style="font-weight:800; color:#0F2A4A; font-size:0.95rem; margin-top:2px;"><?= htmlspecialchars($fea500['nombre']) ?></div>
                                <div style="font-size:0.78rem; color:#64748B;"><?= htmlspecialchars($fea500['ahorro_desc']) ?> ($<?= number_format($fea500['precio_unit'], 0, '', '.') ?> + IVA c/u)</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800; color:#0F2A4A; font-size:1.1rem;">$<?= number_format($fea500['total'], 0, '', '.') ?> CLP</div>
                            <div style="font-size:0.72rem; color:#64748B;">+ IVA ($<?= number_format(round($fea500['total'] * 1.19), 0, '', '.') ?> total)</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Paso 2: Elegir Medio de Pago -->
            <?php if (!$mpB2bActive && !$khipuB2bActive): ?>
                <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; text-align:center;">
                    <div style="color:#B45309; font-weight:700; margin-bottom:4px;">
                        <i class="fas fa-exclamation-triangle"></i> Pasarelas de Pago en Mantención
                    </div>
                    <p style="color:#92400E; font-size:0.85rem; margin:0;">
                        Los pagos electrónicos están temporalmente deshabilitados por mantención programada. Por favor contáctanos a soporte@sinnotario.cl.
                    </p>
                </div>
            <?php else: ?>
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; font-size:0.85rem; font-weight:800; color:#1E293B; margin-bottom:8px;">
                    2. Selecciona la Pasarela de Pago:
                </label>
                <div style="display:grid; grid-template-columns:<?php echo ($mpB2bActive && $khipuB2bActive) ? '1fr 1fr' : '1fr'; ?>; gap:0.75rem;">
                    <?php if ($mpB2bActive): ?>
                    <label style="border:2px solid <?php echo ($defaultB2bGateway === 'MERCADO_PAGO') ? '#009EE3' : '#CBD5E1'; ?>; background:<?php echo ($defaultB2bGateway === 'MERCADO_PAGO') ? '#F0F9FF' : '#FFFFFF'; ?>; border-radius:10px; padding:1rem; cursor:pointer; display:flex; align-items:center; gap:10px;" id="labelGatewayMP" onclick="selectCheckoutGateway('MERCADO_PAGO')">
                        <input type="radio" name="checkout_gateway" value="MERCADO_PAGO" <?php echo ($defaultB2bGateway === 'MERCADO_PAGO') ? 'checked' : ''; ?> style="accent-color:#009EE3;">
                        <div>
                            <div style="font-weight:800; color:#0369A1; font-size:0.92rem;">
                                <i class="fas fa-credit-card"></i> Mercado Pago
                            </div>
                            <div style="font-size:0.75rem; color:#64748B;">Tarjetas de Crédito, Débito / Webpay</div>
                        </div>
                    </label>
                    <?php endif; ?>

                    <?php if ($khipuB2bActive): ?>
                    <label style="border:2px solid <?php echo ($defaultB2bGateway === 'KHIPU') ? '#6366F1' : '#CBD5E1'; ?>; background:<?php echo ($defaultB2bGateway === 'KHIPU') ? '#EEF2FF' : '#FFFFFF'; ?>; border-radius:10px; padding:1rem; cursor:pointer; display:flex; align-items:center; gap:10px;" id="labelGatewayKhipu" onclick="selectCheckoutGateway('KHIPU')">
                        <input type="radio" name="checkout_gateway" value="KHIPU" <?php echo ($defaultB2bGateway === 'KHIPU') ? 'checked' : ''; ?> style="accent-color:#6366F1;">
                        <div>
                            <div style="font-weight:800; color:#4338CA; font-size:0.92rem;">
                                <i class="fas fa-university"></i> Khipu
                            </div>
                            <div style="font-size:0.75rem; color:#64748B;">Transferencia Bancaria Directa</div>
                        </div>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem; display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; font-size:0.85rem;">
                <span style="color:#64748B;"><i class="fas fa-file-invoice-dollar" style="color:#059669;"></i> Emisión automática de Factura Electrónica afecta a IVA (DTE 33).</span>
                <span style="font-weight:800; color:#0F2A4A;" id="checkoutTotalDisplay">$207.655 CLP (IVA incl.)</span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeRechargeModal()">Cancelar</button>
                <button type="button" id="btnConfirmCheckout" class="btn-cta btn-gold" style="padding:10px 22px; font-size:1rem;" onclick="executePackCheckout()">
                    <i class="fas fa-lock"></i> Continuar al Pago Seguro
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE SIMULACIÓN Y PRUEBA PILOTO B2B (SOL-0121) -->
    <div id="simulationModal" style="display:none; position:fixed; inset:0; background:rgba(10,28,51,0.7); z-index:9999; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(4px);">
        <div style="background:#FFFFFF; border-radius:16px; max-width:580px; width:100%; padding:2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid #E2E8F0; max-height:92vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem;">
                <div>
                    <span style="background:#FEF3C7; color:#B45309; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
                        <i class="fas fa-flask"></i> Entorno de Ensayo Corporativo
                    </span>
                    <h3 style="font-size:1.35rem; font-weight:800; color:var(--primary); margin:6px 0 0;">
                        Simulación de Emisión Piloto
                    </h3>
                </div>
                <button type="button" onclick="closeSimulationModal()" style="background:none; border:none; font-size:1.4rem; color:#94A3B8; cursor:pointer;">&times;</button>
            </div>

            <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; padding:1rem; margin-bottom:1.5rem;">
                <p style="font-size:0.85rem; color:#1E40AF; margin:0; line-height:1.5;">
                    <strong>¿Cómo funciona el Piloto?</strong><br>
                    Generamos 1 documento contractual de muestra para que puedas <strong>recibirlo en tu correo institucional y probar el enlace de firma en vivo</strong> antes de emitirlo formalmente a toda la nómina de trabajadores.<br>
                    <span style="color:#059669; font-weight:700;"><i class="fas fa-check-circle"></i> No consume firmas de tu bolsa ni afecta tus estadísticas laborales.</span>
                </p>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                    Correo donde recibirás el documento piloto:
                </label>
                <input type="email" id="simEmail" class="form-control" style="width:100%; padding:10px 12px; font-size:0.95rem; border:1px solid #CBD5E1; border-radius:8px;" placeholder="ejemplo: rrhh@tuempresa.cl">
                <span style="font-size:0.75rem; color:#64748B;">Te enviaremos el contrato con marca de agua y el botón de prueba de firma digital.</span>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                        Nombre del Colaborador (Piloto):
                    </label>
                    <input type="text" id="simWorkerName" class="form-control" style="width:100%; padding:8px 12px; font-size:0.9rem;" value="Patricio Alarcón Soto">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                        RUT Colaborador (Piloto):
                    </label>
                    <input type="text" id="simWorkerRut" class="form-control" style="width:100%; padding:8px 12px; font-size:0.9rem;" value="14.234.567-6">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                        Cargo de Prueba:
                    </label>
                    <input type="text" id="simWorkerCargo" class="form-control" style="width:100%; padding:8px 12px; font-size:0.9rem;" value="Operador Técnico">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">
                        Sueldo Base de Prueba:
                    </label>
                    <input type="text" id="simWorkerSueldo" class="form-control" style="width:100%; padding:8px 12px; font-size:0.9rem;" value="750.000">
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E2E8F0; padding-top:1.25rem;">
                <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeSimulationModal()">Cancelar</button>
                <button type="button" id="btnExecuteSimulation" class="btn-cta btn-gold" style="padding:10px 22px; font-size:0.95rem;" onclick="executeSimulationBatch()">
                    <i class="fas fa-paper-plane"></i> Generar y Enviar Piloto a Mi Correo
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let currentBatch = null;
        let parsedRows = [];
        let currentCompany = {
            rut: '78.089.686-8',
            razon_social: 'Keystone Advisors SpA',
            rep_legal_nombre: 'Maritza Vásquez',
            rep_legal_rut: '17.297.004-4',
            rep_legal_email: 'contacto@sinnotario.cl'
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadCompanyProfile();
            loadCompanyTemplates();
        });

        // Cargar datos de empresa y lotes anteriores
        async function loadCompanyProfile() {
            try {
                const res = await fetch('api/index.php?action=b2b_get_company');
                const json = await res.json();
                if (json.success && json.data) {
                    if (json.data.company) {
                        currentCompany = json.data.company;
                        updateCompanyUI();
                    }
                    renderBatchesHistory(json.data.batches || []);
                }
            } catch (e) {
                console.error("Error al cargar perfil empresa:", e);
            }
        }

        function updateCompanyUI() {
            document.getElementById('dispRazonSocial').textContent = currentCompany.razon_social || 'Keystone Advisors SpA';
            document.getElementById('dispRutEmpresa').textContent = currentCompany.rut || '78.089.686-8';
            document.getElementById('dispTarifaTramo').textContent = '$' + Number(currentCompany.tarifa_tramo_clp || 7990).toLocaleString('es-CL') + ' CLP';
            document.getElementById('dispPlanEstado').textContent = currentCompany.plan_estado || 'ACTIVO';
            
            const modalidad = currentCompany.modalidad_cobro || 'PREPAGO';
            document.getElementById('dispModalidadCobro').textContent = modalidad;

            if (modalidad === 'PREPAGO') {
                document.getElementById('lblSaldoModalidad').textContent = 'BOLSAS PREPAGADAS ACTIVAS';
                const saldoFes = parseInt(currentCompany.saldo_firmas_prepago || 0);
                const saldoFea = parseInt(currentCompany.saldo_firmas_fea || 0);
                document.getElementById('dispSaldoPrepago').textContent = saldoFes;
                document.getElementById('dispSaldoFea').textContent = saldoFea;
                document.getElementById('subSaldoModalidad').innerHTML = `Total firmas consumidas: <strong>${currentCompany.firmas_utilizadas || 0}</strong>`;

                // Actualizar hints de saldo en selectores de emisión
                updateCertHints();
            } else {
                document.getElementById('lblSaldoModalidad').textContent = 'CRÉDITO POSTPAGO MENSUAL';
                const gasto = Number(currentCompany.gasto_acumulado_periodo || 0).toLocaleString('es-CL');
                const umbral = currentCompany.umbral_max_postpago || 200;
                document.getElementById('valSaldoModalidad').innerHTML = `<span style="color:#1D4ED8;">$${gasto} CLP</span> <span style="font-size:0.8rem; font-weight:400; color:#64748B;">a facturar</span>`;
                document.getElementById('subSaldoModalidad').innerHTML = `Consumo: <strong>${currentCompany.firmas_utilizadas || 0}</strong> / Umbral máx: <strong>${umbral}</strong>`;
            }
            
            // Modal fields
            document.getElementById('cfgRazonSocial').value = currentCompany.razon_social || '';
            document.getElementById('cfgRutEmpresa').value = currentCompany.rut || '';
            document.getElementById('cfgRepNombre').value = currentCompany.rep_legal_nombre || '';
            document.getElementById('cfgRepRut').value = currentCompany.rep_legal_rut || '';
            document.getElementById('cfgRepEmail').value = currentCompany.rep_legal_email || '';

            // WhatsApp tab fields
            if (currentCompany.whatsapp_phone) {
                document.getElementById('cfgWaPhone').value = currentCompany.whatsapp_phone;
            }
            if (currentCompany.whatsapp_instance_id) {
                document.getElementById('cfgWaInstance').value = currentCompany.whatsapp_instance_id;
            }
        }

        // Variables de estado del Checkout Modal
        let selectedPackKey = 'PACK_FES_200';
        let selectedGateway = 'MERCADO_PAGO';

        // Variables globales de Certificación Masiva (FES vs FEA)
        let selectedBatchCertType = 'FES';
        let selectedBroadcastCertType = 'FES';

        function selectBatchCertType(type) {
            selectedBatchCertType = type === 'FEA' ? 'FEA' : 'FES';
            const btnFes = document.getElementById('btnBatchCertFes');
            const btnFea = document.getElementById('btnBatchCertFea');
            if (selectedBatchCertType === 'FES') {
                btnFes.style.background = '#16A34A';
                btnFes.style.color = '#FFF';
                btnFea.style.background = 'transparent';
                btnFea.style.color = '#475569';
            } else {
                btnFea.style.background = '#2563EB';
                btnFea.style.color = '#FFF';
                btnFes.style.background = 'transparent';
                btnFes.style.color = '#475569';
            }
            updateCertHints();
        }

        function selectBroadcastCertType(type) {
            selectedBroadcastCertType = type === 'FEA' ? 'FEA' : 'FES';
            const btnFes = document.getElementById('btnBroadcastCertFes');
            const btnFea = document.getElementById('btnBroadcastCertFea');
            if (selectedBroadcastCertType === 'FES') {
                btnFes.style.background = '#16A34A';
                btnFes.style.color = '#FFF';
                btnFea.style.background = 'transparent';
                btnFea.style.color = '#475569';
            } else {
                btnFea.style.background = '#2563EB';
                btnFea.style.color = '#FFF';
                btnFes.style.background = 'transparent';
                btnFes.style.color = '#475569';
            }
            updateCertHints();
        }

        function updateCertHints() {
            const saldoFes = parseInt(currentCompany.saldo_firmas_prepago || 0);
            const saldoFea = parseInt(currentCompany.saldo_firmas_fea || 0);

            // Hint Batch
            const hintBatch = document.getElementById('batchCertHint');
            if (hintBatch) {
                if (selectedBatchCertType === 'FES') {
                    hintBatch.innerHTML = `🟢 Bolsa FES: <strong>${saldoFes}</strong> disponibles`;
                    hintBatch.style.color = saldoFes > 0 ? '#15803D' : '#DC2626';
                } else {
                    hintBatch.innerHTML = `🔵 Bolsa FEA (ClaveÚnica): <strong>${saldoFea}</strong> disponibles`;
                    hintBatch.style.color = saldoFea > 0 ? '#1D4ED8' : '#DC2626';
                }
            }

            // Hint Broadcast
            const hintBroad = document.getElementById('broadcastCertHint');
            if (hintBroad) {
                if (selectedBroadcastCertType === 'FES') {
                    hintBroad.innerHTML = `🟢 Bolsa FES: <strong>${saldoFes}</strong> disponibles`;
                    hintBroad.style.color = saldoFes > 0 ? '#15803D' : '#DC2626';
                } else {
                    hintBroad.innerHTML = `🔵 Bolsa FEA (ClaveÚnica): <strong>${saldoFea}</strong> disponibles`;
                    hintBroad.style.color = saldoFea > 0 ? '#1D4ED8' : '#DC2626';
                }
            }
        }

        // Alternar vista del Banner Principal entre FES y FEA
        function togglePackLine(line) {
            const gridFes = document.getElementById('gridPacksFES');
            const gridFea = document.getElementById('gridPacksFEA');
            const btnFes = document.getElementById('tabPackFesBtn');
            const btnFea = document.getElementById('tabPackFeaBtn');

            if (line === 'FES') {
                gridFes.style.display = 'grid';
                gridFea.style.display = 'none';
                btnFes.style.background = '#16A34A';
                btnFes.style.color = '#FFFFFF';
                btnFea.style.background = 'transparent';
                btnFea.style.color = '#475569';
            } else {
                gridFes.style.display = 'none';
                gridFea.style.display = 'grid';
                btnFea.style.background = '#2563EB';
                btnFea.style.color = '#FFFFFF';
                btnFes.style.background = 'transparent';
                btnFes.style.color = '#475569';
            }
        }

        // Alternar vista dentro del Modal de Compra
        function switchModalPackLine(line) {
            const boxFes = document.getElementById('modalPacksFES');
            const boxFea = document.getElementById('modalPacksFEA');
            const btnFes = document.getElementById('modalLineFesBtn');
            const btnFea = document.getElementById('modalLineFeaBtn');

            if (line === 'FES') {
                boxFes.style.display = 'grid';
                boxFea.style.display = 'none';
                btnFes.style.background = '#16A34A';
                btnFes.style.color = '#FFFFFF';
                btnFea.style.background = 'transparent';
                btnFea.style.color = '#475569';
                selectCheckoutPack('PACK_FES_200');
            } else {
                boxFes.style.display = 'none';
                boxFea.style.display = 'grid';
                btnFea.style.background = '#2563EB';
                btnFea.style.color = '#FFFFFF';
                btnFes.style.background = 'transparent';
                btnFes.style.color = '#475569';
                selectCheckoutPack('PACK_FEA_200');
            }
        }

        const PACK_TOTALS = {
            // Línea FES
            'PACK_FES_10': { neto: 19900, total: 23681, label: '$23.681 CLP (IVA incl.)' },
            'PACK_FES_50': { neto: 84500, total: 100555, label: '$100.555 CLP (IVA incl.)' },
            'PACK_FES_200': { neto: 258000, total: 307020, label: '$307.020 CLP (IVA incl.)' },
            'PACK_FES_500': { neto: 495000, total: 589050, label: '$589.050 CLP (IVA incl.)' },

            // Línea FEA
            'PACK_FEA_10': { neto: 39900, total: 47481, label: '$47.481 CLP (IVA incl.)' },
            'PACK_FEA_50': { neto: 174500, total: 207655, label: '$207.655 CLP (IVA incl.)' },
            'PACK_FEA_200': { neto: 558000, total: 664020, label: '$664.020 CLP (IVA incl.)' },
            'PACK_FEA_500': { neto: 995000, total: 1184050, label: '$1.184.050 CLP (IVA incl.)' },

            // Retrocompatibilidad
            'PACK_10': { neto: 39900, total: 47481, label: '$47.481 CLP (IVA incl.)' },
            'PACK_50': { neto: 174500, total: 207655, label: '$207.655 CLP (IVA incl.)' },
            'PACK_200': { neto: 558000, total: 664020, label: '$664.020 CLP (IVA incl.)' },
            'PACK_500': { neto: 995000, total: 1184050, label: '$1.184.050 CLP (IVA incl.)' }
        };

        function openRechargeModal(preselectedPack = 'PACK_FES_200') {
            const isFea = preselectedPack.indexOf('FEA') !== -1;
            switchModalPackLine(isFea ? 'FEA' : 'FES');
            selectCheckoutPack(preselectedPack);
            document.getElementById('rechargeModal').style.display = 'flex';
        }

        function closeRechargeModal() {
            document.getElementById('rechargeModal').style.display = 'none';
        }

        function selectCheckoutPack(packKey) {
            // Mapear claves legacy
            if (packKey === 'PACK_10') packKey = 'PACK_FEA_10';
            if (packKey === 'PACK_50') packKey = 'PACK_FEA_50';
            if (packKey === 'PACK_200') packKey = 'PACK_FEA_200';
            if (packKey === 'PACK_500') packKey = 'PACK_FEA_500';

            selectedPackKey = packKey;
            const allPacks = ['PACK_FES_10', 'PACK_FES_50', 'PACK_FES_200', 'PACK_FES_500', 'PACK_FEA_10', 'PACK_FEA_50', 'PACK_FEA_200', 'PACK_FEA_500'];
            
            allPacks.forEach(k => {
                let idSuffix = '';
                if (k.startsWith('PACK_FES_')) idSuffix = 'PackFes' + k.replace('PACK_FES_', '');
                else if (k.startsWith('PACK_FEA_')) idSuffix = 'PackFea' + k.replace('PACK_FEA_', '');

                const label = document.getElementById('label' + idSuffix);
                const radio = label ? label.querySelector('input[type="radio"]') : null;
                if (label) {
                    if (k === packKey) {
                        label.style.borderColor = k.indexOf('FEA') !== -1 ? '#2563EB' : '#16A34A';
                        label.style.background = k.indexOf('FEA') !== -1 ? '#EFF6FF' : '#F0FDF4';
                        if (radio) radio.checked = true;
                    } else {
                        label.style.borderColor = '#E2E8F0';
                        label.style.background = '#FFFFFF';
                        if (radio) radio.checked = false;
                    }
                }
            });

            if (PACK_TOTALS[packKey]) {
                document.getElementById('checkoutTotalDisplay').textContent = PACK_TOTALS[packKey].label;
            }
        }

        function selectCheckoutGateway(gw) {
            selectedGateway = gw;
            const labelMP = document.getElementById('labelGatewayMP');
            const labelKhipu = document.getElementById('labelGatewayKhipu');
            const radioMP = labelMP ? labelMP.querySelector('input[type="radio"]') : null;
            const radioKhipu = labelKhipu ? labelKhipu.querySelector('input[type="radio"]') : null;

            if (gw === 'MERCADO_PAGO' && labelMP) {
                labelMP.style.borderColor = '#009EE3';
                labelMP.style.background = '#F0F9FF';
                if (labelKhipu) {
                    labelKhipu.style.borderColor = '#CBD5E1';
                    labelKhipu.style.background = '#FFFFFF';
                }
                if (radioMP) radioMP.checked = true;
                if (radioKhipu) radioKhipu.checked = false;
            } else if (labelKhipu) {
                labelKhipu.style.borderColor = '#6366F1';
                labelKhipu.style.background = '#EEF2FF';
                if (labelMP) {
                    labelMP.style.borderColor = '#CBD5E1';
                    labelMP.style.background = '#FFFFFF';
                }
                if (radioKhipu) radioKhipu.checked = true;
                if (radioMP) radioMP.checked = false;
            }
        }

        function rechargePack(packType) {
            openRechargeModal(packType);
        }

        async function executePackCheckout() {
            if (!currentCompany || !currentCompany.id) {
                alert("Identificando empresa...");
                return;
            }

            const btn = document.getElementById('btnConfirmCheckout');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Conectando con pasarela segura...`;

            try {
                const res = await fetch('api/index.php?action=b2b_recharge_bag', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company_id: currentCompany.id,
                        pack_type: selectedPackKey,
                        gateway: selectedGateway
                    })
                });
                const json = await res.json();
                if (json.success) {
                    if (json.data && json.data.init_point) {
                        // Redirigir a Mercado Pago
                        window.location.href = json.data.init_point;
                    } else if (json.data && json.data.payment_url) {
                        // Redirigir a Khipu
                        window.location.href = json.data.payment_url;
                    } else if (json.data && json.data.saldo_nuevo !== undefined) {
                        // Modo simulador demo sin credenciales configuradas aún
                        alert(`✅ ¡Recarga Exitosa (Modo Simulación)!\n\n${json.data.message}\nNuevo Saldo: ${json.data.saldo_nuevo} firmas.`);
                        closeRechargeModal();
                        loadCompanyProfile();
                    } else {
                        alert(json.data.message || "Orden de compra generada exitosamente.");
                        closeRechargeModal();
                    }
                } else {
                    alert("Error en recarga: " + (json.error || 'No se pudo iniciar el pago'));
                }
            } catch (e) {
                alert("Error de comunicación con la pasarela de pagos: " + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function openCompanyModal() {
            document.getElementById('companyModal').style.display = 'flex';
        }

        function closeCompanyModal() {
            document.getElementById('companyModal').style.display = 'none';
        }

        function saveCompanyData(e) {
            e.preventDefault();
            currentCompany.razon_social = document.getElementById('cfgRazonSocial').value.trim();
            currentCompany.rut = document.getElementById('cfgRutEmpresa').value.trim();
            currentCompany.rep_legal_nombre = document.getElementById('cfgRepNombre').value.trim();
            currentCompany.rep_legal_rut = document.getElementById('cfgRepRut').value.trim();
            currentCompany.rep_legal_email = document.getElementById('cfgRepEmail').value.trim();
            updateCompanyUI();
            closeCompanyModal();
            alert("Datos de la empresa actualizados para los próximos lotes.");
        }

        let cachedCompanyTemplates = [];

        // Cambiar plantilla seleccionada para emisión masiva
        function handleBatchTemplateChange() {
            const select = document.getElementById('selectBatchTemplate');
            const selectedOpt = select.options[select.selectedIndex];
            console.log("Plantilla seleccionada para lote:", select.value);
        }

        function openTemplateWizardPreview() {
            const select = document.getElementById('selectBatchTemplate');
            const slug = select.value;
            const selectedOpt = select.options[select.selectedIndex];
            const isCustom = selectedOpt.getAttribute('data-type') === 'custom';

            if (isCustom) {
                switchB2BTab('plantillas', document.querySelector("button[onclick*='plantillas']"));
            } else {
                window.open(`redactar.php?slug=${slug}`, '_blank');
            }
        }

        // Descarga de CSV modelo según la plantilla seleccionada
        function downloadSampleCsv() {
            const select = document.getElementById('selectBatchTemplate');
            const slug = select.value;
            const selectedOpt = select.options[select.selectedIndex];
            const isCustom = selectedOpt ? selectedOpt.getAttribute('data-type') === 'custom' : false;

            let csvContent = "";
            let fileName = "planilla_modelo_" + slug + ".csv";

            if (isCustom) {
                // Obtener variables de la plantilla propia
                const tplObj = cachedCompanyTemplates.find(t => String(t.id) === String(slug));
                if (tplObj && Array.isArray(tplObj.variables) && tplObj.variables.length > 0) {
                    csvContent = tplObj.variables.map(v => v.toLowerCase()).join(',') + "\n";
                    // Fila de ejemplo
                    csvContent += tplObj.variables.map(v => {
                        const vl = v.toLowerCase();
                        if (vl.includes('rut')) return '14.234.567-6';
                        if (vl.includes('nombre')) return 'Patricio Alarcón Soto';
                        if (vl.includes('email') || vl.includes('correo')) return 'patricio.alarcon@empresa.cl';
                        if (vl.includes('telefono') || vl.includes('tel')) return '+56991122334';
                        if (vl.includes('sueldo') || vl.includes('monto') || vl.includes('renta')) return '750000';
                        if (vl.includes('cargo') || vl.includes('puesto')) return 'Operador Técnico';
                        return 'Ejemplo ' + v;
                    }).join(',') + "\n";
                } else {
                    csvContent = "nombre,rut,email,telefono,cargo,sueldo\nPatricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Operador Técnico,750000\n";
                }
            } else if (slug === 'finiquito-laboral-mutuo-acuerdo') {
                csvContent = "nombre,rut,email,telefono,cargo,fecha_ingreso,fecha_egreso,monto_total_finiquito\n" +
                    "Patricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Operador Técnico,01/03/2023,31/10/2026,1850000\n" +
                    "Daniela Morales Vega,16.789.012-1,daniela.morales@empresa.cl,+56987654321,Jefa de Turno,15/01/2022,31/10/2026,3400000\n";
            } else if (slug === 'prestacion-servicios-honorarios') {
                csvContent = "nombre,rut,email,telefono,servicio_descripcion,honorario_mensual_bruto,fecha_inicio\n" +
                    "Cristián Fuentes Parra,18.456.789-K,cristian.fuentes@empresa.cl,+56977889900,Asesoría en Transformación Digital,1200000,01/11/2026\n";
            } else if (slug === 'acuerdo-confidencialidad-nda') {
                csvContent = "nombre,rut,email,telefono,cargo,finalidad_reunion\n" +
                    "Marcela Silva Henríquez,19.123.456-1,marcela.silva@empresa.cl,+56966554433,Consultora Externa,Evaluación de Alianza Estratégica B2B\n";
            } else if (slug === 'contrato-arriendo-ley-21461') {
                csvContent = "nombre,rut,email,telefono,direccion_inmueble,canon_arriendo_mensual,meses_garantia\n" +
                    "Patricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Av. Las Condes 1020 Depto 402,650000,1\n";
            } else {
                // Por defecto Contrato Individual de Trabajo
                csvContent = "nombre,rut,email,telefono,cargo,sueldo\n" +
                    "Patricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Operador Técnico,750000\n" +
                    "Daniela Morales Vega,16.789.012-1,daniela.morales@empresa.cl,+56987654321,Jefa de Turno,1200000\n" +
                    "Cristián Fuentes Parra,18.456.789-K,cristian.fuentes@empresa.cl,+56977889900,Supervisor Faena,950000\n" +
                    "Marcela Silva Henríquez,19.123.456-1,marcela.silva@empresa.cl,+56966554433,Prevencionista de Riesgos,1100000\n";
            }
            
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Modal de simulación y prueba piloto B2B (SOL-0121)
        function openSimulationModal() {
            const defaultEmail = currentCompany.rep_legal_email || 'contacto@sinnotario.cl';
            document.getElementById('simEmail').value = defaultEmail;
            document.getElementById('simulationModal').style.display = 'flex';
        }

        function closeSimulationModal() {
            document.getElementById('simulationModal').style.display = 'none';
        }

        async function executeSimulationBatch() {
            const email = document.getElementById('simEmail').value.trim();
            if (!email || !email.includes('@')) {
                alert("Por favor ingresa un correo electrónico válido para recibir el documento de prueba.");
                document.getElementById('simEmail').focus();
                return;
            }

            const nombre = document.getElementById('simWorkerName').value.trim() || 'Patricio Alarcón Soto';
            const rut = document.getElementById('simWorkerRut').value.trim() || '14.234.567-6';
            const cargo = document.getElementById('simWorkerCargo').value.trim() || 'Operador Técnico';
            const sueldo = document.getElementById('simWorkerSueldo').value.trim() || '750.000';

            const btn = document.getElementById('btnExecuteSimulation');
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Generando y despachando piloto...`;

            try {
                const sampleRows = [{
                    nombre: nombre,
                    rut: rut,
                    email: email, // Enviar al email corporativo de prueba
                    telefono: '+56984180479',
                    cargo: cargo,
                    sueldo: sueldo
                }];

                const title = "🧪 Piloto de Ensayo - " + new Date().toLocaleDateString('es-CL') + " " + new Date().toLocaleTimeString('es-CL', {hour: '2-digit', minute:'2-digit'});
                
                // 1. Subir lote como simulación
                const selectedSlug = document.getElementById('selectBatchTemplate') ? document.getElementById('selectBatchTemplate').value : 'contrato-individual-trabajo';
                const payloadUpload = {
                    titulo: title,
                    template_slug: selectedSlug,
                    tipo_firma: selectedBatchCertType || 'FES',
                    rows: sampleRows,
                    company: currentCompany,
                    is_simulation: 1,
                    simulation_email: email
                };

                const resUp = await fetch('api/index.php?action=b2b_upload_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payloadUpload)
                });
                const jsonUp = await resUp.json();

                if (!jsonUp.success || !jsonUp.data.batch) {
                    alert("Error creando lote piloto: " + (jsonUp.error || 'Error desconocido'));
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fas fa-paper-plane"></i> Generar y Enviar Piloto a Mi Correo`;
                    return;
                }

                const simBatch = jsonUp.data.batch;

                // 2. Firmar y generar automáticamente el documento piloto sin descontar saldo
                const resSign = await fetch('api/index.php?action=b2b_sign_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        batch_id: simBatch.batch_id,
                        rep_legal: {
                            nombre: currentCompany.rep_legal_nombre,
                            rut: currentCompany.rep_legal_rut,
                            email: email
                        }
                    })
                });
                const jsonSign = await resSign.json();

                closeSimulationModal();
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-paper-plane"></i> Generar y Enviar Piloto a Mi Correo`;

                if (jsonSign.success) {
                    alert(`🎉 ¡Simulación Piloto Ejecutada Exitosamente!\n\n` +
                          `• Hemos generado el contrato de prueba con marca de agua.\n` +
                          `• Te enviamos el correo a ${email} con el enlace para revisar y probar la firma.\n` +
                          `• Saldo consumido: 0 firmas (Tu bolsa se mantiene intacta).\n\n` +
                          `Cuando estés listo, puedes cargar tu nómina definitiva.`);
                    
                    currentBatch = simBatch;
                    renderBatchUI(currentBatch);
                    loadCompanyProfile(); // Actualizar historial con badge de simulación
                } else {
                    alert("Aviso en la emisión del piloto: " + (jsonSign.error || 'Verifica la consola'));
                }

            } catch (err) {
                alert("Error ejecutando simulación: " + err.message);
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-paper-plane"></i> Generar y Enviar Piloto a Mi Correo`;
            }
        }

        // Carga de muestra automática legacy (redirigido a modal de simulación)
        function loadSampleData() {
            openSimulationModal();
        }

        // Manejo de archivo subido
        function handleExcelUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            Papa.parse(file, {
                header: true,
                skipEmptyLines: true,
                dynamicTyping: false,
                complete: function(results) {
                    if (!results.data || results.data.length === 0) {
                        alert("No se detectaron registros en el archivo CSV.");
                        return;
                    }
                    const title = "Lote " + file.name.replace(/\.[^/.]+$/, "") + " - " + new Date().toLocaleDateString('es-CL');
                    uploadBatchToServer(title, results.data);
                },
                error: function(err) {
                    alert("Error leyendo archivo CSV: " + err.message);
                }
            });
        }

        // Enviar batch a la API backend
        async function uploadBatchToServer(titulo, rows) {
            try {
                const selectedSlug = document.getElementById('selectBatchTemplate') ? document.getElementById('selectBatchTemplate').value : 'contrato-individual-trabajo';
                document.getElementById('batchStatusFooter').textContent = 'Procesando lote y validando RUTs en el servidor...';
                const payload = {
                    titulo: titulo,
                    template_slug: selectedSlug,
                    tipo_firma: selectedBatchCertType,
                    rows: rows,
                    company: currentCompany
                };

                const res = await fetch('api/index.php?action=b2b_upload_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (!json.success) {
                    alert("Error al procesar lote: " + (json.error || 'Error desconocido'));
                    return;
                }

                currentBatch = json.data.batch;
                renderBatchUI(currentBatch);
                loadCompanyProfile(); // Refrescar historial

            } catch (e) {
                alert("Error de conexión al subir lote B2B: " + e.message);
            }
        }

        // Renderizar vista del lote
        function renderBatchUI(batch) {
            document.getElementById('batchTitleHeader').textContent = batch.titulo;
            document.getElementById('validCountPill').innerHTML = `<i class="fas fa-check-circle"></i> ${batch.valid_records} Válidos`;
            document.getElementById('invalidCountPill').innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${batch.invalid_records} Inválidos`;
            document.getElementById('totalRecordsText').textContent = `Total: ${batch.total_records} registros`;

            const tbody = document.getElementById('parsedTableBody');
            tbody.innerHTML = batch.items.map(r => `
                <tr style="border-bottom: 1px solid #E2E8F0; background: ${r.is_valid ? '#FFFFFF' : '#FEF2F2'};">
                    <td style="padding:0.75rem;">#${r.fila_num}</td>
                    <td style="padding:0.75rem; font-weight:600;">${r.colaborador_nombre}</td>
                    <td style="padding:0.75rem; font-family:monospace;">${r.colaborador_rut}</td>
                    <td style="padding:0.75rem; color:#64748B;">${r.colaborador_email || '-'}</td>
                    <td style="padding:0.75rem; color:#64748B;">${r.colaborador_telefono || '-'}</td>
                    <td style="padding:0.75rem;">${r.cargo || '-'}</td>
                    <td style="padding:0.75rem;">$${r.sueldo || '-'}</td>
                    <td style="padding:0.75rem; text-align:center;">
                        ${r.is_valid ? 
                            `<span style="background:#DCFCE7; color:#15803D; font-weight:700; font-size:0.75rem; padding:3px 8px; border-radius:4px;">
                                <i class="fas fa-check"></i> ${r.status === 'FIRMADO' ? 'FIRMADO' : 'RUT Válido'}
                            </span>` : 
                            `<span style="background:#FEE2E2; color:#B91C1C; font-weight:700; font-size:0.75rem; padding:3px 8px; border-radius:4px;" title="${r.error_detalle}">
                                <i class="fas fa-times"></i> ${r.error_detalle || 'Error'}
                            </span>`
                        }
                    </td>
                </tr>
            `).join('');

            document.getElementById('parsedWrap').style.display = 'block';
            document.getElementById('btnExportDt').disabled = false;
            document.getElementById('btnSignBatch').disabled = (batch.valid_records === 0);
            document.getElementById('batchStatusFooter').textContent = `Lote #${batch.batch_id} listo. Haz clic en "Firmar Lote con 1 Clic" para emitir todos los contratos certificados.`;
            document.getElementById('parsedWrap').scrollIntoView({ behavior: 'smooth' });
        }

        // Confirmar y firmar el lote en 1 clic
        async function signBatchConfirm() {
            if (!currentBatch || !currentBatch.batch_id) {
                alert("No hay ningún lote activo cargado.");
                return;
            }

            const tipoFirma = (currentBatch.tipo_firma || selectedBatchCertType || 'FES').toUpperCase();
            const tipoLabel = tipoFirma === 'FEA' ? 'Firma Avanzada ClaveÚnica (FEA)' : 'Firma Simple (FES)';
            const saldoCol = tipoFirma === 'FEA' ? 'saldo_firmas_fea' : 'saldo_firmas_prepago';
            const saldoDisp = parseInt(currentCompany[saldoCol] || 0);
            const modalidad = (currentCompany.modalidad_cobro || 'PREPAGO').toUpperCase();

            // Validación de saldo preventivo en UI
            if (modalidad === 'PREPAGO' && saldoDisp < currentBatch.valid_records) {
                alert(`Saldo insuficiente de ${tipoLabel}:\nTienes ${saldoDisp} firmas disponibles y necesitas ${currentBatch.valid_records} para este lote.\n\nPor favor, recarga un pack de ${tipoLabel} para continuar.`);
                openRechargeModal(tipoFirma === 'FEA' ? 'PACK_FEA_50' : 'PACK_FES_50');
                return;
            }

            if (!confirm(`¿Confirmas la emisión y firma masiva en 1 Clic con ${tipoLabel} para los ${currentBatch.valid_records} contratos válidos de este lote a nombre de ${currentCompany.razon_social}?`)) {
                return;
            }

            const btn = document.getElementById('btnSignBatch');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Emitiendo contratos y firmando...`;

            try {
                const res = await fetch('api/index.php?action=b2b_sign_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        batch_id: currentBatch.batch_id,
                        rep_legal: {
                            nombre: currentCompany.rep_legal_nombre,
                            rut: currentCompany.rep_legal_rut,
                            email: currentCompany.rep_legal_email
                        }
                    })
                });
                const json = await res.json();

                if (!json.success) {
                    alert("Error al firmar lote: " + (json.error || 'Error desconocido'));
                    return;
                }

                alert(`✅ ¡Lote Firmado Exitosamente!\n\nSe emitieron ${json.data.signed_count} contratos de trabajo con certificación legal.\nSe convocó a cada trabajador por correo para completar su firma.`);
                loadCompanyProfile(); // Actualizar tabla de historial

                // Actualizar visualmente la tabla
                currentBatch.items.forEach(item => {
                    if (item.is_valid) item.status = 'FIRMADO';
                });
                renderBatchUI(currentBatch);

            } catch (e) {
                alert("Error de comunicación con el motor de firma: " + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Exportar a Libro Digital DT
        function exportCurrentBatchDt() {
            if (!currentBatch || !currentBatch.batch_id) {
                alert("Carga un lote antes de exportar.");
                return;
            }
            window.location.href = `api/index.php?action=b2b_export_dt&batch_id=${currentBatch.batch_id}`;
        }

        // Alternar modo de emisión: Planilla dinámica vs Difusión Masiva PDF
        function switchEmisionMode(mode) {
            const btnA = document.getElementById('btnModeSpreadsheet');
            const btnB = document.getElementById('btnModeBroadcast');
            const wrapA = document.getElementById('modeSpreadsheetWrap');
            const wrapB = document.getElementById('modeBroadcastWrap');

            if (mode === 'broadcast') {
                btnA.classList.remove('active');
                btnB.classList.add('active');
                wrapA.style.display = 'none';
                wrapB.style.display = 'block';
            } else {
                btnB.classList.remove('active');
                btnA.classList.add('active');
                wrapB.style.display = 'none';
                wrapA.style.display = 'block';
            }
        }

        // ==========================================
        // FUNCIONES DE DIFUSIÓN MASIVA DE PDF ÚNICO
        // ==========================================
        let broadcastPdfFile = null;
        let broadcastPdfBase64 = null;
        let broadcastWorkers = [];

        function handleBroadcastPdfUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                alert("Por favor selecciona un archivo PDF válido.");
                return;
            }

            broadcastPdfFile = file;
            document.getElementById('broadcastPdfNameText').textContent = file.name;
            document.getElementById('broadcastPdfInfoText').textContent = `Tamaño: ${(file.size / 1024 / 1024).toFixed(2)} MB | Listo para emitir`;

            const reader = new FileReader();
            reader.onload = function(evt) {
                const base64 = evt.target.result.split(',')[1];
                broadcastPdfBase64 = base64;
                checkBroadcastReady();
            };
            reader.readAsDataURL(file);
        }

        function handleBroadcastCsvUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            Papa.parse(file, {
                header: true,
                skipEmptyLines: true,
                dynamicTyping: false,
                complete: function(results) {
                    if (!results.data || results.data.length === 0) {
                        alert("No se detectaron registros en la nómina.");
                        return;
                    }
                    parseBroadcastWorkers(results.data, file.name);
                },
                error: function(err) {
                    alert("Error leyendo nómina CSV: " + err.message);
                }
            });
        }

        function parseBroadcastWorkers(data, fileName = '') {
            broadcastWorkers = [];
            data.forEach((row, i) => {
                const nombre = row.nombre || row.NOMBRE || row.colaborador || row.trabajador || '';
                const rut = row.rut || row.RUT || row.run || '';
                const email = row.email || row.EMAIL || row.correo || '';
                const telefono = row.telefono || row.TELEFONO || row.whatsapp || row.celular || '';
                const cargo = row.cargo || row.CARGO || row.puesto || 'Colaborador';

                if (nombre.trim() && rut.trim()) {
                    broadcastWorkers.push({
                        nombre: nombre.trim(),
                        rut: rut.trim(),
                        email: email.trim(),
                        telefono: telefono.trim(),
                        cargo: cargo.trim()
                    });
                }
            });

            if (broadcastWorkers.length === 0) {
                alert("No se encontraron registros válidos con 'nombre' y 'rut'.");
                return;
            }

            document.getElementById('broadcastCsvNameText').textContent = fileName ? `Nómina: ${fileName}` : `${broadcastWorkers.length} Colaboradores cargados`;
            document.getElementById('broadcastCsvInfoText').textContent = `${broadcastWorkers.length} colaboradores listos para recibir y firmar`;

            renderBroadcastWorkersPreview();
            checkBroadcastReady();
        }

        function downloadBroadcastSampleCsv() {
            const csv = "nombre,rut,email,telefono,cargo\r\n" +
                        "Patricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Operador Técnico\r\n" +
                        "Daniela Morales Vega,16.789.012-1,daniela.morales@empresa.cl,+56987654321,Jefa de Turno\r\n" +
                        "Cristián Fuentes Parra,18.456.789-K,cristian.fuentes@empresa.cl,+56977889900,Supervisor Faena\r\n" +
                        "Marcela Silva Henríquez,19.123.456-1,marcela.silva@empresa.cl,+56966554433,Prevencionista de Riesgos\r\n";
            const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "nomina_trabajadores_difusion.csv";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function loadBroadcastSampleData() {
            const sampleData = [
                { nombre: 'Patricio Alarcón Soto', rut: '14.234.567-6', email: 'patricio.alarcon@empresa.cl', telefono: '+56991122334', cargo: 'Operador Técnico' },
                { nombre: 'Daniela Morales Vega', rut: '16.789.012-1', email: 'daniela.morales@empresa.cl', telefono: '+56987654321', cargo: 'Jefa de Turno' },
                { nombre: 'Cristián Fuentes Parra', rut: '18.456.789-K', email: 'cristian.fuentes@empresa.cl', telefono: '+56977889900', cargo: 'Supervisor Faena' },
                { nombre: 'Marcela Silva Henríquez', rut: '19.123.456-1', email: 'marcela.silva@empresa.cl', telefono: '+56966554433', cargo: 'Prevencionista de Riesgos' }
            ];
            parseBroadcastWorkers(sampleData, "Muestra Corporativa (4 trabajadores)");
        }

        function renderBroadcastWorkersPreview() {
            const tbody = document.getElementById('broadcastWorkersTbody');
            tbody.innerHTML = broadcastWorkers.map((w, idx) => `
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:6px 10px;">${idx + 1}</td>
                    <td style="padding:6px 10px; font-weight:700;">${w.nombre}</td>
                    <td style="padding:6px 10px; font-family:monospace;">${w.rut}</td>
                    <td style="padding:6px 10px; color:#64748B;">${w.email || '-'}</td>
                    <td style="padding:6px 10px; color:#64748B;">${w.telefono || '-'}</td>
                    <td style="padding:6px 10px;">${w.cargo || '-'}</td>
                </tr>
            `).join('');

            document.getElementById('broadcastTotalWorkersHeader').textContent = `${broadcastWorkers.length} Colaboradores en Nómina`;
        }

        function checkBroadcastReady() {
            const previewWrap = document.getElementById('broadcastPreviewWrap');
            if (broadcastPdfBase64 && broadcastWorkers.length > 0) {
                previewWrap.style.display = 'block';
                previewWrap.scrollIntoView({ behavior: 'smooth' });
            }
        }

        async function submitBroadcastBatch() {
            if (!broadcastPdfBase64) {
                alert("Debes cargar el archivo PDF antes de emitir.");
                return;
            }
            if (broadcastWorkers.length === 0) {
                alert("Debes cargar la nómina de colaboradores.");
                return;
            }

            const docTitle = document.getElementById('broadcastDocTitle').value.trim() || 'Documento Corporativo';
            const categoria = document.getElementById('broadcastDocCategory').value;

            // Validar saldo prepago disponible según línea FES vs FEA
            const tipoFirma = (selectedBroadcastCertType || 'FES').toUpperCase();
            const tipoLabel = tipoFirma === 'FEA' ? 'Firma Avanzada ClaveÚnica (FEA)' : 'Firma Simple (FES)';
            const saldoCol = tipoFirma === 'FEA' ? 'saldo_firmas_fea' : 'saldo_firmas_prepago';
            const saldoDisp = parseInt(currentCompany[saldoCol] || 0);
            const modalidad = (currentCompany.modalidad_cobro || 'PREPAGO').toUpperCase();

            if (modalidad === 'PREPAGO' && saldoDisp < broadcastWorkers.length) {
                alert(`Saldo insuficiente de ${tipoLabel}:\nTienes ${saldoDisp} firmas disponibles y necesitas ${broadcastWorkers.length} para esta nómina.\n\nPor favor, recarga una bolsa de firmas de ${tipoLabel} para continuar.`);
                openRechargeModal(tipoFirma === 'FEA' ? 'PACK_FEA_50' : 'PACK_FES_50');
                return;
            }

            if (!confirm(`¿Confirmas la emisión y difusión masiva con ${tipoLabel} de "${docTitle}" a ${broadcastWorkers.length} colaboradores?\n\nSe certificarán los documentos, se archivarán en el Expediente Digital de cada uno y se despacharán las convocatorias a firma.`)) {
                return;
            }

            const btn = document.getElementById('btnEmitBroadcast');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Emitiendo, certificando y despachando...`;

            try {
                const payload = {
                    company_id: currentCompany.id,
                    user_id: currentCompany.user_id,
                    titulo: docTitle,
                    categoria: categoria,
                    tipo_firma: tipoFirma,
                    pdf_base64: broadcastPdfBase64,
                    file_name: broadcastPdfFile ? broadcastPdfFile.name : 'documento.pdf',
                    workers: broadcastWorkers,
                    rep_legal: {
                        nombre: currentCompany.rep_legal_nombre,
                        rut: currentCompany.rep_legal_rut,
                        email: currentCompany.rep_legal_email
                    }
                };

                const res = await fetch('api/index.php?action=b2b_broadcast_pdf', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (!json.success) {
                    alert("Error en la emisión masiva: " + (json.error || 'Error desconocido'));
                    return;
                }

                alert(`✅ ¡Difusión Masiva Emitida con Éxito!\n\n${json.data.message}\n\nLote ID: #${json.data.batch_id}\nSe han enviado las notificaciones de firma y los expedientes digitales han sido actualizados.`);
                
                // Recargar perfil de empresa (saldo e historial)
                await loadCompanyProfile();

                // Cambiar a la pestaña de expedientes para visualizar las carpetas
                switchB2BTab('expediente', document.querySelector("button[onclick*='expediente']"));

            } catch (e) {
                alert("Error de conexión con el servidor: " + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // ==========================================
        // FUNCIONALIDADES PASO 2: PESTAÑAS, EXPEDIENTES, PLANTILLAS & WHATSAPP
        // ==========================================
        let allWorkerFolders = [];

        function switchB2BTab(tabId, btn) {
            document.querySelectorAll('.tab-btn-b2b').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.b2b-tab-pane').forEach(p => p.classList.remove('active'));

            if (btn) btn.classList.add('active');
            const target = document.getElementById('pane_' + tabId);
            if (target) target.classList.add('active');

            if (tabId === 'plantillas') loadCompanyTemplates();
            if (tabId === 'expediente') loadWorkerFolders();
            if (tabId === 'whatsapp') {
                if (currentCompany.whatsapp_phone) {
                    document.getElementById('cfgWaPhone').value = currentCompany.whatsapp_phone;
                }
                loadCompanyWhatsAppQr();
            } else {
                stopWhatsAppPolling();
            }
        }

        // Cargar plantillas propias
        async function loadCompanyTemplates() {
            if (!currentCompany || !currentCompany.id) return;
            try {
                const res = await fetch(`api/index.php?action=b2b_get_company_templates&company_id=${currentCompany.id}`);
                const json = await res.json();
                const container = document.getElementById('companyTemplatesList');
                const optGroup = document.getElementById('optgroupCustomTemplates');

                if (json.success && json.data) {
                    cachedCompanyTemplates = json.data;

                    // Actualizar el selector de la Pestaña 1
                    if (optGroup) {
                        if (cachedCompanyTemplates.length === 0) {
                            optGroup.innerHTML = `<option value="" disabled>(No has creado plantillas propias aún)</option>`;
                        } else {
                            optGroup.innerHTML = cachedCompanyTemplates.map(t => `
                                <option value="${t.id}" data-type="custom">🏢 ${t.titulo} (${t.variables ? t.variables.length : 0} variables)</option>
                            `).join('');
                        }
                    }

                    if (container) {
                        if (cachedCompanyTemplates.length > 0) {
                            container.innerHTML = cachedCompanyTemplates.map(t => `
                                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:1.25rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                        <h4 style="font-weight:800; color:var(--primary); margin:0 0 6px;">${t.titulo}</h4>
                                        <span style="font-size:0.72rem; font-weight:700; background:#E2E8F0; color:#334155; padding:2px 8px; border-radius:4px;">${t.categoria}</span>
                                    </div>
                                    <div style="font-size:0.8rem; color:#64748B; margin-bottom:10px;">
                                        Variables detectadas (${t.variables.length}):<br>
                                        ${t.variables.map(v => `<code style="background:#FFF; border:1px solid #CBD5E1; padding:1px 5px; border-radius:3px; margin:2px; display:inline-block;">{{${v}}}</code>`).join('')}
                                    </div>
                                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                                        <button type="button" class="btn-cta" style="background:#FFF; color:#334155 !important; border:1px solid #CBD5E1; padding:4px 10px; font-size:0.75rem;" onclick="downloadCustomTemplateCsv(${t.id}, '${t.titulo}', ${JSON.stringify(t.variables).replace(/"/g, '&quot;')})">
                                            <i class="fas fa-file-csv"></i> Descargar Excel Modelo
                                        </button>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            container.innerHTML = `
                                <div style="padding:2.5rem; text-align:center; color:#94A3B8; grid-column:1/-1;">
                                    <i class="fas fa-file-contract fa-3x" style="margin-bottom:0.75rem; color:#CBD5E1;"></i>
                                    <p style="margin:0; font-size:0.95rem;">Aún no has registrado plantillas propias para tu empresa.</p>
                                    <button type="button" class="btn-cta btn-gold" style="margin-top:1rem; font-size:0.85rem;" onclick="openTemplateEditor()">
                                        <i class="fas fa-plus"></i> Crear Mi Primera Plantilla
                                    </button>
                                </div>
                            `;
                        }
                    }
                }
            } catch (e) {
                console.error("Error al cargar plantillas:", e);
            }
        }

        function openTemplateEditor() {
            document.getElementById('templateModal').style.display = 'flex';
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        async function saveCustomTemplate(e) {
            e.preventDefault();
            const titulo = document.getElementById('tplTitleInput').value.trim();
            const cuerpo = document.getElementById('tplBodyInput').value.trim();
            const categoria = document.getElementById('tplCategorySelect').value;

            try {
                const res = await fetch('api/index.php?action=b2b_save_company_template', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company_id: currentCompany.id,
                        titulo: titulo,
                        cuerpo_texto: cuerpo,
                        categoria: categoria
                    })
                });
                const json = await res.json();
                if (json.success) {
                    alert(`✅ Plantilla Guardada Exitosamente.\nSe detectaron ${json.data.total_variables} variables dinámicas:\n` + json.data.variables.map(v => '• {{' + v + '}}').join('\n'));
                    closeTemplateModal();
                    loadCompanyTemplates();
                } else {
                    alert("Error: " + json.error);
                }
            } catch (e) {
                alert("Error al guardar: " + e.message);
            }
        }

        function downloadCustomTemplateCsv(id, title, vars) {
            const defaultCols = vars.length > 0 ? vars : ['nombre', 'rut', 'email', 'telefono', 'cargo', 'sueldo'];
            const header = defaultCols.join(',') + "\n";
            const sampleRow = defaultCols.map(v => 'Ejemplo_' + v).join(',') + "\n";
            const blob = new Blob(["\uFEFF" + header + sampleRow], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `plantilla_${title.toLowerCase().replace(/\s+/g, '_')}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Cargar expedientes digitales
        async function loadWorkerFolders() {
            if (!currentCompany || !currentCompany.id) return;
            try {
                const res = await fetch(`api/index.php?action=b2b_get_worker_folders&company_id=${currentCompany.id}`);
                const json = await res.json();
                const tbody = document.getElementById('workerFoldersTableBody');

                if (json.success && json.data) {
                    allWorkerFolders = json.data;
                    renderWorkerFolders(allWorkerFolders);
                }
            } catch (e) {
                console.error("Error al cargar expedientes:", e);
            }
        }

        function renderWorkerFolders(folders) {
            const tbody = document.getElementById('workerFoldersTableBody');
            if (!folders || folders.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="padding:2rem; text-align:center; color:#94A3B8;">No hay trabajadores registrados en el expediente digital todavía. Se crearán automáticamente con cada emisión de contrato.</td></tr>`;
                return;
            }

            tbody.innerHTML = folders.map(f => `
                <tr style="border-bottom: 1px solid #E2E8F0;">
                    <td style="padding:0.75rem; font-weight:700; color:var(--primary);">${f.colaborador_nombre}</td>
                    <td style="padding:0.75rem; font-family:monospace;">${f.colaborador_rut}</td>
                    <td style="padding:0.75rem;">${f.cargo_actual || 'Operador'}</td>
                    <td style="padding:0.75rem;">
                        <span style="background:#EFF6FF; color:#1D4ED8; font-weight:800; font-size:0.78rem; padding:2px 8px; border-radius:10px;">
                            ${f.total_documentos || 1} doc(s)
                        </span>
                    </td>
                    <td style="padding:0.75rem;">
                        <span style="background:#DCFCE7; color:#15803D; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">
                            ${f.estado_laboral || 'ACTIVO'}
                        </span>
                    </td>
                    <td style="padding:0.75rem; text-align:right;">
                        <button type="button" class="btn-cta btn-gold" style="padding:4px 10px; font-size:0.75rem;" onclick="viewWorkerFolderDetail(${f.id})">
                            <i class="fas fa-folder-open"></i> Ver Expediente
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function filterWorkerFolders() {
            const q = document.getElementById('workerSearchInput').value.toLowerCase().trim();
            const filtered = allWorkerFolders.filter(f => 
                (f.colaborador_nombre && f.colaborador_nombre.toLowerCase().includes(q)) ||
                (f.colaborador_rut && f.colaborador_rut.toLowerCase().includes(q))
            );
            renderWorkerFolders(filtered);
        }

        async function viewWorkerFolderDetail(folderId) {
            try {
                const res = await fetch(`api/index.php?action=b2b_get_worker_folder_detail&folder_id=${folderId}`);
                const json = await res.json();
                if (json.success && json.data) {
                    const f = json.data.folder;
                    const docs = json.data.documents || [];

                    document.getElementById('workerModalName').textContent = f.colaborador_nombre;
                    document.getElementById('workerModalRut').textContent = `RUT: ${f.colaborador_rut} | Cargo: ${f.cargo_actual || 'Colaborador'} | Correo: ${f.colaborador_email || '-'}`;

                    const container = document.getElementById('workerDocsContainer');
                    if (docs.length === 0) {
                        container.innerHTML = `<p style="color:#94A3B8; font-size:0.85rem;">No hay documentos registrados aún.</p>`;
                    } else {
                        container.innerHTML = docs.map(d => `
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:700; color:var(--primary); font-size:0.9rem;">${d.titulo}</div>
                                    <div style="font-size:0.78rem; color:#64748B;">
                                        Tipo: <strong>${d.tipo_documento}</strong> | Fecha: ${d.fecha_emision || d.created_at} | Estado: <span style="color:#15803D; font-weight:700;">${d.estado_firma}</span>
                                    </div>
                                    ${d.pdf_sha256 ? `<div style="font-size:0.7rem; font-family:monospace; color:#94A3B8;">Hash: ${d.pdf_sha256.substring(0,24)}...</div>` : ''}
                                </div>
                                <div>
                                    ${d.codigo_verificacion ? `
                                        <a href="verificar.php?code=${d.codigo_verificacion}" target="_blank" class="btn-cta" style="background:#FFF; color:#334155 !important; border:1px solid #CBD5E1; padding:4px 8px; font-size:0.75rem;">
                                            <i class="fas fa-qrcode"></i> Verificar
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('');
                    }

                    document.getElementById('workerDetailModal').style.display = 'flex';
                }
            } catch (e) {
                alert("Error al cargar expediente: " + e.message);
            }
        }

        function closeWorkerDetailModal() {
            document.getElementById('workerDetailModal').style.display = 'none';
        }

        let whatsappQrPollingTimer = null;

        // Cargar QR y estado de WhatsApp
        async function loadCompanyWhatsAppQr() {
            if (!currentCompany || !currentCompany.id) return;

            const qrLoading = document.getElementById('waQrLoading');
            const imgQr = document.getElementById('imgWaQrCode');
            const connectedNotice = document.getElementById('waConnectedNotice');
            const statusBadge = document.getElementById('waStatusBadgeHeader');
            const btnDisconnect = document.getElementById('btnDisconnectWa');

            if (qrLoading) {
                qrLoading.style.display = 'block';
                qrLoading.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary); margin-bottom:0.75rem;"></i><br>Generando enlace dinámico con WhatsApp...';
            }
            if (imgQr) imgQr.style.display = 'none';
            if (connectedNotice) connectedNotice.style.display = 'none';

            try {
                const res = await fetch(`api/index.php?action=b2b_get_whatsapp_qr&company_id=${currentCompany.id}`);
                const json = await res.json();

                if (json.success && json.data) {
                    const data = json.data;
                    document.getElementById('cfgWaInstance').value = data.instance || data.instance_name || '';

                    if (data.connected || data.state === 'open') {
                        // Ya está conectado
                        showWhatsAppConnectedState(data.instance);
                    } else if (data.base64) {
                        // QR disponible para escaneo
                        const qrSrc = data.base64.startsWith('data:') ? data.base64 : `data:image/png;base64,${data.base64}`;
                        if (imgQr) {
                            imgQr.src = qrSrc;
                            imgQr.style.display = 'block';
                        }
                        if (qrLoading) qrLoading.style.display = 'none';
                        if (statusBadge) {
                            statusBadge.innerHTML = `<span style="background:#FEF3C7; color:#B45309; font-weight:800; font-size:0.82rem; padding:6px 14px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-qrcode"></i> Esperando escaneo QR
                            </span>`;
                        }
                        if (btnDisconnect) btnDisconnect.style.display = 'none';

                        // Iniciar sondeo inteligente de conexión (polling cada 3 segundos)
                        startWhatsAppPolling();
                    } else {
                        if (qrLoading) {
                            qrLoading.innerHTML = `<span style="color:#D97706;"><i class="fas fa-exclamation-circle fa-2x"></i><br>Instancia inicializando en el gateway. Reintentando...</span>`;
                        }
                        setTimeout(loadCompanyWhatsAppQr, 4000);
                    }
                } else {
                    if (qrLoading) {
                        qrLoading.innerHTML = `<span style="color:#DC2626;"><i class="fas fa-times-circle fa-2x"></i><br>Error: ${json.error || 'No fue posible contactar Evolution API'}</span>`;
                    }
                }
            } catch (e) {
                if (qrLoading) {
                    qrLoading.innerHTML = `<span style="color:#DC2626;"><i class="fas fa-times-circle fa-2x"></i><br>Error de comunicación: ${e.message}</span>`;
                }
            }
        }

        function showWhatsAppConnectedState(instanceName) {
            stopWhatsAppPolling();
            const qrLoading = document.getElementById('waQrLoading');
            const imgQr = document.getElementById('imgWaQrCode');
            const connectedNotice = document.getElementById('waConnectedNotice');
            const statusBadge = document.getElementById('waStatusBadgeHeader');
            const btnDisconnect = document.getElementById('btnDisconnectWa');

            if (qrLoading) qrLoading.style.display = 'none';
            if (imgQr) imgQr.style.display = 'none';
            if (connectedNotice) connectedNotice.style.display = 'block';
            if (btnDisconnect) btnDisconnect.style.display = 'inline-block';

            if (statusBadge) {
                statusBadge.innerHTML = `<span style="background:#DCFCE7; color:#15803D; font-weight:800; font-size:0.82rem; padding:6px 14px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-check-circle"></i> Conectado en Vivo (Baileys)
                </span>`;
            }
        }

        function startWhatsAppPolling() {
            stopWhatsAppPolling();
            whatsappQrPollingTimer = setInterval(async () => {
                if (!currentCompany || !currentCompany.id) return;
                try {
                    const res = await fetch(`api/index.php?action=b2b_get_whatsapp_status&company_id=${currentCompany.id}`);
                    const json = await res.json();
                    if (json.success && json.data) {
                        if (json.data.connected || json.data.state === 'open') {
                            showWhatsAppConnectedState(json.data.instance);
                        }
                    }
                } catch (e) {
                    console.log("Polling WhatsApp:", e);
                }
            }, 3000);
        }

        function stopWhatsAppPolling() {
            if (whatsappQrPollingTimer) {
                clearInterval(whatsappQrPollingTimer);
                whatsappQrPollingTimer = null;
            }
        }

        async function disconnectCompanyWhatsApp() {
            if (!confirm("¿Seguro que deseas desvincular el WhatsApp corporativo de esta empresa? Los próximos contratos deberán enviarse por correo hasta que vuelvas a escanear el QR.")) {
                return;
            }

            try {
                const res = await fetch('api/index.php?action=b2b_disconnect_whatsapp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ company_id: currentCompany.id })
                });
                const json = await res.json();
                if (json.success) {
                    alert("Instancia desconectada con éxito.");
                    loadCompanyWhatsAppQr();
                } else {
                    alert("Error: " + json.error);
                }
            } catch (e) {
                alert("Error: " + e.message);
            }
        }

        async function saveCompanyWhatsApp(e) {
            e.preventDefault();
            const phone = document.getElementById('cfgWaPhone').value.trim();
            const instance = document.getElementById('cfgWaInstance').value.trim();

            try {
                const res = await fetch('api/index.php?action=b2b_update_whatsapp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company_id: currentCompany.id,
                        whatsapp_phone: phone,
                        whatsapp_instance_id: instance
                    })
                });
                const json = await res.json();
                if (json.success) {
                    alert("✅ " + json.data.message);
                    currentCompany.whatsapp_phone = phone;
                } else {
                    alert("Error: " + json.error);
                }
            } catch (e) {
                alert("Error al guardar WhatsApp: " + e.message);
            }
        }

        // Renderizar historial de lotes
        function renderBatchesHistory(batches) {
            const tbody = document.getElementById('batchesHistoryBody');
            if (!batches || batches.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: #94A3B8;">Aún no se han procesado lotes corporativos.</td></tr>`;
                return;
            }

            tbody.innerHTML = batches.map(b => {
                const isSim = parseInt(b.is_simulation || 0) === 1;
                return `
                <tr style="border-bottom: 1px solid #E2E8F0; background: ${isSim ? '#FFFBEB' : '#FFFFFF'};">
                    <td style="padding:0.75rem; font-weight:700;">#${b.id}</td>
                    <td style="padding:0.75rem; font-weight:600;">
                        ${b.titulo}
                        ${isSim ? `<span style="background:#FEF3C7; color:#B45309; font-size:0.7rem; font-weight:800; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fas fa-flask"></i> PILOTO / NO OFICIAL</span>` : ''}
                    </td>
                    <td style="padding:0.75rem; color:#64748B;">${b.created_at}</td>
                    <td style="padding:0.75rem;">
                        <span style="font-weight:700; color:var(--primary);">${b.valid_records}</span> / ${b.total_records}
                    </td>
                    <td style="padding:0.75rem;">
                        <span style="background:${b.status === 'EN_FIRMA' ? '#DCFCE7' : '#F1F5F9'}; color:${b.status === 'EN_FIRMA' ? '#15803D' : '#475569'}; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:4px;">
                            ${b.status}
                        </span>
                    </td>
                    <td style="padding:0.75rem; text-align:right; white-space:nowrap;">
                        ${!isSim ? `
                        <a href="api/index.php?action=b2b_export_dt&batch_id=${b.id}" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; padding:4px 10px; font-size:0.75rem; margin-right:4px;">
                            <i class="fas fa-file-csv"></i> Descargar DT
                        </a>` : ''}
                        <button type="button" class="btn-cta" style="background:#FEE2E2; color:#B91C1C !important; padding:4px 10px; font-size:0.75rem; border:none;" onclick="deleteBatchConfirm(${b.id}, ${isSim})" title="Eliminar registro">
                            <i class="fas fa-trash-alt"></i> ${isSim ? 'Limpiar Prueba' : 'Eliminar'}
                        </button>
                    </td>
                </tr>
            `}).join('');
        }

        async function deleteBatchConfirm(batchId, isSim) {
            const promptText = isSim 
                ? `¿Deseas eliminar este lote piloto de prueba (#${batchId}) para mantener tu historial completamente limpio?`
                : `¿Confirmas que deseas eliminar el lote #${batchId}?`;
            if (!confirm(promptText)) return;

            try {
                const res = await fetch('api/index.php?action=b2b_delete_batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: batchId, company_id: currentCompany.id })
                });
                const json = await res.json();
                if (json.success) {
                    alert("✅ Lote eliminado exitosamente.");
                    loadCompanyProfile();
                } else {
                    alert("Error: " + (json.error || 'No se pudo eliminar el lote'));
                }
            } catch (e) {
                alert("Error de comunicación: " + e.message);
            }
        }
    </script>
</body>
</html>
