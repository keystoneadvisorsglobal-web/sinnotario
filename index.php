<?php
/**
 * SinNotario.cl - Portal Oficial
 * Plataforma LegalTech de Contratos Inteligentes, Firma Digital y Validación
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SinNotario.cl | Contratos Legales y Firma Digital sin Notaría</title>
    <meta name="description" content="Genera, firma y valida contratos de arriendo Ley 21.461, promesas, finiquitos laborales y declaraciones juradas 100% online con plena validez legal (Ley 19.799).">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Principales -->
    <link rel="stylesheet" href="assets/css/style.css?v=20261106_3">
</head>
<body>

    <!-- Header / Navbar Limpio -->
    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="<?= $page === 'home' ? 'active' : '' ?>">Trámites</a></li>
                <li><a href="subir-documento.php" style="color: var(--accent); font-weight: 800;"><i class="fas fa-file-upload"></i> Subir tu Documento</a></li>
                <li><a href="verificar.php">Verificar Documento</a></li>
                <li><a href="#empresas">Solución Empresas</a></li>
                <li><a href="https://wa.me/56984180479?text=Hola,%20necesito%20asistencia%20legal%20en%20SinNotario.cl" target="_blank" class="nav-whatsapp"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
            </ul>

            <div>
                <a href="#tramites" class="btn-cta">
                    <i class="fas fa-file-signature"></i> Iniciar Trámite
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-badge">
                <i class="fas fa-balance-scale"></i> Plena Validez Jurídica | Ley N° 19.799 y Ley N° 21.461
            </div>
            <h1 class="hero-title">
                TUS CONTRATOS Y TRÁMITES LEGALES <br>
                <span class="highlight">100% ONLINE Y SIN PISAR UNA NOTARÍA</span>
            </h1>
            <p class="hero-subtitle">
                Firma contratos de arriendo express, declaraciones juradas, promesas y finiquitos desde tu celular o computador con firma electrónica avanzada y certificación digital inmediata.
            </p>

            <!-- Buscador Inteligente -->
            <div class="search-container">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="mainSearchInput" class="search-input" placeholder="¿Qué documento necesitas firmar hoy? (Ej: arriendo, mudanza, finiquito...)">
                    <button class="search-btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>

                <!-- Detector Negativo de Notaría Exclusiva -->
                <div id="negativeAlertBox" class="negative-alert-box"></div>
            </div>

            <!-- Banner Promocional: Subir Documento Propio -->
            <div style="max-width: 760px; margin: 2rem auto 0; background: rgba(255,255,255,0.92); border: 2px dashed #0284C7; border-radius: 14px; padding: 1.25rem 1.75rem; display: flex; align-items: center; justify-content: space-between; gap: 1.2rem; flex-wrap: wrap; box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.15);">
                <div style="display: flex; align-items: center; gap: 1rem; text-align: left;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #0F172A; font-size: 1.05rem;">¿Ya tienes tu documento o contrato redactado?</div>
                        <div style="color: #64748B; font-size: 0.88rem;">Sube tu archivo PDF propio y envíalo a firmar a todas las partes en minutos con validez Ley 19.799.</div>
                    </div>
                </div>
                <a href="subir-documento.php" class="btn-cta" style="padding: 0.7rem 1.4rem; font-size: 0.92rem; background: #0284C7; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); white-space: nowrap;">
                    <i class="fas fa-cloud-upload-alt"></i> Subir PDF Propio &rarr;
                </a>
            </div>
        </section>

        <!-- Catálogo de Plantillas con Carga Progresiva -->
        <section id="tramites" class="catalog-section">
            <div class="filter-pills">
                <button class="filter-btn active" data-cat="todos">Todos los Trámites</button>
                <button class="filter-btn" data-cat="inmobiliario"><i class="fas fa-home"></i> Inmobiliario & Arriendos</button>
                <button class="filter-btn" data-cat="laboral"><i class="fas fa-user-tie"></i> Laboral & RRHH</button>
                <button class="filter-btn" data-cat="comercial"><i class="fas fa-briefcase"></i> Comercial & Deudas</button>
                <button class="filter-btn" data-cat="tramites"><i class="fas fa-file-alt"></i> Declaraciones & Trámites</button>
            </div>

            <div id="templateGrid" class="template-grid">
                <!-- Inyectado dinámicamente vía API (3 iniciales) -->
            </div>

            <!-- Botón Cargar Más Trámites -->
            <div id="loadMoreContainer" class="load-more-wrap" style="display: none;">
                <button id="btnLoadMore" class="btn-load-more" onclick="loadMoreTemplates()">
                    Ver más trámites <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </section>

        <!-- Sección de Validación Pública (/verificar) -->
        <section id="verificar" style="background: #F8FAFC; border-top: 1px solid var(--border); padding: 5rem 1.5rem;">
            <div style="max-width: 1060px; margin: 0 auto; text-align: center;">
                <span class="card-category" style="background: #ECFDF5; color: #047857; font-weight: 700; padding: 0.4rem 1rem; border-radius: 9999px;">
                    Módulo Oficial de Autenticidad
                </span>
                <h2 style="font-size: 2.3rem; font-weight: 800; color: var(--primary); margin: 0.75rem 0 0.85rem; letter-spacing: -0.02em;">
                    Verificador de Integridad y Firma
                </h2>
                <p style="color: #64748B; font-size: 1.05rem; margin-bottom: 2.25rem; line-height: 1.6; max-width: 780px; margin-left: auto; margin-right: auto;">
                    Ingresa el código único alfanumérico impreso al pie de página de cualquier documento emitido por SinNotario.cl para consultar su firma digital y bóveda de custodia en Cloudflare R2.
                </p>

                <!-- Cuadro de Verificación Ampliado y Desahogado -->
                <div style="background: #FFFFFF; border: 1.5px solid #CBD5E1; border-radius: 14px; box-shadow: 0 4px 20px -2px rgba(15, 42, 74, 0.08); padding: 1.5rem 1.75rem; max-width: 920px; margin: 0 auto;">
                    <div style="display: flex; gap: 0.85rem; align-items: center; justify-content: center; flex-wrap: wrap;">
                        <!-- Input con borde claro, padding cómodo y placeholder legible -->
                        <div style="position: relative; flex: 1; min-width: 280px; display: flex; align-items: center;">
                            <i class="fas fa-qrcode" style="position: absolute; left: 1rem; color: #94A3B8; font-size: 1.2rem; pointer-events: none;"></i>
                            <input type="text" id="verifyCodeInput" placeholder="Ingresa el código ej: SN-4A9B-81CD" 
                                   style="width: 100%; border: 1.5px solid #CBD5E1; border-radius: 8px; padding: 0.8rem 1rem 0.8rem 2.85rem; font-size: 1rem; font-weight: 600; color: #0F2A4A; text-transform: uppercase; background: #FFFFFF; outline: none; transition: border-color 0.2s, box-shadow 0.2s;"
                                   onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.12)';"
                                   onblur="this.style.borderColor='#CBD5E1'; this.style.boxShadow='none';">
                        </div>

                        <!-- Botón estilizado con altura controlada y texto balanceado -->
                        <button id="btnSubmitVerify" 
                                style="background: var(--primary); color: #FFFFFF; border: none; border-radius: 8px; padding: 0.8rem 1.6rem; font-size: 0.95rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: background 0.2s; white-space: nowrap; height: 46px; box-shadow: 0 2px 6px rgba(15,42,74,0.2);"
                                onmouseover="this.style.background='#1E3A8A';"
                                onmouseout="this.style.background='var(--primary)';">
                            <i class="fas fa-search"></i> Validar Documento
                        </button>
                    </div>

                    <!-- Enlace secundario separado con espacio amplio y formato desahogado -->
                    <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed #E2E8F0; font-size: 0.92rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="color: #64748B;"><i class="fas fa-fingerprint" style="color: #2563EB; font-size: 1.1rem; vertical-align: middle;"></i> ¿Tienes el archivo en tu equipo?</span>
                        <a href="verificar.php" style="color: #2563EB; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;"
                           onmouseover="this.style.textDecoration='underline';"
                           onmouseout="this.style.textDecoration='none';">
                            Cotejar PDF mediante Hash SHA-256 en el Portal Completo de Verificación <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                </div>

                <!-- Contenedor de Resultado de Validación -->
                <div id="verifyResultDiv" style="margin-top: 1.5rem; text-align: left; display: none; max-width: 920px; margin-left: auto; margin-right: auto;"></div>
            </div>
        </section>

        <!-- Solución Empresas B2B con Ver Más / Detalles de Bondades -->
        <section id="empresas" style="background: #0F2A4A; color: white; padding: 4.5rem 1.5rem;">
            <div style="max-width: 1240px; margin: 0 auto;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 2rem;">
                    <div style="max-width: 720px;">
                        <span class="card-category" style="background: rgba(212, 175, 55, 0.2); color: var(--accent);">Para Departamentos de RRHH y Corredores</span>
                        <h2 style="font-size: 2.1rem; font-weight: 800; margin: 0.5rem 0 0.85rem; color: #FFFFFF; letter-spacing: -0.02em;">¿Necesitas firmar decenas de contratos al mes?</h2>
                        <p style="color: #CBD5E1; font-size: 1rem; line-height: 1.6;">
                            Automatiza la firma masiva de contratos laborales, anexos y arriendos con tarifas preferenciales por volumen, panel multiusuario y máxima validez jurídica.
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                        <button id="btnToggleB2B" class="btn-outline-white" onclick="toggleB2BDetails()">
                            <i class="fas fa-info-circle"></i> Conocer Solución Empresas <i id="b2bIconChevron" class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                        </button>
                        <a href="https://wa.me/56984180479?text=Hola,%20me%20interesa%20la%20solución%20empresas%20de%20SinNotario.cl" target="_blank" class="btn-cta btn-gold" style="padding: 0.65rem 1.4rem; font-size: 0.92rem;">
                            <i class="fab fa-whatsapp"></i> Contactar a Ventas B2B
                        </a>
                    </div>
                </div>

                <!-- Detalle Desplegable de Bondades B2B -->
                <div id="b2bDetailsContainer" style="display: none;">
                    <div class="b2b-features-grid">
                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fas fa-file-excel"></i>
                            </div>
                            <h4>Carga Masiva desde Excel</h4>
                            <p>Sube tu nómina en formato `.xlsx` o `.csv`. Nuestro Smart Bulk Wizard mapea y valida los RUTs y genera cientos de contratos individuales en segundos.</p>
                        </div>

                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <h4>WhatsApp Oficial Dedicado</h4>
                            <p>Tus trabajadores o clientes reciben los contratos desde el <strong>número oficial de tu propia empresa</strong> (vía Evolution API con chip propio), generando 98% de tasa de apertura y cero desconfianza.</p>
                        </div>

                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fas fa-signature"></i>
                            </div>
                            <h4>Firma en 1 Clic del Representante Legal</h4>
                            <p>El Representante Legal de la empresa no necesita firmar documento por documento: ingresa su ClaveÚnica una sola vez y estampa su Firma Avanzada (FEA) sobre todo el lote en paralelo.</p>
                        </div>

                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fas fa-building"></i>
                            </div>
                            <h4>Cumplimiento Dirección del Trabajo (DT)</h4>
                            <p>Descarga el Libro Digital de Contratos codificado según los estándares de la Ley N° 21.327 para registrar los contratos en el portal Mi DT y evitar multas laborales.</p>
                        </div>

                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <h4>Expediente Digital del Trabajador</h4>
                            <p>Carpeta digital por cada colaborador con su contrato primitivo, anexos de jornada (40 Horas), comprobantes de EPP y finiquitos organizados cronológicamente.</p>
                        </div>

                        <div class="b2b-card">
                            <div class="b2b-icon-wrap">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h4>Bolsas Prepagadas con Descuento</h4>
                            <p>Planes corporativos flexibles con tarifas reducidas desde $1.990 por firma electrónica, facturación mensual exenta de IVA y soporte prioritario 24/7.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal Wizard con Validación de RUT -->
    <div id="wizardModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeWizard()">&times;</button>
            <div id="wizardModalBody"></div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-col">
                <h3 style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-balance-scale" style="color: var(--accent);"></i> SinNotario.cl
                </h3>
                <p style="font-size: 0.88rem; line-height: 1.6; margin-bottom: 1rem;">
                    Plataforma LegalTech desarrollada por <strong>Keystone Advisors</strong>. Democratizamos el acceso a instrumentos legales y firma electrónica avanzada en todo Chile, reduciendo tiempos de espera y costos innecesarios.
                </p>
                <p style="font-size: 0.82rem; color: #64748B;">
                    © <?= date('Y') ?> Keystone Advisors SpA. Todos los derechos reservados.
                </p>
            </div>

            <div class="footer-col">
                <h3>Trámites Clave</h3>
                <ul>
                    <li><a href="#tramites">Arriendo Ley 21.461</a></li>
                    <li><a href="#tramites">Promesa Compraventa</a></li>
                    <li><a href="#tramites">Finiquito Laboral</a></li>
                    <li><a href="#tramites">Declaración Residencia</a></li>
                    <li><a href="#tramites">Salvoconducto Mudanza</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Marco Legal</h3>
                <ul>
                    <li><a href="marco-legal.php?ley=ley-19799">Ley N° 19.799 (Firma Electrónica)</a></li>
                    <li><a href="marco-legal.php?ley=ley-21461">Ley N° 21.461 (Devuélveme Mi Casa)</a></li>
                    <li><a href="marco-legal.php?ley=ley-18101">Ley N° 18.101 (Arrendamiento)</a></li>
                    <li><a href="marco-legal.php?ley=codigo-trabajo-177">Código del Trabajo Art. 177</a></li>
                    <li><a href="marco-legal.php?ley=cloudflare-r2">Bóveda Cifrada Cloudflare R2</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Contacto & Soporte</h3>
                <ul>
                    <li><a href="https://wa.me/56984180479" target="_blank"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
                    <li><a href="mailto:soporte@sinnotario.cl"><i class="far fa-envelope"></i> soporte@sinnotario.cl</a></li>
                    <li><a href="verificar.php"><i class="fas fa-shield-alt"></i> Validador de Firmas</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>Cumplimiento normativo estricto bajo legislación de la República de Chile.</div>
            <div style="display: flex; gap: 1rem;">
                <a href="terminos-condiciones.php" style="color: #64748B; text-decoration: none;">Términos y Condiciones</a>
                <a href="politica-privacidad.php" style="color: #64748B; text-decoration: none;">Política de Privacidad</a>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/js/app.js?v=20261106_2"></script>
    <script>
        function scrollToVerify() {
            const el = document.getElementById('verificar');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        }

        function toggleB2BDetails() {
            const container = document.getElementById('b2bDetailsContainer');
            const icon = document.getElementById('b2bIconChevron');
            const btn = document.getElementById('btnToggleB2B');
            if (!container) return;

            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                if (icon) icon.className = 'fas fa-chevron-up';
                btn.classList.add('btn-gold');
                btn.classList.remove('btn-outline-white');
            } else {
                container.style.display = 'none';
                if (icon) icon.className = 'fas fa-chevron-down';
                btn.classList.add('btn-outline-white');
                btn.classList.remove('btn-gold');
            }
        }
    </script>
</body>
</html>
