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
    <title>SinNotario.cl | Contratos Legales y Firma Digital sin Filas ni Esperas</title>
    <meta name="description" content="Genera, firma y valida contratos de arriendo Ley 21.461, promesas, finiquitos laborales y declaraciones juradas 100% online con plena validez legal (Ley 19.799).">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Principales -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header / Navbar -->
    <header class="site-header">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <img src="assets/logos/sinnotario-logo.png" alt="SinNotario.cl Logo">
                <div class="brand-text">
                    <h1>SinNotario<span style="color:var(--accent);">.cl</span></h1>
                    <span>Plataforma LegalTech Digital</span>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="<?= $page === 'home' ? 'active' : '' ?>">Trámites</a></li>
                <li><a href="#verificar" onclick="scrollToVerify()">Verificar Documento</a></li>
                <li><a href="#empresas">Solución Empresas</a></li>
                <li><a href="#sucursales">Sucursales Físicas <span class="nav-badge-pill">Cabinas</span></a></li>
                <li><a href="https://wa.me/56984180479?text=Hola,%20necesito%20asistencia%20legal%20en%20SinNotario.cl" target="_blank" style="color: #25D366;"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
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
                Tus contratos y trámites legales <br>
                <span class="highlight">100% online y sin pisar una notaría</span>
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
        </section>

        <!-- Catálogo de Plantillas -->
        <section id="tramites" class="catalog-section">
            <div class="filter-pills">
                <button class="filter-btn active" data-cat="todos">Todos los Trámites</button>
                <button class="filter-btn" data-cat="inmobiliario"><i class="fas fa-home"></i> Inmobiliario & Arriendos</button>
                <button class="filter-btn" data-cat="laboral"><i class="fas fa-user-tie"></i> Laboral & RRHH</button>
                <button class="filter-btn" data-cat="comercial"><i class="fas fa-briefcase"></i> Comercial & Deudas</button>
                <button class="filter-btn" data-cat="tramites"><i class="fas fa-file-alt"></i> Declaraciones & Trámites</button>
            </div>

            <div id="templateGrid" class="template-grid">
                <!-- Inyectado dinámicamente vía API -->
            </div>
        </section>

        <!-- Sección de Validación Pública (/verificar) -->
        <section id="verificar" style="background: #FFFFFF; border-top: 1px solid var(--border); padding: 4rem 1.5rem;">
            <div style="max-width: 800px; margin: 0 auto; text-align: center;">
                <span class="card-category" style="background: #ECFDF5; color: #047857;">Módulo Oficial de Autenticidad</span>
                <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin: 0.6rem 0 0.8rem;">Verificador de Integridad y Firma</h2>
                <p style="color: #64748B; font-size: 1.05rem; margin-bottom: 2rem;">
                    Ingresa el código único alfanumérico impreso al pie de página de cualquier documento emitido por SinNotario.cl para consultar su firma digital y bóveda de custodia en Cloudflare R2.
                </p>

                <div class="search-input-wrap" style="border: 2px solid var(--primary); box-shadow: var(--shadow-md);">
                    <i class="fas fa-qrcode"></i>
                    <input type="text" id="verifyCodeInput" class="search-input" placeholder="Ej: SN-4A9B-81CD" style="text-transform: uppercase;">
                    <button id="btnSubmitVerify" class="search-btn">
                        <i class="fas fa-search"></i> Validar Documento
                    </button>
                </div>

                <div id="verifyResultDiv" style="margin-top: 1.5rem; text-align: left; display: none;"></div>
            </div>
        </section>

        <!-- Propuesta de Valor Híbrida (Online + Sucursales) -->
        <section id="sucursales" style="background: #F1F5F9; padding: 4.5rem 1.5rem;">
            <div style="max-width: 1280px; margin: 0 auto;">
                <div style="text-align: center; margin-bottom: 3rem;">
                    <span class="card-category">Modelo Híbrido Keystone</span>
                    <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem;">Tu tranquilidad legal, como prefieras</h2>
                    <p style="color: #64748B; font-size: 1.05rem;">100% autoservicio desde tu hogar o en nuestras cabinas privadas de firma asistida.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
                    <div style="background: white; border-radius: 12px; padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                        <div style="width: 50px; height: 50px; border-radius: 10px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem;">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">100% Online en 3 Pasos</h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6;">Completa los datos en el formulario asistido, firma con ClaveÚnica o SMS y recibe el PDF sellado en tu correo y WhatsApp en minutos.</p>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                        <div style="width: 50px; height: 50px; border-radius: 10px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem;">
                            <i class="fas fa-store"></i>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">Cabinas de Firma Asistida</h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6;">Sucursales físicas con módulos privados para firmar en pantalla táctil con validación biométrica y asesoría legal personalizada.</p>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                        <div style="width: 50px; height: 50px; border-radius: 10px; background: #DCFCE7; color: #16A34A; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.25rem;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">Bóveda Segura Cloudflare R2</h3>
                        <p style="color: #64748B; font-size: 0.92rem; line-height: 1.6;">Tus contratos quedan custodiados con cifrado en reposo y estampillado cronológico inalterable durante más de 5 años.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Solución Empresas B2B -->
        <section id="empresas" style="background: #0F2A4A; color: white; padding: 4.5rem 1.5rem;">
            <div style="max-width: 1280px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 2rem;">
                <div style="max-width: 700px;">
                    <span class="card-category" style="background: rgba(212, 175, 55, 0.2); color: var(--accent);">Para Departamentos de RRHH y Corredores</span>
                    <h2 style="font-size: 2.2rem; font-weight: 800; margin: 0.5rem 0 1rem; color: #FFFFFF;">¿Necesitas firmar decenas de contratos al mes?</h2>
                    <p style="color: #CBD5E1; font-size: 1.05rem; line-height: 1.6;">
                        Disponemos de firma masiva por lotes, WhatsApp oficial dedicado por empresa vía Evolution API y panel corporativo multiusuario con tarifas preferenciales por volumen.
                    </p>
                </div>
                <div>
                    <a href="https://wa.me/56984180479?text=Hola,%20me%20interesa%20la%20solución%20empresas%20de%20SinNotario.cl" target="_blank" class="btn-cta btn-gold" style="padding: 1rem 2rem; font-size: 1.05rem;">
                        <i class="fab fa-whatsapp"></i> Contactar a Ventas B2B
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal Wizard -->
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
                <p style="font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem;">
                    Plataforma LegalTech desarrollada por <strong>Keystone Advisors</strong>. Democratizamos el acceso a instrumentos legales y firma electrónica avanzada en todo Chile, reduciendo tiempos de espera y costos innecesarios.
                </p>
                <p style="font-size: 0.85rem; color: #64748B;">
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
                    <li><a href="#">Ley N° 19.799 (Firma Electrónica)</a></li>
                    <li><a href="#">Ley N° 21.461 (Devuélveme Mi Casa)</a></li>
                    <li><a href="#">Ley N° 18.101 (Arrendamiento)</a></li>
                    <li><a href="#">Código del Trabajo Art. 177</a></li>
                    <li><a href="#">Bóveda Cifrada Cloudflare R2</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Contacto & Soporte</h3>
                <ul>
                    <li><a href="https://wa.me/56984180479" target="_blank"><i class="fab fa-whatsapp"></i> +56 9 8418 0479</a></li>
                    <li><a href="mailto:soporte@sinnotario.cl"><i class="far fa-envelope"></i> soporte@sinnotario.cl</a></li>
                    <li><a href="#sucursales"><i class="fas fa-map-marker-alt"></i> Red de Sucursales Chile</a></li>
                    <li><a href="#verificar"><i class="fas fa-shield-alt"></i> Validador de Firmas</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>Cumplimiento normativo estricto bajo legislación de la República de Chile.</div>
            <div style="display: flex; gap: 1rem;">
                <a href="#" style="color: #64748B; text-decoration: none;">Términos y Condiciones</a>
                <a href="#" style="color: #64748B; text-decoration: none;">Política de Privacidad</a>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
    <script>
        function scrollToVerify() {
            const el = document.getElementById('verificar');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
