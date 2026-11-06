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
    <script src="assets/js/app.js?v=20261106_12"></script>
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
        const isUnipersonal = ['declaracion-jurada-residencia', 'declaracion-jurada-ingresos-informales', 'salvoconducto-mudanza'].includes(CURRENT_TEMPLATE.slug);

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

        function toggleFiniquitoFields() {
            const sel = document.getElementById('f_fini_modalidad');
            if (!sel) return;
            const val = sel.value;
            const wrapB = document.getElementById('wrap_fini_opcion_b');
            const wrapC = document.getElementById('wrap_fini_opcion_c');
            const help = document.getElementById('fini_modalidad_help');

            if (wrapB) wrapB.style.display = (val === 'opcion_b') ? 'block' : 'none';
            if (wrapC) wrapC.style.display = (val === 'opcion_c') ? 'block' : 'none';

            if (help) {
                if (val === 'opcion_a') {
                    help.innerText = 'Modelo estándar homologable ante la Dirección del Trabajo (portal Mi DT) con ClaveÚnica (6 cláusulas).';
                } else if (val === 'opcion_b') {
                    help.innerText = 'Modelo de blindaje patronal: incluye acreditación de cotizaciones (Ley Bustos N° 19.631) y declaración de no accidentes laborales (9 cláusulas).';
                } else if (val === 'opcion_c') {
                    help.innerText = 'Modelo transaccional extrajudicial (Art. 2446 Código Civil) con acápite formal de reserva de derechos según jurisprudencia de la Corte Suprema.';
                }
            }
            updateUniversalPreview();
        }

        function toggleServiciosFields() {
            const sel = document.getElementById('f_serv_modalidad');
            if (!sel) return;
            const val = sel.value;
            const wrapB = document.getElementById('wrap_serv_opcion_b');
            const wrapC = document.getElementById('wrap_serv_opcion_c');
            const help = document.getElementById('serv_modalidad_help');

            if (wrapB) wrapB.style.display = (val === 'opcion_b') ? 'block' : 'none';
            if (wrapC) wrapC.style.display = (val === 'opcion_c') ? 'block' : 'none';

            if (help) {
                if (val === 'opcion_a') {
                    help.innerText = 'Exclusión probatoria de relación laboral: plena autonomía técnica, libertad horaria, sin exclusividad ni subordinación (8 cláusulas).';
                } else if (val === 'opcion_b') {
                    help.innerText = 'Servicios por proyectos cerrados: pagos contra hitos aprobados, entregables, garantía técnica y soporte correctivo (7 cláusulas).';
                } else if (val === 'opcion_c') {
                    help.innerText = 'SLA Corporativo continuo: tiempos de respuesta calificados, penalizaciones por demora y sustitución de personal clave (9 cláusulas).';
                }
            }
            updateUniversalPreview();
        }

        function toggleDeudaFields() {
            const sel = document.getElementById('f_deuda_modalidad');
            if (!sel) return;
            const val = sel.value;
            const wrapB = document.getElementById('wrap_deuda_opcion_b');
            const wrapC = document.getElementById('wrap_deuda_opcion_c');
            const help = document.getElementById('deuda_modalidad_help');

            if (wrapB) wrapB.style.display = (val === 'opcion_b') ? 'block' : 'none';
            if (wrapC) wrapC.style.display = (val === 'opcion_c') ? 'block' : 'none';

            if (help) {
                if (val === 'opcion_a') {
                    help.innerText = 'Obligación pura y simple, aceleración ipso facto e intereses penales al máximo legal permitido (6 cláusulas).';
                } else if (val === 'opcion_b') {
                    help.innerText = 'Fianza, aval y codeuda solidaria: comparece un tercero obligado principal a todas las cuotas (8 cláusulas).';
                } else if (val === 'opcion_c') {
                    help.innerText = 'Transaccional extrajudicial previa a juicio: confesión judicial de deuda y pacto procesal Art. 434 N° 5 CPC (7 cláusulas).';
                }
            }
            updateUniversalPreview();
        }

        function toggleActaFields() {
            const sel = document.getElementById('f_acta_modalidad');
            if (!sel) return;
            const val = sel.value;
            const wrapB = document.getElementById('wrap_acta_opcion_b');
            const wrapC = document.getElementById('wrap_acta_opcion_c');
            const help = document.getElementById('acta_modalidad_help');

            if (wrapB) wrapB.style.display = (val === 'opcion_b') ? 'block' : 'none';
            if (wrapC) wrapC.style.display = (val === 'opcion_c') ? 'block' : 'none';

            if (help) {
                if (val === 'opcion_a') {
                    help.innerText = 'Acta definitiva: llaves, lecturas completas de medidores, inventario y conservación por recintos (6 secciones).';
                } else if (val === 'opcion_b') {
                    help.innerText = 'Recepción provisoria: inventario, lecturas y lista de observaciones menores con plazo de subsanación (5 secciones).';
                } else if (val === 'opcion_c') {
                    help.innerText = 'Desocupación final y restitución: constata estado de salida, descuentos y saldo líquido de garantía a restituir (5 secciones).';
                }
            }
            updateUniversalPreview();
        }

        function toggleSiiFields() {
            const sel = document.getElementById('f_sii_modalidad');
            if (!sel) return;
            const val = sel.value;
            const wrapB = document.getElementById('wrap_sii_opcion_b');
            const wrapC = document.getElementById('wrap_sii_opcion_c');
            const help = document.getElementById('sii_modalidad_help');
            const parteBWrap = document.getElementById('parteBFieldsWrap');
            const signBWrap = document.getElementById('pv_sign_parte_b_wrap');

            if (wrapB) wrapB.style.display = (val === 'opcion_b') ? 'block' : 'none';
            if (wrapC) wrapC.style.display = (val === 'opcion_c') ? 'block' : 'none';

            // Si es Opción C (Declaración Unipersonal del propietario), ocultar Parte B en el formulario y firma
            if (val === 'opcion_c') {
                if (parteBWrap) parteBWrap.style.display = 'none';
                if (signBWrap) signBWrap.style.display = 'none';
                document.getElementById('lblStep1').innerText = 'Propietario';
                document.getElementById('lblStep2').innerText = 'Trámite SII';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Propietario Declarante';
            } else {
                if (parteBWrap) parteBWrap.style.display = 'block';
                if (signBWrap) signBWrap.style.display = 'block';
                document.getElementById('lblStep1').innerText = 'Propietario';
                document.getElementById('lblStep2').innerText = 'Empresa';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Propietario / Autorizante';
            }

            if (help) {
                if (val === 'opcion_a') {
                    help.innerText = 'Contrato bilateral de comodato precario gratuito conforme al Art. 2174 del Código Civil, aceptado por el SII (5 cláusulas).';
                } else if (val === 'opcion_b') {
                    help.innerText = 'Autorización de oficina virtual / Co-Work: acreditación de facultades de subarriendo o cesión de espacios (6 cláusulas).';
                } else if (val === 'opcion_c') {
                    help.innerText = 'Declaración jurada simple unipersonal del dueño bajo apercibimiento penal del Art. 210 CP para trámite directo en Mi SII (4 cláusulas).';
                }
            }
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
            } else if (CURRENT_TEMPLATE.slug === 'contrato-individual-trabajo') {
                // Personalización para Contrato Individual de Trabajo (Código del Trabajo Art. 10)
                document.getElementById('lblStep1').innerText = 'Empleador';
                document.getElementById('lblStep2').innerText = 'Trabajador';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Empleador y Representante Legal';
                document.getElementById('step1Desc').innerText = 'Individualización de la empresa empleadora (Razón Social, RUT, Giro, Domicilio) y su Representante Legal.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Razón Social o Nombre del Empleador *';
                document.getElementById('f_parte_a_nombre').value = 'Servicios Tecnológicos SpA';
                document.getElementById('f_parte_a_rut').value = '76.987.654-3';
                document.getElementById('f_parte_a_civil').value = 'Giro: Consultoría en TI y Desarrollo de Software';
                document.getElementById('f_parte_a_profesion').value = 'Rep. Legal: Juan Andrés Pérez González, C.I. 12.345.678-5';
                document.getElementById('f_parte_a_domicilio').value = 'Av. Andrés Bello 2711, Piso 14';
                document.getElementById('f_parte_a_comuna').value = 'Las Condes, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos del Trabajador';
                document.getElementById('step2Desc').innerText = 'Individualización completa del trabajador para el contrato y su registro en la Dirección del Trabajo (REL).';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Nombre Completo del Trabajador *';
                document.getElementById('f_parte_b_nombre').value = 'María José Valenzuela Soto';
                document.getElementById('f_parte_b_rut').value = '15.444.555-6';
                document.getElementById('f_parte_b_civil').value = 'chilena, soltera, Nac. 15/04/1992';
                document.getElementById('f_parte_b_profesion').value = 'Ingeniera en Informática';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Apoquindo 4500, Depto 801';
                document.getElementById('f_parte_b_comuna').value = 'Las Condes, Santiago';
            } else if (CURRENT_TEMPLATE.slug === 'finiquito-laboral-mutuo-acuerdo') {
                document.getElementById('lblStep1').innerText = 'Empleador';
                document.getElementById('lblStep2').innerText = 'Trabajador';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Empleador y Representante';
                document.getElementById('step1Desc').innerText = 'Identificación de la empresa empleadora y de su representante legal que suscribe el finiquito.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Razón Social o Nombre del Empleador *';
                document.getElementById('f_parte_a_nombre').value = 'Servicios Tecnológicos SpA';
                document.getElementById('f_parte_a_rut').value = '76.987.654-3';
                document.getElementById('f_parte_a_civil').value = 'Giro: Consultoría en TI y Desarrollo de Software';
                document.getElementById('f_parte_a_profesion').value = 'Rep. Legal: Juan Andrés Pérez González, C.I. 12.345.678-5';
                document.getElementById('f_parte_a_domicilio').value = 'Av. Andrés Bello 2711, Piso 14';
                document.getElementById('f_parte_a_comuna').value = 'Las Condes, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos del Trabajador Finiquitado';
                document.getElementById('step2Desc').innerText = 'Datos completos del trabajador para la ratificación del finiquito ante ministro de fe o portal Mi DT.';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Nombre Completo del Trabajador *';
                document.getElementById('f_parte_b_nombre').value = 'María José Valenzuela Soto';
                document.getElementById('f_parte_b_rut').value = '15.444.555-6';
                document.getElementById('f_parte_b_civil').value = 'chilena, soltera';
                document.getElementById('f_parte_b_profesion').value = 'Analista de Operaciones';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Apoquindo 4500, Depto 801';
                document.getElementById('f_parte_b_comuna').value = 'Las Condes, Santiago';
            } else if (CURRENT_TEMPLATE.slug === 'acuerdo-confidencialidad-nda') {
                document.getElementById('lblStep1').innerText = 'Parte A';
                document.getElementById('lblStep2').innerText = 'Parte B';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos de la Primera Parte (Divulgante / Receptora)';
                document.getElementById('step1Desc').innerText = 'Identificación de la primera parte que suscribe el Acuerdo de Confidencialidad.';
                document.getElementById('step2Title').innerText = 'Paso 2: Datos de la Segunda Parte (Divulgante / Receptora)';
                document.getElementById('step2Desc').innerText = 'Identificación de la segunda parte obligada a los deberes de confidencialidad y reserva.';
            } else if (CURRENT_TEMPLATE.slug === 'prestacion-servicios-honorarios') {
                document.getElementById('lblStep1').innerText = 'Mandante';
                document.getElementById('lblStep2').innerText = 'Profesional';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Mandante / Empresa Contratante';
                document.getElementById('step1Desc').innerText = 'Identificación de la persona o empresa que encarga y contrata los servicios profesionales independientes.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Razón Social o Nombre del Mandante *';
                document.getElementById('f_parte_a_nombre').value = 'Inversiones y Servicios Keystone SpA';
                document.getElementById('f_parte_a_rut').value = '76.890.123-4';
                document.getElementById('f_parte_a_civil').value = 'Giro: Consultoría y Gestión de Inversiones';
                document.getElementById('f_parte_a_profesion').value = 'Rep. Legal: Juan Andrés Pérez González, C.I. 12.345.678-5';
                document.getElementById('f_parte_a_domicilio').value = 'Av. Andrés Bello 2711, Piso 14';
                document.getElementById('f_parte_a_comuna').value = 'Las Condes, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos del Profesional Independiente';
                document.getElementById('step2Desc').innerText = 'Identificación del prestador de servicios que emitirá la boleta de honorarios electrónica (BHE).';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Nombre Completo del Profesional *';
                document.getElementById('f_parte_b_nombre').value = 'María José Valenzuela Soto';
                document.getElementById('f_parte_b_rut').value = '15.444.555-6';
                document.getElementById('f_parte_b_civil').value = 'chilena, soltera';
                document.getElementById('f_parte_b_profesion').value = 'Ingeniera Civil en Computación';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Apoquindo 4500, Depto 801';
                document.getElementById('f_parte_b_comuna').value = 'Las Condes, Santiago';
            } else if (CURRENT_TEMPLATE.slug === 'reconocimiento-deuda-pagare-privado') {
                document.getElementById('lblStep1').innerText = 'Acreedor';
                document.getElementById('lblStep2').innerText = 'Deudor';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Acreedor / Beneficiario';
                document.getElementById('step1Desc').innerText = 'Identificación de la persona o entidad a quien se le adeuda la suma comprometida.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Nombre o Razón Social del Acreedor *';
                document.getElementById('f_parte_a_nombre').value = 'Inversiones y Capitales SpA';
                document.getElementById('f_parte_a_rut').value = '76.543.210-K';
                document.getElementById('f_parte_a_civil').value = 'Giro: Inversiones y Servicios Financieros';
                document.getElementById('f_parte_a_profesion').value = 'Rep. Legal: Juan Andrés Pérez González, C.I. 12.345.678-5';
                document.getElementById('f_parte_a_domicilio').value = 'Av. El Golf 150, Piso 8';
                document.getElementById('f_parte_a_comuna').value = 'Las Condes, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos del Deudor Principal';
                document.getElementById('step2Desc').innerText = 'Identificación del deudor que suscribe y asume la obligación de pago y los títulos ejecutivos.';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Nombre Completo del Deudor *';
                document.getElementById('f_parte_b_nombre').value = 'María José Valenzuela Soto';
                document.getElementById('f_parte_b_rut').value = '15.444.555-6';
                document.getElementById('f_parte_b_civil').value = 'chilena, soltera';
                document.getElementById('f_parte_b_profesion').value = 'Comerciante Independiente';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Apoquindo 4500, Depto 801';
                document.getElementById('f_parte_b_comuna').value = 'Las Condes, Santiago';
            } else if (CURRENT_TEMPLATE.slug === 'acta-entrega-llaves-e-inventario') {
                document.getElementById('lblStep1').innerText = 'Arrendador';
                document.getElementById('lblStep2').innerText = 'Arrendatario';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Propietario / Arrendador';
                document.getElementById('step1Desc').innerText = 'Identificación de la parte propietaria que entrega o restituye formalmente el inmueble.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Nombre Completo del Arrendador *';
                document.getElementById('f_parte_a_nombre').value = 'Juan Andrés Pérez González';
                document.getElementById('f_parte_a_rut').value = '12.345.678-5';
                document.getElementById('f_parte_a_civil').value = 'chileno, soltero';
                document.getElementById('f_parte_a_profesion').value = 'Ingeniero Comercial';
                document.getElementById('f_parte_a_domicilio').value = 'Av. Providencia 1234, Depto 402';
                document.getElementById('f_parte_a_comuna').value = 'Providencia, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos del Arrendatario';
                document.getElementById('step2Desc').innerText = 'Identificación de la parte arrendataria que recibe o desocupa la propiedad.';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Nombre Completo del Arrendatario *';
                document.getElementById('f_parte_b_nombre').value = 'María José Valenzuela Soto';
                document.getElementById('f_parte_b_rut').value = '15.444.555-6';
                document.getElementById('f_parte_b_civil').value = 'chilena, soltera';
                document.getElementById('f_parte_b_profesion').value = 'Abogada';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Apoquindo 4500, Depto 801';
                document.getElementById('f_parte_b_comuna').value = 'Las Condes, Santiago';
            } else if (CURRENT_TEMPLATE.slug === 'autorizacion-domicilio-tributario-sii') {
                document.getElementById('lblStep1').innerText = 'Propietario';
                document.getElementById('lblStep2').innerText = 'Empresa';
                document.getElementById('step1Title').innerText = 'Paso 1: Datos del Propietario / Autorizante';
                document.getElementById('step1Desc').innerText = 'Identificación del dueño del inmueble que autoriza fijar el domicilio tributario.';
                document.getElementById('lbl_parte_a_nombre').innerText = 'Nombre Completo del Propietario *';
                document.getElementById('f_parte_a_nombre').value = 'Juan Andrés Pérez González';
                document.getElementById('f_parte_a_rut').value = '12.345.678-5';
                document.getElementById('f_parte_a_civil').value = 'chileno, soltero';
                document.getElementById('f_parte_a_profesion').value = 'Ingeniero Comercial';
                document.getElementById('f_parte_a_domicilio').value = 'Av. Providencia 1234, Depto 402';
                document.getElementById('f_parte_a_comuna').value = 'Providencia, Santiago';

                document.getElementById('step2Title').innerText = 'Paso 2: Datos de la Empresa Beneficiaria';
                document.getElementById('step2Desc').innerText = 'Razón Social, RUT y Representante Legal de la sociedad que fija su domicilio tributario ante el SII.';
                document.getElementById('lbl_parte_b_nombre').innerText = 'Razón Social o Nombre de la Empresa *';
                document.getElementById('f_parte_b_nombre').value = 'Servicios y Tecnologías SpA';
                document.getElementById('f_parte_b_rut').value = '76.987.654-3';
                document.getElementById('f_parte_b_civil').value = 'Giro: Consultoría en TI y Desarrollo de Software';
                document.getElementById('f_parte_b_profesion').value = 'Rep. Legal: María José Valenzuela, C.I. 15.444.555-6';
                document.getElementById('f_parte_b_domicilio').value = 'Av. Providencia 1234, Depto 402';
                document.getElementById('f_parte_b_comuna').value = 'Providencia, Santiago';
            }
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
                        <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#1E40AF;">
                            <i class="fas fa-file-contract"></i> <strong>Finiquito de Trabajo y Término de Relación Laboral (Art. 159 y 177 CdT)</strong>:
                            Selecciona la modalidad deseada para adaptar la dogmática jurídica del instrumento.
                        </div>

                        <div class="form-group" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:0.85rem; margin-bottom:1.2rem;">
                            <label style="font-weight:700; color:#0F2A4A; font-size:0.95rem;">Modalidad / Enfoque del Finiquito *</label>
                            <select id="f_fini_modalidad" class="form-control" onchange="toggleFiniquitoFields()" style="font-weight:600; border-color:#3B82F6;">
                                <option value="opcion_a" selected>Opción 1: Estándar Dirección del Trabajo (Portal Mi DT - 6 Cláusulas)</option>
                                <option value="opcion_b">Opción 2: Blindaje Corporativo / Patronal (Ley Bustos + Sin Accidentes - 9 Cláusulas)</option>
                                <option value="opcion_c">Opción 3: Transaccional Extrajudicial con Reserva de Derechos (Art. 2446 CC)</option>
                            </select>
                            <small class="form-text text-muted" id="fini_modalidad_help">Modelo ágil y homologable ante la Dirección del Trabajo (Mi DT) con ClaveÚnica.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Cargo Desempeñado por el Trabajador *</label>
                                <input type="text" id="f_cargo" class="form-control" value="Analista de Operaciones">
                            </div>
                            <div class="form-group">
                                <label>Causal Legal de Término *</label>
                                <select id="f_causal" class="form-control">
                                    <option value="Art. 159 N° 1 Mutuo acuerdo de las partes" selected>Art. 159 N° 1 Mutuo acuerdo de las partes</option>
                                    <option value="Art. 161 inc. 1° Necesidades de la empresa">Art. 161 inc. 1° Necesidades de la empresa</option>
                                    <option value="Art. 159 N° 4 Vencimiento del plazo del contrato">Art. 159 N° 4 Vencimiento del plazo</option>
                                    <option value="Art. 159 N° 2 Renuncia voluntaria del trabajador">Art. 159 N° 2 Renuncia voluntaria</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Fecha de Inicio del Contrato *</label>
                                <input type="date" id="f_fini_fecha_inicio" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Fecha de Término Efectivo de Labores *</label>
                                <input type="date" id="f_fecha_termino" class="form-control">
                            </div>
                        </div>

                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #E2E8F0; padding-bottom:0.35rem;">
                            Liquidación y Desglose de Haberes Finales
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Remuneraciones y Días Pendientes ($ CLP) *</label>
                                <input type="number" id="f_fini_haberes_dias" class="form-control" value="450000">
                            </div>
                            <div class="form-group">
                                <label>Feriado Proporcional / Vacaciones ($ CLP) *</label>
                                <input type="number" id="f_fini_feriado" class="form-control" value="380000">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Indemnización Años de Servicio / Voluntaria ($ CLP) *</label>
                                <input type="number" id="f_fini_indemnizacion" class="form-control" value="620000">
                            </div>
                            <div class="form-group">
                                <label>Monto Total Líquido a Pagar ($ CLP) *</label>
                                <input type="number" id="f_monto_finiquito" class="form-control" value="1450000" readonly style="background:#F1F5F9; font-weight:700;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Medio y Forma de Pago *</label>
                                <input type="text" id="f_fini_forma_pago" class="form-control" value="Transferencia electrónica a la cuenta bancaria personal del trabajador">
                            </div>
                            <div class="form-group">
                                <label>Banco y Cuenta de Destino *</label>
                                <input type="text" id="f_fini_cuenta" class="form-control" value="Banco de Chile - Cuenta Vista N° 987-65432-10">
                            </div>
                        </div>

                        <!-- Campos específicos para Opción B (Ley Bustos y Seguridad) -->
                        <div id="wrap_fini_opcion_b" style="display:none; background:#FEFCE8; border:1px solid #FEF08A; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#854D0E; margin-bottom:0.5rem;">
                                <i class="fas fa-shield-alt"></i> Cláusulas de Blindaje Ley Bustos y Prevención de Riesgos
                            </h5>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Institución AFP y Certificado al Día *</label>
                                    <input type="text" id="f_fini_bustos_afp" class="form-control" value="AFP Habitat - Cotizaciones íntegramente pagadas al mes anterior">
                                </div>
                                <div class="form-group">
                                    <label>Institución de Salud y AFC al Día *</label>
                                    <input type="text" id="f_fini_bustos_salud" class="form-control" value="FONASA y AFC Chile sin deudas previsionales registradas">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Declaración de Accidentes del Trabajo (Ley 16.744) *</label>
                                <input type="text" id="f_fini_accidentes" class="form-control" value="Inexistencia de siniestros, accidentes laborales ni enfermedades profesionales pendientes">
                            </div>
                        </div>

                        <!-- Campos específicos para Opción C (Reserva de Derechos) -->
                        <div id="wrap_fini_opcion_c" style="display:none; background:#FAF5FF; border:1px solid #E9D5FF; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#6B21A8; margin-bottom:0.5rem;">
                                <i class="fas fa-pen-nib"></i> Declaración y Reserva de Derechos (Doctrina Corte Suprema)
                            </h5>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Texto de Reserva de Derechos por el Trabajador (Dejar vacío si no aplica)</label>
                                <textarea id="f_fini_reserva_derechos" class="form-control" rows="2" placeholder="Ej: Me reservo el derecho a reclamar judicialmente la restitución del aporte del empleador a la AFC y diferencias de horas extras.">Me reservo el derecho a reclamar administrativamente diferencias eventuales en la base de cálculo de feriado proporcional.</textarea>
                            </div>
                        </div>
                    `;
                    break;

                case 'acuerdo-confidencialidad-nda':
                    fieldsHtml = `
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#166534;">
                            <i class="fas fa-shield-virus"></i> <strong>Acuerdo de Confidencialidad Bilateral Corporativo (Startups & Software)</strong>:
                            8 cláusulas exhaustivas de blindaje de secretos industriales, know-how, código fuente, excepciones legales y cláusula penal tasada.
                        </div>

                        <div class="form-group">
                            <label>Propósito del Acuerdo y Proyecto en Evaluación *</label>
                            <input type="text" id="f_proyecto_nda" class="form-control" value="Evaluación conjunta, integración tecnológica y desarrollo de plataforma fintech y arquitectura cloud">
                        </div>

                        <div class="form-group">
                            <label>Alcance de la Información Confidencial Protegida *</label>
                            <textarea id="f_nda_alcance" class="form-control" rows="3">Código fuente, arquitectura de sistemas, diagramas de base de datos, algoritmos, modelos financieros, secretos comerciales, listados de clientes y proveedores, metodologías de trabajo e información técnica no accesible públicamente.</textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Vigencia del Deber de Confidencialidad *</label>
                                <select id="f_vigencia_nda" class="form-control">
                                    <option value="2 años" selected>2 años desde la firma</option>
                                    <option value="3 años">3 años desde la firma</option>
                                    <option value="5 años">5 años desde la firma</option>
                                    <option value="Indefinida respecto a secretos industriales">Indefinida para Secretos Industriales (Ley 19.039)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cláusula Penal Compensatoria por Infracción *</label>
                                <input type="text" id="f_multa_nda" class="form-control" value="500 UF (o su equivalente en pesos chilenos a la fecha de cobro)">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Procedimiento al Término de las Negociaciones *</label>
                                <select id="f_nda_destruccion" class="form-control">
                                    <option value="Devolución o destrucción certificada dentro de 10 días" selected>Devolución o destrucción certificada (10 días)</option>
                                    <option value="Borrado lógico seguro de medios digitales">Borrado lógico seguro inmediato</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Jurisdicción y Competencia Legal *</label>
                                <input type="text" id="f_nda_tribunales" class="form-control" value="Tribunales Ordinarios de Justicia de la comuna y ciudad de Santiago">
                            </div>
                        </div>
                    `;
                    break;

                case 'prestacion-servicios-honorarios':
                    fieldsHtml = `
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#166534;">
                            <i class="fas fa-briefcase"></i> <strong>Contrato Civil de Prestación de Servicios Profesionales (Art. 2006 Código Civil)</strong>:
                            Regulado por el derecho civil y mercantil. Selecciona la modalidad para adecuar los hitos, el blindaje anti-laboral o los acuerdos SLA.
                        </div>

                        <div class="form-group" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:0.85rem; margin-bottom:1.2rem;">
                            <label style="font-weight:700; color:#0F2A4A; font-size:0.95rem;">Modalidad del Contrato de Servicios *</label>
                            <select id="f_serv_modalidad" class="form-control" onchange="toggleServiciosFields()" style="font-weight:600; border-color:#10B981;">
                                <option value="opcion_a" selected>Opción A: Civil Profesional con Blindaje Anti-Laboralidad (8 Cláusulas - Recomendado)</option>
                                <option value="opcion_b">Opción B: Por Hito / Proyecto Cerrado (7 Cláusulas)</option>
                                <option value="opcion_c">Opción C: SLA Corporativo / Servicios Continuos con Penalizaciones (9 Cláusulas)</option>
                            </select>
                            <small class="form-text text-muted" id="serv_modalidad_help">Exclusión probatoria de relación laboral: autonomía técnica, libertad de horarios y sin subordinación.</small>
                        </div>

                        <div class="form-group">
                            <label>Descripción Detallada de los Servicios Profesionales *</label>
                            <textarea id="f_servicio_desc" class="form-control" rows="3">Consultoría técnica especializada en arquitectura cloud, desarrollo de integraciones API, optimización de base de datos e informes de seguridad tecnológica.</textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Honorario Bruto ($ CLP) *</label>
                                <input type="number" id="f_honorario_monto" class="form-control" value="1250000">
                            </div>
                            <div class="form-group">
                                <label>Régimen de Retención de Boleta de Honorarios (BHE) *</label>
                                <select id="f_retencion" class="form-control">
                                    <option value="Con retención legal efectuada por el mandante según tasa del SII vigente" selected>Con retención legal efectuada por Mandante (SII)</option>
                                    <option value="Con pago provisional mensual directo asumido por el profesional">Pago provisional directo del profesional</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Forma y Oportunidad de Pago *</label>
                                <input type="text" id="f_serv_forma_pago" class="form-control" value="Dentro de los primeros 5 días hábiles siguientes contra entrega de la BHE emitida">
                            </div>
                            <div class="form-group">
                                <label>Plazo de Desahucio / Aviso Previo de Término Anticipado *</label>
                                <select id="f_serv_aviso_previo" class="form-control">
                                    <option value="30 días corridos de anticipación mediante comunicación escrita" selected>30 días corridos de anticipación</option>
                                    <option value="15 días corridos de anticipación mediante comunicación escrita">15 días corridos de anticipación</option>
                                    <option value="Inmediato por incumplimiento grave de obligaciones">Inmediato ante incumplimiento grave</option>
                                </select>
                            </div>
                        </div>

                        <!-- Campos específicos para Opción B (Hitos / Proyecto Cerrado) -->
                        <div id="wrap_serv_opcion_b" style="display:none; background:#FEFCE8; border:1px solid #FEF08A; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#854D0E; margin-bottom:0.5rem;">
                                <i class="fas fa-tasks"></i> Especificaciones de Hitos, Fases y Entregables
                            </h5>
                            <div class="form-group">
                                <label>Desglose de Fases, Entregables y Criterios de Aceptación *</label>
                                <textarea id="f_serv_hitos_detalle" class="form-control" rows="2">Fase 1 (40%): Diseño y arquitectura aprobada. Fase 2 (40%): Implementación y pruebas QA. Fase 3 (20%): Puesta en marcha y recepción final conforme.</textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Garantía Técnica y Soporte Post-Entrega *</label>
                                <input type="text" id="f_serv_garantia_tecnica" class="form-control" value="60 días corridos de soporte correctivo sin costo adicional sobre fallas o bugs atribuibles al desarrollo">
                            </div>
                        </div>

                        <!-- Campos específicos para Opción C (SLA Corporativo) -->
                        <div id="wrap_serv_opcion_c" style="display:none; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#1E40AF; margin-bottom:0.5rem;">
                                <i class="fas fa-chart-line"></i> Acuerdos de Nivel de Servicio (SLA) y Penalizaciones
                            </h5>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Tiempos de Respuesta SLA (Crítico / Normal) *</label>
                                    <input type="text" id="f_serv_sla_tiempo" class="form-control" value="Incidencias críticas: 2 horas máx. / Requerimientos normales: 24 horas">
                                </div>
                                <div class="form-group">
                                    <label>Penalización por Incumplimiento de SLA *</label>
                                    <input type="text" id="f_serv_sla_multa" class="form-control" value="Descuento del 2% del honorario mensual por cada hora de retraso no justificado (tope 20%)">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Sustitución de Personal Clave y Responsabilidad Civil *</label>
                                <input type="text" id="f_serv_sla_seguro" class="form-control" value="Reemplazo de consultores en máx. 5 días hábiles previa aprobación del Mandante + deber de indemnidad patrimonial">
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
                        <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#1E40AF;">
                            <i class="fas fa-key"></i> <strong>Acta de Entrega Material, Inventario y Estado del Inmueble</strong>:
                            Constancia fehaciente para contratos de arriendo o compraventa. Selecciona la modalidad para adecuar las secciones y liquidaciones.
                        </div>

                        <div class="form-group" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:0.85rem; margin-bottom:1.2rem;">
                            <label style="font-weight:700; color:#0F2A4A; font-size:0.95rem;">Modalidad del Acta *</label>
                            <select id="f_acta_modalidad" class="form-control" onchange="toggleActaFields()" style="font-weight:600; border-color:#2563EB;">
                                <option value="opcion_a" selected>Opción A: Notarial Inmobiliaria Exhaustiva (6 Secciones - Recomendado)</option>
                                <option value="opcion_b">Opción B: Recepción Provisoria con Lista de Observaciones / "Snag List" (5 Secciones)</option>
                                <option value="opcion_c">Opción C: Acta de Desocupación y Restitución Final con Liquidación de Garantía (5 Secciones)</option>
                            </select>
                            <small class="form-text text-muted" id="acta_modalidad_help">Constancia fehaciente de llaves, lecturas exactas de servicios básicos, inventario y conservación por recintos.</small>
                        </div>

                        <div class="form-group">
                            <label>Dirección Exacta del Inmueble (Depto / Casa, Comuna y Ciudad) *</label>
                            <input type="text" id="f_acta_direccion" class="form-control" value="Av. Providencia 1234, Depto 402, Providencia, Santiago">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Cantidad y Detalle de Juegos de Llaves Entregados *</label>
                                <input type="text" id="f_acta_llaves" class="form-control" value="3 juegos completos (chapas principales, bodega y portón de acceso)">
                            </div>
                            <div class="form-group">
                                <label>Lectura Medidor Eléctrico (kWh) *</label>
                                <input type="text" id="f_acta_luz" class="form-control" value="14.850 kWh">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Lectura Medidor de Agua Potable (m³) *</label>
                                <input type="text" id="f_acta_agua" class="form-control" value="1.230 m³">
                            </div>
                            <div class="form-group">
                                <label>Lectura Medidor / Suministro de Gas *</label>
                                <input type="text" id="f_acta_gas" class="form-control" value="450 m³ (Gas por cañería Metrogas al día)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Estado de Conservación por Recintos (Pintura, Muros, Pisos y Grifería) *</label>
                            <textarea id="f_acta_estado" class="form-control" rows="2">Living y dormitorios con pintura látex en perfecto estado sin perforaciones; pisos flotantes limpios sin desniveles; baños y cocina con grifería operativa, sellos de silicona limpios y sin fugas.</textarea>
                        </div>

                        <div class="form-group">
                            <label>Inventario de Artefactos y Bienes Muebles Incluidos *</label>
                            <textarea id="f_acta_inventario" class="form-control" rows="2">Cocina encimera 4 platos vitrocerámica, horno empotrado, campana retráctil, cortinas roller dúo en ventanas, lámparas plafón LED instaladas en todos los recintos.</textarea>
                        </div>

                        <!-- Campos específicos para Opción B (Snag List / Plazo de Subsanación) -->
                        <div id="wrap_acta_opcion_b" style="display:none; background:#FEFCE8; border:1px solid #FEF08A; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#854D0E; margin-bottom:0.5rem;">
                                <i class="fas fa-clipboard-list"></i> Lista de Observaciones y Plazo Perentorio de Reparación
                            </h5>
                            <div class="form-group">
                                <label>Detalle de Observaciones Menores Detectadas *</label>
                                <textarea id="f_acta_snag_list" class="form-control" rows="2">1. Ajuste de cerradura puerta dormitorio principal. 2. Cambio de goma de sellado en desagüe lavamanos baño secundario.</textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Plazo Acordado para Subsanación por el Propietario *</label>
                                <input type="text" id="f_acta_plazo_subsanar" class="form-control" value="10 días corridos contados desde la firma de la presente acta provisoria">
                            </div>
                        </div>

                        <!-- Campos específicos para Opción C (Restitución Final y Liquidación Garantía) -->
                        <div id="wrap_acta_opcion_c" style="display:none; background:#FAF5FF; border:1px solid #E9D5FF; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#6B21A8; margin-bottom:0.5rem;">
                                <i class="fas fa-receipt"></i> Desocupación Final y Liquidación de Garantía de Arriendo
                            </h5>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Monto de la Garantía Original ($ CLP) *</label>
                                    <input type="number" id="f_acta_garantia_monto" class="form-control" value="650000">
                                </div>
                                <div class="form-group">
                                    <label>Descuentos por Cuentas o Reparaciones ($ CLP) *</label>
                                    <input type="number" id="f_acta_descuentos_monto" class="form-control" value="50000">
                                </div>
                            </div>
                            <div class="form-row" style="margin-bottom:0;">
                                <div class="form-group">
                                    <label>Saldo Líquido a Devolver al Arrendatario ($ CLP) *</label>
                                    <input type="number" id="f_acta_saldo_garantia" class="form-control" value="600000" readonly style="background:#F1F5F9; font-weight:700;">
                                </div>
                                <div class="form-group">
                                    <label>Plazo y Medio de Devolución de Garantía *</label>
                                    <input type="text" id="f_acta_plazo_devolucion" class="form-control" value="Dentro de 15 días hábiles mediante transferencia bancaria">
                                </div>
                            </div>
                        </div>
                    `;
                    break;

                case 'contrato-individual-trabajo':
                    fieldsHtml = `
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#166534;">
                            <i class="fas fa-balance-scale"></i> <strong>Contrato Legal Art. 10 Código del Trabajo y Ley 40 Horas (Ley 21.561)</strong>:
                            Estipulaciones obligatorias, jornada de trabajo, régimen de gratificaciones y registro obligatorio en el portal Mi DT.
                        </div>

                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin-bottom:0.75rem; border-bottom:1px solid #E2E8F0; padding-bottom:0.35rem;">
                            1. Cargo, Funciones y Lugar de Prestación
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Cargo o Título del Puesto *</label>
                                <input type="text" id="f_trab_cargo" class="form-control" value="Desarrollador Full Stack / Consultor TI">
                            </div>
                            <div class="form-group">
                                <label>Lugar Específico de Prestación de Servicios *</label>
                                <input type="text" id="f_trab_lugar" class="form-control" value="Av. Andrés Bello 2711, Piso 14, Las Condes, Santiago">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Funciones Principales del Cargo (Enunciativas) *</label>
                            <textarea id="f_trab_funciones" class="form-control" rows="3">1. Desarrollo, arquitectura y mantención de sistemas y plataformas de software.
