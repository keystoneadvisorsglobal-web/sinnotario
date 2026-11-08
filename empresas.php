<?php
/**
 * SinNotario.cl - Suite B2B Empresas & Carga Masiva Excel
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Services/AuthService.php';

$currentUser = AuthService::getCurrentUser();
if (!$currentUser) {
    // Si no ha iniciado sesión, redirigir al portal de acceso institucional
    header('Location: login.php?redirect=' . urlencode('empresas.php') . '&msg=b2b_auth_required');
    exit;
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
    <link rel="stylesheet" href="assets/css/style.css">
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
                <li><a href="admin.php">Consola Operativa</a></li>
            </ul>
            <div>
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
                    <div style="font-size: 0.85rem; color: #64748B;">
                        RUT: <strong id="dispRutEmpresa">78.089.686-8</strong> | Tarifa por Firma: <strong id="dispTarifaTramo">$2.490 CLP</strong>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <!-- Widget Financiero: Bolsa Prepago o Facturación Postpago -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.5rem 1rem; text-align: right;" id="widgetFinanciero">
                    <div style="font-size: 0.72rem; color: #64748B; font-weight: 700; text-transform: uppercase;" id="lblSaldoModalidad">BOLSA PREPAGADA ACTIVA</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #15803D;" id="valSaldoModalidad">
                        <span id="dispSaldoPrepago">50</span> firmas disponibles
                    </div>
                    <div style="font-size: 0.72rem; color: #64748B;" id="subSaldoModalidad">Total histórico emitido: <strong id="dispHistoricoUsadas">0</strong></div>
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

        <!-- Banner de Selección de Bolsas con Descuento -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                <div>
                    <span style="font-size:0.75rem; font-weight:800; color:#2563EB; background:#EFF6FF; padding:2px 8px; border-radius:4px;">PACK 50 FIRMAS</span>
                    <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$3.490 <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                    <p style="margin:0; font-size:0.78rem; color:#64748B;">Ahorro 30% vs tarifa estándar ($174.500 CLP)</p>
                </div>
                <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_50')">
                    Recargar
                </button>
            </div>

            <div style="background:#FFFFFF; border:2px solid #D4AF37; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm); position:relative;">
                <span style="position:absolute; top:-10px; right:15px; background:#D4AF37; color:#0A1C33; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:10px;">MÁS POPULAR</span>
                <div>
                    <span style="font-size:0.75rem; font-weight:800; color:#B45309; background:#FEF3C7; padding:2px 8px; border-radius:4px;">PACK 200 FIRMAS</span>
                    <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$2.790 <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                    <p style="margin:0; font-size:0.78rem; color:#64748B;">Ahorro 45% vs tarifa estándar ($558.000 CLP)</p>
                </div>
                <button type="button" class="btn-cta btn-gold" style="padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_200')">
                    Recargar
                </button>
            </div>

            <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-sm);">
                <div>
                    <span style="font-size:0.75rem; font-weight:800; color:#15803D; background:#DCFCE7; padding:2px 8px; border-radius:4px;">PACK 500+ ENTERPRISE</span>
                    <h4 style="margin:4px 0; font-size:1.15rem; color:var(--primary); font-weight:800;">$1.990 <span style="font-size:0.8rem; font-weight:400; color:#64748B;">+ IVA / firma</span></h4>
                    <p style="margin:0; font-size:0.78rem; color:#64748B;">Tarifa mínima garantizada ($995.000 CLP)</p>
                </div>
                <button type="button" class="btn-cta" style="background:#0F2A4A; color:#FFF !important; padding:6px 12px; font-size:0.82rem;" onclick="rechargePack('PACK_500')">
                    Recargar
                </button>
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
            <!-- Zona de Carga Masiva Drag-and-Drop -->
            <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('excelFileInput').click()">
                <input type="file" id="excelFileInput" accept=".csv, .txt, .xlsx" style="display: none;" onchange="handleExcelUpload(event)">
                <i class="fas fa-file-excel fa-3x" style="color: #10B981; margin-bottom: 1rem;"></i>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">Arrastra tu planilla CSV/Excel o haz clic para cargar</h3>
                <p style="color: #64748B; font-size: 0.88rem; margin-top: 0.35rem;">
                    Formatos admitidos: <code>.csv</code> (separado por coma o punto y coma). Columnas recomendadas: <code>nombre</code>, <code>rut</code>, <code>email</code>, <code>telefono</code>, <code>cargo</code>, <code>sueldo</code>.
                </p>
                <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem;">
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1;" onclick="event.stopPropagation(); downloadSampleCsv();">
                        <i class="fas fa-download"></i> Descargar Planilla Modelo (.csv)
                    </button>
                    <button type="button" class="btn-cta btn-gold" onclick="event.stopPropagation(); loadSampleData();">
                        <i class="fas fa-magic"></i> Cargar Nómina de Muestra
                    </button>
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
            <div style="background:#FFFFFF; border:1px solid var(--border); border-radius:12px; padding:2rem; box-shadow:var(--shadow-sm); max-width:800px; margin:0 auto;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
                    <div style="width:54px; height:54px; border-radius:12px; background:#DCFCE7; color:#15803D; display:flex; align-items:center; justify-content:center; font-size:1.8rem;">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h3 style="font-size:1.3rem; font-weight:800; color:var(--primary); margin:0;">WhatsApp Oficial de tu Propia Empresa</h3>
                        <p style="color:#64748B; font-size:0.88rem; margin:4px 0 0;">
                            Tus trabajadores reciben los enlaces de firma desde el número oficial de tu empresa vía Evolution API v2, generando 98% de tasa de apertura.
                        </p>
                    </div>
                </div>

                <form onsubmit="saveCompanyWhatsApp(event)">
                    <div style="margin-bottom:1.25rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Número de WhatsApp Oficial (con código de país)</label>
                        <input type="text" id="cfgWaPhone" class="form-control" style="width:100%; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px;" placeholder="+56912345678" value="+56984180479">
                        <small style="color:#64748B;">Número corporativo que verán tus trabajadores al recibir la notificación de firma.</small>
                    </div>

                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:4px;">Identificador de Instancia Dedicada</label>
                        <input type="text" id="cfgWaInstance" class="form-control" style="width:100%; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px; font-family:monospace;" placeholder="sinnotario-empresa-instance" readonly>
                        <small style="color:#64748B;">Canal seguro aprovisionado en alta disponibilidad bajo cifrado TLS.</small>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:#15803D; font-weight:700;">
                            <i class="fas fa-check-circle"></i> Instancia Evolution API Activa y Homologada
                        </span>
                        <button type="submit" class="btn-cta btn-gold">
                            <i class="fas fa-save"></i> Guardar Configuración WhatsApp
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
            document.getElementById('dispTarifaTramo').textContent = '$' + Number(currentCompany.tarifa_tramo_clp || 2490).toLocaleString('es-CL') + ' CLP';
            document.getElementById('dispPlanEstado').textContent = currentCompany.plan_estado || 'ACTIVO';
            
            const modalidad = currentCompany.modalidad_cobro || 'PREPAGO';
            document.getElementById('dispModalidadCobro').textContent = modalidad;

            if (modalidad === 'PREPAGO') {
                document.getElementById('lblSaldoModalidad').textContent = 'BOLSA PREPAGADA ACTIVA';
                const saldo = parseInt(currentCompany.saldo_firmas_prepago || 0);
                document.getElementById('valSaldoModalidad').innerHTML = `<span id="dispSaldoPrepago" style="color:${saldo < 10 ? '#DC2626' : '#15803D'};">${saldo}</span> firmas disponibles`;
                document.getElementById('subSaldoModalidad').innerHTML = `Total firmas consumidas: <strong>${currentCompany.firmas_utilizadas || 0}</strong>`;
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
        }

        async function rechargePack(packType) {
            if (!currentCompany || !currentCompany.id) {
                alert("Identificando empresa...");
                return;
            }

            if (!confirm(`¿Confirmas la activación y compra de la bolsa ${packType} para ${currentCompany.razon_social}?`)) {
                return;
            }

            try {
                const res = await fetch('api/index.php?action=b2b_recharge_bag', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company_id: currentCompany.id,
                        pack_type: packType
                    })
                });
                const json = await res.json();
                if (json.success) {
                    alert(`✅ ¡Recarga Exitosa!\n\n${json.data.message}\nNuevo Saldo: ${json.data.saldo_nuevo} firmas.`);
                    loadCompanyProfile();
                } else {
                    alert("Error en recarga: " + json.error);
                }
            } catch (e) {
                alert("Error de comunicación: " + e.message);
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

        // Descarga de CSV modelo
        function downloadSampleCsv() {
            const csvContent = "nombre,rut,email,telefono,cargo,sueldo\n" +
                "Patricio Alarcón Soto,14.234.567-6,patricio.alarcon@empresa.cl,+56991122334,Operador Técnico,750000\n" +
                "Daniela Morales Vega,16.789.012-1,daniela.morales@empresa.cl,+56987654321,Jefa de Turno,1200000\n" +
                "Cristián Fuentes Parra,18.456.789-K,cristian.fuentes@empresa.cl,+56977889900,Supervisor Faena,950000\n" +
                "Marcela Silva Henríquez,19.123.456-1,marcela.silva@empresa.cl,+56966554433,Prevencionista de Riesgos,1100000\n";
            
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "planilla_modelo_nomina_sinnotario.csv";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Carga de muestra automática
        function loadSampleData() {
            const sampleRows = [
                { nombre: 'Patricio Alarcón Soto', rut: '14.234.567-6', email: 'patricio.alarcon@empresa.cl', telefono: '+56991122334', cargo: 'Operador Técnico', sueldo: '750.000' },
                { nombre: 'Daniela Morales Vega', rut: '16.789.012-1', email: 'daniela.morales@empresa.cl', telefono: '+56987654321', cargo: 'Jefa de Turno', sueldo: '1.200.000' },
                { nombre: 'Cristián Fuentes Parra', rut: '18.456.789-K', email: 'cristian.fuentes@empresa.cl', telefono: '+56977889900', cargo: 'Supervisor Faena', sueldo: '950.000' },
                { nombre: 'Marcela Silva Henríquez', rut: '19.123.456-1', email: 'marcela.silva@empresa.cl', telefono: '+56966554433', cargo: 'Prevencionista de Riesgos', sueldo: '1.100.000' }
            ];
            uploadBatchToServer("Lote Nómina Faena - " + new Date().toLocaleDateString('es-CL'), sampleRows);
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
                document.getElementById('batchStatusFooter').textContent = 'Procesando lote y validando RUTs en el servidor...';
                const payload = {
                    titulo: titulo,
                    template_slug: 'contrato-individual-trabajo',
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

            if (!confirm(`¿Confirmas la firma masiva en 1 Clic para los ${currentBatch.valid_records} contratos válidos de este lote a nombre de ${currentCompany.razon_social}?`)) {
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
                document.getElementById('cfgWaPhone').value = currentCompany.whatsapp_phone || '+56984180479';
                document.getElementById('cfgWaInstance').value = currentCompany.whatsapp_instance_id || 'sinnotario-b2b-' + currentCompany.id;
            }
        }

        // Cargar plantillas propias
        async function loadCompanyTemplates() {
            if (!currentCompany || !currentCompany.id) return;
            try {
                const res = await fetch(`api/index.php?action=b2b_get_company_templates&company_id=${currentCompany.id}`);
                const json = await res.json();
                const container = document.getElementById('companyTemplatesList');

                if (json.success && json.data && json.data.length > 0) {
                    container.innerHTML = json.data.map(t => `
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

            tbody.innerHTML = batches.map(b => `
                <tr style="border-bottom: 1px solid #E2E8F0;">
                    <td style="padding:0.75rem; font-weight:700;">#${b.id}</td>
                    <td style="padding:0.75rem; font-weight:600;">${b.titulo}</td>
                    <td style="padding:0.75rem; color:#64748B;">${b.created_at}</td>
                    <td style="padding:0.75rem;">
                        <span style="font-weight:700; color:var(--primary);">${b.valid_records}</span> / ${b.total_records}
                    </td>
                    <td style="padding:0.75rem;">
                        <span style="background:${b.status === 'EN_FIRMA' ? '#DCFCE7' : '#F1F5F9'}; color:${b.status === 'EN_FIRMA' ? '#15803D' : '#475569'}; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:4px;">
                            ${b.status}
                        </span>
                    </td>
                    <td style="padding:0.75rem; text-align:right;">
                        <a href="api/index.php?action=b2b_export_dt&batch_id=${b.id}" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; padding:4px 10px; font-size:0.75rem;">
                            <i class="fas fa-file-csv"></i> Descargar DT
                        </a>
                    </td>
                </tr>
            `).join('');
        }
    </script>
</body>
</html>
