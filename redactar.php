<?php
/**
 * SinNotario.cl - Wizard Interactivo de Redacción (Layout 60/40)
 * Contrato de Arriendo Residencial Ley N° 21.461 ("Devuélveme Mi Casa")
 */
require_once __DIR__ . '/config/app.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : 'contrato-arriendo-ley-21461';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redactar Contrato de Arriendo Ley 21.461 | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .wizard-container {
            display: grid;
            grid-template-columns: 58% 42%;
            gap: 2rem;
            max-width: 1400px;
            margin: 1.5rem auto 3rem;
            padding: 0 1.5rem;
            align-items: start;
        }

        /* Formulario Izquierda (60%) */
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
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
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

        /* Marca de agua Borrador */
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
                <span class="card-category inmobiliario">Inmobiliario</span> Contrato de Arriendo Residencial Ley N° 21.461
            </div>
            <div>
                <a href="index.php" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </header>

    <!-- Wizard 60/40 -->
    <main class="wizard-container">
        
        <!-- Columna Izquierda: Formulario Guiado (60%) -->
        <div class="form-pane">
            <div class="step-progress-bar">
                <div id="pStep1" class="step-item active">
                    <div class="step-bubble">1</div>
                    <div class="step-label">Arrendador</div>
                </div>
                <div id="pStep2" class="step-item">
                    <div class="step-bubble">2</div>
                    <div class="step-label">Arrendatario</div>
                </div>
                <div id="pStep3" class="step-item">
                    <div class="step-bubble">3</div>
                    <div class="step-label">Inmueble</div>
                </div>
                <div id="pStep4" class="step-item">
                    <div class="step-bubble">4</div>
                    <div class="step-label">Renta y Plazos</div>
                </div>
                <div id="pStep5" class="step-item">
                    <div class="step-bubble">5</div>
                    <div class="step-label">Finalizar</div>
                </div>
            </div>

            <form id="arriendoWizardForm" onsubmit="event.preventDefault();">
                
                <!-- PASO 1: ARRENDADOR -->
                <div id="step1" class="wizard-step active">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 1: Datos del Propietario / Arrendador
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Identifica a quien da en arriendo el inmueble. Sus datos quedarán asociados a la firma con ClaveÚnica.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre Completo del Arrendador *</label>
                            <input type="text" id="f_arrendador_nombre" class="form-control" placeholder="Ej: Carlos Eduardo Morales Tapia" value="Carlos Eduardo Morales Tapia">
                        </div>
                        <div class="form-group">
                            <label>RUT Arrendador (Módulo 11) *</label>
                            <input type="text" id="f_arrendador_rut" class="form-control" placeholder="11.222.333-4" value="11.222.333-4" maxlength="12">
                            <span id="err_arrendador_rut" class="rut-error-text">RUT inválido</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Correo Electrónico (Para recibir enlace de firma) *</label>
                            <input type="email" id="f_arrendador_email" class="form-control" placeholder="carlos.morales@gmail.com" value="carlos.morales@gmail.com">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp del Arrendador *</label>
                            <input type="tel" id="f_arrendador_tel" class="form-control" placeholder="+56 9 9123 4567" value="+56 9 9123 4567">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Domicilio Particular *</label>
                            <input type="text" id="f_arrendador_domicilio" class="form-control" placeholder="Av. Providencia 1234, Depto 402" value="Av. Providencia 1234, Depto 402">
                        </div>
                        <div class="form-group">
                            <label>Comuna y Ciudad *</label>
                            <input type="text" id="f_arrendador_comuna" class="form-control" placeholder="Providencia, Santiago" value="Providencia, Santiago">
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <div></div>
                        <button type="button" class="btn-cta" onclick="nextStep(2)">
                            Siguiente: Arrendatario <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 2: ARRENDATARIO -->
                <div id="step2" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 2: Datos del Arrendatario (Inquilino)
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        A esta persona se le despachará el enlace seguro para suscribir el contrato desde su celular o computador.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre Completo del Arrendatario *</label>
                            <input type="text" id="f_arrendatario_nombre" class="form-control" placeholder="Ej: María José Valenzuela Castro" value="María José Valenzuela Castro">
                        </div>
                        <div class="form-group">
                            <label>RUT Arrendatario (Módulo 11) *</label>
                            <input type="text" id="f_arrendatario_rut" class="form-control" placeholder="15.444.555-6" value="15.444.555-6" maxlength="12">
                            <span id="err_arrendatario_rut" class="rut-error-text">RUT inválido</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Correo Electrónico Arrendatario *</label>
                            <input type="email" id="f_arrendatario_email" class="form-control" placeholder="mj.valenzuela@gmail.com" value="mj.valenzuela@gmail.com">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp del Arrendatario *</label>
                            <input type="tel" id="f_arrendatario_tel" class="form-control" placeholder="+56 9 8765 4321" value="+56 9 8765 4321">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" id="chk_tiene_aval" onchange="toggleAvalFields()">
                            <span><strong>¿Agregar Codeudor Solidario (Aval)?</strong> (Opcional para mayor respaldo)</span>
                        </label>
                    </div>

                    <div id="avalFieldsWrap" style="display:none; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:1rem; margin-top:1rem;">
                        <h4 style="font-size:0.9rem; font-weight:700; color:var(--primary); margin-bottom:0.75rem;">Datos del Codeudor Solidario / Aval</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre del Aval</label>
                                <input type="text" id="f_aval_nombre" class="form-control" placeholder="Nombre completo">
                            </div>
                            <div class="form-group">
                                <label>RUT del Aval</label>
                                <input type="text" id="f_aval_rut" class="form-control" placeholder="RUT" maxlength="12">
                                <span id="err_aval_rut" class="rut-error-text">RUT inválido</span>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevStep(1)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" class="btn-cta" onclick="nextStep(3)">
                            Siguiente: Inmueble <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 3: INMUEBLE -->
                <div id="step3" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 3: Identificación del Inmueble
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Especifica la dirección exacta de la propiedad objeto del arriendo.
                    </p>

                    <div class="form-group">
                        <label>Dirección de la Propiedad (Calle y Número) *</label>
                        <input type="text" id="f_propiedad_direccion" class="form-control" placeholder="Ej: Los Leones 2500" value="Los Leones 2500">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Depto / Casa / Oficina N°</label>
                            <input type="text" id="f_propiedad_depto" class="form-control" placeholder="Ej: Depto 504" value="Depto 504">
                        </div>
                        <div class="form-group">
                            <label>Comuna y Ciudad *</label>
                            <input type="text" id="f_propiedad_comuna" class="form-control" placeholder="Ej: Providencia, Santiago" value="Providencia, Santiago">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Bodega N° (Opcional)</label>
                            <input type="text" id="f_propiedad_bodega" class="form-control" placeholder="Ej: B-12" value="B-12">
                        </div>
                        <div class="form-group">
                            <label>Estacionamiento N° (Opcional)</label>
                            <input type="text" id="f_propiedad_estacionamiento" class="form-control" placeholder="Ej: E-45" value="E-45">
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevStep(2)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" class="btn-cta" onclick="nextStep(4)">
                            Siguiente: Renta y Plazos <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 4: RENTA Y PLAZOS -->
                <div id="step4" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 4: Renta Mensual, Garantía y Plazos
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Parámetros económicos y blindaje de la Ley N° 21.461 ("Devuélveme Mi Casa").
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Renta Mensual de Arriendo ($ CLP) *</label>
                            <input type="number" id="f_renta_clp" class="form-control" placeholder="Ej: 550000" value="550000">
                        </div>
                        <div class="form-group">
                            <label>Día Límite de Pago de Cada Mes *</label>
                            <input type="number" id="f_dia_pago" class="form-control" placeholder="5" value="5" min="1" max="28">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto de Garantía ($ CLP) *</label>
                            <input type="number" id="f_garantia_clp" class="form-control" placeholder="550000" value="550000">
                        </div>
                        <div class="form-group">
                            <label>Reajuste IPC *</label>
                            <select id="f_reajuste" class="form-control">
                                <option value="semestral">Semestral según variación IPC</option>
                                <option value="anual" selected>Anual según variación IPC</option>
                                <option value="trimestral">Trimestral según variación IPC</option>
                                <option value="sin reajuste">Sin reajuste</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Inicio del Contrato *</label>
                            <input type="date" id="f_fecha_inicio" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Duración del Contrato *</label>
                            <select id="f_duracion_meses" class="form-control">
                                <option value="12" selected>12 Meses (Renovable)</option>
                                <option value="6">6 Meses</option>
                                <option value="24">24 Meses</option>
                            </select>
                        </div>
                    </div>

                    <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; padding:1rem; margin-top:1rem; font-size:0.85rem; color:#1E3A8A;">
                        <i class="fas fa-balance-scale" style="color:var(--primary);"></i> <strong>Cláusula Ley N° 21.461:</strong> El contrato incorpora de forma irrevocable la sumisión al procedimiento monitorio con restitución anticipada de la propiedad y lanzamiento en 10 días corridos ante no pago de rentas.
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" class="btn-cta" onclick="nextStep(5)">
                            Siguiente: Revisar y Finalizar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PASO 5: RESUMEN Y CHECKOUT -->
                <div id="step5" class="wizard-step">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                        Paso 5: Resumen de la Orden y Despacho
                    </h2>
                    <p style="font-size: 0.85rem; color: #64748B; margin-bottom: 1.5rem;">
                        Verifica el resumen. Al confirmar, se generará el borrador criptográfico y el código QR de verificación.
                    </p>

                    <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:1.25rem; margin-bottom:1.5rem; font-size:0.88rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem;">
                            <span>Contrato de Arriendo Residencial Ley N° 21.461</span>
                            <strong>$9.990 CLP</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem; color:#64748B;">
                            <span>2 Firmas Electrónicas Avanzadas (ClaveÚnica)</span>
                            <strong style="color:#059669;">INCLUIDAS</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.6rem; color:#64748B;">
                            <span>Custodia Cifrada en Cloudflare R2 (5 años)</span>
                            <strong style="color:#059669;">INCLUIDA</strong>
                        </div>
                        <div style="border-top:1px solid #CBD5E1; padding-top:0.75rem; display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; color:var(--primary);">
                            <span>Total a Pagar:</span>
                            <span>$9.990 CLP</span>
                        </div>
                    </div>

                    <div style="margin-bottom:1.5rem;">
                        <label style="display:flex; align-items:flex-start; gap:0.5rem; font-size:0.85rem; color:#475569; cursor:pointer;">
                            <input type="checkbox" id="chk_terminos" checked style="margin-top:2px;">
                            <span>Acepto los Términos y Condiciones y declaro que los datos ingresados son fidedignos para la celebración del instrumento privado bajo la Ley N° 19.799.</span>
                        </label>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="prevStep(4)">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button type="button" id="btnEmitirContrato" class="btn-cta btn-gold" onclick="emitirContratoFinal()">
                            <i class="fas fa-shield-alt"></i> Emitir Contrato y Despachar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Columna Derecha: Live Document Preview (40%) -->
        <div class="preview-pane">
            <div class="preview-header">
                <h3><i class="far fa-file-alt"></i> Vista Previa en Vivo</h3>
                <span class="card-category" style="background:#FEF3C7; color:#92400E; font-size:0.7rem;">
                    Borrador Dinámico
                </span>
            </div>
            
            <div class="preview-body" id="docPreviewContainer">
                <div class="watermark">BORRADOR</div>

                <div class="preview-doc-title">Contrato de Arrendamiento de Inmueble</div>
                <div class="preview-doc-subtitle">Conforme a la Ley N° 18.101 y Ley N° 21.461 ("Devuélveme Mi Casa")</div>

                <p>
                    En la ciudad de <span id="pv_ciudad" class="highlight-var">Santiago</span>, República de Chile, a <span id="pv_fecha_hoy" class="highlight-var"><?= date('d/m/Y') ?></span>, comparecen:
                </p>

                <p style="margin-top: 0.75rem;">
                    <strong>DE UNA PARTE:</strong> Don/Doña <span id="pv_arrendador_nombre" class="highlight-var">Carlos Eduardo Morales Tapia</span>, cédula nacional de identidad N° <span id="pv_arrendador_rut" class="highlight-var">11.222.333-4</span>, domiciliado en <span id="pv_arrendador_domicilio" class="highlight-var">Av. Providencia 1234, Depto 402</span>, comuna de <span id="pv_arrendador_comuna" class="highlight-var">Providencia, Santiago</span>, correo <span id="pv_arrendador_email" class="highlight-var">carlos.morales@gmail.com</span>, en adelante el <strong>"Arrendador"</strong>;
                </p>

                <p style="margin-top: 0.75rem;">
                    <strong>DE OTRA PARTE:</strong> Don/Doña <span id="pv_arrendatario_nombre" class="highlight-var">María José Valenzuela Castro</span>, cédula nacional de identidad N° <span id="pv_arrendatario_rut" class="highlight-var">15.444.555-6</span>, correo <span id="pv_arrendatario_email" class="highlight-var">mj.valenzuela@gmail.com</span>, en adelante el <strong>"Arrendatario"</strong>;
                </p>

                <div id="pv_aval_clause" style="display:none; margin-top:0.75rem;">
                    <strong>Y COMO CODEUDOR SOLIDARIO:</strong> Don/Doña <span id="pv_aval_nombre" class="highlight-var">[Nombre Aval]</span>, cédula de identidad N° <span id="pv_aval_rut" class="highlight-var">[RUT Aval]</span>.
                </div>

                <div class="preview-clause-title">PRIMERO: Inmueble y Destino</div>
                <p>
                    El Arrendador da en arrendamiento al Arrendatario el inmueble ubicado en <span id="pv_propiedad_direccion" class="highlight-var">Los Leones 2500</span>, <span id="pv_propiedad_depto" class="highlight-var">Depto 504</span>, Bodega <span id="pv_propiedad_bodega" class="highlight-var">B-12</span>, Estacionamiento <span id="pv_propiedad_estacionamiento" class="highlight-var">E-45</span>, de la comuna de <span id="pv_propiedad_comuna" class="highlight-var">Providencia, Santiago</span>. El inmueble se destinará exclusivamente a fines habitacionales.
                </p>

                <div class="preview-clause-title">SEGUNDO: Renta de Arrendamiento y Reajuste</div>
                <p>
                    La renta mensual de arrendamiento será la suma de <strong>$<span id="pv_renta_clp" class="highlight-var">550.000</span> CLP</strong>, pagadera por mesadas anticipadas dentro de los primeros <span id="pv_dia_pago" class="highlight-var">5</span> días de cada mes calendario. La renta se reajustará de forma <span id="pv_reajuste" class="highlight-var">anual</span> según variación IPC.
                </p>

                <div class="preview-clause-title">TERCERO: Plazo de Vigencia</div>
                <p>
                    El contrato comenzará a regir el <span id="pv_fecha_inicio" class="highlight-var"><?= date('d/m/Y') ?></span> y tendrá una duración de <span id="pv_duracion_meses" class="highlight-var">12</span> meses, renovable automáticamente.
                </p>

                <div class="preview-clause-title">CUARTO: Sometimiento a la Ley N° 21.461 ("Devuélveme Mi Casa")</div>
                <p>
                    Las partes declaran someterse al procedimiento judicial monitorio consagrado en el Título III bis de la Ley N° 18.101 incorporado por la Ley N° 21.461, facultando el lanzamiento en el plazo de 10 días corridos ante mora no desvirtuada.
                </p>

                <div class="preview-clause-title">QUINTO: Firma Electrónica Avanzada y Notificaciones</div>
                <p>
                    Las partes suscriben este contrato digitalmente mediante Firma Electrónica Avanzada bajo la Ley N° 19.799, reconociéndole plena validez y mérito ejecutivo.
                </p>

                <div style="margin-top: 2rem; border-top: 1px dashed #CBD5E1; padding-top: 1rem; display: flex; justify-content: space-around; text-align: center; font-size: 0.75rem; color: #64748B;">
                    <div>
                        <div style="border: 1px solid #CBD5E1; border-radius: 4px; padding: 0.5rem 1rem; background: #F8FAFC;">[Pendiente Firma Arrendador]</div>
                        <div style="margin-top: 0.25rem; font-weight: 600;" id="pv_sign_arr">Carlos Eduardo Morales Tapia</div>
                    </div>
                    <div>
                        <div style="border: 1px solid #CBD5E1; border-radius: 4px; padding: 0.5rem 1rem; background: #F8FAFC;">[Pendiente Firma Arrendatario]</div>
                        <div style="margin-top: 0.25rem; font-weight: 600;" id="pv_sign_arrat">María José Valenzuela Castro</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Scripts del Wizard -->
    <script src="assets/js/app.js"></script>
    <script>
        // Inicializar fechas por defecto
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('f_fecha_inicio');
            if (dateInput) dateInput.value = today;

            // Formatear RUTs iniciales
            const arrRut = document.getElementById('f_arrendador_rut');
            const arrRutErr = document.getElementById('err_arrendador_rut');
            RutHelper.attachToInput(arrRut, arrRutErr);

            const arratRut = document.getElementById('f_arrendatario_rut');
            const arratRutErr = document.getElementById('err_arrendatario_rut');
            RutHelper.attachToInput(arratRut, arratRutErr);

            const avalRut = document.getElementById('f_aval_rut');
            const avalRutErr = document.getElementById('err_aval_rut');
            RutHelper.attachToInput(avalRut, avalRutErr);

            // Escuchar cambios en todos los inputs para actualizar la vista previa en tiempo real
            bindPreviewSync();
            updateLivePreview();
        });

        let currentStep = 1;

        function nextStep(step) {
            // Validar RUTs del paso previo
            if (currentStep === 1) {
                const rut = document.getElementById('f_arrendador_rut').value;
                if (!RutHelper.validate(rut)) {
                    alert('Por favor ingresa un RUT de Arrendador válido con dígito verificador correcto.');
                    document.getElementById('f_arrendador_rut').focus();
                    return;
                }
            } else if (currentStep === 2) {
                const rut = document.getElementById('f_arrendatario_rut').value;
                if (!RutHelper.validate(rut)) {
                    alert('Por favor ingresa un RUT de Arrendatario válido con dígito verificador correcto.');
                    document.getElementById('f_arrendatario_rut').focus();
                    return;
                }
            }

            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        function goToStep(step) {
            document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));

            const targetStep = document.getElementById('step' + step);
            if (targetStep) targetStep.classList.add('active');

            for (let i = 1; i <= 5; i++) {
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
            window.scrollTo({ top: 100, behavior: 'smooth' });
        }

        function toggleAvalFields() {
            const chk = document.getElementById('chk_tiene_aval');
            const wrap = document.getElementById('avalFieldsWrap');
            const pvClause = document.getElementById('pv_aval_clause');
            if (chk.checked) {
                wrap.style.display = 'block';
                pvClause.style.display = 'block';
            } else {
                wrap.style.display = 'none';
                pvClause.style.display = 'none';
            }
            updateLivePreview();
        }

        function bindPreviewSync() {
            const inputs = [
                'f_arrendador_nombre', 'f_arrendador_rut', 'f_arrendador_email', 'f_arrendador_domicilio', 'f_arrendador_comuna',
                'f_arrendatario_nombre', 'f_arrendatario_rut', 'f_arrendatario_email',
                'f_aval_nombre', 'f_aval_rut',
                'f_propiedad_direccion', 'f_propiedad_depto', 'f_propiedad_comuna', 'f_propiedad_bodega', 'f_propiedad_estacionamiento',
                'f_renta_clp', 'f_dia_pago', 'f_reajuste', 'f_fecha_inicio', 'f_duracion_meses'
            ];

            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', updateLivePreview);
                    el.addEventListener('change', updateLivePreview);
                }
            });
        }

        function updateLivePreview() {
            setVal('pv_arrendador_nombre', 'f_arrendador_nombre', 'Carlos Eduardo Morales Tapia');
            setVal('pv_arrendador_rut', 'f_arrendador_rut', '11.222.333-4');
            setVal('pv_arrendador_domicilio', 'f_arrendador_domicilio', 'Av. Providencia 1234, Depto 402');
            setVal('pv_arrendador_comuna', 'f_arrendador_comuna', 'Providencia, Santiago');
            setVal('pv_arrendador_email', 'f_arrendador_email', 'carlos.morales@gmail.com');
            setVal('pv_sign_arr', 'f_arrendador_nombre', 'Carlos Eduardo Morales Tapia');

            setVal('pv_arrendatario_nombre', 'f_arrendatario_nombre', 'María José Valenzuela Castro');
            setVal('pv_arrendatario_rut', 'f_arrendatario_rut', '15.444.555-6');
            setVal('pv_arrendatario_email', 'f_arrendatario_email', 'mj.valenzuela@gmail.com');
            setVal('pv_sign_arrat', 'f_arrendatario_nombre', 'María José Valenzuela Castro');

            setVal('pv_aval_nombre', 'f_aval_nombre', '[Nombre Aval]');
            setVal('pv_aval_rut', 'f_aval_rut', '[RUT Aval]');

            setVal('pv_propiedad_direccion', 'f_propiedad_direccion', 'Los Leones 2500');
            setVal('pv_propiedad_depto', 'f_propiedad_depto', 'Depto 504');
            setVal('pv_propiedad_comuna', 'f_propiedad_comuna', 'Providencia, Santiago');
            setVal('pv_propiedad_bodega', 'f_propiedad_bodega', 'B-12');
            setVal('pv_propiedad_estacionamiento', 'f_propiedad_estacionamiento', 'E-45');

            const rentaRaw = document.getElementById('f_renta_clp')?.value || '550000';
            const rentaFmt = new Intl.NumberFormat('es-CL').format(rentaRaw);
            const pvRenta = document.getElementById('pv_renta_clp');
            if (pvRenta) pvRenta.innerText = rentaFmt;

            setVal('pv_dia_pago', 'f_dia_pago', '5');
            setVal('pv_reajuste', 'f_reajuste', 'anual');
            setVal('pv_duracion_meses', 'f_duracion_meses', '12');

            const dateVal = document.getElementById('f_fecha_inicio')?.value;
            if (dateVal) {
                const parts = dateVal.split('-');
                if (parts.length === 3) {
                    const pvFecha = document.getElementById('pv_fecha_inicio');
                    if (pvFecha) pvFecha.innerText = `${parts[2]}/${parts[1]}/${parts[0]}`;
                }
            }
        }

        function setVal(targetId, sourceId, fallback) {
            const target = document.getElementById(targetId);
            const source = document.getElementById(sourceId);
            if (target && source) {
                target.innerText = source.value.trim() || fallback;
            }
        }

        async function emitirContratoFinal() {
            const chkTerminos = document.getElementById('chk_terminos');
            if (!chkTerminos || !chkTerminos.checked) {
                alert('Debes aceptar los Términos y Condiciones para emitir el contrato.');
                return;
            }

            const btn = document.getElementById('btnEmitirContrato');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando borrador criptográfico...';

            const payload = {
                template_slug: 'contrato-arriendo-ley-21461',
                signers: [
                    {
                        rol: 'ARRENDADOR',
                        nombre: document.getElementById('f_arrendador_nombre').value,
                        rut: document.getElementById('f_arrendador_rut').value,
                        email: document.getElementById('f_arrendador_email').value,
                        telefono: document.getElementById('f_arrendador_tel').value
                    },
                    {
                        rol: 'ARRENDATARIO',
                        nombre: document.getElementById('f_arrendatario_nombre').value,
                        rut: document.getElementById('f_arrendatario_rut').value,
                        email: document.getElementById('f_arrendatario_email').value,
                        telefono: document.getElementById('f_arrendatario_tel').value
                    }
                ],
                form_data: {
                    arrendador_nombre: document.getElementById('f_arrendador_nombre').value,
                    arrendador_rut: document.getElementById('f_arrendador_rut').value,
                    arrendador_email: document.getElementById('f_arrendador_email').value,
                    arrendador_tel: document.getElementById('f_arrendador_tel').value,
                    arrendador_domicilio: document.getElementById('f_arrendador_domicilio').value,
                    arrendador_comuna: document.getElementById('f_arrendador_comuna').value,
                    arrendatario_nombre: document.getElementById('f_arrendatario_nombre').value,
                    arrendatario_rut: document.getElementById('f_arrendatario_rut').value,
                    arrendatario_email: document.getElementById('f_arrendatario_email').value,
                    arrendatario_tel: document.getElementById('f_arrendatario_tel').value,
                    propiedad_direccion: document.getElementById('f_propiedad_direccion').value,
                    propiedad_depto: document.getElementById('f_propiedad_depto').value,
                    propiedad_comuna: document.getElementById('f_propiedad_comuna').value,
                    propiedad_bodega: document.getElementById('f_propiedad_bodega').value,
                    propiedad_estacionamiento: document.getElementById('f_propiedad_estacionamiento').value,
                    renta_clp: document.getElementById('f_renta_clp').value,
                    dia_pago: document.getElementById('f_dia_pago').value,
                    garantia_clp: document.getElementById('f_garantia_clp').value,
                    reajuste: document.getElementById('f_reajuste').value,
                    fecha_inicio: document.getElementById('f_fecha_inicio').value,
                    duracion_meses: document.getElementById('f_duracion_meses').value
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
                    alert(`¡Borrador Creado con Éxito!\n\nCódigo Único de Verificación: ${json.data.codigo_verificacion}\nEstado: BORRADOR EN CUSTODIA\n\nPróximo paso: Pasarela de pago Webpay/Flow para activar las firmas.`);
                    window.location.href = `index.php#verificar`;
                } else {
                    alert('Error al generar contrato: ' + (json.error || 'Error en servidor'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-shield-alt"></i> Emitir Contrato y Despachar <i class="fas fa-arrow-right"></i>';
                }
            } catch (err) {
                alert('Error de conexión al emitir el contrato.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt"></i> Emitir Contrato y Despachar <i class="fas fa-arrow-right"></i>';
            }
        }
    </script>
</body>
</html>
