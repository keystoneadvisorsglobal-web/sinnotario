<?php
/**
 * SinNotario.cl - Wizard Universal de Redacción y Live Preview (Split View 60/40)
 * Cubre el 100% de las 13 plantillas legales de la plataforma:
 * Inmobiliario, Laboral, Comercial y Declaraciones Juradas
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/database/Database.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : 'contrato-arriendo-ley-21461';
$pdo = Database::getInstance();

$stmt = $pdo->prepare("SELECT * FROM templates WHERE slug = ?");
$stmt->execute([$slug]);
$tpl = $stmt->fetch();

if (!$tpl) {
    // Si no existe, fallback a contrato de arriendo
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE slug = 'contrato-arriendo-ley-21461'");
    $stmt->execute();
    $tpl = $stmt->fetch();
    $slug = 'contrato-arriendo-ley-21461';
}

$precioFormatted = '$' . number_format($tpl['precio_base'], 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tpl['nombre']) ?> | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261106_6">
    <style>
        .wizard-container {
            display: grid;
            grid-template-columns: 58% 42%;
            gap: 2rem;
            max-width: 1400px;
            margin: 1.5rem auto 3.5rem;
            padding: 0 1.5rem;
            align-items: start;
        }

        .form-pane {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
        }

        .step-progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .step-progress-bar::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #E2E8F0;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            background: #FFFFFF;
            padding: 0 0.5rem;
        }

        .step-bubble {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #F1F5F9;
            color: #64748B;
            border: 2px solid #CBD5E1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0 auto 0.35rem;
            transition: all 0.2s;
        }

        .step-item.active .step-bubble {
            background: var(--accent);
            color: #0A1C33;
            border-color: var(--accent);
        }

        .step-item.completed .step-bubble {
            background: var(--success);
            color: #FFFFFF;
            border-color: var(--success);
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748B;
        }

        .step-item.active .step-label {
            color: var(--primary);
            font-weight: 700;
        }

        .wizard-step {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .wizard-step.active {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        .form-control {
            width: 100%;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            padding: 0.55rem 0.75rem;
            font-size: 0.9rem;
            color: var(--text-main);
            outline: none;
            transition: border-color 0.15s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(15, 42, 74, 0.1);
        }

        .wizard-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Previsualizador Derecha (40%) */
        .preview-pane {
            position: sticky;
            top: 5rem;
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 6.5rem);
        }

        .preview-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F8FAFC;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .preview-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-body {
            padding: 1.75rem;
            overflow-y: auto;
            flex: 1;
            background: #FFFFFF;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #334155;
            position: relative;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 3.5rem;
            font-weight: 900;
            color: rgba(15, 42, 74, 0.05);
            pointer-events: none;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .preview-doc-title {
            text-align: center;
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--primary);
            margin-bottom: 0.35rem;
            text-transform: uppercase;
        }

        .preview-doc-subtitle {
            text-align: center;
            font-size: 0.8rem;
            color: #64748B;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .preview-clause-title {
            font-weight: 700;
            color: var(--primary);
            margin-top: 1rem;
            margin-bottom: 0.35rem;
        }

        .highlight-var {
            background: #FEF3C7;
            padding: 0 3px;
            border-radius: 3px;
            font-weight: 600;
            color: #92400E;
        }

        @media (max-width: 1024px) {
            .wizard-container {
                grid-template-columns: 1fr;
            }
            .preview-pane {
                position: static;
                max-height: 500px;
            }
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
            <div style="font-size: 0.9rem; color: #64748B;">
                <span class="card-category <?= htmlspecialchars($tpl['categoria']) ?>"><?= ucfirst(htmlspecialchars($tpl['categoria'])) ?></span> 
                <?= htmlspecialchars($tpl['nombre']) ?>
            </div>
            <div>
                <a href="index.php" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;">
                    <i class="fas fa-times"></i> Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Wizard Universal Split View 60/40 -->
    <main class="wizard-container">
        
        <!-- Formulario Izquierda (60%) -->
        <div class="form-pane">
            <div class="step-progress-bar">
                <div id="pStep1" class="step-item active">
                    <div class="step-bubble">1</div>
                    <div class="step-label" id="lblStep1">Titular / Parte A</div>
                </div>
                <div id="pStep2" class="step-item">
                    <div class="step-bubble">2</div>
                    <div class="step-label" id="lblStep2">Contraparte B</div>
                </div>
                <div id="pStep3" class="step-item">
                    <div class="step-bubble">3</div>
                    <div class="step-label" id="lblStep3">Detalles</div>
                </div>
                <div id="pStep4" class="step-item">
                    <div class="step-bubble">4</div>
                    <div class="step-label" id="lblStep4">Revisión & Pago</div>
                </div>
            </div>

            <form id="universalWizardForm" onsubmit="event.preventDefault();">
                
                <!-- PASO 1: PARTE A / TITULAR -->
                <div id="step1" class="wizard-step active">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;" id="step1Title">
                        Paso 1: Datos del Titular / Primer Compareciente
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;" id="step1Desc">
                        Ingresa los datos de identificación oficial. Se validará con RUT Módulo 11 para la firma electrónica.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label id="lbl_parte_a_nombre">Nombre Completo *</label>
                            <input type="text" id="f_parte_a_nombre" class="form-control" placeholder="Ej: Juan Andrés Pérez González" value="Juan Andrés Pérez González">
                        </div>
                        <div class="form-group">
                            <label>RUT (Módulo 11) *</label>
                            <input type="text" id="f_parte_a_rut" class="form-control" placeholder="12.345.678-5" value="12.345.678-5" maxlength="12">
                            <span id="err_parte_a_rut" class="rut-error-text">RUT inválido</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nacionalidad y Estado Civil *</label>
                            <input type="text" id="f_parte_a_civil" class="form-control" placeholder="Ej: chileno, soltero" value="chileno, soltero">
                        </div>
                        <div class="form-group">
                            <label>Profesión u Oficio *</label>
                            <input type="text" id="f_parte_a_profesion" class="form-control" placeholder="Ej: Ingeniero Comercial" value="Ingeniero Comercial">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Correo Electrónico (Para recibir enlace de firma) *</label>
                            <input type="email" id="f_parte_a_email" class="form-control" placeholder="juan.perez@ejemplo.cl" value="juan.perez@ejemplo.cl">
                        </div>
                        <div class="form-group">
                            <label>Teléfono WhatsApp *</label>
                            <input type="tel" id="f_parte_a_tel" class="form-control" placeholder="+56 9 9123 4567" value="+56 9 9123 4567">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Domicilio Particular *</label>
                            <input type="text" id="f_parte_a_domicilio" class="form-control" placeholder="Av. Providencia 1234, Depto 402" value="Av. Providencia 1234, Depto 402">
                        </div>
                        <div class="form-group">
                            <label>Comuna y Ciudad *</label>
                            <input type="text" id="f_parte_a_comuna" class="form-control" placeholder="Providencia, Santiago" value="Providencia, Santiago">
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <div></div>
                        <button type="button" class="btn-cta" onclick="nextUniversalStep(2)">
                            Siguiente Paso <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 2: PARTE B / CONTRAPARTE (Omitible en declaraciones unipersonales) -->
                <div id="step2" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;" id="step2Title">
                        Paso 2: Datos de la Contraparte
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;" id="step2Desc">
                        Ingresa los datos de la segunda parte compareciente para el despacho de su enlace de firma.
                    </p>

                    <div id="parteBFieldsWrap">
                        <div class="form-row">
                            <div class="form-group">
                                <label id="lbl_parte_b_nombre">Nombre Completo de la Contraparte *</label>
                                <input type="text" id="f_parte_b_nombre" class="form-control" placeholder="Ej: María José Valenzuela" value="María José Valenzuela">
                            </div>
                            <div class="form-group">
                                <label>RUT de la Contraparte (Módulo 11) *</label>
                                <input type="text" id="f_parte_b_rut" class="form-control" placeholder="15.444.555-6" value="15.444.555-6" maxlength="12">
                                <span id="err_parte_b_rut" class="rut-error-text">RUT inválido</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Nacionalidad y Estado Civil *</label>
                                <input type="text" id="f_parte_b_civil" class="form-control" placeholder="Ej: chilena, casada" value="chilena, soltera">
                            </div>
                            <div class="form-group">
                                <label>Profesión u Oficio *</label>
                                <input type="text" id="f_parte_b_profesion" class="form-control" placeholder="Ej: Médico Cirujano" value="Abogada">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Domicilio Particular de la Contraparte *</label>
                                <input type="text" id="f_parte_b_domicilio" class="form-control" placeholder="Ej: Av. Apoquindo 4500, Las Condes" value="Av. Apoquindo 4500, Depto 801, Las Condes">
                            </div>
                            <div class="form-group">
                                <label>Comuna y Ciudad *</label>
                                <input type="text" id="f_parte_b_comuna" class="form-control" placeholder="Las Condes, Santiago" value="Las Condes, Santiago">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Correo Electrónico de la Contraparte *</label>
                                <input type="email" id="f_parte_b_email" class="form-control" placeholder="mj.valenzuela@ejemplo.cl" value="mj.valenzuela@ejemplo.cl">
                            </div>
                            <div class="form-group">
                                <label>WhatsApp de la Contraparte *</label>
                                <input type="tel" id="f_parte_b_tel" class="form-control" placeholder="+56 9 8765 4321" value="+56 9 8765 4321">
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje para declaraciones juradas unipersonales -->
                    <div id="unipersonalNotice" style="display:none; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1.25rem; text-align:center;">
                        <i class="fas fa-user-check fa-2x" style="color:var(--success); margin-bottom:0.5rem;"></i>
                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary);">Trámite Unipersonal</h4>
                        <p style="font-size:0.85rem; color:#64748B;">Este documento solo requiere la comparecencia y firma del titular declarante (no intervienen contrapartes adicionales).</p>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevUniversalStep(1)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" class="btn-cta" onclick="nextUniversalStep(3)">
                            Siguiente: Condiciones <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 3: DETALLES, CONDICIONES Y VARIABLES ESPECÍFICAS -->
                <div id="step3" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 3: Condiciones Específicas del Documento
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Configura las cláusulas, montos, fechas y destinos particulares de este instrumento.
                    </p>

                    <div id="dynamicSpecificFields">
                        <!-- Campos inyectados según plantilla -->
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevUniversalStep(2)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" class="btn-cta" onclick="nextUniversalStep(4)">
                            Siguiente: Revisar y Finalizar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 4: REVISIÓN Y DESPACHO -->
                <div id="step4" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 4: Resumen de la Orden y Emisión
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Al confirmar, se emitirá el borrador protegido con sellado criptográfico y código único QR.
                    </p>

                    <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; font-size:0.88rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem;">
                            <span><strong>Trámite:</strong> <?= htmlspecialchars($tpl['nombre']) ?></span>
                            <strong>$<?= number_format($tpl['precio_base'], 0, ',', '.') ?> CLP</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem; color:#64748B;">
                            <span>Firma Electrónica Avanzada / Simple (Ley 19.799)</span>
                            <strong style="color:#059669;">INCLUIDA</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem; color:#64748B;">
                            <span>Custodia Cifrada en Bóveda Cloudflare R2</span>
                            <strong style="color:#059669;">INCLUIDA</strong>
                        </div>
                        <div style="border-top:1px solid #CBD5E1; padding-top:0.75rem; display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; color:var(--primary);">
                            <span>Total a Pagar:</span>
                            <span>$<?= number_format($tpl['precio_base'], 0, ',', '.') ?> CLP</span>
                        </div>
                    </div>

                    <div style="margin-bottom:1.5rem;">
                        <label style="display:flex; align-items:flex-start; gap:0.5rem; font-size:0.85rem; color:#475569; cursor:pointer;">
                            <input type="checkbox" id="chk_terminos" checked style="margin-top:2px;">
                            <span>Declaro que los antecedentes proporcionados son fidedignos y acepto celebrar este instrumento privado con validez jurídica según la Ley N° 19.799.</span>
                        </label>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevUniversalStep(3)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" id="btnEmitirUniversal" class="btn-cta btn-gold" onclick="emitirUniversalFinal()">
                            <i class="fas fa-shield-alt"></i> Emitir Documento y Despachar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Previsualizador Derecha (40%) -->
        <div class="preview-pane">
            <div class="preview-header">
                <h3><i class="far fa-file-alt"></i> Vista Previa en Vivo</h3>
                <span class="card-category" style="background:#FEF3C7; color:#92400E; font-size:0.7rem;">
                    Borrador Dinámico
                </span>
            </div>
            
            <div class="preview-body" id="universalDocPreview">
                <div class="watermark">BORRADOR</div>

                <div class="preview-doc-title" id="pv_titulo_documento"><?= htmlspecialchars($tpl['nombre']) ?></div>
                <div class="preview-doc-subtitle" id="pv_subtitulo_legal"><?= htmlspecialchars($tpl['alerta_legal']) ?></div>

                <div id="pv_dynamic_body_content">
                    <!-- Contenido dinámico del contrato renderizado letra por letra -->
                </div>

                <div id="pv_signers_block" style="margin-top: 2rem; border-top: 1px dashed #CBD5E1; padding-top: 1rem; display: flex; justify-content: space-around; text-align: center; font-size: 0.75rem; color: #64748B;">
                    <div>
                        <div style="border: 1px solid #CBD5E1; border-radius: 4px; padding: 0.5rem 1rem; background: #F8FAFC;">[Firma Digital Pendiente]</div>
                        <div style="margin-top: 0.25rem; font-weight: 600;" id="pv_sign_parte_a">Juan Andrés Pérez González</div>
                    </div>
                    <div id="pv_sign_parte_b_wrap">
                        <div style="border: 1px solid #CBD5E1; border-radius: 4px; padding: 0.5rem 1rem; background: #F8FAFC;">[Firma Digital Pendiente]</div>
                        <div style="margin-top: 0.25rem; font-weight: 600;" id="pv_sign_parte_b">María José Valenzuela</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Scripts Universal Wizard -->
    <script src="assets/js/app.js?v=20261106_6"></script>
    <script>
        // Funciones auxiliares seguras locales
        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        const CURRENT_TEMPLATE = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
        let currentStep = 1;
        const isUnipersonal = ['declaracion-jurada-residencia', 'declaracion-jurada-ingresos-informales', 'salvoconducto-mudanza', 'autorizacion-domicilio-tributario-sii'].includes(CURRENT_TEMPLATE.slug);

        function initUniversalWizard() {
            setupTemplateFields();

            // Rut validation setup defensivo
            const rutA = document.getElementById('f_parte_a_rut');
            const errA = document.getElementById('err_parte_a_rut');
            if (rutA && typeof RutHelper !== 'undefined') {
                RutHelper.attachToInput(rutA, errA);
            }

            const rutB = document.getElementById('f_parte_b_rut');
            const errB = document.getElementById('err_parte_b_rut');
            if (rutB && typeof RutHelper !== 'undefined') {
                RutHelper.attachToInput(rutB, errB);
            }

            bindUniversalPreviewSync();
            updateUniversalPreview();
        }

        // Ejecución inmediata o diferida según estado del DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initUniversalWizard);
        } else {
            initUniversalWizard();
        }

        function setupTemplateFields() {
            const specificWrap = document.getElementById('dynamicSpecificFields');
            const parteBWrap = document.getElementById('parteBFieldsWrap');
            const unipersonalNotice = document.getElementById('unipersonalNotice');
            const signBWrap = document.getElementById('pv_sign_parte_b_wrap');

            // Ajustar si es unipersonal
            if (isUnipersonal) {
                if (parteBWrap) parteBWrap.style.display = 'none';
                if (unipersonalNotice) unipersonalNotice.style.display = 'block';
                if (signBWrap) signBWrap.style.display = 'none';
                document.getElementById('lblStep1').innerText = 'Declarante';
                document.getElementById('lblStep2').innerText = 'Tipo Trámite';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Declarante';
            }

            // Campos específicos según slug
            let fieldsHtml = '';

            switch (CURRENT_TEMPLATE.slug) {
                case 'contrato-arriendo-ley-21461':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Dirección del Inmueble a Arrendar *</label>
                            <input type="text" id="f_arr_direccion" class="form-control" value="Av. Providencia 1234, Depto 402, Providencia">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Renta Mensual de Arriendo ($ CLP) *</label>
                                <input type="number" id="f_arr_renta" class="form-control" value="650000">
                            </div>
                            <div class="form-group">
                                <label>Garantía de Arriendo ($ CLP) *</label>
                                <input type="number" id="f_arr_garantia" class="form-control" value="650000">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Día de Pago Mensual (1 al 31) *</label>
                                <input type="number" id="f_arr_dia_pago" class="form-control" value="5" min="1" max="31">
                            </div>
                            <div class="form-group">
                                <label>Duración del Contrato (Meses) *</label>
                                <input type="number" id="f_arr_duracion" class="form-control" value="12">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nombre y RUT del Aval / Codeudor Solidario (Opcional)</label>
                            <input type="text" id="f_arr_aval" class="form-control" placeholder="Ej: Carlos Silva - 11.222.333-4">
                        </div>
                    `;
                    break;

                case 'promesa-compraventa-inmueble':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Dirección del Inmueble Objeto de la Promesa *</label>
                            <input type="text" id="f_propiedad_dir" class="form-control" value="Av. Las Condes 8900, Depto 1204, Las Condes, Santiago">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Inscripción CBR (Fojas, Número y Año) *</label>
                                <input type="text" id="f_prop_cbr" class="form-control" value="Fojas 12.345 N° 18.920 del año 2018 del CBR de Santiago">
                            </div>
                            <div class="form-group">
                                <label>Rol de Avalúo Fiscal (SII) *</label>
                                <input type="text" id="f_prop_rol" class="form-control" value="3456-78 de Las Condes">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Precio Total Acordado (en UF o CLP) *</label>
                                <input type="text" id="f_precio_total" class="form-control" value="4.500 UF">
                            </div>
                            <div class="form-group">
                                <label>Monto de Arras / Garantía de la Promesa *</label>
                                <input type="text" id="f_arras" class="form-control" value="450 UF (10% del precio total)">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Forma de Pago del Saldo de Precio *</label>
                                <select id="f_forma_pago" class="form-control">
                                    <option value="Contado con fondos propios mediante vale vista bancario" selected>Contado (Vale Vista / Transferencia)</option>
                                    <option value="Crédito hipotecario aprobado en banco o institución financiera">Crédito Hipotecario Bancario</option>
                                    <option value="Mixto (Pie con fondos propios + Saldo con crédito hipotecario)">Mixto (Pie + Crédito Hipotecario)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Plazo Máximo para Escriturar Compraventa Definitiva *</label>
                                <input type="date" id="f_fecha_limite" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Multa por Desistimiento (Cláusula Penal) *</label>
                                <input type="text" id="f_multa_penal" class="form-control" value="10% del precio total convenido">
                            </div>
                            <div class="form-group">
                                <label>Oportunidad de Entrega Material del Inmueble *</label>
                                <select id="f_entrega_inmueble" class="form-control">
                                    <option value="Contra inscripción de dominio a nombre del comprador en el CBR" selected>Al quedar inscrito en el Conservador (CBR)</option>
                                    <option value="Al momento de la suscripción de la escritura pública definitiva">Al firmar escritura pública definitiva</option>
                                    <option value="Entrega anticipada a la firma de la presente promesa">Entrega material anticipada</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gastos de Notaría, Conservador e Impuestos *</label>
                                <select id="f_gastos_cbr" class="form-control">
                                    <option value="Ambas partes por mitades en Notaría; Comprador íntegro en inscripción CBR" selected>Notaría por mitades / CBR Comprador (Estándar)</option>
                                    <option value="100% de cargo exclusivo del Promitente Comprador">100% Cargo del Comprador</option>
                                    <option value="100% de los gastos divididos en partes iguales">50% y 50% entre ambas partes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Comisión de Corretaje de Propiedades (Opcional)</label>
                                <input type="text" id="f_corretaje" class="form-control" placeholder="Ej: 2% más IVA pagadero por cada parte" value="2% más IVA pagadero a la firma de escritura">
                            </div>
                        </div>
                    `;
                    break;

                case 'finiquito-laboral-mutuo-acuerdo':
                    fieldsHtml = `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Cargo Desempeñado por el Trabajador *</label>
                                <input type="text" id="f_cargo" class="form-control" value="Analista de Operaciones">
                            </div>
                            <div class="form-group">
                                <label>Fecha de Término de Relación Laboral *</label>
                                <input type="date" id="f_fecha_termino" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Causal Legal de Término *</label>
                                <select id="f_causal" class="form-control">
                                    <option value="Art. 159 N° 1 Mutuo acuerdo de las partes" selected>Art. 159 N° 1 Mutuo acuerdo de las partes</option>
                                    <option value="Art. 161 Necesidades de la empresa">Art. 161 Necesidades de la empresa</option>
                                    <option value="Art. 159 N° 4 Vencimiento del plazo">Art. 159 N° 4 Vencimiento del plazo</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Monto Total Líquido a Pagar ($ CLP) *</label>
                                <input type="number" id="f_monto_finiquito" class="form-control" value="1450000">
                            </div>
                        </div>
                    `;
                    break;

                case 'acuerdo-confidencialidad-nda':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Propósito del Acuerdo / Proyecto en Evaluación *</label>
                            <input type="text" id="f_proyecto_nda" class="form-control" value="Desarrollo conjunto de plataforma fintech y arquitectura cloud">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vigencia de la Confidencialidad *</label>
                                <select id="f_vigencia_nda" class="form-control">
                                    <option value="2 años" selected>2 años a partir de la firma</option>
                                    <option value="3 años">3 años a partir de la firma</option>
                                    <option value="5 años">5 años a partir de la firma</option>
                                    <option value="Indefinida para secretos industriales">Indefinida para secretos industriales</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cláusula Penal por Infracción ($ CLP) *</label>
                                <input type="text" id="f_multa_nda" class="form-control" value="500 UF">
                            </div>
                        </div>
                    `;
                    break;

                case 'prestacion-servicios-honorarios':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Descripción del Servicio Profesional *</label>
                            <input type="text" id="f_servicio_desc" class="form-control" value="Asesoría tributaria, financiera y control de gestión contable">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Honorario Mensual Bruto ($ CLP) *</label>
                                <input type="number" id="f_honorario_monto" class="form-control" value="850000">
                            </div>
                            <div class="form-group">
                                <label>Retención de Boleta de Honorarios *</label>
                                <select id="f_retencion" class="form-control">
                                    <option value="Con retención legal efectuada por el mandante" selected>Con retención efectuada por mandante</option>
                                    <option value="Con pago provisional directo del profesional">Pago provisional directo del profesional</option>
                                </select>
                            </div>
                        </div>
                    `;
                    break;

                case 'declaracion-jurada-residencia':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Dirección Exacta del Domicilio Declarado *</label>
                            <input type="text" id="f_decl_domicilio" class="form-control" value="Pasaje Los Copihues 456, Maipú, Santiago">
                        </div>
                        <div class="form-group">
                            <label>Institución o Fin para el cual se Presenta *</label>
                            <input type="text" id="f_decl_destino" class="form-control" value="Acreditación de residencia para trámites bancarios y escolares">
                        </div>
                    `;
                    break;

                case 'salvoconducto-mudanza':
                    fieldsHtml = `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dirección de Origen (Desde donde se traslada) *</label>
                                <input type="text" id="f_mud_origen" class="form-control" value="Av. Grecia 1234, Ñuñoa">
                            </div>
                            <div class="form-group">
                                <label>Dirección de Destino (Hacia donde se traslada) *</label>
                                <input type="text" id="f_mud_destino" class="form-control" value="Av. El Bosque 567, Viña del Mar">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Fecha Programada del Traslado *</label>
                                <input type="date" id="f_mud_fecha" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Datos del Vehículo o Empresa de Flete *</label>
                                <input type="text" id="f_mud_vehiculo" class="form-control" value="Camión Hyundai HD78 patente XX-YY-12">
                            </div>
                        </div>
                    `;
                    break;

                case 'acta-entrega-llaves-e-inventario':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Dirección del Inmueble Entregado *</label>
                            <input type="text" id="f_acta_direccion" class="form-control" value="Av. Providencia 1234, Depto 402, Providencia">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Cantidad de Juegos de Llaves Entregados *</label>
                                <input type="number" id="f_acta_llaves" class="form-control" value="3" min="1">
                            </div>
                            <div class="form-group">
                                <label>Lectura Medidor Eléctrico (kWh) *</label>
                                <input type="text" id="f_acta_luz" class="form-control" value="14.850 kWh">
                            </div>
                            <div class="form-group">
                                <label>Lectura Medidor Agua (m³) *</label>
                                <input type="text" id="f_acta_agua" class="form-control" value="1.230 m³">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Estado de Pintura, Muros e Instalaciones *</label>
                            <input type="text" id="f_acta_estado" class="form-control" value="Pintura en buen estado general, sin daños estructurales, grifería y artefactos operativos">
                        </div>
                        <div class="form-group">
                            <label>Inventario de Bienes Muebles y Artefactos Incluidos</label>
                            <textarea id="f_acta_inventario" class="form-control" rows="2">Cocina encimera 4 platos, horno eléctrico empotrado, campana extractora, cortinas roller en living y dormitorios.</textarea>
                        </div>
                    `;
                    break;

                case 'contrato-individual-trabajo':
                    fieldsHtml = `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Cargo o Función a Desempeñar *</label>
                                <input type="text" id="f_trab_cargo" class="form-control" value="Desarrollador Full Stack / Consultor TI">
                            </div>
                            <div class="form-group">
                                <label>Jornada Semanal (Ley 40 Horas - Ley 21.561) *</label>
                                <select id="f_trab_jornada" class="form-control">
                                    <option value="40 horas semanales distribuidas de lunes a viernes" selected>40 Horas Semanales (Lunes a Viernes)</option>
                                    <option value="42 horas semanales en régimen de transición">42 Horas Semanales (Transición 2026)</option>
                                    <option value="Artículo 22 inciso 2° sin fiscalización superior">Artículo 22 inc. 2° (Excluido de fiscalización)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Sueldo Base Mensual Bruto ($ CLP) *</label>
                                <input type="number" id="f_trab_sueldo" class="form-control" value="1200000">
                            </div>
                            <div class="form-group">
                                <label>Gratificación Legal *</label>
                                <select id="f_trab_gratificacion" class="form-control">
                                    <option value="25% según Art. 47 y 50 del Código del Trabajo (tope 4.75 IMM)" selected>Art. 50 (25% con tope 4,75 IMM)</option>
                                    <option value="Prorrateada según utilidades líquidas del ejercicio">Según utilidades anuales Art. 47</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Comuna y Lugar de Prestación de Servicios *</label>
                                <input type="text" id="f_trab_lugar" class="form-control" value="Las Condes, Santiago (modalidad híbrida / teletrabajo)">
                            </div>
                            <div class="form-group">
                                <label>Tipo de Contrato *</label>
                                <select id="f_trab_tipo" class="form-control">
                                    <option value="Indefinido" selected>Plazo Indefinido</option>
                                    <option value="Plazo Fijo de 3 meses">Plazo Fijo (3 meses)</option>
                                    <option value="Plazo Fijo de 1 año">Plazo Fijo (1 año)</option>
                                </select>
                            </div>
                        </div>
                    `;
                    break;

                case 'reconocimiento-deuda-pagare-privado':
                    fieldsHtml = `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Monto Total de la Deuda Reconocida ($ CLP) *</label>
                                <input type="number" id="f_deuda_total" class="form-control" value="3500000">
                            </div>
                            <div class="form-group">
                                <label>Número de Cuotas Mensuales Convenidas *</label>
                                <input type="number" id="f_deuda_cuotas" class="form-control" value="10" min="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Causa u Origen de la Deuda *</label>
                                <input type="text" id="f_deuda_causa" class="form-control" value="Saldo insoluto por compraventa de mercaderías y servicios comerciales">
                            </div>
                            <div class="form-group">
                                <label>Día de Vencimiento de Cada Cuota (1 al 31) *</label>
                                <input type="number" id="f_deuda_dia_pago" class="form-control" value="10" min="1" max="31">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Cláusula de Aceleración y Pagaré en Garantía *</label>
                            <select id="f_deuda_pagare" class="form-control">
                                <option value="Con Cláusula de Aceleración ipso facto e interés penal máximo convencional" selected>Con Cláusula de Aceleración + Interés Máximo Convencional (Ley 18.010)</option>
                                <option value="Sin intereses penales adicionales">Sin interés moratorio adicional</option>
                            </select>
                        </div>
                    `;
                    break;

                case 'declaracion-jurada-ingresos-informales':
                    fieldsHtml = `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Actividad u Oficio Generador de Ingresos *</label>
                                <input type="text" id="f_ingr_actividad" class="form-control" value="Comercio independiente, venta de productos y servicios autónomos">
                            </div>
                            <div class="form-group">
                                <label>Ingreso Promedio Mensual Declarado ($ CLP) *</label>
                                <input type="number" id="f_ingr_monto" class="form-control" value="950000">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Institución o Finalidad para la cual se Declara *</label>
                            <input type="text" id="f_ingr_destino" class="form-control" value="Acreditación de solvencia económica para postulación a arriendo y créditos">
                        </div>
                    `;
                    break;

                case 'poder-especial-privado':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Facultades y Gestiones Específicas Delegadas *</label>
                            <textarea id="f_poder_facultades" class="form-control" rows="2">Representar al mandante ante empresas de telecomunicaciones, bancos para trámites de mesón, retiro de encomiendas en Chilexpress y Correos de Chile, y trámites municipales.</textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vigencia del Poder Especial *</label>
                                <select id="f_poder_vigencia" class="form-control">
                                    <option value="6 meses contados desde la firma" selected>6 Meses a contar de la firma</option>
                                    <option value="1 año contados desde la firma">1 Año a contar de la firma</option>
                                    <option value="Válido exclusivamente para una única gestión">Para una única gestión específica</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Facultad de Sustituir / Delegar *</label>
                                <select id="f_poder_delegar" class="form-control">
                                    <option value="Sin facultad de delegación ni sustitución" selected>No permite delegar en terceros</option>
                                    <option value="Con facultad expresa de delegar en terceros">Permite delegar en terceros</option>
                                </select>
                            </div>
                        </div>
                    `;
                    break;

                case 'autorizacion-domicilio-tributario-sii':
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Dirección del Inmueble Cedido como Domicilio Tributario *</label>
                            <input type="text" id="f_sii_direccion" class="form-control" value="Av. Andrés Bello 2711, Piso 8, Las Condes, Santiago">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Rol de Avalúo Fiscal del Inmueble (SII) *</label>
                                <input type="text" id="f_sii_rol" class="form-control" value="1234-56 de Las Condes">
                            </div>
                            <div class="form-group">
                                <label>Razón Social y RUT de la Empresa Autorizada *</label>
                                <input type="text" id="f_sii_empresa" class="form-control" value="Inversiones & Consultorías SpA - RUT 77.888.999-0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Modalidad de Autorización ante el SII *</label>
                            <select id="f_sii_modalidad" class="form-control">
                                <option value="Comodato de uso gratuito de inmueble (Art. 2174 Código Civil)" selected>Comodato Gratuito (Préstamo de uso sin renta - Art. 2174 CC)</option>
                                <option value="Autorización expresa del arrendador para subarriendo o cesión de oficina">Autorización del Arrendador para Domicilio Tributario</option>
                            </select>
                        </div>
                    `;
                    break;

                default:
                    // Genérico para reconocimiento de deuda, poderes, contrato trabajo, etc.
                    fieldsHtml = `
                        <div class="form-group">
                            <label>Materia u Objeto Principal del Instrumento *</label>
                            <input type="text" id="f_gen_objeto" class="form-control" value="Celebración de acuerdo y compromiso voluntario entre las partes">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Monto o Compromiso ($ CLP o UF)</label>
                                <input type="text" id="f_gen_monto" class="form-control" value="$1.000.000 CLP">
                            </div>
                            <div class="form-group">
                                <label>Plazo de Cumplimiento / Vigencia</label>
                                <input type="text" id="f_gen_plazo" class="form-control" value="12 Meses">
                            </div>
                        </div>
                    `;
                    break;
            }

            specificWrap.innerHTML = fieldsHtml;

            // Fecha por defecto hoy
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = specificWrap.querySelectorAll('input[type="date"]');
            dateInputs.forEach(d => { if (!d.value) d.value = today; });
        }

        function bindUniversalPreviewSync() {
            const form = document.getElementById('universalWizardForm');
            if (form) {
                form.addEventListener('input', updateUniversalPreview);
                form.addEventListener('change', updateUniversalPreview);
            }
        }

        function updateUniversalPreview() {
            const nomA = document.getElementById('f_parte_a_nombre')?.value || 'Juan Andrés Pérez González';
            const rutA = document.getElementById('f_parte_a_rut')?.value || '12.345.678-5';
            const civilA = document.getElementById('f_parte_a_civil')?.value || 'chileno, soltero';
            const profA = document.getElementById('f_parte_a_profesion')?.value || 'Ingeniero Comercial';
            const domA = document.getElementById('f_parte_a_domicilio')?.value || 'Av. Providencia 1234, Depto 402';
            const comA = document.getElementById('f_parte_a_comuna')?.value || 'Providencia, Santiago';

            const nomB = document.getElementById('f_parte_b_nombre')?.value || 'María José Valenzuela';
            const rutB = document.getElementById('f_parte_b_rut')?.value || '15.444.555-6';
            const civilB = document.getElementById('f_parte_b_civil')?.value || 'chilena, soltera';
            const profB = document.getElementById('f_parte_b_profesion')?.value || 'Abogada';
            const domB = document.getElementById('f_parte_b_domicilio')?.value || 'Av. Apoquindo 4500, Depto 801, Las Condes';
            const comB = document.getElementById('f_parte_b_comuna')?.value || 'Las Condes, Santiago';

            // Actualizar firmas visuales
            const pvSignA = document.getElementById('pv_sign_parte_a');
            if (pvSignA) pvSignA.innerText = nomA;
            const pvSignB = document.getElementById('pv_sign_parte_b');
            if (pvSignB) pvSignB.innerText = nomB;

            const bodyContainer = document.getElementById('pv_dynamic_body_content');
            if (!bodyContainer) return;

            let html = '';

            if (isUnipersonal) {
                html = `
                    <p>En la ciudad de Santiago, República de Chile, a ${new Date().toLocaleDateString('es-CL')}, comparece Don/Doña <span class="highlight-var">${escapeHtml(nomA)}</span>, de nacionalidad y estado civil <span class="highlight-var">${escapeHtml(civilA)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profA)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutA)}</span>, domiciliado en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, quien en pleno ejercicio de sus derechos civiles y facultades viene en declarar libre y solemnemente lo siguiente:</p>
                    <div class="preview-clause-title">DECLARACIÓN PRIMERA</div>
                    <p>Que de conformidad a la legislación vigente y bajo juramento de veracidad, declara su voluntad y antecedentes para todo efecto legal ante quien corresponda.</p>
                `;
                if (CURRENT_TEMPLATE.slug === 'declaracion-jurada-residencia') {
                    const declDom = document.getElementById('f_decl_domicilio')?.value || domA;
                    const declFin = document.getElementById('f_decl_destino')?.value || 'fines pertinentes';
                    html += `
                        <div class="preview-clause-title">ACREDITACIÓN DE DOMICILIO</div>
                        <p>Que su residencia fija y morada habitual corresponde a la ubicada en <span class="highlight-var">${escapeHtml(declDom)}</span>, presentada para <span class="highlight-var">${escapeHtml(declFin)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'salvoconducto-mudanza') {
                    const origen = document.getElementById('f_mud_origen')?.value || 'Origen declarado';
                    const destino = document.getElementById('f_mud_destino')?.value || 'Destino declarado';
                    const vehiculo = document.getElementById('f_mud_vehiculo')?.value || 'Vehículo flete';
                    html += `
                        <div class="preview-clause-title">TRASLADO DE ENSERES (LEY N° 20.227)</div>
                        <p>Que trasladará sus muebles y enseres domésticos desde <span class="highlight-var">${escapeHtml(origen)}</span> con destino a <span class="highlight-var">${escapeHtml(destino)}</span>, mediante el vehículo <span class="highlight-var">${escapeHtml(vehiculo)}</span>, no existiendo impedimento legal ni contractual para dicha mudanza.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'declaracion-jurada-ingresos-informales') {
                    const activ = document.getElementById('f_ingr_actividad')?.value || 'Actividades comerciales independientes';
                    const montIng = document.getElementById('f_ingr_monto')?.value || '950000';
                    const destIng = document.getElementById('f_ingr_destino')?.value || 'fines pertinentes';
                    const ingFmt = new Intl.NumberFormat('es-CL').format(montIng);
                    html += `
                        <div class="preview-clause-title">DECLARACIÓN DE ACTIVIDADES E INGRESOS PERCIBIDOS</div>
                        <p>Que percibe legítimamente ingresos de carácter independiente derivados de <span class="highlight-var">${escapeHtml(activ)}</span> por un promedio mensual de <span class="highlight-var">$${ingFmt} CLP</span>, careciendo de relación de dependencia laboral subordinada.</p>
                        <div class="preview-clause-title">DESTINO Y RESPONSABILIDAD PENAL</div>
                        <p>La presente declaración se extiende para <span class="highlight-var">${escapeHtml(destIng)}</span>, asumiendo el declarante la responsabilidad civil y penal derivada del Art. 210 del Código Penal en caso de falsedad.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'autorizacion-domicilio-tributario-sii') {
                    const siiDir = document.getElementById('f_sii_direccion')?.value || domA;
                    const siiRol = document.getElementById('f_sii_rol')?.value || 'Rol de Avalúo SII';
                    const siiEmp = document.getElementById('f_sii_empresa')?.value || 'Empresa autorizada';
                    const siiMod = document.getElementById('f_sii_modalidad')?.value || 'Comodato de uso gratuito';
                    html += `
                        <div class="preview-clause-title">CLÁUSULA PRIMERA: Inmueble y Rol de Avalúo Fiscal</div>
                        <p>El Compareciente declara ser legítimo poseedor del inmueble de <span class="highlight-var">${escapeHtml(siiDir)}</span>, Rol de Avalúo SII N° <span class="highlight-var">${escapeHtml(siiRol)}</span>.</p>
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Autorización Expresa de Domicilio Tributario</div>
                        <p>En este acto otorga expresa y suficiente autorización para que <span class="highlight-var">${escapeHtml(siiEmp)}</span> fije y acredite su domicilio tributario, comercial y postal ante el Servicio de Impuestos Internos (SII) bajo la modalidad de <span class="highlight-var">${escapeHtml(siiMod)}</span>.</p>
                    `;
                }
            } else {
                html = `
                    <p>En la ciudad de Santiago, República de Chile, a ${new Date().toLocaleDateString('es-CL')}, comparecen:</p>
                    <p style="margin-top:0.6rem;"><strong>POR UNA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomA)}</span>, de nacionalidad y estado civil <span class="highlight-var">${escapeHtml(civilA)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profA)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutA)}</span>, domiciliado en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, en adelante indistintamente como la "Parte Promitente Vendedora" o "Primer Compareciente";</p>
                    <p style="margin-top:0.6rem;"><strong>Y POR LA OTRA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span>, de nacionalidad y estado civil <span class="highlight-var">${escapeHtml(civilB)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutB)}</span>, domiciliado en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante indistintamente como la "Parte Promitente Compradora" o "Segunda Compareciente";</p>
                    <p style="margin-top:0.6rem;">Ambos comparecientes mayores de edad, quienes acreditan su identidad con sus respectivas cédulas de identidad y declaran convenir en el otorgamiento del presente instrumento bajo las siguientes estipulaciones:</p>
                `;

                if (CURRENT_TEMPLATE.slug === 'contrato-arriendo-ley-21461') {
                    const arrDir = document.getElementById('f_arr_direccion')?.value || 'Inmueble arrendado';
                    const arrRenta = document.getElementById('f_arr_renta')?.value || '650000';
                    const arrGarantia = document.getElementById('f_arr_garantia')?.value || '650000';
                    const arrDia = document.getElementById('f_arr_dia_pago')?.value || '5';
                    const arrDur = document.getElementById('f_arr_duracion')?.value || '12';
                    const arrAval = document.getElementById('f_arr_aval')?.value || '';
                    const rentaFmt = new Intl.NumberFormat('es-CL').format(arrRenta);
                    const garFmt = new Intl.NumberFormat('es-CL').format(arrGarantia);
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Inmueble, Renta y Vigencia (Ley 21.461)</div>
                        <p>El Arrendador da en arrendamiento la propiedad ubicada en <span class="highlight-var">${escapeHtml(arrDir)}</span> por una renta mensual de <span class="highlight-var">$${rentaFmt} CLP</span> pagadera hasta el día <span class="highlight-var">${escapeHtml(arrDia)}</span> de cada mes, con garantía de <span class="highlight-var">$${garFmt} CLP</span> y duración de <span class="highlight-var">${escapeHtml(arrDur)} meses</span>.</p>
                        <p style="margin-top:0.4rem; font-size:0.82rem; color:#475569;">Las partes se someten al procedimiento monitorio de restitución rápida consagrado en la Ley N° 21.461 'Devuélveme Mi Casa'.</p>
                    `;
                    if (arrAval.trim()) {
                        html += `
                            <div class="preview-clause-title">CLÁUSULA TERCERA: Aval y Codeudor Solidario</div>
                            <p>Comparece además como fiador, aval y codeudor solidario Don/Doña <span class="highlight-var">${escapeHtml(arrAval)}</span> quien se obliga a todas las prestaciones emanadas del contrato.</p>
                        `;
                    }
                } else if (CURRENT_TEMPLATE.slug === 'promesa-compraventa-inmueble') {
                    const prop = document.getElementById('f_propiedad_dir')?.value || 'Av. Las Condes 8900, Depto 1204, Las Condes';
                    const cbr = document.getElementById('f_prop_cbr')?.value || 'Fojas 12.345 N° 18.920 del año 2018 del CBR de Santiago';
                    const rol = document.getElementById('f_prop_rol')?.value || '3456-78';
                    const precio = document.getElementById('f_precio_total')?.value || '4.500 UF';
                    const arras = document.getElementById('f_arras')?.value || '450 UF (10% del precio total)';
                    const formaPago = document.getElementById('f_forma_pago')?.value || 'Contado mediante vale vista bancario';
                    const fechaLim = document.getElementById('f_fecha_limite')?.value || 'Plazo máximo según condiciones';
                    const multa = document.getElementById('f_multa_penal')?.value || '10% del precio total';
                    const entrega = document.getElementById('f_entrega_inmueble')?.value || 'Contra inscripción de dominio a nombre del comprador en el CBR';
                    const gastos = document.getElementById('f_gastos_cbr')?.value || 'Ambas partes por mitades en Notaría; Comprador íntegro en inscripción CBR';
                    const corretaje = document.getElementById('f_corretaje')?.value || '';

                    html += `
                        <div class="preview-clause-title">CLÁUSULA PRIMERA: Individualización del Inmueble y Títulos de Dominio</div>
                        <p>El Promitente Vendedor declara ser único y exclusivo dueño del inmueble ubicado en <span class="highlight-var">${escapeHtml(prop)}</span>, inscrito a su nombre a <span class="highlight-var">${escapeHtml(cbr)}</span>, Rol de Avalúo Fiscal N° <span class="highlight-var">${escapeHtml(rol)}</span>.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Promesa Recíproca de Compraventa (Art. 1554 Código Civil)</div>
                        <p>Por el presente instrumento, el Promitente Vendedor promete, conviene y se obliga a vender, ceder y transferir, y el Promitente Comprador promete y se obliga a comprar, aceptar y adquirir para sí, el cien por ciento (100%) del inmueble singularizado en la cláusula precedente.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA TERCERA: Estado de Conservación, Gravámenes y Saneamiento</div>
                        <p>La compraventa se celebrará ad corpus, en el estado en que actualmente se encuentra la propiedad y que es plenamente conocida del Promitente Comprador, con todos sus derechos, usos, costumbres y servidumbres activas y pasivas, completamente libre de hipotecas, gravámenes, embargos, litigios, medidas precautorias, prohibiciones y derechos preferentes de terceros, con sus cuentas de servicios básicos, gastos comunes y contribuciones territoriales al día. El Promitente Vendedor quedará legalmente obligado al saneamiento de la evicción conforme a la ley.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA CUARTA: Precio Definitivo, Arras y Forma de Pago</div>
                        <p>El precio total y definitivo de la compraventa prometida asciende a la suma de <span class="highlight-var">${escapeHtml(precio)}</span>, el cual se solucionará en la siguiente forma: 
                        a) En garantía de seriedad y fiel cumplimiento de la promesa, el Promitente Comprador entrega en este acto la suma de <span class="highlight-var">${escapeHtml(arras)}</span> por concepto de arras penitenciales (Art. 1803 Código Civil); 
                        b) El saldo restante de precio se solucionará mediante <span class="highlight-var">${escapeHtml(formaPago)}</span>, quedando las instrucciones de pago resguardadas hasta que la propiedad se encuentre debidamente inscrita a nombre del comprador en el Conservador de Bienes Raíces respectivo libre de todo gravamen.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA QUINTA: Plazo, Condiciones Suspensivas y Fecha Límite de Escrituración</div>
                        <p>Serán condiciones suspensivas y copulativas para la suscripción de la compraventa definitiva: que los títulos de dominio se encuentren conformes a derecho y que el inmueble se encuentre apto para su libre enajenación. Cumplidas dichas condiciones, la escritura pública definitiva de compraventa se otorgará a más tardar el día <span class="highlight-var">${escapeHtml(fechaLim)}</span> en la Notaría que las partes designen. Para efectos del Artículo 1552 del Código Civil, el deudor quedará constituido en mora desde el vencimiento del plazo pactado sin necesidad de requerimiento judicial ni interpelación previa.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA SEXTA: Oportunidad de Entrega Material</div>
                        <p>La entrega material y física de la propiedad, desocupada de moradores y bienes, se verificará: <span class="highlight-var">${escapeHtml(entrega)}</span>.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA SÉPTIMA: Cláusula Penal Tasada y Perjuicios Convencionales</div>
                        <p>Si cualquiera de las partes se negare injustificadamente a concurrir a la suscripción de la escritura pública de compraventa definitiva dentro del plazo convenido, o incurriere en incumplimiento de las obligaciones estipuladas, la parte diligente y cumplidora podrá, a su exclusivo arbitrio: a) Exigir el cumplimiento forzado de la promesa con indemnización de perjuicios; o b) Resolver y tener por desistido el contrato, cobrando ipso facto a título de indemnización de perjuicios compensatoria tasada anticipadamente la cantidad equivalente a <span class="highlight-var">${escapeHtml(multa)}</span>.</p>
                        
                        <div class="preview-clause-title">CLÁUSULA OCTAVA: Gastos de Escrituración e Inscripciones</div>
                        <p>Las partes acuerdan que los gastos notariales, derechos arancelarios y derechos de inscripción registral se distribuirán en la siguiente forma: <span class="highlight-var">${escapeHtml(gastos)}</span>.</p>
                    `;
                    if (corretaje.trim()) {
                        html += `
                            <div class="preview-clause-title">CLÁUSULA NOVENA: Honorarios de Intermediación y Corretaje</div>
                            <p>Las partes dejan constancia que la presente operación fue intermediada por corredores profesionales, pactándose una comisión de <span class="highlight-var">${escapeHtml(corretaje)}</span>.</p>
                        `;
                    }
                } else if (CURRENT_TEMPLATE.slug === 'finiquito-laboral-mutuo-acuerdo') {
                    const cargo = document.getElementById('f_cargo')?.value || 'Cargo';
                    const causal = document.getElementById('f_causal')?.value || 'Mutuo acuerdo';
                    const monto = document.getElementById('f_monto_finiquito')?.value || '0';
                    const montoFmt = new Intl.NumberFormat('es-CL').format(monto);
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Término de la Relación Laboral</div>
                        <p>Las partes ponen término a la relación laboral correspondiente al cargo de <span class="highlight-var">${escapeHtml(cargo)}</span> por la causal de <span class="highlight-var">${escapeHtml(causal)}</span>, acordando un pago total y liquidación de <span class="highlight-var">$${montoFmt} CLP</span> con amplio finiquito.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'acuerdo-confidencialidad-nda') {
                    const proy = document.getElementById('f_proyecto_nda')?.value || 'Proyecto confidencial';
                    const vig = document.getElementById('f_vigencia_nda')?.value || '2 años';
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Secreto Profesional y Deber de Reserva</div>
                        <p>Las partes se comprometen a resguardar bajo estricta reserva toda información técnica, comercial y sensible vinculada al proyecto <span class="highlight-var">${escapeHtml(proy)}</span>, por una vigencia de <span class="highlight-var">${escapeHtml(vig)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'acta-entrega-llaves-e-inventario') {
                    const dirActa = document.getElementById('f_acta_direccion')?.value || domA;
                    const llaves = document.getElementById('f_acta_llaves')?.value || '3';
                    const luz = document.getElementById('f_acta_luz')?.value || '14.850 kWh';
                    const agua = document.getElementById('f_acta_agua')?.value || '1.230 m³';
                    const estado = document.getElementById('f_acta_estado')?.value || 'Buen estado';
                    const inv = document.getElementById('f_acta_inventario')?.value || 'Sin muebles';
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Entrega Material y Llaves</div>
                        <p>Las partes formalizan la entrega y recepción material del inmueble de <span class="highlight-var">${escapeHtml(dirActa)}</span>, haciendo entrega de <span class="highlight-var">${escapeHtml(llaves)} juegos de llaves</span> completos.</p>
                        <div class="preview-clause-title">CLÁUSULA TERCERA: Estado de Conservación e Inventario</div>
                        <p>Se deja constancia del estado: <span class="highlight-var">${escapeHtml(estado)}</span>. Lecturas de servicios: Electricidad: <span class="highlight-var">${escapeHtml(luz)}</span> | Agua: <span class="highlight-var">${escapeHtml(agua)}</span>. Bienes incorporados: <span class="highlight-var">${escapeHtml(inv)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'contrato-individual-trabajo') {
                    const cargo = document.getElementById('f_trab_cargo')?.value || 'Cargo';
                    const jornada = document.getElementById('f_trab_jornada')?.value || '40 horas semanales';
                    const sueldo = document.getElementById('f_trab_sueldo')?.value || '1200000';
                    const grat = document.getElementById('f_trab_gratificacion')?.value || 'Art. 50';
                    const lugar = document.getElementById('f_trab_lugar')?.value || 'Santiago';
                    const tipo = document.getElementById('f_trab_tipo')?.value || 'Indefinido';
                    const sueldoFmt = new Intl.NumberFormat('es-CL').format(sueldo);
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Servicios y Lugar de Desempeño</div>
                        <p>El Trabajador se compromete a prestar servicios en el cargo de <span class="highlight-var">${escapeHtml(cargo)}</span> en <span class="highlight-var">${escapeHtml(lugar)}</span> bajo contrato de duración <span class="highlight-var">${escapeHtml(tipo)}</span>.</p>
                        <div class="preview-clause-title">CLÁUSULA TERCERA: Jornada de Trabajo (Ley 40 Horas - Ley 21.561)</div>
                        <p>La jornada ordinaria será de <span class="highlight-var">${escapeHtml(jornada)}</span> con debido descanso para colación y registro de asistencia conforme a la DT.</p>
                        <div class="preview-clause-title">CLÁUSULA CUARTA: Remuneraciones y Gratificación</div>
                        <p>El Empleador pagará un sueldo base de <span class="highlight-var">$${sueldoFmt} CLP</span> mensuales brutos, más gratificación legal según modalidad de <span class="highlight-var">${escapeHtml(grat)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'reconocimiento-deuda-pagare-privado') {
                    const deuda = document.getElementById('f_deuda_total')?.value || '3500000';
                    const cuotas = document.getElementById('f_deuda_cuotas')?.value || '10';
                    const causa = document.getElementById('f_deuda_causa')?.value || 'Causa comercial';
                    const diaPago = document.getElementById('f_deuda_dia_pago')?.value || '10';
                    const pagare = document.getElementById('f_deuda_pagare')?.value || 'Con Cláusula de Aceleración';
                    const deudaFmt = new Intl.NumberFormat('es-CL').format(deuda);
                    const cuotaFmt = new Intl.NumberFormat('es-CL').format(Math.round(deuda / (cuotas || 1)));
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Reconocimiento Expreso de Deuda</div>
                        <p>El Deudor reconoce expresa, líquida e irrevocablemente adeudar al Acreedor la suma de <span class="highlight-var">$${deudaFmt} CLP</span> por concepto de <span class="highlight-var">${escapeHtml(causa)}</span>.</p>
                        <div class="preview-clause-title">CLÁUSULA TERCERA: Calendario de Pago y Cláusula de Aceleración</div>
                        <p>La deuda se cancelará en <span class="highlight-var">${escapeHtml(cuotas)} cuotas mensuales</span> de <span class="highlight-var">$${cuotaFmt} CLP</span> pagaderas hasta el día <span class="highlight-var">${escapeHtml(diaPago)}</span> de cada mes. Régimen de garantías: <span class="highlight-var">${escapeHtml(pagare)}</span> facultando la exigibilidad total inmediata ante simple retardo.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'poder-especial-privado') {
                    const fac = document.getElementById('f_poder_facultades')?.value || 'Gestiones administrativas';
                    const vig = document.getElementById('f_poder_vigencia')?.value || '6 meses';
                    const del = document.getElementById('f_poder_delegar')?.value || 'Sin facultad de delegar';
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Mandato y Facultades Especiales</div>
                        <p>El Mandante confiere poder especial suficiente al Mandatario para que en su nombre y representación realice: <span class="highlight-var">${escapeHtml(fac)}</span>.</p>
                        <div class="preview-clause-title">CLÁUSULA TERCERA: Vigencia y Delegación</div>
                        <p>El presente poder tendrá una vigencia de <span class="highlight-var">${escapeHtml(vig)}</span>, estipulándose expresamente que actúa <span class="highlight-var">${escapeHtml(del)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'prestacion-servicios-honorarios') {
                    const serv = document.getElementById('f_servicio_desc')?.value || 'Servicio';
                    const honor = document.getElementById('f_honorario_monto')?.value || '0';
                    const honorFmt = new Intl.NumberFormat('es-CL').format(honor);
                    html += `
                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: Prestación y Honorarios</div>
                        <p>El Profesional prestará los servicios de <span class="highlight-var">${escapeHtml(serv)}</span>, fijándose un honorario de <span class="highlight-var">$${honorFmt} CLP</span> brutos.</p>
                    `;
                }
            }

            html += `
                <div class="preview-clause-title">VALIDEZ LEGAL Y JURISDICCIÓN</div>
                <p>El presente documento produce plena fe probatoria conforme a la Ley N° 19.799 de Firma Electrónica, quedando custodiado en la bóveda criptográfica de SinNotario.cl.</p>
            `;

            bodyContainer.innerHTML = html;
        }

        function nextUniversalStep(step) {
            // Formatear y validar RUT parte A
            if (currentStep === 1) {
                const inputRutA = document.getElementById('f_parte_a_rut');
                if (inputRutA) {
                    if (typeof RutHelper !== 'undefined') {
                        inputRutA.value = RutHelper.format(inputRutA.value);
                    }
                    const rut = inputRutA.value;
                    const isValid = typeof RutHelper !== 'undefined' ? RutHelper.validate(rut) : rut.length >= 8;
                    if (!isValid) {
                        alert('Por favor ingresa un RUT válido con dígito verificador correcto.');
                        inputRutA.focus();
                        return;
                    }
                }
            } else if (currentStep === 2 && !isUnipersonal) {
                const inputRutB = document.getElementById('f_parte_b_rut');
                if (inputRutB) {
                    if (typeof RutHelper !== 'undefined') {
                        inputRutB.value = RutHelper.format(inputRutB.value);
                    }
                    const rut = inputRutB.value;
                    const isValid = typeof RutHelper !== 'undefined' ? RutHelper.validate(rut) : rut.length >= 8;
                    if (!isValid) {
                        alert('Por favor ingresa un RUT de Contraparte válido con dígito verificador correcto.');
                        inputRutB.focus();
                        return;
                    }
                }
            }

            goToUniversalStep(step);
        }

        function prevUniversalStep(step) {
            goToUniversalStep(step);
        }

        function goToUniversalStep(step) {
            document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));

            const targetStep = document.getElementById('step' + step);
            if (targetStep) targetStep.classList.add('active');

            for (let i = 1; i <= 4; i++) {
                const pItem = document.getElementById('pStep' + i);
                if (!pItem) continue;
                if (i < step) {
                    pItem.classList.add('completed');
                    pItem.classList.remove('active');
                } else if (i === step) {
                    pItem.classList.add('active');
                    pItem.classList.remove('completed');
                } else {
                    pItem.classList.remove('active', 'completed');
                }
            }

            currentStep = step;
            window.scrollTo({ top: 80, behavior: 'smooth' });
        }

        async function emitirUniversalFinal() {
            const chkTerminos = document.getElementById('chk_terminos');
            if (!chkTerminos || !chkTerminos.checked) {
                alert('Debes aceptar los Términos y Condiciones para emitir el trámite.');
                return;
            }

            const btn = document.getElementById('btnEmitirUniversal');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando borrador criptográfico...';

            const signers = [
                {
                    rol: isUnipersonal ? 'DECLARANTE' : 'PARTE_A',
                    nombre: document.getElementById('f_parte_a_nombre').value,
                    rut: document.getElementById('f_parte_a_rut').value,
                    email: document.getElementById('f_parte_a_email').value,
                    telefono: document.getElementById('f_parte_a_tel').value
                }
            ];

            if (!isUnipersonal) {
                signers.push({
                    rol: 'PARTE_B',
                    nombre: document.getElementById('f_parte_b_nombre').value,
                    rut: document.getElementById('f_parte_b_rut').value,
                    email: document.getElementById('f_parte_b_email').value,
                    telefono: document.getElementById('f_parte_b_tel').value
                });
            }

            const payload = {
                template_slug: CURRENT_TEMPLATE.slug,
                signers: signers,
                form_data: {
                    parte_a_nombre: document.getElementById('f_parte_a_nombre').value,
                    parte_a_rut: document.getElementById('f_parte_a_rut').value,
                    parte_a_email: document.getElementById('f_parte_a_email').value,
                    parte_a_tel: document.getElementById('f_parte_a_tel').value,
                    parte_a_domicilio: document.getElementById('f_parte_a_domicilio').value,
                    parte_a_comuna: document.getElementById('f_parte_a_comuna').value
                }
            };

            try {
                const res = await fetch('api/index.php?action=create_draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (json.success && json.data) {
                    alert(`¡Borrador Creado con Éxito!\n\nDocumento: ${CURRENT_TEMPLATE.nombre}\nCódigo Único: ${json.data.codigo_verificacion}\n\nPróximo paso: Activación del circuito de firma digital y pasarela de pago.`);
                    window.location.href = `index.php#verificar`;
                } else {
                    alert('Error al generar borrador: ' + (json.error || 'Error en servidor'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-shield-alt"></i> Emitir Documento y Despachar <i class="fas fa-arrow-right"></i>';
                }
            } catch (err) {
                alert('Error de conexión al emitir el documento.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt"></i> Emitir Documento y Despachar <i class="fas fa-arrow-right"></i>';
            }
        }
    </script>
</body>
</html>
