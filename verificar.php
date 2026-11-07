<?php
/**
 * SinNotario.cl - Portal Oficial de Verificación Pública y Trazabilidad Forense (Módulo 4)
 * Valida la autenticidad, autoría e integridad de cualquier documento bajo la Ley N° 19.799.
 * Soporta 3 vías de verificación:
 * 1. Código Alfanumérico Único de Verificación (Folio: SN-XXXX-XXXX).
 * 2. Escaneo Directo de Código QR Dinámico (?code=SN-XXXX / ?doc=SN-XXXX).
 * 3. Cotejo Forense por Carga de Archivo PDF (Cálculo y Contraste de Hash SHA-256).
 */
require_once __DIR__ . '/database/Database.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : (isset($_GET['doc']) ? trim($_GET['doc']) : '');
$hashParam = isset($_GET['hash']) ? trim($_GET['hash']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador Oficial de Documentos y Firmas | SinNotario.cl</title>
    <meta name="description" content="Portal público de verificación de autenticidad y cotejo forense SHA-256 para contratos y documentos firmados bajo la Ley N° 19.799 en SinNotario.cl.">
    
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261106_16">

    <style>
        :root {
            --verif-primary: #0F2A4A;
            --verif-accent: #D4AF37;
            --verif-success: #059669;
            --verif-danger: #DC2626;
            --verif-card-bg: #FFFFFF;
        }

        body {
            background-color: #F8FAFC;
            color: #1E293B;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .verif-hero {
            background: linear-gradient(135deg, #0A1C33 0%, #0F2A4A 100%);
            color: #FFFFFF;
            padding: 4rem 1.5rem 3rem;
            text-align: center;
            position: relative;
            border-bottom: 4px solid var(--verif-accent);
        }

        .verif-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0.5rem 0 0.8rem;
            letter-spacing: -0.02em;
        }

        .verif-hero p {
            font-size: 1.05rem;
            color: #CBD5E1;
            max-width: 720px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .verif-badge-top {
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
            margin-bottom: 1rem;
        }

        .verif-container {
            max-width: 980px;
            margin: -2.5rem auto 4rem;
            padding: 0 1.5rem;
            position: relative;
            z-index: 10;
        }

        .verif-methods-card {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #E2E8F0;
            padding: 2rem;
        }

        .tab-nav {
            display: flex;
            border-bottom: 2px solid #E2E8F0;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #64748B;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            color: var(--verif-primary);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--verif-primary);
            border-radius: 3px 3px 0 0;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .search-box-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .search-box-input {
            flex: 1;
            min-width: 260px;
            padding: 0.9rem 1.25rem;
            font-size: 1.05rem;
            font-weight: 600;
            border: 2px solid #CBD5E1;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0F2A4A;
            transition: border-color 0.2s;
        }

        .search-box-input:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn-verif-submit {
            background: var(--verif-primary);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 0.9rem 1.75rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.2s ease;
        }

        .btn-verif-submit:hover {
            background: #1B3B6F;
            transform: translateY(-1px);
        }

        .dropzone-area {
            border: 2px dashed #94A3B8;
            border-radius: 12px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s;
        }

        .dropzone-area:hover, .dropzone-area.dragover {
            border-color: #2563EB;
            background: #EFF6FF;
        }

        .result-card {
            margin-top: 2rem;
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-valid {
            background: #FFFFFF;
            border: 2px solid #059669;
        }

        .result-invalid {
            background: #FFFFFF;
            border: 2px solid #DC2626;
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #E2E8F0;
            margin-bottom: 1.5rem;
        }

        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .status-icon-valid {
            background: #DEF7EC;
            color: #059669;
        }

        .status-icon-invalid {
            background: #FEE2E2;
            color: #DC2626;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 1rem;
        }

        .meta-item label {
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748B;
            display: block;
            margin-bottom: 0.35rem;
        }

        .meta-item value {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0F2A4A;
            display: block;
            word-break: break-all;
        }

        .signers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.88rem;
        }

        .signers-table th {
            background: #0F2A4A;
            color: #FFFFFF;
            padding: 0.65rem 0.85rem;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .signers-table td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid #E2E8F0;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-pill-success {
            background: #DEF7EC;
            color: #03543F;
        }

        .badge-pill-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .forensic-hash-box {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.82rem;
            color: #334155;
            word-break: break-all;
            margin-top: 0.5rem;
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
                <li><a href="subir-documento.php" style="color: var(--accent); font-weight: 800;"><i class="fas fa-file-upload"></i> Subir tu Documento</a></li>
                <li><a href="verificar.php" style="color: var(--accent); font-weight: 700;">Verificar Documento</a></li>
                <li><a href="index.php#empresas">Solución Empresas</a></li>
                <li><a href="https://wa.me/56984180479?text=Hola,%20necesito%20asistencia%20legal%20en%20SinNotario.cl" target="_blank" class="nav-whatsapp"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
            </ul>
            <div>
                <a href="index.php#catalogo" class="btn-cta btn-primary" style="padding:0.5rem 1.1rem; font-size:0.88rem;">
                    <i class="fas fa-file-signature"></i> Redactar Contrato
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="verif-hero">
        <div class="verif-badge-top">
            <i class="fas fa-certificate"></i> Ley N° 19.799 & Bóveda Cloudflare R2
        </div>
        <h1>Portal Oficial de Verificación y Cotejo Forense</h1>
        <p>
            Comprueba en tiempo real la autenticidad, autoría, fecha cierta e integridad criptográfica de cualquier contrato o declaración jurada emitida por la plataforma SinNotario.cl.
        </p>
    </section>

    <!-- Main Container -->
    <main class="verif-container">
        <div class="verif-methods-card">
            <!-- Tabs -->
            <div class="tab-nav">
                <button type="button" class="tab-btn active" onclick="switchVerifTab('tab-folio')">
                    <i class="fas fa-barcode"></i> Verificación por Folio / QR
                </button>
                <button type="button" class="tab-btn" onclick="switchVerifTab('tab-hash')">
                    <i class="fas fa-fingerprint"></i> Cotejo Forense de Archivo (Hash SHA-256)
                </button>
            </div>

            <!-- Tab 1: Código Folio -->
            <div id="tab-folio" class="tab-content active">
                <p style="color: #64748B; font-size: 0.95rem; margin-bottom: 1.25rem;">
                    Ingresa el código alfanumérico único impreso en el pie de página de cada hoja o leído por el código QR:
                </p>
                <form id="formVerifyCode" onsubmit="event.preventDefault(); verifyByCode();">
                    <div class="search-box-row">
                        <input type="text" id="inputFolio" class="search-box-input" placeholder="Ej: SN-297C-BA91" value="<?= htmlspecialchars($code) ?>">
                        <button type="submit" id="btnSubmitFolio" class="btn-verif-submit">
                            <i class="fas fa-search"></i> Validar Documento
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Cotejo de Archivo PDF -->
            <div id="tab-hash" class="tab-content">
                <p style="color: #64748B; font-size: 0.95rem; margin-bottom: 1.25rem;">
                    Arrastra o selecciona el archivo PDF que recibiste por correo. El sistema calculará su huella criptográfica SHA-256 en tu propio navegador y la contrastará contra el original sellado:
                </p>
                <div class="dropzone-area" id="pdfDropzone" onclick="document.getElementById('filePdfInput').click()">
                    <i class="fas fa-file-pdf fa-3x" style="color: #DC2626; margin-bottom: 0.75rem;"></i>
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: #0F2A4A; margin-bottom: 0.35rem;">
                        Haz clic o arrastra aquí tu archivo PDF
                    </h3>
                    <p style="font-size: 0.82rem; color: #64748B; margin: 0;">
                        Cálculo seguro local sin subir contenido confidencial innecesario.
                    </p>
                    <input type="file" id="filePdfInput" accept="application/pdf" style="display: none;" onchange="handleFileUpload(this.files[0])">
                </div>
                <div id="fileHashStatus" style="margin-top: 1rem; display: none;"></div>
            </div>

            <!-- Contenedor Dinámico de Resultados -->
            <div id="verifResultContainer" style="display: none;"></div>
        </div>

        <!-- Garantías Legales y Pilares -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-top: 2.5rem;">
            <div style="background: white; border-radius: 12px; border: 1px solid #E2E8F0; padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="background: #EFF6FF; color: #2563EB; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: #0F2A4A; margin: 0;">Ley N° 19.799</h4>
                </div>
                <p style="font-size: 0.85rem; color: #64748B; line-height: 1.5; margin: 0;">
                    Equivalencia legal plena entre soporte electrónico y papel. Los documentos suscritos digitalmente hacen fe probatoria en tribunales civiles y laborales.
                </p>
            </div>

            <div style="background: white; border-radius: 12px; border: 1px solid #E2E8F0; padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="background: #ECFDF5; color: #059669; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="fas fa-shield-virus"></i>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: #0F2A4A; margin: 0;">Anti-Adulteración</h4>
                </div>
                <p style="font-size: 0.85rem; color: #64748B; line-height: 1.5; margin: 0;">
                    Cualquier intento de alteración posterior a la firma cambia el hash SHA-256 del documento, invalidándolo instantáneamente ante cualquier tercero receptor.
                </p>
            </div>

            <div style="background: white; border-radius: 12px; border: 1px solid #E2E8F0; padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="background: #FAF5FF; color: #7C3AED; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="fas fa-cloud"></i>
                    </div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: #0F2A4A; margin: 0;">Cloudflare R2 Vault</h4>
                </div>
                <p style="font-size: 0.85rem; color: #64748B; line-height: 1.5; margin: 0;">
                    Custodia distribuida y cifrada en reposo con AES-256. Garantía de disponibilidad 24/7 y trazabilidad forense permanente para bancos y juzgados.
                </p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background: #0A1C33; color: #94A3B8; padding: 3rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem;">
        <div style="max-width: 1140px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <strong style="color: white;">SinNotario.cl</strong> — Servicios Tecnológicos y Firma Electrónica Avanzada bajo la Ley N° 19.799.
            </div>
            <div style="display: flex; gap: 1.5rem;">
                <a href="index.php" style="color: #CBD5E1; text-decoration: none;">Inicio</a>
                <a href="marco-legal.php" style="color: #CBD5E1; text-decoration: none;">Marco Legal</a>
                <a href="terminos-condiciones.php" style="color: #CBD5E1; text-decoration: none;">Términos & Condiciones</a>
                <a href="politica-privacidad.php" style="color: #CBD5E1; text-decoration: none;">Privacidad</a>
            </div>
        </div>
    </footer>

    <!-- Scripts de Validación -->
    <script>
        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function switchVerifTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tabId === 'tab-folio') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('tab-folio').classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('tab-hash').classList.add('active');
            }
        }

        async function verifyByCode(overrideCode = null) {
            const input = document.getElementById('inputFolio');
            const code = overrideCode || (input ? input.value.trim().toUpperCase() : '');
            const btn = document.getElementById('btnSubmitFolio');
            const container = document.getElementById('verifResultContainer');

            if (!code) {
                alert('Por favor ingresa un código de folio válido (Ej: SN-297C-BA91)');
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando Bóveda...';
            }

            container.style.display = 'block';
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #64748B;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: #2563EB; margin-bottom: 0.75rem;"></i>
                    <p style="margin: 0; font-weight: 600;">Verificando credenciales criptográficas y sellos de tiempo...</p>
                </div>
            `;

            try {
                const res = await fetch(`api/index.php?action=verify_document&code=${encodeURIComponent(code)}`);
                const json = await res.json();

                if (json.success && json.data && json.data.document) {
                    renderValidDocument(json.data.document, json.data.signers, json.data.is_valid);
                } else {
                    renderInvalidDocument(code, json.error || 'El código ingresado no existe o no corresponde a un documento emitido por SinNotario.cl.');
                }
            } catch (err) {
                container.innerHTML = `
                    <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 1.5rem; color: #991B1B; text-align: center;">
                        <i class="fas fa-exclamation-triangle fa-2x" style="color: #DC2626; margin-bottom: 0.5rem;"></i>
                        <h4 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.5rem;">Error de Conexión</h4>
                        <p style="font-size: 0.9rem; margin: 0;">No fue posible conectar con el servicio de verificación. Inténtalo nuevamente.</p>
                    </div>
                `;
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-search"></i> Validar Documento';
                }
            }
        }

        function renderValidDocument(doc, signers, isValid) {
            const container = document.getElementById('verifResultContainer');
            const isCompleted = (doc.estado === 'COMPLETADO');
            const badgeColor = isCompleted ? '#059669' : '#D97706';
            const badgeBg = isCompleted ? '#ECFDF5' : '#FEF3C7';
            const statusText = isCompleted ? 'DOCUMENTO VÁLIDO Y FIRMADO ELECTRÓNICAMENTE' : 'DOCUMENTO REGISTRADO EN TRÁMITE / BORRADOR ACTIVO';
            const statusDesc = isCompleted 
                ? 'El presente instrumento cuenta con plena validez jurídica conforme al Artículo 3° de la Ley N° 19.799 y se encuentra sellado criptográficamente en la Bóveda Cloudflare R2.'
                : 'El documento existe en la plataforma SinNotario.cl y se encuentra en etapa de emisión o firma de los comparecientes.';

            let signersHtml = '';
            if (signers && signers.length > 0) {
                signersHtml = `
                    <div style="margin-top: 1.5rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: #0F2A4A; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-users"></i> Cadena de Custodia de Firmantes
                        </h4>
                        <table class="signers-table">
                            <thead>
                                <tr>
                                    <th>Compareciente</th>
                                    <th>RUT Oficial</th>
                                    <th>Rol</th>
                                    <th>Estado de Firma</th>
                                    <th>Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${signers.map(s => `
                                    <tr>
                                        <td><strong>${escapeHtml(s.nombre_completo)}</strong></td>
                                        <td>${escapeHtml(s.rut)}</td>
                                        <td><span style="color:#64748B; font-weight:600;">${escapeHtml(s.rol_firmante)}</span></td>
                                        <td>
                                            ${s.estado_firma === 'FIRMADO' 
                                                ? `<span class="badge-pill badge-pill-success"><i class="fas fa-check-circle"></i> FIRMADO (${escapeHtml(s.metodo_firma)})</span>`
                                                : `<span class="badge-pill badge-pill-pending"><i class="fas fa-clock"></i> ${escapeHtml(s.estado_firma)}</span>`}
                                        </td>
                                        <td style="color:#64748B;">${s.fecha_firma ? escapeHtml(s.fecha_firma) : 'Pendiente'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            container.innerHTML = `
                <div class="result-card result-valid">
                    <div class="status-header">
                        <div class="status-icon status-icon-valid">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <span style="background:${badgeBg}; color:${badgeColor}; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:9999px; text-transform:uppercase; letter-spacing:0.5px; border:1px solid ${badgeColor}33;">
                                ${escapeHtml(doc.estado)}
                            </span>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: #065F46; margin: 0.4rem 0 0.2rem;">
                                ${statusText}
                            </h3>
                            <p style="font-size: 0.88rem; color: #047857; margin: 0; line-height: 1.5;">
                                ${statusDesc}
                            </p>
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-item">
                            <label>Nombre del Instrumento</label>
                            <value>${escapeHtml(doc.titulo || doc.template_nombre)}</value>
                        </div>
                        <div class="meta-item">
                            <label>Código de Folio Oficial</label>
                            <value style="color: #2563EB;">${escapeHtml(doc.codigo_verificacion)}</value>
                        </div>
                        <div class="meta-item">
                            <label>Fecha de Emisión y Registro</label>
                            <value>${escapeHtml(doc.created_at)}</value>
                        </div>
                        <div class="meta-item">
                            <label>Bóveda de Custodia</label>
                            <value><i class="fas fa-server" style="color:#F59E0B;"></i> Cloudflare R2 Vault (Chile)</value>
                        </div>
                    </div>

                    ${doc.pdf_sha256 ? `
                        <div style="margin-top: 1rem;">
                            <label style="font-size:0.78rem; text-transform:uppercase; font-weight:700; color:#64748B;">
                                <i class="fas fa-fingerprint"></i> Huella Digital Criptográfica Original (Hash SHA-256)
                            </label>
                            <div class="forensic-hash-box">
                                ${escapeHtml(doc.pdf_sha256)}
                            </div>
                        </div>
                    ` : ''}

                    ${signersHtml}

                    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed #CBD5E1; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <div style="font-size:0.8rem; color:#64748B;">
                            <i class="fas fa-lock"></i> Sellado bajo estándar ISO/IEC 27001 con firma electrónica según Ley N° 19.799.
                        </div>
                        <button type="button" class="btn-verif-submit" style="padding:0.6rem 1.2rem; font-size:0.88rem; background:#059669;" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir Certificado de Validación
                        </button>
                    </div>
                </div>
            `;
        }

        function renderInvalidDocument(code, reason) {
            const container = document.getElementById('verifResultContainer');
            container.innerHTML = `
                <div class="result-card result-invalid">
                    <div class="status-header">
                        <div class="status-icon status-icon-invalid">
                            <i class="fas fa-times"></i>
                        </div>
                        <div>
                            <span style="background:#FEE2E2; color:#DC2626; font-weight:800; font-size:0.75rem; padding:3px 10px; border-radius:9999px; text-transform:uppercase; letter-spacing:0.5px; border:1px solid rgba(220,38,38,0.2);">
                                ADVERTENCIA DE SEGURIDAD
                            </span>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: #991B1B; margin: 0.4rem 0 0.2rem;">
                                DOCUMENTO NO ENCONTRADO O CÓDIGO NO VÁLIDO
                            </h3>
                            <p style="font-size: 0.88rem; color: #7F1D1D; margin: 0; line-height: 1.5;">
                                El folio consultado no coincide con ningún registro activo o firmado en la plataforma.
                            </p>
                        </div>
                    </div>
                    <div style="background:#FFF5F5; border:1px solid #FECACA; border-radius:8px; padding:1.25rem; font-size:0.9rem; color:#7F1D1D;">
                        <p style="margin: 0 0 0.5rem;"><strong>Código Consultado:</strong> <code style="font-weight:700;">${escapeHtml(code)}</code></p>
                        <p style="margin: 0; line-height: 1.5;">${escapeHtml(reason)}</p>
                    </div>
                </div>
            `;
        }

        // Manejo de drag and drop para el cotejo forense SHA-256
        const dropzone = document.getElementById('pdfDropzone');
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    handleFileUpload(files[0]);
                }
            }, false);
        }

        async function handleFileUpload(file) {
            if (!file || file.type !== 'application/pdf') {
                alert('Por favor selecciona un archivo PDF válido.');
                return;
            }

            const status = document.getElementById('fileHashStatus');
            status.style.display = 'block';
            status.innerHTML = `
                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 1rem; color: #1E40AF; font-size: 0.9rem;">
                    <i class="fas fa-spinner fa-spin"></i> Leyendo archivo <strong>${escapeHtml(file.name)}</strong> (${(file.size / 1024).toFixed(1)} KB) y computando huella SHA-256...
                </div>
            `;

            try {
                const arrayBuffer = await file.arrayBuffer();
                const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                const computedHash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

                status.innerHTML = `
                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 1rem; color: #166534; font-size: 0.9rem;">
                        <p style="margin:0 0 0.5rem;"><strong><i class="fas fa-check-circle"></i> Archivo Procesado Localmente:</strong> ${escapeHtml(file.name)}</p>
                        <p style="margin:0; font-size:0.8rem; color:#475569;">Hash SHA-256 Computado:</p>
                        <div class="forensic-hash-box" style="margin-top:0.3rem; background:white;">${computedHash}</div>
                    </div>
                `;

                // Consultar a la API por el hash computado
                queryByHash(computedHash);

            } catch (err) {
                status.innerHTML = `<div style="color: #DC2626; padding: 0.5rem 0;">Error al calcular el hash del archivo: ${escapeHtml(err.message)}</div>`;
            }
        }

        async function queryByHash(hash) {
            const container = document.getElementById('verifResultContainer');
            container.style.display = 'block';
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #64748B;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: #2563EB; margin-bottom: 0.75rem;"></i>
                    <p style="margin: 0; font-weight: 600;">Contrastando huella criptográfica contra los documentos sellados en la bóveda...</p>
                </div>
            `;

            try {
                const res = await fetch(`api/index.php?action=verify_by_hash&hash=${encodeURIComponent(hash)}`);
                const json = await res.json();

                if (json.success && json.data && json.data.document) {
                    renderValidDocument(json.data.document, json.data.signers, json.data.is_valid);
                } else {
                    renderInvalidDocument(hash.substring(0, 16) + '...', 'El archivo PDF subido no coincide con ningún documento sellado en la bóveda de SinNotario.cl. Si el archivo fue editado o alterado posteriormente a la firma, el hash resultante deja de coincidir.');
                }
            } catch (err) {
                container.innerHTML = '<div style="color:#DC2626; text-align:center; padding:1.5rem;">Error al cotejar hash con el servidor.</div>';
            }
        }

        // Si viene un código por URL (?code= o ?doc=), ejecutar automáticamente
        document.addEventListener('DOMContentLoaded', () => {
            const urlCode = "<?= addslashes($code) ?>";
            if (urlCode.trim().length > 0) {
                verifyByCode(urlCode.trim());
            }
        });
    </script>
</body>
</html>
