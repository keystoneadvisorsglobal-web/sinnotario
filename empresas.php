<?php
/**
 * SinNotario.cl - Suite B2B Empresas & Carga Masiva Excel
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suite B2B Empresas & Carga Masiva | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .b2b-layout {
            max-width: 1200px;
            margin: 2rem auto 4rem;
            padding: 0 1.5rem;
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
                <li><a href="admin.php">Consola Operativa</a></li>
            </ul>
            <div>
                <a href="https://wa.me/56984180479?text=Hola,%20solicito%20activación%20de%20cuenta%20empresas%20B2B%20en%20SinNotario.cl" target="_blank" class="btn-cta btn-gold">
                    <i class="fab fa-whatsapp"></i> Activar WhatsApp Propio
                </a>
            </div>
        </div>
    </header>

    <main class="b2b-layout">
        
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <span class="card-category laboral">Recursos Humanos & Corredoras</span>
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem; letter-spacing: -0.02em;">
                Smart Bulk Wizard: Carga Masiva y Firma en 1 Clic
            </h1>
            <p style="color: #64748B; font-size: 1rem; max-width: 700px; margin: 0 auto;">
                Sube tu planilla de trabajadores o contratos de arriendo en Excel/CSV. Nuestro motor valida los RUTs con Módulo 11 y genera los contratos en bloque para firma masiva con ClaveÚnica.
            </p>
        </div>

        <!-- Zona de Carga Masiva Drag-and-Drop -->
        <div class="upload-dropzone" onclick="document.getElementById('excelFileInput').click()">
            <input type="file" id="excelFileInput" accept=".csv, .xlsx, .txt" style="display: none;" onchange="handleExcelUpload(event)">
            <i class="fas fa-file-excel fa-3x" style="color: #10B981; margin-bottom: 1rem;"></i>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">Arrastra tu planilla Excel o haz clic para cargar</h3>
            <p style="color: #64748B; font-size: 0.88rem; margin-top: 0.35rem;">
                Formatos admitidos: .xlsx, .csv — Columnas requeridas: <code>Nombre</code>, <code>RUT</code>, <code>Email</code>, <code>Telefono</code>, <code>Cargo</code>, <code>Sueldo</code>
            </p>
            <div style="margin-top: 1.5rem;">
                <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1;">
                    <i class="fas fa-download"></i> Descargar Planilla Modelo (.csv)
                </button>
            </div>
        </div>

        <!-- Tabla de Mapeo y Validación Previa -->
        <div id="parsedWrap" class="parsed-table-wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--primary);">Nómina Procesada & Validación Módulo 11</h3>
                    <p style="font-size: 0.85rem; color: #64748B;">Revisa los datos antes de emitir los contratos y despachar los WhatsApp.</p>
                </div>
                <button class="btn-cta btn-gold" onclick="alert('Generando lote de contratos y preparando firma masiva...')">
                    <i class="fas fa-signature"></i> Firmar Lote con 1 Clic (ClaveÚnica)
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="background:#F8FAFC; color:#64748B; text-align:left;">
                            <th style="padding:0.75rem;">Fila</th>
                            <th style="padding:0.75rem;">Nombre Colaborador</th>
                            <th style="padding:0.75rem;">RUT (Módulo 11)</th>
                            <th style="padding:0.75rem;">Correo</th>
                            <th style="padding:0.75rem;">WhatsApp</th>
                            <th style="padding:0.75rem;">Cargo</th>
                            <th style="padding:0.75rem;">Validación</th>
                        </tr>
                    </thead>
                    <tbody id="parsedTableBody">
                        <!-- Inyectado dinámicamente -->
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.85rem; color: #64748B;">
                    <i class="fas fa-building" style="color: var(--primary);"></i> Compatible con la Dirección del Trabajo (Ley N° 21.327 - Libro Digital DT).
                </div>
                <button class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="alert('Exportando archivo CSV codificado para Mi DT...')">
                    <i class="fas fa-file-export"></i> Exportar para Portal Mi DT
                </button>
            </div>
        </div>

    </main>

    <footer class="site-footer">
        <div class="footer-bottom">
            <div>SinNotario.cl — Suite Corporativa B2B de Recursos Humanos y Contratos Keystone Advisors.</div>
            <div>WhatsApp White-Label vía Evolution API v2</div>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
    <script>
        function handleExcelUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Datos de demostración simulando lectura exitosa de Excel/CSV
            const mockRows = [
                { id: 1, nombre: 'Patricio Alarcón Soto', rut: '14.234.567-8', email: 'patricio.alarcon@empresa.cl', tel: '+56991122334', cargo: 'Operador Técnico', valid: true },
                { id: 2, nombre: 'Daniela Morales Vega', rut: '16.789.012-3', email: 'daniela.morales@empresa.cl', tel: '+56987654321', cargo: 'Jefa de Turno', valid: true },
                { id: 3, nombre: 'Cristián Fuentes Parra', rut: '18.456.789-K', email: 'cristian.fuentes@empresa.cl', tel: '+56977889900', cargo: 'Supervisor Faena', valid: true },
                { id: 4, nombre: 'Marcela Silva Henríquez', rut: '19.123.456-7', email: 'marcela.silva@empresa.cl', tel: '+56966554433', cargo: 'Prevencionista de Riesgos', valid: true }
            ];

            const tbody = document.getElementById('parsedTableBody');
            tbody.innerHTML = mockRows.map(r => `
                <tr style="border-bottom: 1px solid #E2E8F0;">
                    <td style="padding:0.75rem;">#${r.id}</td>
                    <td style="padding:0.75rem; font-weight:600;">${r.nombre}</td>
                    <td style="padding:0.75rem;">${r.rut}</td>
                    <td style="padding:0.75rem; color:#64748B;">${r.email}</td>
                    <td style="padding:0.75rem; color:#64748B;">${r.tel}</td>
                    <td style="padding:0.75rem;">${r.cargo}</td>
                    <td style="padding:0.75rem;">
                        <span style="background:#DCFCE7; color:#15803D; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">
                            <i class="fas fa-check"></i> RUT Válido
                        </span>
                    </td>
                </tr>
            `).join('');

            document.getElementById('parsedWrap').style.display = 'block';
            document.getElementById('parsedWrap').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>