2. Integración de APIs, servicios en la nube y pruebas de aseguramiento de calidad (QA).
3. Documentación técnica y soporte a las unidades operativas de la empresa.</textarea>
                        </div>

                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #E2E8F0; padding-bottom:0.35rem;">
                            2. Jornada de Trabajo y Control de Asistencia
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Modalidad de Jornada de Trabajo *</label>
                                <select id="f_trab_modalidad_jornada" class="form-control">
                                    <option value="ordinaria" selected>Jornada Ordinaria con Horario (Ley 40 Horas)</option>
                                    <option value="art22">Exenta de Jornada (Art. 22 inc. 2° Código del Trabajo)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Horas Semanales / Distribución *</label>
                                <input type="text" id="f_trab_horas_sem" class="form-control" value="40 horas semanales distribuidas de Lunes a Viernes de 09:00 a 18:00 horas">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tiempo de Colación (No imputable a jornada) *</label>
                                <input type="text" id="f_trab_colacion_tiempo" class="form-control" value="60 minutos diarios">
                            </div>
                            <div class="form-group">
                                <label>Sistema de Control de Asistencia *</label>
                                <input type="text" id="f_trab_asistencia_sist" class="form-control" value="Reloj control digital con registro biométrico autorizado por la DT">
                            </div>
                        </div>

                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #E2E8F0; padding-bottom:0.35rem;">
                            3. Remuneración, Beneficios y Cuenta de Pago
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Sueldo Base Mensual ($ CLP) *</label>
                                <input type="number" id="f_trab_sueldo" class="form-control" value="1200000">
                            </div>
                            <div class="form-group">
                                <label>Gratificación Legal *</label>
                                <select id="f_trab_gratificacion" class="form-control">
                                    <option value="Artículo 50 (25% mensual con tope legal de 4,75 IMM)" selected>Artículo 50 (25% mensual con tope 4,75 IMM)</option>
                                    <option value="Artículo 47 (30% de la utilidad líquida del ejercicio anual)">Artículo 47 (30% de la utilidad líquida)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Asignación de Colación ($ CLP no remuneracional) *</label>
                                <input type="number" id="f_trab_colacion_monto" class="form-control" value="60000">
                            </div>
                            <div class="form-group">
                                <label>Asignación de Movilización ($ CLP no remuneracional) *</label>
                                <input type="number" id="f_trab_movilizacion_monto" class="form-control" value="40000">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Día de Pago de Remuneraciones *</label>
                                <input type="text" id="f_trab_dia_pago" class="form-control" value="Último día hábil de cada mes">
                            </div>
                            <div class="form-group">
                                <label>Datos Bancarios del Trabajador para Transferencia *</label>
                                <input type="text" id="f_trab_banco_datos" class="form-control" value="Banco de Chile - Cuenta Corriente N° 123-45678-90">
                            </div>
                        </div>

                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #E2E8F0; padding-bottom:0.35rem;">
                            4. Previsión, Salud y Duración del Contrato
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Institución Previsional (AFP) *</label>
                                <input type="text" id="f_trab_afp" class="form-control" value="AFP Habitat">
                            </div>
                            <div class="form-group">
                                <label>Institución de Salud (FONASA / ISAPRE) *</label>
                                <input type="text" id="f_trab_salud" class="form-control" value="FONASA Tramo D">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Modalidad de Duración *</label>
                                <select id="f_trab_duracion_tipo" class="form-control">
                                    <option value="Indefinida" selected>Indefinida</option>
                                    <option value="Plazo Fijo">Plazo Fijo</option>
                                    <option value="Por Obra o Faena">Por Obra o Faena Determinada</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Fecha de Inicio de Labores *</label>
                                <input type="date" id="f_trab_fecha_inicio" class="form-control">
                            </div>
                        </div>
                    `;
                    break;

                case 'reconocimiento-deuda-pagare-privado':
                    fieldsHtml = `
                        <div style="background:#FEF3C7; border:1px solid #FDE68A; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#92400E;">
                            <i class="fas fa-money-bill-wave"></i> <strong>Reconocimiento de Deuda y Compromiso de Pago (Ley N° 18.010)</strong>:
                            Selecciona la modalidad para estructurar la aceleración de cuotas, el tercero fiador y aval, o la confesión de deuda previa a juicio.
                        </div>

                        <div class="form-group" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:0.85rem; margin-bottom:1.2rem;">
                            <label style="font-weight:700; color:#0F2A4A; font-size:0.95rem;">Modalidad / Enfoque del Documento *</label>
                            <select id="f_deuda_modalidad" class="form-control" onchange="toggleDeudaFields()" style="font-weight:600; border-color:#D97706;">
                                <option value="opcion_a" selected>Opción A: Notarial Bancario con Cláusula de Aceleración y Pagaré (6 Cláusulas - Recomendado)</option>
                                <option value="opcion_b">Opción B: Con Codeudor Solidario / Fiador y Aval (8 Cláusulas)</option>
                                <option value="opcion_c">Opción C: Transaccional Judicial / Pre-Demanda con Confesión de Deuda Art. 434 CPC (7 Cláusulas)</option>
                            </select>
                            <small class="form-text text-muted" id="deuda_modalidad_help">Obligación pura y simple, aceleración ipso facto e intereses penales al máximo legal permitido.</small>
                        </div>

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
                                <label>Causa u Origen Lícito de la Deuda *</label>
                                <input type="text" id="f_deuda_causa" class="form-control" value="Saldo insoluto por compraventa de mercaderías, suministros y servicios comerciales">
                            </div>
                            <div class="form-group">
                                <label>Día de Vencimiento Mensual (1 al 31) *</label>
                                <input type="number" id="f_deuda_dia_pago" class="form-control" value="10" min="1" max="31">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Interés Penal por Mora (Ley N° 18.010) *</label>
                                <select id="f_deuda_interes" class="form-control">
                                    <option value="Interés Máximo Convencional fijado por la CMF" selected>Interés Máximo Convencional (CMF / Ley 18.010)</option>
                                    <option value="Interés Corriente para operaciones no reajustables">Interés Corriente para créditos comerciales</option>
                                    <option value="Sin intereses penales adicionales">Sin interés adicional en caso de mora</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pagaré Notarial en Garantía *</label>
                                <select id="f_deuda_pagare" class="form-control">
                                    <option value="Con suscripción paralela de Pagaré a la vista a la orden del acreedor" selected>Con Pagaré a la vista en garantía (título ejecutivo)</option>
                                    <option value="Sin suscripción de pagaré paralelo">Solo reconocimiento privado sin pagaré</option>
                                </select>
                            </div>
                        </div>

                        <!-- Campos específicos para Opción B (Codeudor Solidario) -->
                        <div id="wrap_deuda_opcion_b" style="display:none; background:#FEFCE8; border:1px solid #FEF08A; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#854D0E; margin-bottom:0.5rem;">
                                <i class="fas fa-user-shield"></i> Individualización del Tercero Fiador, Aval y Codeudor Solidario
                            </h5>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre Completo del Aval / Codeudor *</label>
                                    <input type="text" id="f_deuda_aval_nombre" class="form-control" value="Rodrigo Ignacio Morales Soto">
                                </div>
                                <div class="form-group">
                                    <label>RUT del Aval / Codeudor (Módulo 11) *</label>
                                    <input type="text" id="f_deuda_aval_rut" class="form-control" value="16.789.012-3">
                                </div>
                            </div>
                            <div class="form-row" style="margin-bottom:0;">
                                <div class="form-group">
                                    <label>Domicilio del Aval *</label>
                                    <input type="text" id="f_deuda_aval_domicilio" class="form-control" value="Av. Manquehue Norte 1200, Depto 502, Las Condes">
                                </div>
                                <div class="form-group">
                                    <label>Profesión y Estado Civil del Aval *</label>
                                    <input type="text" id="f_deuda_aval_prof" class="form-control" value="Empresario, casado en régimen de separación de bienes">
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Opción C (Transaccional Judicial / CPC) -->
                        <div id="wrap_deuda_opcion_c" style="display:none; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#1E40AF; margin-bottom:0.5rem;">
                                <i class="fas fa-gavel"></i> Estipulaciones Procesales para Cobro Ejecutivo Inmediato
                            </h5>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Pacto de Confesión Judicial y Notificación Electrónica (Art. 434 N° 5 CPC) *</label>
                                <input type="text" id="f_deuda_cpc_tribunal" class="form-control" value="Sometimiento expreso a gestión preparatoria de la vía ejecutiva de reconocimiento de firma y confesión de deuda ante Juzgados Civiles de Santiago">
                            </div>
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
                        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:6px; padding:0.8rem; margin-bottom:1.2rem; font-size:0.85rem; color:#166534;">
                            <i class="fas fa-landmark"></i> <strong>Acreditación de Domicilio Tributario ante el SII (Circular N° 31/2007 SII)</strong>:
                            Autorización formal del inmueble para inicio de actividades, timbraje, facturación electrónica y verificación de actividad.
                        </div>

                        <div class="form-group" style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:6px; padding:0.85rem; margin-bottom:1.2rem;">
                            <label style="font-weight:700; color:#0F2A4A; font-size:0.95rem;">Modalidad de Acreditación ante el SII *</label>
                            <select id="f_sii_modalidad" class="form-control" onchange="toggleSiiFields()" style="font-weight:600; border-color:#059669;">
                                <option value="opcion_a" selected>Opción A: Comodato Gratuito Inmobiliario - Formato Estándar SII Art. 2174 CC (5 Cláusulas - Recomendado)</option>
                                <option value="opcion_b">Opción B: Autorización por Subarrendamiento / Espacio Co-Work (6 Cláusulas)</option>
                                <option value="opcion_c">Opción C: Declaración Jurada Simple Unipersonal del Propietario (4 Cláusulas)</option>
                            </select>
                            <small class="form-text text-muted" id="sii_modalidad_help">Contrato bilateral de comodato precario gratuito conforme al Art. 2174 del Código Civil, aceptado por el SII.</small>
                        </div>

                        <div class="form-group">
                            <label>Dirección Exacta del Inmueble (Calle, Número, Oficina/Depto, Comuna y Ciudad) *</label>
                            <input type="text" id="f_sii_direccion" class="form-control" value="Av. Andrés Bello 2711, Piso 8, Oficina 802, Las Condes, Santiago">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Rol de Avalúo Fiscal del Inmueble (SII) *</label>
                                <input type="text" id="f_sii_rol" class="form-control" value="1234-56 de Las Condes">
                            </div>
                            <div class="form-group">
                                <label>Destino o Finalidad Autorizada *</label>
                                <input type="text" id="f_sii_destino" class="form-control" value="Domicilio tributario, comercial, postal y administrativo exclusivo">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Vigencia de la Autorización *</label>
                                <select id="f_sii_vigencia" class="form-control">
                                    <option value="Indefinida mientras subsista el giro de la sociedad" selected>Indefinida (estándar para inicio de actividades)</option>
                                    <option value="1 año renovable tácita y automáticamente">1 Año renovable automáticamente</option>
                                    <option value="2 años renovables">2 Años renovables</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Procedimiento de Revocación / Restitución *</label>
                                <input type="text" id="f_sii_restitucion" class="form-control" value="Aviso escrito previo de 60 días corridos para que la empresa traslade su domicilio tributario en el SII">
                            </div>
                        </div>

                        <!-- Campos específicos para Opción B (Co-Work / Subarrendamiento) -->
                        <div id="wrap_sii_opcion_b" style="display:none; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#1E40AF; margin-bottom:0.5rem;">
                                <i class="fas fa-building"></i> Datos de la Administración del Co-Work / Centro de Negocios
                            </h5>
                            <div class="form-row" style="margin-bottom:0;">
                                <div class="form-group">
                                    <label>Facultad Expresa de Ceder Domicilio Tributario *</label>
                                    <input type="text" id="f_sii_facultad_cowork" class="form-control" value="Facultad expresa según contrato de arrendamiento matriz y estatutos del centro de negocios">
                                </div>
                                <div class="form-group">
                                    <label>Servicios de Oficina Virtual Incluidos *</label>
                                    <input type="text" id="f_sii_servicios_virtual" class="form-control" value="Recepción de correspondencia postal, notificación del SII y uso de salas de reuniones">
                                </div>
                            </div>
                        </div>

                        <!-- Campos específicos para Opción C (Declaración Unipersonal Propietario) -->
                        <div id="wrap_sii_opcion_c" style="display:none; background:#FEFCE8; border:1px solid #FEF08A; border-radius:6px; padding:0.85rem; margin-top:1rem;">
                            <h5 style="font-size:0.9rem; font-weight:700; color:#854D0E; margin-bottom:0.5rem;">
                                <i class="fas fa-file-signature"></i> Declaración Solemne Unipersonal ante el SII
                            </h5>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Apercibimiento Penal por Falsedad (Art. 210 Código Penal) *</label>
                                <input type="text" id="f_sii_apercibimiento" class="form-control" value="Bajo fe de juramento y apercibimiento del Art. 210 del Código Penal en caso de falsedad en declaración jurada ante la autoridad tributaria">
                            </div>
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
                    const modFini = document.getElementById('f_fini_modalidad')?.value || 'opcion_a';
                    const cargo = document.getElementById('f_cargo')?.value || 'Analista de Operaciones';
                    const causal = document.getElementById('f_causal')?.value || 'Art. 159 N° 1 Mutuo acuerdo de las partes';
                    const fechaInicio = document.getElementById('f_fini_fecha_inicio')?.value || '01-03-2023';
                    const fechaTermino = document.getElementById('f_fecha_termino')?.value || new Date().toLocaleDateString('es-CL');
                    const diasPend = parseInt(document.getElementById('f_fini_haberes_dias')?.value || '0', 10);
                    const feriado = parseInt(document.getElementById('f_fini_feriado')?.value || '0', 10);
                    const indemn = parseInt(document.getElementById('f_fini_indemnizacion')?.value || '0', 10);
                    const totalLiq = diasPend + feriado + indemn;
                    const formaPago = document.getElementById('f_fini_forma_pago')?.value || 'Transferencia electrónica';
                    const cuentaBancaria = document.getElementById('f_fini_cuenta')?.value || 'Cuenta bancaria personal';

                    const totalFmt = new Intl.NumberFormat('es-CL').format(totalLiq);
                    const diasFmt = new Intl.NumberFormat('es-CL').format(diasPend);
                    const feriadoFmt = new Intl.NumberFormat('es-CL').format(feriado);
                    const indemnFmt = new Intl.NumberFormat('es-CL').format(indemn);

                    // Sincronizar input readonly si existe
                    const totalInput = document.getElementById('f_monto_finiquito');
                    if (totalInput) totalInput.value = totalLiq;

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            FINIQUITO DE CONTRATO DE TRABAJO
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR UNA PARTE:</strong> La entidad empleadora <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, Actividad / Giro: <span class="highlight-var">${escapeHtml(civilA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, debidamente representada por <span class="highlight-var">${escapeHtml(profA)}</span>, en adelante indistintamente el <strong>«Empleador»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR LA OTRA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutB)}</span>, antecedentes: <span class="highlight-var">${escapeHtml(civilB)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante el <strong>«Trabajador»</strong>.
                        </p>

                        <p style="margin-top:0.6rem;">
                            Ambos comparecientes mayores de edad, quienes acreditan sus respectivas identidades, convienen en suscribir el presente Finiquito de Contrato de Trabajo conforme a las siguientes estipulaciones:
                        </p>

                        <div class="preview-clause-title">PRIMERO: ANTECEDENTES Y RELACIÓN LABORAL</div>
                        <p>El Trabajador prestó servicios subordinados y dependientes para el Empleador desde el día <span class="highlight-var">${escapeHtml(fechaInicio)}</span>, desempeñando las labores de <span class="highlight-var">${escapeHtml(cargo)}</span>.</p>

                        <div class="preview-clause-title">SEGUNDO: CESE Y CAUSAL DE TÉRMINO</div>
                        <p>Las partes convienen formal y expresamente en poner término a la relación laboral con fecha <span class="highlight-var">${escapeHtml(fechaTermino)}</span>, por la causal consagrada en el <span class="highlight-var">${escapeHtml(causal)}</span> del Código del Trabajo de la República de Chile.</p>

                        <div class="preview-clause-title">TERCERO: LIQUIDACIÓN DE PRESTACIONES Y HABERES</div>
                        <p>El Empleador practica en este acto la liquidación final de todos los haberes devengados con motivo de la vigencia y cese del contrato de trabajo:<br>
                        a) Remuneraciones y días pendientes: <span class="highlight-var">$${diasFmt} CLP</span>.<br>
                        b) Feriado legal y proporcional adeudado: <span class="highlight-var">$${feriadoFmt} CLP</span>.<br>
                        c) Indemnización convenida o voluntaria de término: <span class="highlight-var">$${indemnFmt} CLP</span>.<br>
                        <strong>TOTAL LÍQUIDO A PAGAR:</strong> <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">$${totalFmt} CLP</span>.</p>

                        <div class="preview-clause-title">CUARTO: PAGO EFECTIVO Y RECEPCIÓN CONFORME</div>
                        <p>El Trabajador declara haber recibido a su total y entera satisfacción la suma total liquidada en la cláusula anterior mediante <span class="highlight-var">${escapeHtml(formaPago)}</span> transferida a <span class="highlight-var">${escapeHtml(cuentaBancaria)}</span>, no existiendo saldos de remuneración pendientes.</p>
                    `;

                    if (modFini === 'opcion_b') {
                        // Opción 2: Blindaje Corporativo / Patronal con Ley Bustos y Prevención de Riesgos
                        const afpBustos = document.getElementById('f_fini_bustos_afp')?.value || 'AFP al día';
                        const saludBustos = document.getElementById('f_fini_bustos_salud')?.value || 'FONASA y AFC al día';
                        const accid = document.getElementById('f_fini_accidentes')?.value || 'Sin siniestros pendientes';

                        html += `
                            <div class="preview-clause-title">QUINTO: CUMPLIMIENTO PREVISIONAL ÍNTEGRO (LEY BUSTOS / LEY N° 19.631)</div>
                            <p>El Empleador declara haber integrado y pagado la totalidad de las imposiciones y cotizaciones de seguridad social devengadas durante la vigencia de la relación laboral hasta el último día del mes precedente al término, dando estricto cumplimiento al Artículo 162 del Código del Trabajo (Ley N° 19.631):<br>
                            - Administradora de Fondos de Pensiones (AFP): <span class="highlight-var">${escapeHtml(afpBustos)}</span>.<br>
                            - Salud y Seguro de Cesantía (AFC): <span class="highlight-var">${escapeHtml(saludBustos)}</span>.<br>
                            El Trabajador declara tener conocimiento fehaciente de la planilla de cotizaciones exhibida y ratificada.</p>

                            <div class="preview-clause-title">SEXTO: DECLARACIÓN DE ACCIDENTES Y ENFERMEDADES (LEY N° 16.744)</div>
                            <p>El Trabajador expone y declara expresamente que durante todo el tiempo servido no sufrió accidentes del trabajo ni contrajo enfermedades de índole profesional: <span class="highlight-var">${escapeHtml(accid)}</span>, declarando que el Empleador cumplió cabalmente con su deber de protección, seguridad y provisión de elementos de protección personal conforme al Artículo 184 del Código del Trabajo.</p>

                            <div class="preview-clause-title">SÉPTIMO: AMPLIO, COMPLETO Y RECÍPROCO FINIQUITO</div>
                            <p>En mérito de lo estipulado, las partes declaran que nada se adeudan por concepto alguno emanado de la relación laboral, horas extraordinarias, descansos, gratificaciones, feriados o indemnizaciones, otorgándose mutuamente el más amplio, total, irrevocable y definitivo finiquito.</p>

                            <div class="preview-clause-title">OCTAVO: RENUNCIA EXPRESA DE ACCIONES JUDICIALES Y ADMINISTRATIVAS</div>
                            <p>El Trabajador renuncia irrevocablemente a interponer cualquier demanda, acción, reclamo, denuncia o recurso en contra del Empleador ante los Tribunales de Justicia, la Dirección del Trabajo u otro órgano jurisdiccional, con valor de transacción extrajudicial conforme al Artículo 2446 del Código Civil.</p>

                            <div class="preview-clause-title">NOVENO: RATIFICACIÓN LEGAL ANTE MINISTRO DE FE (ART. 177 CÓDIGO DEL TRABAJO)</div>
                            <p>Para constancia y plena validez legal, el presente instrumento es suscrito y ratificado electrónicamente mediante firma digital con valor probatorio pleno en conformidad a la Ley N° 19.799 y la Ley N° 21.327 sobre modernización de la Dirección del Trabajo (portal Mi DT).</p>
                        `;
                    } else if (modFini === 'opcion_c') {
                        // Opción 3: Transaccional Extrajudicial con Reserva de Derechos
                        const txtReserva = document.getElementById('f_fini_reserva_derechos')?.value || '';

                        html += `
                            <div class="preview-clause-title">QUINTO: NATURALEZA TRANSACCIONAL EXTRAJUDICIAL (ART. 2446 CÓDIGO CIVIL)</div>
                            <p>Las partes dejan expresa constancia de que el presente finiquito reviste la naturaleza de una transacción extrajudicial en conformidad con los Artículos 2446 y siguientes del Código Civil chileno, produciendo el efecto de cosa juzgada en última instancia sobre las materias aquí reguladas.</p>

                            <div class="preview-clause-title">SEXTO: ALCANCE DEL FINIQUITO Y FINIQUITO RECÍPROCO</div>
                            <p>Sin perjuicio de la reserva formal estipulada en la cláusula siguiente, las partes se otorgan recíproco y amplio finiquito respecto de todos los rubros, estipulaciones contractuales y haberes laborales no expresamente controvertidos ni reservados.</p>
                        `;

                        if (txtReserva.trim()) {
                            html += `
                                <div class="preview-clause-title">SÉPTIMO: RESERVA DE DERECHOS FORMAL DEL TRABAJADOR</div>
                                <p style="background:#FAF5FF; border-left:3px solid #9333EA; padding:0.5rem 0.75rem;"><strong>Constancia expresa de Reserva de Derechos:</strong><br>
                                En conformidad a la doctrina uniforme de la Excma. Corte Suprema de Justicia respecto de la validez de la reserva formulada al momento de la suscripción del finiquito, el Trabajador deja constancia de la siguiente reserva explícita: <br>
                                <span class="highlight-var" style="font-weight:600; color:#581C87;">«${escapeHtml(txtReserva)}»</span>.<br>
                                El Empleador toma conocimiento de dicha reserva a los fines que en derecho correspondan.</p>
                            `;
                        } else {
                            html += `
                                <div class="preview-clause-title">SÉPTIMO: RENUNCIA VOLUNTARIA DE ACCIONES</div>
                                <p>El Trabajador manifiesta no formular reserva alguna de derechos, declarando encontrarse íntegramente satisfecho en todas las acreencias laborales y previsionales.</p>
                            `;
                        }

                        html += `
                            <div class="preview-clause-title">OCTAVO: RATIFICACIÓN ANTE MINISTRO DE FE Y VALIDEZ LEGAL</div>
                            <p>El presente documento es firmado electrónicamente al amparo de la Ley N° 19.799 y las disposiciones del Artículo 177 del Código del Trabajo, remitiéndose copia fidedigna para constancia de ambas partes.</p>
                        `;
                    } else {
                        // Opción 1: Estándar Dirección del Trabajo (Portal Mi DT - 6 Cláusulas)
                        html += `
                            <div class="preview-clause-title">QUINTO: RENUNCIA MUTUA DE ACCIONES Y FINIQUITO DEFINITIVO</div>
                            <p>Por medio del presente instrumento, y no existiendo prestaciones pendientes de ninguna especie, el Trabajador otorga al Empleador el más amplio, completo, total y definitivo finiquito por los servicios prestados, renunciando expresamente a cualquier acción, reclamo o demanda posterior en contra de este, ya sea por concepto de remuneraciones, cotizaciones previsionales, indemnizaciones o compensaciones derivadas directa o indirectamente de la relación laboral o de su término.</p>

                            <div class="preview-clause-title">SEXTO: RATIFICACIÓN ELECTRÓNICA ANTE LA DIRECCIÓN DEL TRABAJO (MI DT)</div>
                            <p>Las partes suscriben el presente instrumento en señal de aceptación y conformidad. Para constancia jurídica y en cumplimiento del Artículo 177 del Código del Trabajo y la Ley N° 21.327, el documento es ratificado electrónicamente con ClaveÚnica a través del portal institucional de la Dirección del Trabajo (Mi DT) o mediante firma electrónica avanzada amparada en la Ley N° 19.799.</p>
                        `;
                    }
                } else if (CURRENT_TEMPLATE.slug === 'acuerdo-confidencialidad-nda') {
                    const proy = document.getElementById('f_proyecto_nda')?.value || 'Proyecto tecnológico y comercial';
                    const alcance = document.getElementById('f_nda_alcance')?.value || 'Código fuente, bases de datos y algoritmos';
                    const vig = document.getElementById('f_vigencia_nda')?.value || '2 años desde la firma';
                    const multa = document.getElementById('f_multa_nda')?.value || '500 UF';
                    const destrucc = document.getElementById('f_nda_destruccion')?.value || 'Devolución o destrucción certificada dentro de 10 días';
                    const tribunales = document.getElementById('f_nda_tribunales')?.value || 'Tribunales de Santiago';

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            ACUERDO BILATERAL DE CONFIDENCIALIDAD Y NO DIVULGACIÓN (NDA)
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>DE UNA PARTE:</strong> Don/Doña / Entidad <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, representada para estos efectos por <span class="highlight-var">${escapeHtml(profA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, en adelante indistintamente la <strong>«Parte Divulgante»</strong> o <strong>«Parte Receptora»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>DE LA OTRA PARTE:</strong> Don/Doña / Entidad <span class="highlight-var">${escapeHtml(nomB)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutB)}</span>, representada para estos efectos por <span class="highlight-var">${escapeHtml(profB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante indistintamente la <strong>«Parte Receptora»</strong> o <strong>«Parte Divulgante»</strong>.
                        </p>

                        <p style="margin-top:0.6rem;">
                            Ambas partes en conjunto denominadas las «Partes», quienes en el marco de sus negociaciones, alianzas y evaluaciones estratégicas convienen en celebrar el presente Acuerdo de Confidencialidad y No Divulgación sujeto a las siguientes 8 estipulaciones:
                        </p>

                        <div class="preview-clause-title">CLÁUSULA PRIMERA: PROPÓSITO DEL ACUERDO Y ANTECEDENTES</div>
                        <p>Las Partes declaran que se encuentran en un proceso de evaluación, análisis y desarrollo conjunto relativo al siguiente propósito específico: <span class="highlight-var">${escapeHtml(proy)}</span> (en adelante, el «Proyecto»). Para tales efectos, resultará indispensable el intercambio y revelación de información técnica, comercial, operativa y estratégica de carácter reservado.</p>

                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: DEFINICIÓN AMPLIA DE INFORMACIÓN CONFIDENCIAL</div>
                        <p>Para todos los efectos del presente contrato, se entenderá por «Información Confidencial» toda aquella información de naturaleza técnica, científica, financiera, operativa, legal o de negocios transmitida por una de las Partes a la otra, ya sea en formato verbal, escrito, digital, visual o electromagnético, incluyendo especialmente pero sin limitarse a: <br>
                        <span class="highlight-var" style="font-weight:600;">${escapeHtml(alcance)}</span>.<br>
                        Asimismo, comprenderá todos los análisis, compilaciones, prototipos y desarrollos que contengan o se fundamenten en dicha información.</p>

                        <div class="preview-clause-title">CLÁUSULA TERCERA: DEBER DE CUSTODIA Y ESTÁNDAR DE DILIGENCIA</div>
                        <p>La Parte Receptora se compromete a: a) Mantener estricta confidencialidad de toda la información recibida, aplicándole el mismo grado de cuidado que emplea para resguardar su propia información confidencial más sensible, y en ningún caso menor a una diligencia calificada; b) Utilizar la información única y exclusivamente para los fines del Proyecto; c) No reproducir, duplicar ni realizar ingeniería inversa sobre la misma; y d) Limitar el acceso únicamente a aquellos dependientes, directores y asesores legales o técnicos que requieran conocerla para la ejecución del Proyecto, previa suscripción de obligaciones análogas de confidencialidad.</p>

                        <div class="preview-clause-title">CLÁUSULA CUARTA: EXCEPCIONES AL DEBER DE RESERVA</div>
                        <p>El deber de confidencialidad no será aplicable a aquella información que: a) Sea de dominio público al momento de su divulgación o adquiera tal calidad sin mediar infracción del presente Acuerdo; b) Se encontrare legítimamente en posesión de la Parte Receptora con anterioridad a su recepción; c) Haya sido recibida legalmente de un tercero libre de restricciones de confidencialidad; o d) Deba ser revelada por mandato imperativo de una ley o por orden de tribunal u órgano regulador competente, caso en el cual la Parte Receptora notificará de inmediato y por escrito a la Parte Divulgante para que pueda instar por las medidas cautelares o de protección pertinentes.</p>

                        <div class="preview-clause-title">CLÁUSULA QUINTA: PROPIEDAD INTELECTUAL E INEXISTENCIA DE LICENCIA</div>
                        <p>Toda la Información Confidencial continuará siendo de propiedad única y exclusiva de la Parte Divulgante. Nada de lo contenido en el presente Acuerdo se interpretará como la cesión, enajenación, licencia o transferencia de derechos de autor (Ley N° 17.336), patentes de invención, marcas comerciales o modelos industriales (Ley N° 19.039).</p>

                        <div class="preview-clause-title">CLÁUSULA SEXTA: VIGENCIA Y PROCEDIMIENTO DE DEVOLUCIÓN O DESTRUCCIÓN</div>
                        <p>El deber de confidencialidad y no divulgación permanecerá vigente por el plazo de <span class="highlight-var">${escapeHtml(vig)}</span> contados desde la firma del presente instrumento, subsistiendo indefinidamente respecto de secretos industriales y comerciales. A la conclusión del Proyecto o a requerimiento de la Parte Divulgante, la Parte Receptora procederá conforme a la siguiente modalidad: <span class="highlight-var">${escapeHtml(destrucc)}</span>, certificando dicho acto por escrito a través de su representante.</p>

                        <div class="preview-clause-title">CLÁUSULA SÉPTIMA: CLÁUSULA PENAL TASADA Y REPARACIÓN DEL DAÑO</div>
                        <p>En caso de contravención, revelación no autorizada o incumplimiento culpable o doloso de cualquiera de las obligaciones del presente instrumento por la Parte Receptora o sus dependientes, la parte infractora quedará obligada a pagar ipso facto a la Parte Divulgante, a título de Cláusula Penal tasada y convencional de perjuicios conforme al Artículo 1535 y siguientes del Código Civil, la suma de <span class="highlight-var" style="font-weight:700; color:#DC2626;">${escapeHtml(multa)}</span>, sin perjuicio del derecho de la Parte Divulgante a exigir la cesación inmediata del ilícito mediante medidas precautorias y accionar por la indemnización de mayores perjuicios que fueren acreditados.</p>

                        <div class="preview-clause-title">CLÁUSULA OCTAVA: DOMICILIO, LEY APLICABLE Y JURISDICCIÓN</div>
                        <p>El presente instrumento se rige íntegramente por las leyes de la República de Chile. Para todos los efectos legales que de él emanen, las Partes fijan su domicilio convencional en la comuna y ciudad de Santiago y se someten a la competencia de los <span class="highlight-var">${escapeHtml(tribunales)}</span>.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'acta-entrega-llaves-e-inventario') {
                    const modActa = document.getElementById('f_acta_modalidad')?.value || 'opcion_a';
                    const dirActa = document.getElementById('f_acta_direccion')?.value || domA;
                    const llaves = document.getElementById('f_acta_llaves')?.value || '3 juegos de llaves';
                    const luz = document.getElementById('f_acta_luz')?.value || '14.850 kWh';
                    const agua = document.getElementById('f_acta_agua')?.value || '1.230 m³';
                    const gas = document.getElementById('f_acta_gas')?.value || '450 m³';
                    const estado = document.getElementById('f_acta_estado')?.value || 'Buen estado general';
                    const inv = document.getElementById('f_acta_inventario')?.value || 'Bienes incluidos en el inmueble';

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            ACTA DE RECEPCIÓN Y ENTREGA MATERIAL DE INMUEBLE
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR UNA PARTE:</strong> Don/Doña / Entidad <span class="highlight-var">${escapeHtml(nomA)}</span>, cédula nacional de identidad / RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, en adelante el <strong>«Propietario / Arrendador»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR LA OTRA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutB)}</span>, antecedentes: <span class="highlight-var">${escapeHtml(civilB)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante el <strong>«Arrendatario / Receptor»</strong>.
                        </p>

                        <p style="margin-top:0.6rem;">
                            Las partes dejan formal y fidedigna constancia de la inspección física, recepción y condiciones del inmueble ubicado en <span class="highlight-var" style="font-weight:600;">${escapeHtml(dirActa)}</span>, con arreglo a las siguientes cláusulas y secciones:
                        </p>
                    `;

                    if (modActa === 'opcion_b') {
                        // Opción B: Recepción Provisoria con Lista de Observaciones / "Snag List" (5 Secciones)
                        const snagList = document.getElementById('f_acta_snag_list')?.value || 'Observaciones menores';
                        const plazoSub = document.getElementById('f_acta_plazo_subsanar')?.value || '10 días corridos';

                        html += `
                            <div class="preview-clause-title">SECCIÓN PRIMERA: CARÁCTER PROVISORIO DE LA RECEPCIÓN</div>
                            <p>El Arrendatario recibe materialmente el inmueble en calidad provisoria, sujeto a la verificación y subsanación obligatoria de las observaciones detalladas en la presente acta.</p>

                            <div class="preview-clause-title">SECCIÓN SEGUNDA: ENTREGA DE LLAVES Y LECTURA DE MEDIDORES</div>
                            <p>Se hace entrega formal de: <span class="highlight-var">${escapeHtml(llaves)}</span>.<br>
                            Lecturas exactas de servicios a la fecha de entrega:<br>
                            - Electricidad: <span class="highlight-var">${escapeHtml(luz)}</span> | Agua Potable: <span class="highlight-var">${escapeHtml(agua)}</span> | Gas: <span class="highlight-var">${escapeHtml(gas)}</span>.<br>
                            Los consumos devengados a partir de esta fecha serán de cargo exclusivo del Arrendatario.</p>

                            <div class="preview-clause-title">SECCIÓN TERCERA: LISTA DETALLADA DE OBSERVACIONES ("SNAG LIST")</div>
                            <p>Las partes constatan de mutuo acuerdo los siguientes detalles o reparaciones menores pendientes de subsanar por el Propietario:<br>
                            <span class="highlight-var" style="background:#FEFCE8; color:#854D0E; font-weight:600; white-space:pre-line;">${escapeHtml(snagList)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN CUARTA: PLAZO PERENTORIO Y COMPROMISO DE REPARACIÓN</div>
                            <p>El Propietario se compromete formalmente a reparar a su entero costo todos los ítems singularizados en la sección precedente dentro del plazo de <span class="highlight-var">${escapeHtml(plazoSub)}</span>, coordinando previamente las visitas técnicas pertinentes.</p>

                            <div class="preview-clause-title">SECCIÓN QUINTA: CONVERSIÓN EN RECEPCIÓN DEFINITIVA</div>
                            <p>Subsanadas las observaciones a entera conformidad del Arrendatario, o vencido el plazo sin reclamo fundado, la recepción se entenderá convertida de pleno derecho en definitiva para todos los efectos contractuales y legales.</p>
                        `;
                    } else if (modActa === 'opcion_c') {
                        // Opción C: Acta de Desocupación y Restitución Final con Liquidación de Garantía (5 Secciones)
                        const garOrig = parseInt(document.getElementById('f_acta_garantia_monto')?.value || '0', 10);
                        const descMonto = parseInt(document.getElementById('f_acta_descuentos_monto')?.value || '0', 10);
                        const saldoGar = Math.max(0, garOrig - descMonto);
                        const plazoDev = document.getElementById('f_acta_plazo_devolucion')?.value || 'Dentro de 15 días hábiles';

                        const garFmt = new Intl.NumberFormat('es-CL').format(garOrig);
                        const descFmt = new Intl.NumberFormat('es-CL').format(descMonto);
                        const saldoFmt = new Intl.NumberFormat('es-CL').format(saldoGar);

                        // Sincronizar input readonly
                        const saldoInput = document.getElementById('f_acta_saldo_garantia');
                        if (saldoInput) saldoInput.value = saldoGar;

                        html += `
                            <div class="preview-clause-title">SECCIÓN PRIMERA: RESTITUCIÓN Y DESOCUPACIÓN MATERIAL DEFINITIVA</div>
                            <p>El Arrendatario hace entrega y restitución material formal de la propiedad al Propietario, completamente desocupada de personas, muebles y enseres que no formen parte del inventario original.</p>

                            <div class="preview-clause-title">SECCIÓN SEGUNDA: DEVOLUCIÓN DE LLAVES Y CONSUMOS DE SALIDA</div>
                            <p>El Arrendatario devuelve la totalidad de las llaves: <span class="highlight-var">${escapeHtml(llaves)}</span>.<br>
                            Lecturas finales de servicios a la fecha de restitución:<br>
                            - Electricidad: <span class="highlight-var">${escapeHtml(luz)}</span> | Agua Potable: <span class="highlight-var">${escapeHtml(agua)}</span> | Gas: <span class="highlight-var">${escapeHtml(gas)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN TERCERA: INSPECCIÓN FÍSICA Y ESTADO DE SALIDA</div>
                            <p>El Propietario recibe el inmueble y deja constancia de su estado: <span class="highlight-var">${escapeHtml(estado)}</span>.<br>
                            Inventario revisado conforme: <span class="highlight-var">${escapeHtml(inv)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN CUARTA: LIQUIDACIÓN FORMAL DE LA GARANTÍA DE ARRIENDO</div>
                            <p>Las partes practican la liquidación de la garantía constituida al inicio del contrato:<br>
                            - Garantía original entregada: <span class="highlight-var">$${garFmt} CLP</span>.<br>
                            - Descuentos acordados por cuentas pendientes o reparaciones: <span class="highlight-var" style="color:#DC2626;">$${descFmt} CLP</span>.<br>
                            <strong>SALDO LÍQUIDO A RESTITUIR AL ARRENDATARIO:</strong> <span class="highlight-var" style="font-weight:700; color:#15803D;">$${saldoFmt} CLP</span>.<br>
                            Plazo y forma de devolución pactada: <span class="highlight-var">${escapeHtml(plazoDev)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN QUINTA: FINIQUITO RECÍPROCO DEL CONTRATO DE ARRENDAMIENTO</div>
                            <p>Con el pago del saldo de garantía y la entrega material, las partes se otorgan recíproco y completo finiquito respecto de las obligaciones derivadas del contrato de arriendo, no adeudándose suma alguna posterior.</p>
                        `;
                    } else {
                        // Opción A: Notarial Inmobiliaria Exhaustiva (6 Secciones - Recomendado)
                        html += `
                            <div class="preview-clause-title">SECCIÓN PRIMERA: ENTREGA FÍSICA Y JUEGOS DE LLAVES</div>
                            <p>El Propietario hace entrega real y efectiva al Arrendatario de las llaves correspondientes al inmueble: <span class="highlight-var" style="font-weight:600;">${escapeHtml(llaves)}</span>, declarando el Arrendatario recibirlas a su entera satisfacción.</p>

                            <div class="preview-clause-title">SECCIÓN SEGUNDA: LECTURAS OFICIALES DE MEDIDORES Y SERVICIOS BÁSICOS</div>
                            <p>Las partes constatan conjuntamente las siguientes lecturas de suministros básicos al momento exacto de la entrega material:<br>
                            - Medidor de Electricidad: <span class="highlight-var" style="font-weight:600;">${escapeHtml(luz)}</span>.<br>
                            - Medidor de Agua Potable: <span class="highlight-var" style="font-weight:600;">${escapeHtml(agua)}</span>.<br>
                            - Suministro de Gas: <span class="highlight-var" style="font-weight:600;">${escapeHtml(gas)}</span>.<br>
                            El Propietario declara que los consumos anteriores a esta fecha se encuentran completamente pagados o serán de su cargo exclusivo.</p>

                            <div class="preview-clause-title">SECCIÓN TERCERA: ESTADO DE CONSERVACIÓN DETALLADO POR RECINTOS</div>
                            <p>Las partes inspeccionan minuciosamente cada dependencia del inmueble (living-comedor, dormitorios, baños, cocina, terraza y logia), dejando constancia de su estado: <br>
                            <span class="highlight-var" style="white-space:pre-line;">${escapeHtml(estado)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN CUARTA: TABLA DE INVENTARIO DE ARTEFACTOS Y BIENES MUEBLES</div>
                            <p>Se deja expresa constancia de que el inmueble se entrega con los siguientes artefactos, electrodomésticos y bienes muebles en perfecto estado operativo y de conservación:<br>
                            <span class="highlight-var" style="white-space:pre-line;">${escapeHtml(inv)}</span>.</p>

                            <div class="preview-clause-title">SECCIÓN QUINTA: DEBER DE CUSTODIA Y MANTENCIÓN</div>
                            <p>El Arrendatario se compromete a custodiar el inmueble y los bienes inventariados con la diligencia debida, asumiendo las reparaciones locativas que por ley le correspondan y comprometiéndose a restituirlos al término del arriendo en el mismo buen estado recibido, salvo el deterioro legítimo por uso natural y paso del tiempo.</p>

                            <div class="preview-clause-title">SECCIÓN SEXTA: FIRMA DE CONFORMIDAD Y VALIDEZ LEGAL</div>
                            <p>Las partes firman electrónicamente la presente acta en señal de plena conformidad y veracidad probatoria, quedando incorporada como anexo vinculante al contrato de arrendamiento respectivo.</p>
                        `;
                    }
                } else if (CURRENT_TEMPLATE.slug === 'contrato-individual-trabajo') {
                    const cargo = document.getElementById('f_trab_cargo')?.value || 'Desarrollador Full Stack';
                    const lugar = document.getElementById('f_trab_lugar')?.value || 'Av. Andrés Bello 2711, Las Condes';
                    const funciones = document.getElementById('f_trab_funciones')?.value || 'Funciones asignadas al cargo';
                    const modJornada = document.getElementById('f_trab_modalidad_jornada')?.value || 'ordinaria';
                    const horasSem = document.getElementById('f_trab_horas_sem')?.value || '40 horas semanales';
                    const colacionTiempo = document.getElementById('f_trab_colacion_tiempo')?.value || '60 minutos diarios';
                    const sistAsistencia = document.getElementById('f_trab_asistencia_sist')?.value || 'Reloj control digital con registro biométrico';
                    const sueldo = document.getElementById('f_trab_sueldo')?.value || '1200000';
                    const grat = document.getElementById('f_trab_gratificacion')?.value || 'Artículo 50';
                    const colacionMonto = document.getElementById('f_trab_colacion_monto')?.value || '60000';
                    const movilizacionMonto = document.getElementById('f_trab_movilizacion_monto')?.value || '40000';
                    const diaPago = document.getElementById('f_trab_dia_pago')?.value || 'Último día hábil de cada mes';
                    const bancoDatos = document.getElementById('f_trab_banco_datos')?.value || 'Banco de Chile - Cta Corriente';
                    const afp = document.getElementById('f_trab_afp')?.value || 'AFP Habitat';
                    const salud = document.getElementById('f_trab_salud')?.value || 'FONASA';
                    const durTipo = document.getElementById('f_trab_duracion_tipo')?.value || 'Indefinida';
                    const fechaInicio = document.getElementById('f_trab_fecha_inicio')?.value || new Date().toISOString().split('T')[0];

                    const sueldoFmt = new Intl.NumberFormat('es-CL').format(sueldo);
                    const colacionFmt = new Intl.NumberFormat('es-CL').format(colacionMonto);
                    const movilFmt = new Intl.NumberFormat('es-CL').format(movilizacionMonto);

                    let jornadaClausulaHtml = '';
                    if (modJornada === 'art22') {
                        jornadaClausulaHtml = `
                            <p><strong>Modalidad Exenta de Jornada (Art. 22 inciso 2° del Código del Trabajo):</strong><br>
                            El Trabajador queda excluido de la limitación de jornada ordinaria de trabajo de conformidad con lo preceptuado en el inciso segundo del artículo 22 del Código del Trabajo, por prestar servicios en razón de la naturaleza de las funciones desempeñadas y sin fiscalización superior inmediata.</p>
                        `;
                    } else {
                        jornadaClausulaHtml = `
                            <p><strong>Modalidad con sujeción a horario ordinario:</strong><br>
                            La jornada ordinaria semanal será de <span class="highlight-var">${escapeHtml(horasSem)}</span>.<br>
                            El Trabajador dispondrá de un tiempo de colación diario de <span class="highlight-var">${escapeHtml(colacionTiempo)}</span>, el cual no será imputable a la jornada de trabajo.<br>
                            El control de asistencia y determinación de horas de trabajo se efectuará mediante <span class="highlight-var">${escapeHtml(sistAsistencia)}</span>.</p>
                        `;
                    }

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            CONTRATO INDIVIDUAL DE TRABAJO
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, entre:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>EMPLEADOR:</strong> Razón Social / Nombre: <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT: <span class="highlight-var">${escapeHtml(rutA)}</span>, Actividad / Giro: <span class="highlight-var">${escapeHtml(civilA)}</span>, Domicilio: <span class="highlight-var">${escapeHtml(domA)}</span>, Comuna y Ciudad: <span class="highlight-var">${escapeHtml(comA)}</span>, Representante Legal: <span class="highlight-var">${escapeHtml(profA)}</span>, en adelante el <strong>«Empleador»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>TRABAJADOR:</strong> Nombre Completo: <span class="highlight-var">${escapeHtml(nomB)}</span>, Cédula Nacional de Identidad: <span class="highlight-var">${escapeHtml(rutB)}</span>, Antecedentes: <span class="highlight-var">${escapeHtml(civilB)}</span>, Profesión: <span class="highlight-var">${escapeHtml(profB)}</span>, Domicilio: <span class="highlight-var">${escapeHtml(domB)}</span>, Comuna y Ciudad: <span class="highlight-var">${escapeHtml(comB)}</span>, Correo Electrónico: <span class="highlight-var">${escapeHtml(emailB)}</span>, Teléfono: <span class="highlight-var">${escapeHtml(telB)}</span>, en adelante el <strong>«Trabajador»</strong>.
                        </p>

                        <p style="margin-top:0.6rem;">
                            Las partes han convenido celebrar el siguiente Contrato Individual de Trabajo, sujeto a las disposiciones del Código del Trabajo de la República de Chile y a las estipulaciones siguientes:
                        </p>

                        <div class="preview-clause-title">PRIMERO: NATURALEZA DE LOS SERVICIOS Y CARGO</div>
                        <p>El Trabajador se compromete a prestar servicios personales y subordinados para el Empleador desempeñando el cargo de: <strong><span class="highlight-var">${escapeHtml(cargo)}</span></strong>.<br>
                        Las funciones principales, sin que la enumeración sea taxativa sino meramente enunciativa, comprenden:<br>
                        <span class="highlight-var" style="white-space:pre-line;">${escapeHtml(funciones)}</span><br>
                        Asimismo, realizará todas aquellas tareas conexas, complementarias y accesorias que resulten necesarias e inherentes a la adecuada ejecución de sus funciones principales.</p>

                        <div class="preview-clause-title">SEGUNDO: LUGAR DE PRESTACIÓN DE SERVICIOS</div>
                        <p>El Trabajador prestará sus servicios en las dependencias ubicadas en <span class="highlight-var">${escapeHtml(lugar)}</span>.<br>
                        De conformidad con lo dispuesto en el artículo 12 del Código del Trabajo, el Empleador podrá alterar la naturaleza de los servicios o el sitio o recinto en que ellos deban prestarse, a condición de que se trate de labores similares, que el nuevo sitio o recinto quede dentro de la misma localidad o ciudad, sin que ello importe menoscabo para el Trabajador.</p>

                        <div class="preview-clause-title">TERCERO: JORNADA DE TRABAJO (LEY N° 21.561)</div>
                        ${jornadaClausulaHtml}

                        <div class="preview-clause-title">CUARTO: REMUNERACIÓN Y BENEFICIOS</div>
                        <p>El Empleador pagará al Trabajador las siguientes prestaciones por concepto de remuneración y asignaciones no remuneracionales:<br>
                        1. <strong>Sueldo Base:</strong> Una suma mensual fija de <span class="highlight-var">$${sueldoFmt} CLP</span>, pagadera por períodos mensuales vencidos.<br>
                        2. <strong>Gratificación Legal:</strong> Se conviene el pago de gratificación legal conforme a la modalidad de <span class="highlight-var">${escapeHtml(grat)}</span> del Código del Trabajo.<br>
                        3. <strong>Asignación de Colación (No Remuneracional):</strong> Una asignación mensual de <span class="highlight-var">$${colacionFmt} CLP</span> por concepto de reembolso de gastos de alimentación (Art. 41 del Código del Trabajo).<br>
                        4. <strong>Asignación de Movilización (No Remuneracional):</strong> Una asignación mensual de <span class="highlight-var">$${movilFmt} CLP</span> por concepto de traslado entre el domicilio y el lugar de trabajo.<br>
                        <strong>Forma y Fecha de Pago:</strong> Las remuneraciones se pagarán el <span class="highlight-var">${escapeHtml(diaPago)}</span> mediante transferencia bancaria a la cuenta personal del Trabajador: <span class="highlight-var">${escapeHtml(bancoDatos)}</span>.</p>

                        <div class="preview-clause-title">QUINTO: DESCUENTOS LEGALES Y PREVISIONALES</div>
                        <p>De las remuneraciones a pagar, el Empleador deducirá los impuestos que las graven, las cotizaciones de seguridad social (AFP, FONASA/ISAPRE, y Seguro de Cesantía de la Ley N° 19.728), y demás descuentos legalmente obligatorios o expresamente autorizados por escrito por el Trabajador en conformidad con el artículo 58 del Código del Trabajo.<br>
                        * Institución Previsional: <span class="highlight-var">${escapeHtml(afp)}</span> | Institución de Salud: <span class="highlight-var">${escapeHtml(salud)}</span>.</p>

                        <div class="preview-clause-title">SEXTO: DURACIÓN DEL CONTRATO</div>
                        <p>El presente contrato tendrá una duración de modalidad: <strong><span class="highlight-var">${escapeHtml(durTipo)}</span></strong>, a contar del día <span class="highlight-var">${escapeHtml(fechaInicio)}</span>.</p>

                        <div class="preview-clause-title">SÉPTIMO: OBLIGACIONES Y PROHIBICIONES</div>
                        <p>El Trabajador se compromete a: desempeñar sus labores con la debida diligencia, lealtad y cuidado; cumplir estrictamente con las órdenes e instrucciones que imparta el Empleador o sus representantes; observar rigurosamente el Reglamento Interno de Orden, Higiene y Seguridad de la empresa; y cumplir con las normativas sobre prevención de riesgos y uso de elementos de protección personal (EPP).</p>

                        <div class="preview-clause-title">OCTAVO: CONFIDENCIALIDAD, PROPIEDAD INTELECTUAL Y DATOS PERSONALES</div>
                        <p>1. <strong>Confidencialidad:</strong> El Trabajador guardará estricta reserva y secreto profesional respecto de toda información técnica, financiera, comercial o secretos del negocio del Empleador.<br>
                        2. <strong>Propiedad Intelectual / Industrial:</strong> Todas las creaciones, desarrollos o programas computacionales generados pertenecerán al Empleador conforme a las Leyes 17.336 y 19.039.<br>
                        3. <strong>Protección de Datos Personales:</strong> El Empleador tratará los datos personales exclusivamente para los fines de la relación laboral y cumplimiento legal.</p>

                        <div class="preview-clause-title">NOVENO: PREVENCIÓN, ACOSO Y CONVIVENCIA LABORAL</div>
                        <p>La empresa mantiene un compromiso de tolerancia cero frente a toda conducta de acoso laboral, acoso sexual o violencia en el trabajo. Las partes se comprometen a respetar los protocolos de prevención y de investigación de la normativa legal vigente.</p>

                        <div class="preview-clause-title">DÉCIMO: TERMINACIÓN DEL CONTRATO</div>
                        <p>El presente contrato terminará por cualquiera de las causales señaladas en los artículos 159, 160 y 161 del Código del Trabajo, rigiendo los plazos, formalidades y comunicaciones prescritos en el artículo 162 del mismo cuerpo legal.</p>

                        <div class="preview-clause-title">DÉCIMO PRIMERO: DOMICILIO Y JURISDICCIÓN</div>
                        <p>Para todos los efectos legales derivados del presente contrato, las partes fijan su domicilio en la ciudad y comuna del lugar de prestación de servicios y se someten a la jurisdicción de los Juzgados de Letras del Trabajo competentes.</p>

                        <div class="preview-clause-title">DÉCIMO SEGUNDO: REGISTRO ELECTRÓNICO LABORAL (REL) Y EJEMPLARES</div>
                        <p>De conformidad con el artículo 9° del Código del Trabajo y la Ley N° 21.327, el Empleador registrará el presente contrato en el sitio electrónico de la Dirección del Trabajo (portal Mi DT) dentro de los plazos legales establecidos. El presente contrato se firma electrónicamente mediante firma digital con valor probatorio pleno (Ley N° 19.799), quedando copia íntegra y fehaciente a disposición de cada una de las partes en la plataforma SinNotario.cl.</p>
                    `;
                } else if (CURRENT_TEMPLATE.slug === 'reconocimiento-deuda-pagare-privado') {
                    const modDeuda = document.getElementById('f_deuda_modalidad')?.value || 'opcion_a';
                    const deuda = parseInt(document.getElementById('f_deuda_total')?.value || '0', 10);
                    const cuotas = parseInt(document.getElementById('f_deuda_cuotas')?.value || '1', 10);
                    const causa = document.getElementById('f_deuda_causa')?.value || 'Saldo comercial pendiente';
                    const diaPago = document.getElementById('f_deuda_dia_pago')?.value || '10';
                    const interes = document.getElementById('f_deuda_interes')?.value || 'Interés Máximo Convencional fijado por la CMF';
                    const pagare = document.getElementById('f_deuda_pagare')?.value || 'Con suscripción paralela de Pagaré';

                    const deudaFmt = new Intl.NumberFormat('es-CL').format(deuda);
                    const cuotaMonto = Math.round(deuda / (cuotas || 1));
                    const cuotaFmt = new Intl.NumberFormat('es-CL').format(cuotaMonto);

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            RECONOCIMIENTO DE DEUDA Y COMPROMISO DE PAGO
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR UNA PARTE:</strong> La entidad o persona acreedora <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, Actividad / Giro: <span class="highlight-var">${escapeHtml(civilA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, debidamente representada por <span class="highlight-var">${escapeHtml(profA)}</span>, en adelante el <strong>«Acreedor»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR LA OTRA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutB)}</span>, antecedentes: <span class="highlight-var">${escapeHtml(civilB)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante el <strong>«Deudor»</strong>.
                        </p>
                    `;

                    if (modDeuda === 'opcion_b') {
                        // Opción B: Con Codeudor Solidario / Fiador y Aval (8 Cláusulas)
                        const avalNom = document.getElementById('f_deuda_aval_nombre')?.value || 'Fiador y Aval';
                        const avalRut = document.getElementById('f_deuda_aval_rut')?.value || '11.222.333-4';
                        const avalDom = document.getElementById('f_deuda_aval_domicilio')?.value || 'Santiago';
                        const avalProf = document.getElementById('f_deuda_aval_prof')?.value || 'Empresario';

                        html += `
                            <p style="margin-top:0.6rem;">
                                <strong>Y EN CALIDAD DE TERCERO GARANTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(avalNom)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(avalRut)}</span>, de profesión y estado civil <span class="highlight-var">${escapeHtml(avalProf)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(avalDom)}</span>, en adelante el <strong>«Fiador, Aval y Codeudor Solidario»</strong>.
                            </p>
                            <p style="margin-top:0.6rem;">Los comparecientes convienen en celebrar el presente instrumento conforme a las siguientes 8 estipulaciones:</p>

                            <div class="preview-clause-title">CLÁUSULA PRIMERA: RECONOCIMIENTO EXPRESO Y LÍQUIDO DE LA DEUDA</div>
                            <p>El Deudor reconoce expresa, pura, simple y categóricamente adeudar al Acreedor la suma líquida de <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">$${deudaFmt} CLP</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA SEGUNDA: CAUSA U ORIGEN LÍCITO DE LA OBLIGACIÓN</div>
                            <p>La deuda reconocida emana de: <span class="highlight-var">${escapeHtml(causa)}</span>, declarando el Deudor haber recibido conforme las contraprestaciones que dieron origen a este crédito.</p>

                            <div class="preview-clause-title">CLÁUSULA TERCERA: CALENDARIO DE PAGO EN CUOTAS MENSUALES</div>
                            <p>La obligación se solucionará en <span class="highlight-var">${escapeHtml(cuotas)} cuotas mensuales, iguales y sucesivas</span> de <span class="highlight-var">$${cuotaFmt} CLP</span> cada una, pagaderas a más tardar el día <span class="highlight-var">${escapeHtml(diaPago)}</span> de cada mes calendario.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: INTERÉS PENAL MORATORIO (LEY N° 18.010)</div>
                            <p>En caso de simple retardo o mora en el pago íntegro y oportuno de cualquiera de las cuotas, la suma adeudada devengará de pleno derecho: <span class="highlight-var">${escapeHtml(interes)}</span> hasta su pago efectivo.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: CLÁUSULA DE ACELERACIÓN Y CADUCIDAD DE PLAZOS</div>
                            <p>El no pago íntegro de cualquiera de las cuotas a su respectivo vencimiento facultará al Acreedor a hacer exigible de inmediato y sin necesidad de requerimiento judicial previo el total del saldo insoluto de la deuda, considerándose la obligación como de plazo vencido (Cláusula de Aceleración ipso facto).</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: COMPARECENCIA DE FIADOR, AVAL Y CODEUDOR SOLIDARIO</div>
                            <p>Presente en este acto Don/Doña <span class="highlight-var">${escapeHtml(avalNom)}</span>, expone que se constituye en fiador, aval y codeudor solidario de todas y cada una de las obligaciones, cuotas, intereses y costas asumidas por el Deudor en este instrumento, renunciando formalmente a los beneficios de orden y excusión.</p>

                            <div class="preview-clause-title">CLÁUSULA SÉPTIMA: PAGARÉ EN GARANTÍA Y TÍTULO EJECUTIVO</div>
                            <p>En resguardo del crédito, se estipula: <span class="highlight-var">${escapeHtml(pagare)}</span>, sin que su otorgamiento constituya novación de la obligación civil subyacente.</p>

                            <div class="preview-clause-title">CLÁUSULA OCTAVA: DOMICILIO Y JURISDICCIÓN</div>
                            <p>Para todos los efectos legales, las partes y el codeudor solidario fijan domicilio en la ciudad de Santiago y prorrogan competencia ante sus Tribunales Ordinarios de Justicia.</p>
                        `;
                    } else if (modDeuda === 'opcion_c') {
                        // Opción C: Transaccional Judicial / Pre-Demanda con Confesión de Deuda Art. 434 CPC (7 Cláusulas)
                        const pactoProc = document.getElementById('f_deuda_cpc_tribunal')?.value || 'Sometimiento a gestión preparatoria de la vía ejecutiva Art. 434 N° 5 CPC';

                        html += `
                            <p style="margin-top:0.6rem;">Las partes mayores de edad convienen en celebrar la presente Transacción Extrajudicial con valor de cosa juzgada conforme a las siguientes estipulaciones:</p>

                            <div class="preview-clause-title">CLÁUSULA PRIMERA: NATURALEZA TRANSACCIONAL EXTRAJUDICIAL (ART. 2446 CÓDIGO CIVIL)</div>
                            <p>Las partes celebran el presente instrumento con el carácter de transacción extrajudicial para precaver un litigio judicial inminente, produciendo entre ellas el efecto de cosa juzgada en última instancia (Art. 2460 Código Civil).</p>

                            <div class="preview-clause-title">CLÁUSULA SEGUNDA: CONFESIÓN EXPRESA DE DEUDA LÍQUIDA Y DETERMINADA</div>
                            <p>El Deudor confiesa y reconoce pura y simplemente adeudar en favor del Acreedor la suma líquida, vencida y actualmente exigible de <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">$${deudaFmt} CLP</span>, por concepto de <span class="highlight-var">${escapeHtml(causa)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA TERCERA: PLAN DE PAGO TRANSACCIONAL</div>
                            <p>El Acreedor concede gracia de pago al Deudor, quien se compromete a solucionar el total adeudado en <span class="highlight-var">${escapeHtml(cuotas)} cuotas mensuales</span> de <span class="highlight-var">$${cuotaFmt} CLP</span> pagaderas hasta el día <span class="highlight-var">${escapeHtml(diaPago)}</span> de cada mes.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: INTERESES MORATORIOS Y CLÁUSULA PENAL COMPENSATORIA</div>
                            <p>La mora devengará el interés penal máximo convencional legal (Ley N° 18.010). Asimismo, ante simple retardo, caducarán de inmediato todos los plazos otorgados, haciéndose exigible el total insoluto.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: PACTO PROCESAL PREPARATORIO DE VÍA EJECUTIVA (ART. 434 N° 5 CPC)</div>
                            <p>Para efectos de su cobro compulsivo en caso de incumplimiento, las partes pactan expresamente: <span class="highlight-var">${escapeHtml(pactoProc)}</span>, confiriendo mérito ejecutivo directo con habilitación de embargo inmediato de bienes.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: NOTIFICACIÓN ELECTRÓNICA Y DOMICILIO PROCESAL</div>
                            <p>El Deudor designa como domicilio procesal válido para toda notificación judicial el indicado en la comparecencia y autoriza expresamente su notificación por correo electrónico conforme a la Ley N° 20.886 sobre Tramitación Electrónica.</p>

                            <div class="preview-clause-title">CLÁUSULA SÉPTIMA: GASTOS Y COSTAS JUDICIALES</div>
                            <p>Todas las costas procesales y personales originadas por el cobro judicial de las cuotas impagas serán de cargo exclusivo del Deudor moroso.</p>
                        `;
                    } else {
                        // Opción A: Notarial Bancario con Cláusula de Aceleración y Pagaré (6 Cláusulas - Recomendado)
                        html += `
                            <p style="margin-top:0.6rem;">Las partes mayores de edad acuerdan celebrar el presente Reconocimiento de Deuda sujeto a las siguientes 6 estipulaciones:</p>

                            <div class="preview-clause-title">CLÁUSULA PRIMERA: RECONOCIMIENTO DE DEUDA LÍQUIDA Y EXIGIBLE</div>
                            <p>Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span> reconoce expresa, libre y espontáneamente adeudar a <span class="highlight-var">${escapeHtml(nomA)}</span> la suma total de <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">$${deudaFmt} CLP</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA SEGUNDA: CAUSA Y ORIGEN DE LA OBLIGACIÓN</div>
                            <p>Se deja constancia de que la deuda proviene de: <span class="highlight-var">${escapeHtml(causa)}</span>, que el Deudor declara conocer, aceptar y liquidar a su total conformidad.</p>

                            <div class="preview-clause-title">CLÁUSULA TERCERA: FORMA Y CALENDARIO DE PAGO</div>
                            <p>La deuda reconocida se pagará en <span class="highlight-var">${escapeHtml(cuotas)} cuotas mensuales y consecutivas</span> de <span class="highlight-var">$${cuotaFmt} CLP</span> pagaderas hasta el día <span class="highlight-var">${escapeHtml(diaPago)}</span> de cada mes calendario mediante transferencia bancaria o vale vista.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: INTERESES PENALES MORATORIOS (LEY N° 18.010)</div>
                            <p>En caso de mora o retraso en el pago de cualquiera de las cuotas, se devengará de pleno derecho: <span class="highlight-var">${escapeHtml(interes)}</span>, computado diariamente sobre el saldo en mora.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: CLÁUSULA DE ACELERACIÓN Y RENUNCIA A REQUERIMIENTO</div>
                            <p>El no pago oportuno de una cualquiera de las cuotas convenidas facultará al Acreedor para considerar vencida y exigible la totalidad de la deuda no pagada, pudiendo demandar de inmediato el cobro total del saldo insoluto más intereses y costas (Cláusula de Aceleración ipso facto), renunciando el Deudor a todo requerimiento previo de pago o interpelación judicial.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: PAGARÉ EN GARANTÍA, DOMICILIO Y JURISDICCIÓN</div>
                            <p>Régimen de títulos ejecutivos: <span class="highlight-var">${escapeHtml(pagare)}</span>. Para todos los efectos legales, las partes fijan domicilio especial y único en la comuna y ciudad de Santiago, prorrogando expresamente jurisdicción y competencia ante sus Tribunales Ordinarios de Justicia.</p>
                        `;
                    }
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
                    const modServ = document.getElementById('f_serv_modalidad')?.value || 'opcion_a';
                    const servDesc = document.getElementById('f_servicio_desc')?.value || 'Consultoría especializada y servicios técnicos';
                    const honorario = parseInt(document.getElementById('f_honorario_monto')?.value || '0', 10);
                    const honorFmt = new Intl.NumberFormat('es-CL').format(honorario);
                    const retencion = document.getElementById('f_retencion')?.value || 'Con retención legal efectuada por el mandante';
                    const formaPago = document.getElementById('f_serv_forma_pago')?.value || 'Dentro de los primeros 5 días hábiles';
                    const avisoPrevio = document.getElementById('f_serv_aviso_previo')?.value || '30 días corridos de anticipación';

                    html = `
                        <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                            CONTRATO DE PRESTACIÓN DE SERVICIOS PROFESIONALES INDEPENDIENTES
                        </div>
                        <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR UNA PARTE:</strong> La entidad o persona <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, Actividad / Giro: <span class="highlight-var">${escapeHtml(civilA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, debidamente representada por <span class="highlight-var">${escapeHtml(profA)}</span>, en adelante el <strong>«Mandante»</strong>; y
                        </p>
                        
                        <p style="margin-top:0.6rem;">
                            <strong>POR LA OTRA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutB)}</span>, antecedentes: <span class="highlight-var">${escapeHtml(civilB)}</span>, de profesión u oficio <span class="highlight-var">${escapeHtml(profB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante el <strong>«Profesional»</strong>.
                        </p>

                        <p style="margin-top:0.6rem;">
                            Las partes mayores de edad, en pleno ejercicio de sus facultades civiles, convienen en celebrar el presente Contrato de Prestación de Servicios Profesionales regulado por el Artículo 2006 y siguientes del Código Civil, al tenor de las siguientes estipulaciones:
                        </p>

                        <div class="preview-clause-title">CLÁUSULA PRIMERA: OBJETO Y ENCARGO PROFESIONAL</div>
                        <p>El Mandante encomienda al Profesional, quien acepta para sí, la prestación de los siguientes servicios técnicos y profesionales especializados:<br>
                        <span class="highlight-var" style="font-weight:600;">${escapeHtml(servDesc)}</span>.</p>

                        <div class="preview-clause-title">CLÁUSULA SEGUNDA: HONORARIOS Y RÉGIMEN TRIBUTARIO</div>
                        <p>Por la debida y oportuna ejecución de los servicios encomendados, el Mandante pagará al Profesional la suma bruta de <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">$${honorFmt} CLP</span>.<br>
                        El pago se efectuará: <span class="highlight-var">${escapeHtml(formaPago)}</span>, previa emisión y envío de la respectiva Boleta de Honorarios Electrónica (BHE).<br>
                        Régimen tributario: <span class="highlight-var">${escapeHtml(retencion)}</span> conforme a las normas de la Ley sobre Impuesto a la Renta y circulares del SII.</p>
                    `;

                    if (modServ === 'opcion_b') {
                        // Opción B: Por Hito / Proyecto Cerrado (7 Cláusulas)
                        const hitos = document.getElementById('f_serv_hitos_detalle')?.value || 'Entregables por fases aprobadas';
                        const garantia = document.getElementById('f_serv_garantia_tecnica')?.value || '60 días de soporte correctivo';

                        html += `
                            <div class="preview-clause-title">CLÁUSULA TERCERA: FASES, ENTREGABLES Y CRITERIOS DE ACEPTACIÓN</div>
                            <p>La ejecución y los pagos parciales se estructuran bajo el siguiente esquema de hitos verificables:<br>
                            <span class="highlight-var" style="white-space:pre-line;">${escapeHtml(hitos)}</span>.<br>
                            Cada hito se entenderá completado una vez que el Mandante otorgue su aprobación formal por escrito dentro de los 5 días hábiles siguientes a su presentación.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: GARANTÍA TÉCNICA Y SOPORTE CORRECTIVO POST-ENTREGA</div>
                            <p>El Profesional garantiza la calidad técnica, estabilidad y funcionalidad de los entregables conforme a los estándares de la industria, obligándose a: <span class="highlight-var">${escapeHtml(garantia)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: PROPIEDAD INTELECTUAL DE LOS ENTREGABLES</div>
                            <p>Una vez pagada la totalidad de los honorarios correspondientes al hito o entrega final, la titularidad patrimonial sobre todos los informes, planos, código fuente, desarrollos o diseños elaborados se transferirá íntegra y exclusivamente al Mandante (Ley N° 17.336), conservando el Profesional únicamente sus derechos morales irrenunciables.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: CONFIDENCIALIDAD Y DEBER DE RESERVA</div>
                            <p>El Profesional se compromete a mantener estricta reserva de toda información técnica, financiera y estratégica del Mandante a la que acceda con ocasión del servicio, subsistiendo este deber indefinidamente tras la conclusión del proyecto.</p>

                            <div class="preview-clause-title">CLÁUSULA SÉPTIMA: TÉRMINO ANTICIPADO Y JURISDICCIÓN</div>
                            <p>Cualquiera de las partes podrá poner término anticipado al contrato con <span class="highlight-var">${escapeHtml(avisoPrevio)}</span>, liquidándose exclusivamente los hitos efectivamente concluidos y aprobados. Para todos los efectos legales, las partes fijan domicilio en la ciudad de Santiago y se someten a sus tribunales ordinarios.</p>
                        `;
                    } else if (modServ === 'opcion_c') {
                        // Opción C: SLA Corporativo / Servicios Continuos con Penalizaciones (9 Cláusulas)
                        const slaTiempo = document.getElementById('f_serv_sla_tiempo')?.value || 'Crítico: 2h / Normal: 24h';
                        const slaMulta = document.getElementById('f_serv_sla_multa')?.value || '2% por hora de retardo';
                        const slaSeguro = document.getElementById('f_serv_sla_seguro')?.value || 'Sustitución en 5 días y responsabilidad civil';

                        html += `
                            <div class="preview-clause-title">CLÁUSULA TERCERA: ACUERDOS DE NIVEL DE SERVICIO (SLA) Y DISPONIBILIDAD</div>
                            <p>El Profesional y su equipo técnico se comprometen a prestar los servicios continuos asegurando los siguientes niveles de atención:<br>
                            <span class="highlight-var" style="font-weight:600;">${escapeHtml(slaTiempo)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: AUDITORÍA DE CALIDAD Y PENALIZACIONES POR INCUMPLIMIENTO</div>
                            <p>El Mandante auditará mensualmente el cumplimiento de los indicadores SLA pactados. En caso de retraso o incumplimiento atribuible al Profesional, se aplicará la siguiente penalización deducible de la liquidación mensual: <br>
                            <span class="highlight-var" style="color:#DC2626; font-weight:600;">${escapeHtml(slaMulta)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: CONTINUIDAD OPERATIVA Y SUSTITUCIÓN DE PERSONAL CLAVE</div>
                            <p>El Profesional garantiza la asignación de profesionales idóneos y titulados para el servicio. Procedimiento de reemplazo y resguardo: <span class="highlight-var">${escapeHtml(slaSeguro)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: AUTONOMÍA TÉCNICA E INDEPENDENCIA PATRIMONIAL</div>
                            <p>El Profesional ejecutará las labores con total autonomía técnica y con sus propios medios materiales, asumiendo la exclusiva responsabilidad respecto de sus colaboradores y dependientes, sin que exista vínculo laboral alguno con el Mandante.</p>

                            <div class="preview-clause-title">CLÁUSULA SÉPTIMA: PROPIEDAD INTELECTUAL Y CONFIDENCIALIDAD</div>
                            <p>Todos los desarrollos, scripts, bases de datos y metodologías adaptadas pertenecerán en forma exclusiva al Mandante al amparo de las Leyes 17.336 y 19.039, rigiendo secreto profesional perpetuo.</p>

                            <div class="preview-clause-title">CLÁUSULA OCTAVA: VIGENCIA Y CONDICIONES DE RESCISIÓN</div>
                            <p>El presente contrato tendrá vigencia indefinida sujeta a evaluación periódica de SLAs, pudiendo rescindirlo unilateralmente cualquiera de las partes con <span class="highlight-var">${escapeHtml(avisoPrevio)}</span> o en forma inmediata por reiteración de multas graves.</p>

                            <div class="preview-clause-title">CLÁUSULA NOVENA: DOMICILIO Y TRIBUNALES COMPETENTES</div>
                            <p>Las partes fijan su domicilio convencional en la comuna y ciudad de Santiago, sometiéndose a la competencia de sus Juzgados Ordinarios de Letras en lo Civil.</p>
                        `;
                    } else {
                        // Opción A: Civil Profesional con Blindaje Anti-Laboralidad (8 Cláusulas - Recomendado)
                        html += `
                            <div class="preview-clause-title">CLÁUSULA TERCERA: EXCLUSIÓN CATEGÓRICA DE SUBORDINACIÓN Y DEPENDENCIA</div>
                            <p>Las partes declaran expresa y solemnemente que el presente contrato es de naturaleza estrictamente civil conforme al Artículo 2006 del Código Civil, careciendo de vínculo laboral de subordinación y dependencia. En consecuencia, el Profesional:<br>
                            a) No está sujeto a jornada de trabajo, registro de asistencia ni horario rígido impuesto por el Mandante;<br>
                            b) No recibe órdenes jerárquicas directas ni está sujeto a potestad disciplinaria patronal;<br>
                            c) Cuenta con plena y absoluta autonomía técnica para determinar la forma, lugar e instrumentos para prestar sus servicios;<br>
                            d) No goza de exclusividad, pudiendo prestar libremente servicios para otras personas naturales o jurídicas.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: PROVISIÓN DE MEDIOS E INDEMNIDAD</div>
                            <p>El Profesional utilizará sus propios medios computacionales, licencias, vehículos y herramientas para el cumplimiento de sus labores, no teniendo derecho a prestaciones laborales tales como feriado legal, gratificaciones, asignaciones de colación o movilización, ni cotizaciones de seguridad social patronales.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: PROPIEDAD INTELECTUAL Y ENTREGA DE RESULTADOS</div>
                            <p>Todos los estudios, informes, diagnósticos, creaciones técnicas, código y documentación generada con ocasión de este contrato pertenecerán íntegra y exclusivamente al Mandante una vez satisfechos los honorarios acordados, en conformidad a la Ley N° 17.336 sobre Propiedad Intelectual.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: CONFIDENCIALIDAD Y SECRETO PROFESIONAL</div>
                            <p>El Profesional se obliga a guardar estricta reserva y secreto profesional respecto de toda información, datos personales, estrategias y antecedentes técnicos o comerciales a los que tenga acceso en virtud de este contrato, manteniéndose vigente este deber por un lapso no inferior a tres años tras su término.</p>

                            <div class="preview-clause-title">CLÁUSULA SÉPTIMA: VIGENCIA Y TÉRMINO ANTICIPADO (DESAHUCIO CIVIL)</div>
                            <p>El presente contrato entrará en vigencia a contar de la firma electrónica. Cualquiera de las partes podrá ponerle término anticipado, sin necesidad de invocar causa y sin derecho a indemnización alguna, dando aviso escrito a la otra con a lo menos <span class="highlight-var">${escapeHtml(avisoPrevio)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA OCTAVA: DOMICILIO, LEY APLICABLE Y JURISDICCIÓN</div>
                            <p>Para todos los efectos legales y judiciales emanados del presente contrato, las partes fijan su domicilio en la comuna y ciudad de Santiago y prorrogan competencia en sus Tribunales Ordinarios de Justicia en lo Civil, renunciando a cualquier otro fuero.</p>
                        `;
                    }
                } else if (CURRENT_TEMPLATE.slug === 'autorizacion-domicilio-tributario-sii') {
                    const modSii = document.getElementById('f_sii_modalidad')?.value || 'opcion_a';
                    const siiDir = document.getElementById('f_sii_direccion')?.value || 'Av. Andrés Bello 2711, Piso 8, Oficina 802, Las Condes, Santiago';
                    const siiRol = document.getElementById('f_sii_rol')?.value || '1234-56 de Las Condes';
                    const siiDest = document.getElementById('f_sii_destino')?.value || 'Domicilio tributario, comercial, postal y administrativo exclusivo';
                    const siiVig = document.getElementById('f_sii_vigencia')?.value || 'Indefinida mientras subsista el giro de la sociedad';
                    const siiRest = document.getElementById('f_sii_restitucion')?.value || 'Aviso escrito previo de 60 días corridos para que la empresa traslade su domicilio tributario en el SII';

                    if (modSii === 'opcion_c') {
                        // Opción C: Declaración Jurada Simple Unipersonal del Propietario (4 Cláusulas)
                        const apercib = document.getElementById('f_sii_apercibimiento')?.value || 'Bajo fe de juramento y apercibimiento del Art. 210 del Código Penal en caso de falsedad en declaración jurada ante la autoridad tributaria';
                        
                        html = `
                            <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                                DECLARACIÓN JURADA SIMPLE DE AUTORIZACIÓN DE DOMICILIO TRIBUTARIO (SII)
                            </div>
                            <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparece:</p>
                            
                            <p style="margin-top:0.6rem;">
                                <strong>EL PROPIETARIO DECLARANTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomA)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutA)}</span>, de nacionalidad y estado civil <span class="highlight-var">${escapeHtml(civilA)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, quien en pleno uso de sus facultades civiles viene en formular la siguiente declaración solemne ante el Servicio de Impuestos Internos (SII):
                            </p>

                            <div class="preview-clause-title">DECLARACIÓN PRIMERA: CALIDAD DE PROPIETARIO Y ROL DE AVALÚO FISCAL</div>
                            <p>El declarante declara bajo fe de juramento ser el exclusivo y legítimo propietario del bien raíz ubicado en <span class="highlight-var" style="font-weight:600;">${escapeHtml(siiDir)}</span>, enrolado para efectos fiscales ante el Servicio de Impuestos Internos bajo el <strong>Rol N° <span class="highlight-var">${escapeHtml(siiRol)}</span></strong>.</p>

                            <div class="preview-clause-title">DECLARACIÓN SEGUNDA: AUTORIZACIÓN EXPRESA DE USO Y DESTINO TRIBUTARIO</div>
                            <p>Por el presente instrumento, autoriza en forma libre, expresa e irrevocable para los trámites tributarios pertinentes, que la persona jurídica o contribuyente <span class="highlight-var" style="font-weight:700; color:#0F2A4A;">${escapeHtml(nomB)}</span> (RUT N° <span class="highlight-var">${escapeHtml(rutB)}</span>) fije, acredite y mantenga su <strong>domicilio tributario, comercial y administrativo</strong> en el inmueble singularizado precedentemente, para efectos de inicio de actividades, verificación de actividad, timbraje y emisión de documentos tributarios electrónicos (DTE).</p>

                            <div class="preview-clause-title">DECLARACIÓN TERCERA: DESTINO EXCLUSIVO Y VIGENCIA</div>
                            <p>Se deja constancia de que la autorización se otorga con carácter de: <span class="highlight-var">${escapeHtml(siiDest)}</span>, con una vigencia de <span class="highlight-var">${escapeHtml(siiVig)}</span>, no existiendo gravamen, prohibición ni impedimento contractual alguno que limite la presente autorización.</p>

                            <div class="preview-clause-title">DECLARACIÓN CUARTA: APERCIBIMIENTO LEGAL Y RESPONSABILIDAD PENAL (ART. 210 CP)</div>
                            <p>El declarante asume plena responsabilidad legal sobre la autenticidad de lo manifestado, declarando expresamente conocer las sanciones prescritas en el artículo 210 del Código Penal en relación al perjurio y falsedad en declaraciones formuladas ante la autoridad pública y tributaria: <span class="highlight-var" style="color:#B45309; font-weight:600;">${escapeHtml(apercib)}</span>.</p>
                        `;
                    } else if (modSii === 'opcion_b') {
                        // Opción B: Autorización por Subarrendamiento / Espacio Co-Work (6 Cláusulas)
                        const facCowork = document.getElementById('f_sii_facultad_cowork')?.value || 'Facultad expresa según contrato de arrendamiento matriz y estatutos del centro de negocios';
                        const servVirt = document.getElementById('f_sii_servicios_virtual')?.value || 'Recepción de correspondencia postal, notificación del SII y uso de salas de reuniones';

                        html = `
                            <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                                CONTRATO DE AUTORIZACIÓN DE DOMICILIO TRIBUTARIO Y OFICINA VIRTUAL (CO-WORK)
                            </div>
                            <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                            
                            <p style="margin-top:0.6rem;">
                                <strong>POR UNA PARTE:</strong> La entidad administradora y operadora del centro de negocios / espacio de trabajo colaborativo <span class="highlight-var">${escapeHtml(nomA)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutA)}</span>, Actividad / Giro: <span class="highlight-var">${escapeHtml(civilA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, debidamente representada por su Representante Legal <span class="highlight-var">${escapeHtml(profA)}</span>, en adelante el <strong>«Autorizante / Operador Co-Work»</strong>; y
                            </p>
                            
                            <p style="margin-top:0.6rem;">
                                <strong>POR LA OTRA PARTE:</strong> La sociedad o contribuyente <span class="highlight-var">${escapeHtml(nomB)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutB)}</span>, debidamente representada por don/doña <span class="highlight-var">${escapeHtml(profB)}</span>, cédula de identidad N° <span class="highlight-var">${escapeHtml(civilB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante el <strong>«Usuario / Contribuyente»</strong>.
                            </p>

                            <p style="margin-top:0.6rem;">Las partes comparecientes convienen en celebrar el presente contrato de autorización y cesión de domicilio al tenor de las siguientes 6 estipulaciones:</p>

                            <div class="preview-clause-title">CLÁUSULA PRIMERA: INMUEBLE, INSTALACIONES Y ROL DE AVALÚO FISCAL</div>
                            <p>El Operador Co-Work es legítimo poseedor y administrador del inmueble y dependencias comerciales ubicadas en <span class="highlight-var" style="font-weight:600;">${escapeHtml(siiDir)}</span>, enrolado en el Servicio de Impuestos Internos bajo el <strong>Rol N° <span class="highlight-var">${escapeHtml(siiRol)}</span></strong>.</p>

                            <div class="preview-clause-title">CLÁUSULA SEGUNDA: FACULTADES EXPRESAS DE CESIÓN Y SUBARRENDAMIENTO</div>
                            <p>El Operador declara y acredita contar con las debidas facultades legales y contractuales para autorizar el uso de domicilio tributario a terceros: <span class="highlight-var">${escapeHtml(facCowork)}</span>, cumpliendo cabalmente con las exigencias impartidas por el SII en su Circular N° 31 de 2007.</p>

                            <div class="preview-clause-title">CLÁUSULA TERCERA: SERVICIOS DE OFICINA VIRTUAL Y RECEPCIÓN TRIBUTARIA</div>
                            <p>El Operador autoriza formalmente al Usuario para fijar en el inmueble su domicilio tributario, comercial y postal oficial ante el SII y la Municipalidad respectiva, incluyendo los siguientes servicios anexos: <span class="highlight-var">${escapeHtml(servVirt)}</span>.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: DESTINO EXCLUSIVO Y FISCALIZACIÓN DEL SII</div>
                            <p>El inmueble será utilizado única y exclusivamente como: <span class="highlight-var">${escapeHtml(siiDest)}</span>. El Usuario se obliga a mantener disponible en el domicilio su documentación tributaria, patente comercial y autorizaciones sectoriales para facilitar las inspecciones y visitas de verificación de actividad de funcionarios del SII.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: VIGENCIA, PRÓRROGA Y TRASLADO DE DOMICILIO</div>
                            <p>La presente autorización tendrá una vigencia de <span class="highlight-var">${escapeHtml(siiVig)}</span>. Al término del contrato, regirá el siguiente procedimiento: <span class="highlight-var">${escapeHtml(siiRest)}</span>, debiendo acreditar el Usuario dicho cambio con el certificado respectivo emitido por el SII.</p>

                            <div class="preview-clause-title">CLÁUSULA SEXTA: DOMICILIO CONVENCIONAL Y JURISDICCIÓN</div>
                            <p>Para todos los efectos derivados de este contrato, las partes fijan domicilio convencional en la comuna y ciudad de Santiago y se someten a sus tribunales ordinarios de justicia.</p>
                        `;
                    } else {
                        // Opción A: Comodato Gratuito Inmobiliario - Formato Estándar SII Art. 2174 CC (5 Cláusulas - Recomendado)
                        html = `
                            <div style="text-align:center; font-weight:800; font-size:1.05rem; color:#0F2A4A; margin-bottom:1rem; letter-spacing:0.5px;">
                                CONTRATO DE COMODATO GRATUITO PARA DOMICILIO TRIBUTARIO (SII)
                            </div>
                            <p>En la ciudad de <strong>${escapeHtml(comA || 'Santiago')}</strong>, República de Chile, a <strong>${new Date().toLocaleDateString('es-CL')}</strong>, comparecen:</p>
                            
                            <p style="margin-top:0.6rem;">
                                <strong>POR UNA PARTE:</strong> Don/Doña <span class="highlight-var">${escapeHtml(nomA)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(rutA)}</span>, de nacionalidad y estado civil <span class="highlight-var">${escapeHtml(civilA)}</span>, profesión u oficio <span class="highlight-var">${escapeHtml(profA)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domA)}</span>, comuna de <span class="highlight-var">${escapeHtml(comA)}</span>, en adelante el <strong>«Comodante / Propietario»</strong>; y
                            </p>
                            
                            <p style="margin-top:0.6rem;">
                                <strong>POR LA OTRA PARTE:</strong> La entidad o sociedad <span class="highlight-var">${escapeHtml(nomB)}</span>, RUT N° <span class="highlight-var">${escapeHtml(rutB)}</span>, debidamente representada por su Representante Legal don/doña <span class="highlight-var">${escapeHtml(profB)}</span>, cédula nacional de identidad número <span class="highlight-var">${escapeHtml(civilB)}</span>, con domicilio en <span class="highlight-var">${escapeHtml(domB)}</span>, comuna de <span class="highlight-var">${escapeHtml(comB)}</span>, en adelante la <strong>«Comodataria / Empresa»</strong>.
                            </p>

                            <p style="margin-top:0.6rem;">Las partes mayores de edad convienen en celebrar el siguiente contrato de comodato o préstamo de uso al amparo del Artículo 2174 y siguientes del Código Civil y Circular N° 31 de 2007 del SII, sujeto a las siguientes 5 estipulaciones:</p>

                            <div class="preview-clause-title">CLÁUSULA PRIMERA: INDIVIDUALIZACIÓN DEL INMUEBLE Y ROL DE AVALÚO FISCAL</div>
                            <p>El Comodante declara ser el único y legítimo dueño del bien raíz ubicado en <span class="highlight-var" style="font-weight:600;">${escapeHtml(siiDir)}</span>, individualizado ante el Servicio de Impuestos Internos con el <strong>Rol de Avalúo Fiscal N° <span class="highlight-var">${escapeHtml(siiRol)}</span></strong>.</p>

                            <div class="preview-clause-title">CLÁUSULA SEGUNDA: OTORGAMIENTO DE COMODATO GRATUITO (ART. 2174 CÓDIGO CIVIL)</div>
                            <p>Por este instrumento, el Comodante entrega en comodato o préstamo de uso a título enteramente gratuito a la Comodataria, quien acepta para sí, el uso y goce de una parte determinada o de la totalidad del inmueble singularizado precedentemente, autorizándola de modo expreso a señalarlo, acreditarlo y registrarlo como su <strong>domicilio tributario, comercial, postal y legal</strong> ante el Servicio de Impuestos Internos (SII), Municipalidades, Tesorería General de la República y organismos públicos pertinentes.</p>

                            <div class="preview-clause-title">CLÁUSULA TERCERA: DESTINO EXCLUSIVO Y PROHIBICIÓN DE CESIÓN</div>
                            <p>El inmueble prestado en comodato se destinará exclusivamente a: <span class="highlight-var">${escapeHtml(siiDest)}</span>. Queda expresamente prohibido a la Comodataria subarrendar, ceder o transferir a terceros, a cualquier título, el uso del inmueble o del domicilio tributario conferido sin la expresa y previa autorización escrita del Comodante.</p>

                            <div class="preview-clause-title">CLÁUSULA CUARTA: VIGENCIA Y PROCEDIMIENTO DE RESTITUCIÓN / REVOCACIÓN</div>
                            <p>El presente comodato tendrá una duración de: <span class="highlight-var">${escapeHtml(siiVig)}</span>. No obstante lo anterior, el Comodante podrá requerir la restitución del inmueble y el cese del domicilio tributario conforme al siguiente procedimiento: <span class="highlight-var">${escapeHtml(siiRest)}</span>, debiendo la Comodataria tramitar y acreditar ante el Comodante el cambio oficial de domicilio ante el SII.</p>

                            <div class="preview-clause-title">CLÁUSULA QUINTA: DOMICILIO CONVENCIONAL Y PRÓRROGA DE COMPETENCIA</div>
                            <p>Para todos los efectos legales que emanen del presente contrato, las partes fijan su domicilio convencional en la comuna y ciudad de Santiago, República de Chile, y prorrogan expresamente jurisdicción ante sus Tribunales Ordinarios de Justicia en lo Civil.</p>
                        `;
                    }
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

            const isActuallyUnipersonal = isUnipersonal || (CURRENT_TEMPLATE.slug === 'autorizacion-domicilio-tributario-sii' && document.getElementById('f_sii_modalidad')?.value === 'opcion_c');

            const signers = [
                {
                    rol: isActuallyUnipersonal ? 'DECLARANTE' : 'PARTE_A',
                    nombre: document.getElementById('f_parte_a_nombre').value,
                    rut: document.getElementById('f_parte_a_rut').value,
                    email: document.getElementById('f_parte_a_email').value,
                    telefono: document.getElementById('f_parte_a_tel').value
                }
            ];

            if (!isActuallyUnipersonal) {
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
                    parte_a_comuna: document.getElementById('f_parte_a_comuna').value,
                    f_sii_modalidad: document.getElementById('f_sii_modalidad')?.value,
                    f_sii_direccion: document.getElementById('f_sii_direccion')?.value,
                    f_sii_rol: document.getElementById('f_sii_rol')?.value,
                    f_sii_destino: document.getElementById('f_sii_destino')?.value,
                    f_sii_vigencia: document.getElementById('f_sii_vigencia')?.value,
                    f_sii_restitucion: document.getElementById('f_sii_restitucion')?.value,
                    f_sii_facultad_cowork: document.getElementById('f_sii_facultad_cowork')?.value,
                    f_sii_servicios_virtual: document.getElementById('f_sii_servicios_virtual')?.value,
                    f_sii_apercibimiento: document.getElementById('f_sii_apercibimiento')?.value
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
