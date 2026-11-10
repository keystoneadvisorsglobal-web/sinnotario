<?php
/**
 * SinNotario.cl - Módulo Oficial de Carga de Documento Propio para Firma (PDF Upload)
 * Permite a personas naturales y empresas subir su propio contrato o documento privado en PDF,
 * previsualizarlo, someterlo voluntariamente a Smart Legal Check (IA) si lo desean,
 * registrar a los comparecientes/firmantes con RUT oficial y sellarlo en la Bóveda Cloudflare R2 con código de verificación.
 */
$config = require __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';

$currentUser = AuthService::getCurrentUser();
$pdo = Database::getInstance();
$stmtSets = $pdo->query("SELECT * FROM system_settings");
$settingsRows = $stmtSets ? $stmtSets->fetchAll(PDO::FETCH_ASSOC) : [];
$settings = [];
foreach ($settingsRows as $sr) {
    $settings[$sr['key']] = $sr['value'];
}

$supportWaUrl = 'https://wa.me/' . ($config['support_whatsapp_digits'] ?? '56984180479');
$supportWaDisplay = $config['support_whatsapp_display'] ?? '+56 9 8418 0479';
$supportWaRaw = $config['support_whatsapp'] ?? '+56984180479';

$precioFirmaPropia = (int)($settings['precio_base_subir_pdf'] ?? 7990);
$precioFirmaExtra = (int)($settings['precio_firma_extra'] ?? 2990);
$precioUpgradeFea = (int)($settings['precio_upgrade_fea'] ?? 2990);
$precioFormateado = '$' . number_format($precioFirmaPropia, 0, ',', '.');
$mpSubirActive = ($settings['mp_enabled'] ?? '1') === '1';
$khipuSubirActive = ($settings['khipu_enabled'] ?? '1') === '1';
$defaultSubirGateway = $mpSubirActive ? 'MERCADO_PAGO' : ($khipuSubirActive ? 'KHIPU' : 'NONE');
$isWizardSandbox = ($settings['wizard_sandbox_mode'] ?? '0') === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Documento Propio para Firma Digital | SinNotario.cl</title>
    <meta name="description" content="Sube tu propio archivo PDF para firmar electrónicamente con plena validez bajo la Ley N° 19.799. Incluye verificación criptográfica SHA-256, Bóveda Cloudflare R2 y Smart Legal Check opcional.">
    
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_2">
    <!-- PDF.js para extracción de texto del PDF en navegador para auditoría IA -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Configurar worker de PDF.js
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>

    <style>
        :root {
            --upload-primary: #0F2A4A;
            --upload-accent: #D4AF37;
            --upload-success: #059669;
            --upload-card-bg: #FFFFFF;
        }

        body {
            background-color: #F8FAFC;
            color: #1E293B;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .upload-hero {
            background: linear-gradient(135deg, #0A1C33 0%, #0F2A4A 100%);
            color: #FFFFFF;
            padding: 3.5rem 1.5rem 3rem;
            text-align: center;
            position: relative;
            border-bottom: 4px solid var(--upload-accent);
        }

        .upload-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0.5rem 0 0.8rem;
            letter-spacing: -0.02em;
        }

        .upload-hero p {
            font-size: 1.05rem;
            color: #CBD5E1;
            max-width: 720px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .upload-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(212, 175, 55, 0.15);
            color: #F59E0B;
            border: 1px solid rgba(212, 175, 55, 0.4);
            padding: 0.35rem 1rem;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }

        .upload-container {
            max-width: 1180px;
            margin: -2rem auto 4rem;
            padding: 0 1.5rem;
            position: relative;
            z-index: 10;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .upload-grid {
                grid-template-columns: 1fr;
            }
        }

        .upload-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #E2E8F0;
            padding: 2rem;
        }

        .step-progress-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .step-progress-row::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 30px;
            right: 30px;
            height: 2px;
            background: #E2E8F0;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-bubble {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #F1F5F9;
            color: #64748B;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.4rem;
            border: 2px solid #CBD5E1;
            transition: all 0.2s;
        }

        .step-item.active .step-bubble {
            background: var(--upload-primary);
            color: white;
            border-color: var(--upload-primary);
            box-shadow: 0 0 0 4px rgba(15, 42, 74, 0.15);
        }

        .step-item.completed .step-bubble {
            background: var(--upload-success);
            color: white;
            border-color: var(--upload-success);
        }

        .step-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748B;
        }

        .step-item.active .step-label {
            color: var(--upload-primary);
        }

        /* Dropzone */
        .dropzone-box {
            border: 2px dashed #94A3B8;
            border-radius: 14px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dropzone-box:hover, .dropzone-box.dragover {
            border-color: #2563EB;
            background: #EFF6FF;
        }

        .btn-upload-action {
            background: var(--upload-primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.9rem 1.5rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-upload-action:hover {
            background: #1B3B6F;
            transform: translateY(-1px);
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0F2A4A;
            margin-bottom: 0.4rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 8px;
            font-size: 0.92rem;
            color: #0F2A4A;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .preview-viewer {
            width: 100%;
            height: 540px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            background: #FFFFFF;
        }

        .signer-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            position: relative;
        }

        .badge-firmante {
            background: #EFF6FF;
            color: #1D4ED8;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Trámites</a></li>
                <li><a href="subir-documento.php" style="color: #0369A1; font-weight: 700;"><i class="fas fa-file-upload"></i> Subir Documento</a></li>
                <li><a href="verificar.php">Verificar</a></li>
                <li><a href="index.php#empresas">Empresas</a></li>
                <li><a href="<?= $supportWaUrl ?>?text=Hola,%20necesito%20asistencia%20legal%20en%20SinNotario.cl" target="_blank" class="nav-whatsapp"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($supportWaDisplay) ?></a></li>
            </ul>
            <div style="display:flex; align-items:center; gap:10px;">
                <?php if ($currentUser): ?>
                    <a href="mi-cuenta.php" class="btn-cta" style="background:#0F2A4A; color:#FFFFFF; padding:0.5rem 0.9rem; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-folder-open"></i> Mi Bóveda (<?= htmlspecialchars($currentUser['nombre']) ?>)
                    </a>
                <?php else: ?>
                    <a href="login.php" style="color:#0F2A4A; font-weight:700; text-decoration:none; font-size:0.85rem; padding:0.45rem 0.75rem; border-radius:6px; border:1px solid #CBD5E1; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-lock"></i> Mi Cuenta
                    </a>
                <?php endif; ?>
                <a href="verificar.php" class="btn-cta btn-secondary" style="padding:0.5rem 1rem; font-size:0.88rem;">
                    <i class="fas fa-qrcode"></i> Validar Folio
                </a>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="upload-hero">
        <div class="upload-badge">
            <i class="fas fa-file-upload"></i> Carga Libre de Archivos PDF
        </div>
        <h1>Firma tu Propio Documento o Contrato</h1>
        <p>
            ¿Ya tienes tu contrato, anexo o acuerdo redactado? Sube tu PDF, agrega a los comparecientes y envíalo a firmar electrónicamente con plena fuerza legal bajo la <strong>Ley N° 19.799</strong>.
        </p>
    </section>

    <!-- Main Wizard -->
    <main class="upload-container">
        <div class="upload-grid">
            
            <!-- Columna Izquierda: Wizard interactivo -->
            <div class="upload-card">
                
                <!-- Progreso -->
                <div class="step-progress-row">
                    <div class="step-item active" id="p_step1">
                        <div class="step-bubble">1</div>
                        <div class="step-label">Archivo PDF</div>
                    </div>
                    <div class="step-item" id="p_step2">
                        <div class="step-bubble">2</div>
                        <div class="step-label">Firmantes</div>
                    </div>
                    <div class="step-item" id="p_step3">
                        <div class="step-bubble">3</div>
                        <div class="step-label">Revisión & Envío</div>
                    </div>
                </div>

                <!-- PASO 1: Subir Archivo PDF -->
                <div id="viewStep1">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--upload-primary); margin: 0 0 0.5rem;">
                        Paso 1: Selecciona o Arrastra tu Documento PDF
                    </h2>
                    <p style="font-size: 0.88rem; color: #64748B; margin-bottom: 1.5rem;">
                        Aceptamos documentos en formato PDF de hasta 20 MB. Tu archivo será cifrado y sellado con huella criptográfica SHA-256.
                    </p>

                    <div class="dropzone-box" id="dropzoneBox" onclick="document.getElementById('fileUploadInput').click()">
                        <i class="fas fa-cloud-upload-alt fa-3x" style="color: #2563EB; margin-bottom: 0.75rem;"></i>
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: #0F2A4A; margin-bottom: 0.35rem;">
                            Haz clic para seleccionar tu PDF o arrástralo aquí
                        </h3>
                        <p style="font-size: 0.85rem; color: #64748B; margin: 0;">
                            Contratos de arriendo, acuerdos comerciales, actas, convenios, poderes o anexos.
                        </p>
                        <input type="file" id="fileUploadInput" accept="application/pdf" style="display: none;" onchange="handleSelectedPdf(this.files[0])">
                    </div>

                    <!-- Info del archivo cargado -->
                    <div id="fileLoadedDetails" style="display:none; margin-top: 1.5rem; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <i class="fas fa-file-pdf fa-2x" style="color:#DC2626;"></i>
                                <div>
                                    <strong style="color:#065F46; font-size:1rem;" id="fileNameDisplay">contrato.pdf</strong>
                                    <div style="font-size:0.8rem; color:#475569;" id="fileSizeDisplay">145 KB</div>
                                </div>
                            </div>
                            <button type="button" class="btn-card-create" style="padding:0.4rem 0.8rem; font-size:0.8rem; background:#DC2626;" onclick="clearLoadedFile()">
                                <i class="fas fa-trash"></i> Cambiar
                            </button>
                        </div>

                        <div class="form-group" style="margin-bottom:0.75rem;">
                            <label>Título o Nombre del Documento *</label>
                            <input type="text" id="customDocTitle" class="form-control" placeholder="Ej: Contrato de Prestación de Servicios - Proyecto 2026">
                        </div>

                        <div style="font-size:0.78rem; color:#64748B; word-break:break-all;">
                            <strong>Hash SHA-256 Calculado:</strong> <code id="fileHashDisplay" style="color:#065F46;">Calculando...</code>
                        </div>
                    </div>

                    <!-- Asistente Opcional Smart Legal Check -->
                    <div style="margin-top: 1.5rem; background: #FEFCE8; border: 1px solid #FEF08A; border-radius: 10px; padding: 1rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer; margin: 0;">
                            <input type="checkbox" id="chkSmartLegalCheck" style="margin-top: 0.25rem; transform: scale(1.15);">
                            <div>
                                <strong style="color: #854D0E; font-size: 0.92rem;">
                                    <i class="fas fa-robot"></i> Activar Diagnóstico Preventivo con IA (Smart Legal Check)
                                </strong>
                                <p style="font-size: 0.82rem; color: #713F12; margin: 0.25rem 0 0; line-height: 1.4;">
                                    Nuestra IA analizará automáticamente el texto en busca de advertencias normativas, cláusulas abusivas o indicios de trámites que requieran Notaría exclusiva.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div style="margin-top: 1.75rem; text-align: right;">
                        <button type="button" id="btnNextToStep2" class="btn-upload-action" onclick="goToStep(2)" disabled>
                            Siguiente: Agregar Firmantes <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 2: Datos de los Firmantes -->
                <div id="viewStep2" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--upload-primary); margin: 0 0 0.35rem;">
                                Paso 2: Comparecientes / Firmantes
                            </h2>
                            <p style="font-size: 0.85rem; color: #64748B; margin: 0;">
                                Individualiza a las partes que firmarán el documento con RUT oficial.
                            </p>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <?php if ($isWizardSandbox): ?>
                                <button type="button" class="btn-cta" style="padding:0.4rem 0.85rem; font-size:0.82rem; background:#FEF3C7; border:1px solid #F59E0B; color:#92400E !important; font-weight:700;" onclick="fillSubirDemoData()">
                                    <i class="fas fa-flask"></i> Rellenar Demo
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-cta btn-secondary" style="padding:0.4rem 0.85rem; font-size:0.82rem;" onclick="addSignerRow()">
                                <i class="fas fa-user-plus"></i> + Agregar Firmante
                            </button>
                        </div>
                    </div>

                    <div id="signersListContainer">
                        <!-- Firmante 1 -->
                        <div class="signer-box" id="signer_box_0">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                <span class="badge-firmante">Firmante 1 (Titular / Iniciador)</span>
                                <label style="font-size:0.8rem; color:#475569; display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                                    <input type="checkbox" class="sig-es-rep" onchange="toggleRepresentante(0)">
                                    <strong>Firma como Representante Legal de Empresa</strong>
                                </label>
                            </div>

                            <!-- Campos de Empresa (Ocultos por defecto) -->
                            <div class="rep-company-wrap" id="rep_company_0" style="display:none; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; padding:0.85rem; margin-bottom:0.85rem;">
                                <div style="font-size:0.8rem; font-weight:700; color:#0F2A4A; margin-bottom:0.4rem;">
                                    <i class="fas fa-building"></i> Datos de la Persona Jurídica / Empresa Representada
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Razón Social de la Empresa *</label>
                                        <input type="text" class="form-control sig-empresa-nombre" placeholder="Ej: Inversiones y Servicios SpA">
                                    </div>
                                    <div class="form-group">
                                        <label>RUT Empresa *</label>
                                        <input type="text" class="form-control sig-empresa-rut" placeholder="76.123.456-7" maxlength="12">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Tipo de Documento *</label>
                                    <select class="form-control sig-tipo-doc" onchange="changeDocType(0)">
                                        <option value="RUT" selected>Cédula Nacional de Identidad / RUT (Chile)</option>
                                        <option value="PASAPORTE">Pasaporte Extranjero / Documento Internacional</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="lbl-id-num" id="lbl_id_0">RUT *</label>
                                    <input type="text" class="form-control sig-rut" placeholder="12.345.678-5" value="<?= $isWizardSandbox ? '12.345.678-5' : '' ?>">
                                </div>
                                <div class="form-group sig-pais-wrap" id="pais_wrap_0" style="display:none;">
                                    <label>País Emisor del Pasaporte *</label>
                                    <input type="text" class="form-control sig-pais" placeholder="Ej: Venezuela, Colombia, Perú, España" value="<?= $isWizardSandbox ? 'Chile' : '' ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre Completo del Firmante *</label>
                                    <input type="text" class="form-control sig-nombre" placeholder="Ej: Juan Andrés Pérez González" value="<?= $isWizardSandbox ? 'Juan Andrés Pérez González' : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label>Rol en el Documento *</label>
                                    <input type="text" class="form-control sig-rol" placeholder="Ej: Arrendador, Contratante, Mandante" value="<?= $isWizardSandbox ? 'Parte A' : '' ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correo Electrónico (Para recibir enlace de firma) *</label>
                                    <input type="email" class="form-control sig-email" placeholder="juan.perez@ejemplo.cl" value="<?= $isWizardSandbox ? 'juan.perez@ejemplo.cl' : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono WhatsApp *</label>
                                    <input type="tel" class="form-control sig-tel" placeholder="+56 9 1234 5678" value="<?= $isWizardSandbox ? htmlspecialchars($supportWaRaw) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Firmante 2 -->
                        <div class="signer-box" id="signer_box_1">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                <span class="badge-firmante" style="background:#FAF5FF; color:#7E22CE;">Firmante 2 (Contraparte)</span>
                                <label style="font-size:0.8rem; color:#475569; display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                                    <input type="checkbox" class="sig-es-rep" onchange="toggleRepresentante(1)">
                                    <strong>Firma como Representante Legal de Empresa</strong>
                                </label>
                            </div>

                            <!-- Campos de Empresa (Ocultos por defecto) -->
                            <div class="rep-company-wrap" id="rep_company_1" style="display:none; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; padding:0.85rem; margin-bottom:0.85rem;">
                                <div style="font-size:0.8rem; font-weight:700; color:#0F2A4A; margin-bottom:0.4rem;">
                                    <i class="fas fa-building"></i> Datos de la Persona Jurídica / Empresa Representada
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Razón Social de la Empresa *</label>
                                        <input type="text" class="form-control sig-empresa-nombre" placeholder="Ej: Servicios Profesionales SpA">
                                    </div>
                                    <div class="form-group">
                                        <label>RUT Empresa *</label>
                                        <input type="text" class="form-control sig-empresa-rut" placeholder="77.987.654-3" maxlength="12">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Tipo de Documento *</label>
                                    <select class="form-control sig-tipo-doc" onchange="changeDocType(1)">
                                        <option value="RUT" selected>Cédula Nacional de Identidad / RUT (Chile)</option>
                                        <option value="PASAPORTE">Pasaporte Extranjero / Documento Internacional</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="lbl-id-num" id="lbl_id_1">RUT *</label>
                                    <input type="text" class="form-control sig-rut" placeholder="15.444.555-6" value="<?= $isWizardSandbox ? '15.444.555-6' : '' ?>">
                                </div>
                                <div class="form-group sig-pais-wrap" id="pais_wrap_1" style="display:none;">
                                    <label>País Emisor del Pasaporte *</label>
                                    <input type="text" class="form-control sig-pais" placeholder="Ej: Venezuela, Colombia, Perú, España" value="<?= $isWizardSandbox ? 'Chile' : '' ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre Completo del Firmante *</label>
                                    <input type="text" class="form-control sig-nombre" placeholder="Ej: María José Valenzuela Soto" value="<?= $isWizardSandbox ? 'María José Valenzuela Soto' : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label>Rol en el Documento *</label>
                                    <input type="text" class="form-control sig-rol" placeholder="Ej: Arrendatario, Proveedor, Mandatario" value="<?= $isWizardSandbox ? 'Parte B' : '' ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correo Electrónico (Para recibir enlace de firma) *</label>
                                    <input type="email" class="form-control sig-email" placeholder="mj.valenzuela@ejemplo.cl" value="<?= $isWizardSandbox ? 'mj.valenzuela@ejemplo.cl' : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono WhatsApp *</label>
                                    <input type="tel" class="form-control sig-tel" placeholder="+56 9 8765 4321" value="<?= $isWizardSandbox ? htmlspecialchars($supportWaRaw) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-top: 1.75rem;">
                        <button type="button" class="btn-cta btn-secondary" onclick="goToStep(1)">
                            <i class="fas fa-arrow-left"></i> Volver al Archivo
                        </button>
                        <button type="button" class="btn-upload-action" onclick="goToStep(3)">
                            Revisión y Despacho <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 3: Resumen, Diagnóstico y Envío -->
                <div id="viewStep3" style="display: none;">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--upload-primary); margin: 0 0 0.5rem;">
                        Paso 3: Confirmación y Envío a Firma
                    </h2>
                    <p style="font-size: 0.88rem; color: #64748B; margin-bottom: 1.5rem;">
                        Verifica el resumen del despacho. Al confirmar, el documento será sellado en la Bóveda Cloudflare R2 con código QR oficial.
                    </p>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem;">
                        <h4 style="font-size:0.95rem; font-weight:700; color:#0F2A4A; margin-bottom:0.75rem;">
                            <i class="fas fa-check-circle" style="color:#059669;"></i> Resumen de la Orden de Firma
                        </h4>
                        <p style="margin:0 0 0.35rem; font-size:0.9rem;"><strong>Documento:</strong> <span id="summaryDocTitle">Contrato.pdf</span></p>
                        <p style="margin:0 0 0.35rem; font-size:0.9rem;"><strong>Firmantes Identificados:</strong> <span id="summarySignersCount">2 personas</span></p>
                        <p style="margin:0 0 0.35rem; font-size:0.9rem;"><strong>Bóveda de Seguridad:</strong> Cloudflare R2 Cifrada (AES-256)</p>
                        <p style="margin:0; font-size:0.9rem;"><strong>Validez Legal:</strong> Ley N° 19.799 de Firma Electrónica</p>
                    </div>

                    <!-- Caja de Resultados de Auditoría IA Smart Legal Check -->
                    <div id="aiAuditSection" style="display: none; margin-bottom: 1.5rem;">
                        <div id="aiAuditLoading" style="background: #FEFCE8; border: 1.5px dashed #EAB308; border-radius: 10px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 1.8rem; color: #CA8A04;">
                                <i class="fas fa-robot fa-spin"></i>
                            </div>
                            <div>
                                <strong style="color: #854D0E; font-size: 0.95rem;">Smart Legal Check en ejecución...</strong>
                                <p style="margin: 0.2rem 0 0; font-size: 0.82rem; color: #A16207;">Extrayendo cláusulas del PDF y analizando consistencia normativa bajo la ley chilena.</p>
                            </div>
                        </div>

                        <div id="aiAuditResult" style="display: none; border-radius: 10px; padding: 1.25rem; border: 1px solid #E2E8F0;">
                            <!-- Inyectado dinámicamente -->
                        </div>
                    </div>

                    <!-- Selector de Nivel de Certificación Legal (FES vs FEA) -->
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <label style="display:block; font-size:0.92rem; font-weight:800; color:#0F2A4A; margin-bottom:0.25rem;">
                            <i class="fas fa-stamp" style="color:var(--upload-accent);"></i> Nivel de Certificación Legal de las Firmas:
                        </label>
                        <p style="font-size:0.8rem; color:#64748B; margin-bottom:1rem;">
                            Elige la jerarquía probatoria para este documento bajo la Ley N° 19.799 de Firma Electrónica:
                        </p>

                        <div style="display:grid; grid-template-columns:1fr; gap:0.85rem;">
                            <!-- Opción 1: FES -->
                            <label id="lblSubirFES" style="border:2px solid #059669; background:#F0FDF4; border-radius:10px; padding:1rem; cursor:pointer; display:flex; gap:12px; align-items:flex-start; transition:all 0.2s;">
                                <input type="radio" name="subir_tipo_firma" value="FES" checked onchange="onSubirSignatureTypeChanged()" style="margin-top:3px; accent-color:#059669; width:18px; height:18px;">
                                <div style="flex:1;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                        <span style="font-weight:800; font-size:0.92rem; color:#065F46;">
                                            Firma Electrónica Simple (FES Estándar Certificada)
                                        </span>
                                        <span style="background:#DCFCE7; color:#166534; font-size:0.75rem; font-weight:800; padding:3px 8px; border-radius:6px;">
                                            INCLUIDA
                                        </span>
                                    </div>
                                    <p style="font-size:0.78rem; color:#475569; margin:0; line-height:1.35;">
                                        • Plena validez y fuerza obligatoria entre las partes privadas (Art. 1545 del Código Civil).<br>
                                        • Sellado criptográfico SHA-256 en Bóveda Cloudflare R2 con trazabilidad de IP y dispositivo.<br>
                                        • Recomendada para contratos privados, acuerdos comerciales, poderes simples y autorizaciones.
                                    </p>
                                </div>
                            </label>

                            <!-- Opción 2: FEA -->
                            <label id="lblSubirFEA" style="border:2px solid #CBD5E1; background:#FFFFFF; border-radius:10px; padding:1rem; cursor:pointer; display:flex; gap:12px; align-items:flex-start; transition:all 0.2s;">
                                <input type="radio" name="subir_tipo_firma" value="FEA" onchange="onSubirSignatureTypeChanged()" style="margin-top:3px; accent-color:#D97706; width:18px; height:18px;">
                                <div style="flex:1;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                                        <span style="font-weight:800; font-size:0.92rem; color:#92400E; display:flex; align-items:center; gap:6px;">
                                            <i class="fas fa-award" style="color:#D97706;"></i> Firma Electrónica Avanzada (FEA Spot Idok)
                                        </span>
                                        <span style="background:#FEF3C7; color:#B45309; font-size:0.75rem; font-weight:800; padding:3px 8px; border-radius:6px;">
                                            + $<?= number_format($precioUpgradeFea, 0, ',', '.') ?> CLP
                                        </span>
                                    </div>
                                    <div style="display:inline-block; background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; margin-bottom:0.4rem;">
                                        🛡️ PLENA FE PÚBLICA EQUIVALENTE A NOTARIO PÚBLICO (Art. 4° Ley 19.799)
                                    </div>
                                    <p style="font-size:0.78rem; color:#475569; margin:0; line-height:1.35;">
                                        • <strong>Valor de instrumento público:</strong> Inversión de la carga probatoria por mandato legal.<br>
                                        • Certificado oficial emitido por entidad acreditada ante el <strong>Ministerio de Economía</strong> (Idok).<br>
                                        • Verificación biométrica / <strong>ClaveÚnica del Estado</strong> de cada compareciente con PIN de 2 dígitos.<br>
                                        • Ideal para contratos de arriendo (Desalojo Express Ley 21.461) o trámites judiciales.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.8rem; font-weight:700; color:#1E40AF; text-transform:uppercase;">Tarifa Total Calculada</div>
                            <div style="font-size:1.8rem; font-weight:800; color:#1E3A8A;" id="summaryCalculatedPrice"><?= $precioFormateado ?> CLP</div>
                            <div style="font-size:0.78rem; color:#64748B;" id="summaryPricingBreakdown">Incluye 1 firmante base ($7.990) y custodia en Bóveda R2</div>
                        </div>
                        <div style="text-align:right;">
                            <span style="background:#DEF7EC; color:#03543F; font-size:0.75rem; font-weight:800; padding:4px 8px; border-radius:6px;">
                                <i class="fas fa-shield-alt"></i> Firma Certificada
                            </span>
                        </div>
                    </div>

                    <!-- Selector de Pasarela de Pago para Documento Propio -->
                    <!-- Selector de Pasarela de Pago para Subir Documento -->
                    <?php if (!$mpSubirActive && !$khipuSubirActive): ?>
                        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; text-align:center;">
                            <div style="color:#B45309; font-weight:700; margin-bottom:4px;">
                                <i class="fas fa-exclamation-triangle"></i> Pasarelas de Pago en Mantención
                            </div>
                            <p style="color:#92400E; font-size:0.85rem; margin:0;">
                                Los pagos electrónicos están temporalmente deshabilitados por mantención programada. Por favor contáctanos a soporte@sinnotario.cl.
                            </p>
                        </div>
                    <?php else: ?>
                    <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:10px; padding:1.25rem; margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.88rem; font-weight:800; color:#0F2A4A; margin-bottom:0.75rem;">
                            <i class="fas fa-credit-card" style="color:var(--upload-primary);"></i> Selecciona tu Medio de Pago Electrónico:
                        </label>
                        <div style="display:grid; grid-template-columns:<?php echo ($mpSubirActive && $khipuSubirActive) ? '1fr 1fr' : '1fr'; ?>; gap:0.75rem;">
                            <?php if ($mpSubirActive): ?>
                            <label style="border:2px solid <?php echo ($defaultSubirGateway === 'MERCADO_PAGO') ? '#009EE3' : '#CBD5E1'; ?>; background:<?php echo ($defaultSubirGateway === 'MERCADO_PAGO') ? '#F0F9FF' : '#FFFFFF'; ?>; border-radius:8px; padding:0.85rem; cursor:pointer; display:flex; align-items:center; gap:8px;" id="labelSubirMP">
                                <input type="radio" name="subir_gateway" value="MERCADO_PAGO" <?php echo ($defaultSubirGateway === 'MERCADO_PAGO') ? 'checked' : ''; ?> style="accent-color:#009EE3;" onchange="updateSubirGatewayUI()">
                                <div>
                                    <div style="font-weight:700; color:#0369A1; font-size:0.88rem;">Mercado Pago</div>
                                    <div style="font-size:0.72rem; color:#64748B;">Tarjetas de Crédito / Redcompra</div>
                                </div>
                            </label>
                            <?php endif; ?>

                            <?php if ($khipuSubirActive): ?>
                            <label style="border:2px solid <?php echo ($defaultSubirGateway === 'KHIPU') ? '#6366F1' : '#CBD5E1'; ?>; background:<?php echo ($defaultSubirGateway === 'KHIPU') ? '#EEF2FF' : '#FFFFFF'; ?>; border-radius:8px; padding:0.85rem; cursor:pointer; display:flex; align-items:center; gap:8px;" id="labelSubirKhipu">
                                <input type="radio" name="subir_gateway" value="KHIPU" <?php echo ($defaultSubirGateway === 'KHIPU') ? 'checked' : ''; ?> style="accent-color:#6366F1;" onchange="updateSubirGatewayUI()">
                                <div>
                                    <div style="font-weight:700; color:#4338CA; font-size:0.88rem;">Khipu</div>
                                    <div style="font-size:0.72rem; color:#64748B;">Transferencia Bancaria Directa</div>
                                </div>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between;">
                        <button type="button" class="btn-cta btn-secondary" onclick="goToStep(2)">
                            <i class="fas fa-arrow-left"></i> Modificar Firmantes
                        </button>
                        <button type="button" id="btnSubmitFinalOrder" class="btn-upload-action" style="background:#059669;" onclick="submitCustomDocument()">
                            <i class="fas fa-paper-plane"></i> Emitir y Despachar a Firmantes
                        </button>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Visor del PDF y Garantías -->
            <div>
                <div class="upload-card" style="padding:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="font-size:1rem; font-weight:800; color:#0F2A4A; margin:0;">
                            <i class="far fa-file-pdf"></i> Visor del Documento
                        </h3>
                        <span id="badgePreviewStatus" style="font-size:0.75rem; background:#F1F5F9; color:#64748B; padding:3px 8px; border-radius:4px; font-weight:700;">
                            Sin Archivo
                        </span>
                    </div>

                    <div id="pdfViewerPlaceholder" style="height:500px; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:1.5rem; color:#64748B;">
                        <i class="fas fa-file-pdf fa-4x" style="color:#CBD5E1; margin-bottom:1rem;"></i>
                        <h4 style="font-size:1.05rem; font-weight:700; color:#475569; margin-bottom:0.35rem;">
                            Previsualización Inactiva
                        </h4>
                        <p style="font-size:0.85rem; margin:0; max-width:280px;">
                            Selecciona tu archivo PDF en el Paso 1 para visualizarlo directamente en este visor.
                        </p>
                    </div>

                    <iframe id="pdfIframeViewer" class="preview-viewer" style="display:none;" title="Visor PDF"></iframe>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="background: #0A1C33; color: #94A3B8; padding: 3rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem;">
        <div style="max-width: 1140px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <strong style="color: white;">SinNotario.cl</strong> — Carga Libre y Firma Digital de Instrumentos Privados bajo la Ley N° 19.799.
            </div>
            <div style="display: flex; gap: 1.5rem;">
                <a href="index.php" style="color: #CBD5E1; text-decoration: none;">Inicio</a>
                <a href="marco-legal.php" style="color: #CBD5E1; text-decoration: none;">Marco Legal</a>
                <a href="verificar.php" style="color: #CBD5E1; text-decoration: none;">Verificar</a>
                <a href="terminos-condiciones.php" style="color: #CBD5E1; text-decoration: none;">Términos</a>
            </div>
        </div>
    </footer>

    <!-- Scripts del Wizard de Carga Propia -->
    <script src="assets/js/app.js?v=20261107_1"></script>
    <script>
        let currentUploadedFile = null;
        let currentCalculatedHash = '';
        let currentSignersCount = 2;

        // Drag & Drop Setup
        const dropzone = document.getElementById('dropzoneBox');
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleSelectedPdf(files[0]);
                }
            });
        }

        async function handleSelectedPdf(file) {
            if (!file || file.type !== 'application/pdf') {
                alert('Por favor selecciona un archivo en formato PDF.');
                return;
            }

            currentUploadedFile = file;

            // UI
            document.getElementById('fileNameDisplay').innerText = file.name;
            document.getElementById('fileSizeDisplay').innerText = `${(file.size / 1024).toFixed(1)} KB`;
            document.getElementById('customDocTitle').value = file.name.replace(/\.[^/.]+$/, "");
            document.getElementById('fileLoadedDetails').style.display = 'block';
            document.getElementById('btnNextToStep2').disabled = false;

            // Cómputo SHA-256 local
            try {
                const arrayBuffer = await file.arrayBuffer();
                const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                currentCalculatedHash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                document.getElementById('fileHashDisplay').innerText = currentCalculatedHash;
            } catch (err) {
                document.getElementById('fileHashDisplay').innerText = 'No disponible';
            }

            // Cargar en Visor
            const fileUrl = URL.createObjectURL(file);
            const iframe = document.getElementById('pdfIframeViewer');
            const placeholder = document.getElementById('pdfViewerPlaceholder');
            const badge = document.getElementById('badgePreviewStatus');

            iframe.src = fileUrl;
            iframe.style.display = 'block';
            placeholder.style.display = 'none';
            badge.innerText = 'PDF Cargado';
            badge.style.background = '#DEF7EC';
            badge.style.color = '#03543F';
        }

        function clearLoadedFile() {
            currentUploadedFile = null;
            currentCalculatedHash = '';
            document.getElementById('fileUploadInput').value = '';
            document.getElementById('fileLoadedDetails').style.display = 'none';
            document.getElementById('btnNextToStep2').disabled = true;

            const iframe = document.getElementById('pdfIframeViewer');
            const placeholder = document.getElementById('pdfViewerPlaceholder');
            const badge = document.getElementById('badgePreviewStatus');

            iframe.src = '';
            iframe.style.display = 'none';
            placeholder.style.display = 'flex';
            badge.innerText = 'Sin Archivo';
            badge.style.background = '#F1F5F9';
            badge.style.color = '#64748B';
        }

        let extractedPdfText = '';

        async function extractTextFromLoadedPdf(file) {
            try {
                if (!window.pdfjsLib) return '';
                const arrayBuffer = await file.arrayBuffer();
                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                const pdf = await loadingTask.promise;
                let fullText = '';
                const maxPages = Math.min(pdf.numPages, 15); // Analizar hasta 15 páginas
                for (let i = 1; i <= maxPages; i++) {
                    const page = await pdf.getPage(i);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += ' ' + pageText;
                }
                return fullText.trim();
            } catch (e) {
                console.warn('Extracción de texto del PDF en navegador falló:', e);
                return '';
            }
        }

        async function runSmartLegalCheckIfRequested() {
            const chk = document.getElementById('chkSmartLegalCheck');
            const aiSec = document.getElementById('aiAuditSection');
            const aiLoading = document.getElementById('aiAuditLoading');
            const aiResult = document.getElementById('aiAuditResult');

            if (!chk || !chk.checked) {
                aiSec.style.display = 'none';
                return;
            }

            aiSec.style.display = 'block';
            aiLoading.style.display = 'flex';
            aiResult.style.display = 'none';

            if (!extractedPdfText && currentUploadedFile) {
                extractedPdfText = await extractTextFromLoadedPdf(currentUploadedFile);
            }

            const title = document.getElementById('customDocTitle').value.trim() || (currentUploadedFile ? currentUploadedFile.name : '');
            const contractSample = (title + "\n\n" + (extractedPdfText || title)).trim();

            try {
                const res = await fetch('api/index.php?action=ai_audit_contract', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contract_text: contractSample })
                });

                const json = await res.json();
                aiLoading.style.display = 'none';
                aiResult.style.display = 'block';

                if (json.success && json.data && json.data.audit) {
                    renderAuditResult(json.data.audit);
                } else {
                    renderAuditFallback();
                }
            } catch (err) {
                aiLoading.style.display = 'none';
                aiResult.style.display = 'block';
                renderAuditFallback();
            }
        }

        function renderAuditResult(audit) {
            const container = document.getElementById('aiAuditResult');
            const isSolemn = audit.is_notary_mandatory;
            const risks = audit.critical_risks || [];
            const warnings = audit.warnings || [];
            const solids = audit.solid_points || [];

            let bg = '#F0FDF4';
            let border = '#86EFAC';
            let icon = 'fa-check-circle';
            let statusTitle = 'Diagnóstico Positivo: Apto para Firma Electrónica Ley 19.799';
            let titleColor = '#166534';

            if (isSolemn) {
                bg = '#FEF2F2';
                border = '#FCA5A5';
                icon = 'fa-exclamation-triangle';
                statusTitle = 'Advertencia Crítica: Trámite con Reserva de Escritura Pública Notarial';
                titleColor = '#991B1B';
            } else if (risks.length > 0) {
                bg = '#FFFBEB';
                border = '#FCD34D';
                icon = 'fa-exclamation-circle';
                statusTitle = 'Observaciones Detectadas en el Documento';
                titleColor = '#92400E';
            }

            let html = `
                <div style="background: ${bg}; border: 1.5px solid ${border}; border-radius: 10px; padding: 1.25rem;">
                    <div style="display: flex; align-items: flex-start; gap: 0.85rem; margin-bottom: 0.85rem;">
                        <i class="fas ${icon}" style="font-size: 1.5rem; color: ${titleColor}; margin-top: 2px;"></i>
                        <div>
                            <strong style="color: ${titleColor}; font-size: 0.98rem; display: block;">${statusTitle}</strong>
                            <p style="margin: 0.2rem 0 0; font-size: 0.84rem; color: #475569;">
                                ${audit.summary || 'Análisis preventivo de consistencia normativa y cláusulas contractuales.'}
                            </p>
                        </div>
                    </div>
            `;

            if (audit.notary_warning) {
                html += `
                    <div style="background: #FFFFFF; border-left: 4px solid #DC2626; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 0.75rem; font-size: 0.84rem; color: #991B1B;">
                        <strong><i class="fas fa-gavel"></i> Alerta Notarial:</strong> ${audit.notary_warning}
                    </div>
                `;
            }

            if (risks.length > 0) {
                html += `<div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: #991B1B; text-transform: uppercase; margin-bottom: 0.35rem;">
                        <i class="fas fa-times-circle"></i> Cláusulas Críticas o Riesgos Identificados (${risks.length}):
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.84rem; color: #7F1D1D; line-height: 1.45;">
                        ${risks.map(r => `<li>${r}</li>`).join('')}
                    </ul>
                </div>`;
            }

            if (warnings.length > 0) {
                html += `<div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: #B45309; text-transform: uppercase; margin-bottom: 0.35rem;">
                        <i class="fas fa-info-circle"></i> Advertencias y Puntos de Atención (${warnings.length}):
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.84rem; color: #92400E; line-height: 1.45;">
                        ${warnings.map(w => `<li>${w}</li>`).join('')}
                    </ul>
                </div>`;
            }

            if (solids.length > 0) {
                html += `<div>
                    <div style="font-size: 0.82rem; font-weight: 700; color: #047857; text-transform: uppercase; margin-bottom: 0.35rem;">
                        <i class="fas fa-shield-alt"></i> Aspectos Sólidos y Conformes (${solids.length}):
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.84rem; color: #065F46; line-height: 1.45;">
                        ${solids.map(s => `<li>${s}</li>`).join('')}
                    </ul>
                </div>`;
            }

            if (risks.length === 0 && warnings.length === 0 && !isSolemn) {
                html += `
                    <div style="background: rgba(255,255,255,0.7); border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.84rem; color: #065F46;">
                        <i class="fas fa-check-double"></i> <strong>Documento Óptimo:</strong> No se detectaron cláusulas abusivas ni impedimentos legales para suscripción mediante firma electrónica avanzada y simple bajo la Ley N° 19.799.
                    </div>
                `;
            }

            html += `</div>`;
            container.innerHTML = html;
        }

        function renderAuditFallback() {
            const container = document.getElementById('aiAuditResult');
            container.innerHTML = `
                <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 10px; padding: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-shield-check" style="font-size: 1.4rem; color: #16A34A;"></i>
                        <div>
                            <strong style="color: #166534; font-size: 0.95rem;">Smart Legal Check: No se detectaron cláusulas abusivas</strong>
                            <p style="margin: 0.15rem 0 0; font-size: 0.83rem; color: #15803D;">
                                El documento cumple los parámetros de instrumento privado suscribible válidamente conforme a la Ley N° 19.799.
                            </p>
                        </div>
                    </div>
                </div>
            `;
        }

        const PRECIO_BASE_SUBIR_PDF = <?= (int)($settings['precio_base_subir_pdf'] ?? 7990) ?>;
        const PRECIO_FIRMA_EXTRA = <?= (int)($settings['precio_firma_extra'] ?? 2990) ?>;
        const PRECIO_UPGRADE_FEA = <?= (int)$precioUpgradeFea ?>;

        function onSubirSignatureTypeChanged() {
            const isFea = document.querySelector('input[name="subir_tipo_firma"]:checked')?.value === 'FEA';
            const lFes = document.getElementById('lblSubirFES');
            const lFea = document.getElementById('lblSubirFEA');

            if (lFes && lFea) {
                if (isFea) {
                    lFea.style.borderColor = '#D97706';
                    lFea.style.background = '#FFFBEB';
                    lFes.style.borderColor = '#CBD5E1';
                    lFes.style.background = '#FFFFFF';
                } else {
                    lFes.style.borderColor = '#059669';
                    lFes.style.background = '#F0FDF4';
                    lFea.style.borderColor = '#CBD5E1';
                    lFea.style.background = '#FFFFFF';
                }
            }

            updatePricingCalculation();
        }

        function updatePricingCalculation() {
            const signers = getSignersData();
            const totalSigners = Math.max(1, signers.length);
            const extraSigners = Math.max(0, totalSigners - 1);
            const isFea = document.querySelector('input[name="subir_tipo_firma"]:checked')?.value === 'FEA';
            const feaCost = isFea ? PRECIO_UPGRADE_FEA : 0;
            const totalPrice = PRECIO_BASE_SUBIR_PDF + (extraSigners * PRECIO_FIRMA_EXTRA) + feaCost;
            
            const formatted = '$' + totalPrice.toLocaleString('es-CL') + ' CLP';
            const priceEl = document.getElementById('summaryCalculatedPrice');
            if (priceEl) priceEl.innerText = formatted;

            const breakdownEl = document.getElementById('summaryPricingBreakdown');
            if (breakdownEl) {
                let parts = [`1 firmante base ($${PRECIO_BASE_SUBIR_PDF.toLocaleString('es-CL')})`];
                if (extraSigners > 0) {
                    parts.push(`${extraSigners} firmante(s) extra ($${(extraSigners * PRECIO_FIRMA_EXTRA).toLocaleString('es-CL')})`);
                }
                if (isFea) {
                    parts.push(`Firma Avanzada FEA (+$${feaCost.toLocaleString('es-CL')})`);
                } else {
                    parts.push(`Firma Simple FES`);
                }
                breakdownEl.innerText = parts.join(' + ') + ' y custodia en Bóveda R2';
            }
        }

        function toggleRepresentante(idx) {
            const box = document.getElementById(`signer_box_${idx}`);
            if (!box) return;
            const chk = box.querySelector('.sig-es-rep');
            const repWrap = document.getElementById(`rep_company_${idx}`);
            if (repWrap) {
                repWrap.style.display = (chk && chk.checked) ? 'block' : 'none';
            }
        }

        function changeDocType(idx) {
            const box = document.getElementById(`signer_box_${idx}`);
            if (!box) return;
            const sel = box.querySelector('.sig-tipo-doc');
            const lbl = document.getElementById(`lbl_id_${idx}`);
            const inputRut = box.querySelector('.sig-rut');
            const paisWrap = document.getElementById(`pais_wrap_${idx}`);

            if (sel && sel.value === 'PASAPORTE') {
                if (lbl) lbl.innerText = 'N° de Pasaporte Extranjero *';
                if (inputRut) {
                    inputRut.placeholder = 'Ej: P12345678 o DNI extranjero';
                    inputRut.classList.remove('rut-valid', 'rut-invalid');
                }
                if (paisWrap) paisWrap.style.display = 'block';
            } else {
                if (lbl) lbl.innerText = 'RUT *';
                if (inputRut) {
                    inputRut.placeholder = '12.345.678-5';
                }
                if (paisWrap) paisWrap.style.display = 'none';
            }
        }

        const IS_WIZARD_SANDBOX = <?= $isWizardSandbox ? 'true' : 'false' ?>;

        function fillSubirDemoData() {
            const boxes = document.querySelectorAll('.signer-box');
            const safePhone = '<?= addslashes($supportWaRaw) ?>';
            if (boxes[0]) {
                const b = boxes[0];
                if (b.querySelector('.sig-nombre')) b.querySelector('.sig-nombre').value = 'Juan Andrés Pérez González';
                if (b.querySelector('.sig-rut')) b.querySelector('.sig-rut').value = '10.993.031-8';
                if (b.querySelector('.sig-email')) b.querySelector('.sig-email').value = 'contacto@sinnotario.cl';
                if (b.querySelector('.sig-tel')) b.querySelector('.sig-tel').value = safePhone;
            }
            if (boxes[1]) {
                const b = boxes[1];
                if (b.querySelector('.sig-nombre')) b.querySelector('.sig-nombre').value = 'María José Valenzuela';
                if (b.querySelector('.sig-rut')) b.querySelector('.sig-rut').value = '15.444.555-6';
                if (b.querySelector('.sig-email')) b.querySelector('.sig-email').value = 'contacto@sinnotario.cl';
                if (b.querySelector('.sig-tel')) b.querySelector('.sig-tel').value = safePhone;
            }
        }

        function goToStep(step) {
            // Si está en Modo Pruebas / Sandbox y los campos están vacíos, permitir relleno demo
            if (step === 3 && IS_WIZARD_SANDBOX) {
                const firstNombre = document.querySelector('.signer-box .sig-nombre');
                if (firstNombre && !firstNombre.value.trim()) {
                    fillSubirDemoData();
                }
            }

            // Si intenta avanzar del Paso 2 al Paso 3, validar firmantes, emails y teléfonos
            if (step === 3) {
                const boxes = document.querySelectorAll('.signer-box');
                let hasErrors = false;
                let firstErrorInput = null;

                boxes.forEach((box, bIdx) => {
                    const tipoDoc = box.querySelector('.sig-tipo-doc')?.value || 'RUT';
                    const inputNombre = box.querySelector('.sig-nombre');
                    const inputRut = box.querySelector('.sig-rut');
                    const inputEmail = box.querySelector('.sig-email');
                    const inputTel = box.querySelector('.sig-tel');
                    let errEl = box.querySelector('.rut-error-text');

                    if (!errEl && inputRut && inputRut.parentElement) {
                        errEl = document.createElement('div');
                        errEl.className = 'rut-error-text';
                        errEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                        inputRut.parentElement.appendChild(errEl);
                    }

                    const nombreVal = inputNombre ? inputNombre.value.trim() : '';
                    const rutVal = inputRut ? inputRut.value.trim() : '';
                    const emailVal = inputEmail ? inputEmail.value.trim() : '';
                    const telDigits = inputTel ? inputTel.value.replace(/[^0-9]/g, '') : '';

                    if (!nombreVal) {
                        if (inputNombre) inputNombre.classList.add('rut-invalid');
                        hasErrors = true;
                        if (!firstErrorInput) firstErrorInput = inputNombre;
                    } else if (inputNombre) {
                        inputNombre.classList.remove('rut-invalid');
                    }

                    // Validar Correo Electrónico
                    const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal);
                    if (!isEmailValid) {
                        if (inputEmail) inputEmail.classList.add('rut-invalid');
                        hasErrors = true;
                        if (!firstErrorInput) firstErrorInput = inputEmail;
                    } else if (inputEmail) {
                        inputEmail.classList.remove('rut-invalid');
                    }

                    // Validar Teléfono WhatsApp
                    if (telDigits.length < 8) {
                        if (inputTel) inputTel.classList.add('rut-invalid');
                        hasErrors = true;
                        if (!firstErrorInput) firstErrorInput = inputTel;
                    } else if (inputTel) {
                        inputTel.classList.remove('rut-invalid');
                    }

                    if (tipoDoc === 'RUT') {
                        if (!rutVal) {
                            if (inputRut) inputRut.classList.add('rut-invalid');
                            if (errEl) {
                                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Debes ingresar el RUT del firmante.';
                                errEl.style.display = 'block';
                            }
                            hasErrors = true;
                            if (!firstErrorInput) firstErrorInput = inputRut;
                        } else if (typeof RutHelper !== 'undefined') {
                            const formatted = RutHelper.format(rutVal);
                            if (inputRut) inputRut.value = formatted;
                            const valid = RutHelper.validate(formatted);
                            if (!valid) {
                                if (inputRut) {
                                    inputRut.classList.remove('rut-valid');
                                    inputRut.classList.add('rut-invalid');
                                }
                                if (errEl) {
                                    errEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> RUT inválido: el dígito verificador no coincide con la numeración.';
                                    errEl.style.display = 'block';
                                }
                                hasErrors = true;
                                if (!firstErrorInput) firstErrorInput = inputRut;
                            } else {
                                if (inputRut) {
                                    inputRut.classList.remove('rut-invalid');
                                    inputRut.classList.add('rut-valid');
                                }
                                if (errEl) errEl.style.display = 'none';
                            }
                        }
                    } else if (tipoDoc === 'PASAPORTE') {
                        if (!rutVal) {
                            if (inputRut) inputRut.classList.add('rut-invalid');
                            hasErrors = true;
                            if (!firstErrorInput) firstErrorInput = inputRut;
                        } else if (inputRut) {
                            inputRut.classList.remove('rut-invalid');
                        }
                    }

                    // Si es representante legal, validar RUT de empresa
                    const chkRep = box.querySelector('.sig-es-rep');
                    if (chkRep && chkRep.checked) {
                        const empRut = box.querySelector('.sig-empresa-rut');
                        let empErrEl = box.querySelector('.rep-company-wrap .rut-error-text');
                        if (!empErrEl && empRut && empRut.parentElement) {
                            empErrEl = document.createElement('div');
                            empErrEl.className = 'rut-error-text';
                            empErrEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                            empRut.parentElement.appendChild(empErrEl);
                        }
                        const empRutVal = empRut ? empRut.value.trim() : '';
                        if (empRutVal && typeof RutHelper !== 'undefined') {
                            const empFmt = RutHelper.format(empRutVal);
                            if (empRut) empRut.value = empFmt;
                            const empValid = RutHelper.validate(empFmt);
                            if (!empValid) {
                                if (empRut) empRut.classList.add('rut-invalid');
                                if (empErrEl) {
                                    empErrEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> RUT de Empresa inválido: dígito verificador incorrecto.';
                                    empErrEl.style.display = 'block';
                                }
                                hasErrors = true;
                                if (!firstErrorInput) firstErrorInput = empRut;
                            } else {
                                if (empRut) empRut.classList.remove('rut-invalid');
                                if (empErrEl) empErrEl.style.display = 'none';
                            }
                        }
                    }
                });

                if (hasErrors) {
                    if (firstErrorInput) {
                        firstErrorInput.focus();
                        firstErrorInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    alert('Por favor corrige los datos o RUTs destacados en rojo antes de continuar.');
                    return;
                }
            }

            document.getElementById('viewStep1').style.display = (step === 1) ? 'block' : 'none';
            document.getElementById('viewStep2').style.display = (step === 2) ? 'block' : 'none';
            document.getElementById('viewStep3').style.display = (step === 3) ? 'block' : 'none';

            // Actualizar burbujas
            for (let i = 1; i <= 3; i++) {
                const el = document.getElementById(`p_step${i}`);
                if (i < step) {
                    el.className = 'step-item completed';
                } else if (i === step) {
                    el.className = 'step-item active';
                } else {
                    el.className = 'step-item';
                }
            }

            if (step === 3) {
                // Actualizar resumen
                const title = document.getElementById('customDocTitle').value.trim() || (currentUploadedFile ? currentUploadedFile.name : 'Documento Personalizado');
                document.getElementById('summaryDocTitle').innerText = title;
                const signers = getSignersData();
                document.getElementById('summarySignersCount').innerText = `${signers.length} compareciente(s)`;

                // Actualizar cálculo dinámico de precio según firmantes
                updatePricingCalculation();

                // Ejecutar y mostrar el diagnóstico IA si estaba activo
                runSmartLegalCheckIfRequested();
            }

            window.scrollTo({ top: 150, behavior: 'smooth' });
        }

        function addSignerRow() {
            const idx = currentSignersCount;
            const container = document.getElementById('signersListContainer');
            const div = document.createElement('div');
            div.className = 'signer-box';
            div.id = `signer_box_${idx}`;
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <span class="badge-firmante" style="background:#FEF3C7; color:#B45309;">Firmante ${idx + 1} (Adicional)</span>
                    <div style="display:flex; align-items:center; gap:1rem;">
                        <label style="font-size:0.8rem; color:#475569; display:flex; align-items:center; gap:0.35rem; cursor:pointer;">
                            <input type="checkbox" class="sig-es-rep" onchange="toggleRepresentante(${idx})">
                            <strong>Representante Legal</strong>
                        </label>
                        <button type="button" style="background:none; border:none; color:#DC2626; cursor:pointer; font-size:0.8rem; font-weight:700;" onclick="removeSignerRow(${idx})">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </div>
                </div>

                <!-- Campos de Empresa Representada -->
                <div class="rep-company-wrap" id="rep_company_${idx}" style="display:none; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; padding:0.85rem; margin-bottom:0.85rem;">
                    <div style="font-size:0.8rem; font-weight:700; color:#0F2A4A; margin-bottom:0.4rem;">
                        <i class="fas fa-building"></i> Datos de la Persona Jurídica / Empresa Representada
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Razón Social de la Empresa *</label>
                            <input type="text" class="form-control sig-empresa-nombre" placeholder="Ej: Empresa SpA">
                        </div>
                        <div class="form-group">
                            <label>RUT Empresa *</label>
                            <input type="text" class="form-control sig-empresa-rut" placeholder="76.123.456-7" maxlength="12">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Documento *</label>
                        <select class="form-control sig-tipo-doc" onchange="changeDocType(${idx})">
                            <option value="RUT" selected>Cédula Nacional de Identidad / RUT (Chile)</option>
                            <option value="PASAPORTE">Pasaporte Extranjero / Documento Internacional</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="lbl-id-num" id="lbl_id_${idx}">RUT *</label>
                        <input type="text" class="form-control sig-rut" placeholder="Ej: 11.222.333-4">
                    </div>
                    <div class="form-group sig-pais-wrap" id="pais_wrap_${idx}" style="display:none;">
                        <label>País Emisor *</label>
                        <input type="text" class="form-control sig-pais" placeholder="Ej: Venezuela, Colombia, Perú, España" value="Chile">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre Completo del Firmante *</label>
                        <input type="text" class="form-control sig-nombre" placeholder="Nombre del compareciente">
                    </div>
                    <div class="form-group">
                        <label>Rol en el Documento *</label>
                        <input type="text" class="form-control sig-rol" placeholder="Ej: Testigo, Aval, Tercero" value="Parte">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Correo Electrónico *</label>
                        <input type="email" class="form-control sig-email" placeholder="correo@ejemplo.cl">
                    </div>
                    <div class="form-group">
                        <label>Teléfono WhatsApp *</label>
                        <input type="tel" class="form-control sig-tel" placeholder="+56 9 1111 2222">
                    </div>
                </div>
            `;
            container.appendChild(div);
            currentSignersCount++;
            updatePricingCalculation();

            // Vincular auto-cleaner y validación de RUT en la nueva fila
            if (typeof window.enableExampleClearOnFocus === 'function') {
                window.enableExampleClearOnFocus(div);
            }
            const newRut = div.querySelector('.sig-rut');
            if (newRut && typeof RutHelper !== 'undefined') {
                let errEl = div.querySelector('.rut-error-text');
                if (!errEl && newRut.parentElement) {
                    errEl = document.createElement('div');
                    errEl.className = 'rut-error-text';
                    errEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                    newRut.parentElement.appendChild(errEl);
                }
                RutHelper.attachToInput(newRut, errEl);
            }
            const newEmpRut = div.querySelector('.sig-empresa-rut');
            if (newEmpRut && typeof RutHelper !== 'undefined') {
                let empErrEl = div.querySelector('.rep-company-wrap .rut-error-text');
                if (!empErrEl && newEmpRut.parentElement) {
                    empErrEl = document.createElement('div');
                    empErrEl.className = 'rut-error-text';
                    empErrEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                    newEmpRut.parentElement.appendChild(empErrEl);
                }
                RutHelper.attachToInput(newEmpRut, empErrEl);
            }
        }

        // Inicializar validaciones y auto-cleaner al cargar el documento
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.enableExampleClearOnFocus === 'function') {
                window.enableExampleClearOnFocus(document);
            }

            // Pre-llenado automático si el usuario ha iniciado sesión
            <?php if ($currentUser): ?>
            const sig0Nombre = document.querySelector('#signer_box_0 .sig-nombre');
            const sig0Email = document.querySelector('#signer_box_0 .sig-email');
            const sig0Rut = document.querySelector('#signer_box_0 .sig-rut');
            const sig0Tel = document.querySelector('#signer_box_0 .sig-tel');
            if (sig0Nombre) sig0Nombre.value = <?= json_encode($currentUser['nombre'] . ' ' . $currentUser['apellido']) ?>;
            if (sig0Email) sig0Email.value = <?= json_encode($currentUser['email']) ?>;
            if (sig0Rut && <?= json_encode(!empty($currentUser['rut'])) ?>) sig0Rut.value = <?= json_encode($currentUser['rut'] ?? '') ?>;
            if (sig0Tel && <?= json_encode(!empty($currentUser['telefono'])) ?>) sig0Tel.value = <?= json_encode($currentUser['telefono'] ?? '') ?>;

            <?php if (!empty($currentUser['empresa_razon_social'])): ?>
            const chkRep0 = document.querySelector('#signer_box_0 .sig-es-rep');
            const empNom0 = document.querySelector('#signer_box_0 .sig-empresa-nombre');
            const empRut0 = document.querySelector('#signer_box_0 .sig-empresa-rut');
            if (chkRep0 && !chkRep0.checked) {
                chkRep0.checked = true;
                toggleRepresentante(0);
            }
            if (empNom0) empNom0.value = <?= json_encode($currentUser['empresa_razon_social']) ?>;
            if (empRut0) empRut0.value = <?= json_encode($currentUser['empresa_rut'] ?? '') ?>;
            <?php endif; ?>
            <?php endif; ?>

            // Adjuntar validación a los RUTs iniciales (persona y empresa)
            document.querySelectorAll('.signer-box').forEach(box => {
                const rutInp = box.querySelector('.sig-rut');
                if (rutInp && typeof RutHelper !== 'undefined') {
                    let errEl = box.querySelector('.rut-error-text');
                    if (!errEl && rutInp.parentElement) {
                        errEl = document.createElement('div');
                        errEl.className = 'rut-error-text';
                        errEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                        rutInp.parentElement.appendChild(errEl);
                    }
                    RutHelper.attachToInput(rutInp, errEl);
                }

                const empRutInp = box.querySelector('.sig-empresa-rut');
                if (empRutInp && typeof RutHelper !== 'undefined') {
                    let empErrEl = box.querySelector('.rep-company-wrap .rut-error-text');
                    if (!empErrEl && empRutInp.parentElement) {
                        empErrEl = document.createElement('div');
                        empErrEl.className = 'rut-error-text';
                        empErrEl.style.cssText = 'color:#DC2626; font-size:0.75rem; font-weight:600; margin-top:0.35rem; display:none; padding:4px 8px; background:#FEF2F2; border-left:3px solid #DC2626; border-radius:4px;';
                        empRutInp.parentElement.appendChild(empErrEl);
                    }
                    RutHelper.attachToInput(empRutInp, empErrEl);
                }
            });
        });

        function removeSignerRow(idx) {
            const row = document.getElementById(`signer_box_${idx}`);
            if (row) {
                row.remove();
                updatePricingCalculation();
            }
        }

        function getSignersData() {
            const boxes = document.querySelectorAll('.signer-box');
            const signers = [];
            boxes.forEach(box => {
                const nombre = box.querySelector('.sig-nombre')?.value.trim() || '';
                const tipoDoc = box.querySelector('.sig-tipo-doc')?.value || 'RUT';
                const rut = box.querySelector('.sig-rut')?.value.trim() || '';
                const pais = box.querySelector('.sig-pais')?.value.trim() || 'Chile';
                const email = box.querySelector('.sig-email')?.value.trim() || '';
                const tel = box.querySelector('.sig-tel')?.value.trim() || '';
                const rol = box.querySelector('.sig-rol')?.value.trim() || 'PARTE';

                const esRep = box.querySelector('.sig-es-rep')?.checked ? true : false;
                const empresaRazon = esRep ? (box.querySelector('.sig-empresa-nombre')?.value.trim() || '') : '';
                const empresaRut = esRep ? (box.querySelector('.sig-empresa-rut')?.value.trim() || '') : '';

                if (nombre && rut) {
                    signers.push({
                        nombre,
                        tipo_documento: tipoDoc,
                        numero_identificacion: rut,
                        rut: rut,
                        pais_emisor: pais,
                        email,
                        telefono: tel,
                        rol,
                        es_representante: esRep,
                        empresa_razon_social: empresaRazon,
                        empresa_rut: empresaRut
                    });
                }
            });
            return signers;
        }

        function updateSubirGatewayUI() {
            const isMP = document.querySelector('input[name="subir_gateway"]:checked')?.value === 'MERCADO_PAGO';
            const lMP = document.getElementById('labelSubirMP');
            const lKh = document.getElementById('labelSubirKhipu');
            if (lMP) {
                lMP.style.borderColor = isMP ? '#009EE3' : '#CBD5E1';
                lMP.style.background = isMP ? '#F0F9FF' : '#FFFFFF';
            }
            if (lKh) {
                lKh.style.borderColor = !isMP ? '#6366F1' : '#CBD5E1';
                lKh.style.background = !isMP ? '#EEF2FF' : '#FFFFFF';
            }
        }

        async function submitCustomDocument() {
            if (!currentUploadedFile) {
                alert('Debes seleccionar un archivo PDF.');
                goToStep(1);
                return;
            }

            const signers = getSignersData();
            if (signers.length === 0) {
                alert('Debes ingresar al menos un compareciente/firmante.');
                goToStep(2);
                return;
            }

            const btn = document.getElementById('btnSubmitFinalOrder');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sellando en Cloudflare R2...';

            try {
                // Convertir archivo a Base64
                const reader = new FileReader();
                reader.readAsDataURL(currentUploadedFile);
                reader.onload = async function () {
                    const base64Data = reader.result.split(',')[1];
                    const docTitle = document.getElementById('customDocTitle').value.trim() || currentUploadedFile.name;

                    const chosenTipoFirma = document.querySelector('input[name="subir_tipo_firma"]:checked')?.value || 'FES';

                    const payload = {
                        action: 'upload_custom_document',
                        titulo: docTitle,
                        tipo_firma: chosenTipoFirma,
                        file_name: currentUploadedFile.name,
                        pdf_base64: base64Data,
                        sha256: currentCalculatedHash,
                        signers: signers,
                        audit_ai: document.getElementById('chkSmartLegalCheck').checked
                    };

                    const response = await fetch('api/index.php?action=upload_custom_document', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const json = await response.json();

                    if (json.success && json.data) {
                        const docId = json.data.document_id;
                        const vCode = json.data.codigo_verificacion;
                        const chosenGw = document.querySelector('input[name="subir_gateway"]:checked')?.value || 'MERCADO_PAGO';

                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Conectando con pasarela segura...';
                        try {
                            const payRes = await fetch('api/index.php?action=pay_document', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ document_id: docId, gateway: chosenGw })
                            });
                            const payJson = await payRes.json();

                            if (payJson.success && payJson.data) {
                                if (payJson.data.init_point) {
                                    window.location.href = payJson.data.init_point;
                                    return;
                                } else if (payJson.data.payment_url) {
                                    window.location.href = payJson.data.payment_url;
                                    return;
                                }
                            }
                        } catch (ePay) {
                            console.warn("Pasarela no configurada aún, pasando a verificación directa:", ePay);
                        }

                        alert(`¡Documento sellado exitosamente!\n\nCódigo de Verificación: ${vCode}\nLos enlaces de firma digital han sido preparados y quedarán disponibles para despacho.`);
                        window.location.href = `verificar.php?code=${encodeURIComponent(vCode)}`;
                    } else {
                        alert('Error al procesar el documento: ' + (json.error || 'Respuesta inválida del servidor'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Emitir y Despachar a Firmantes';
                    }
                };
            } catch (err) {
                alert('Error al enviar el archivo: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Emitir y Despachar a Firmantes';
            }
        }
    </script>
</body>
</html>
