<?php
/**
 * SinNotario.cl - Suite B2B Empresas & Carga Masiva Excel
 * Keystone Advisors
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Services/AuthService.php';
$currentUser = AuthService::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suite B2B Empresas & Emisión Masiva | SinNotario.cl</title>
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

        <!-- Barra de Empresa y Configuración del Emisor -->
        <div class="company-badge-bar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 10px; background: #0A1C33; color: #D4AF37; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--primary);" id="dispRazonSocial">Keystone Advisors SpA</div>
                    <div style="font-size: 0.85rem; color: #64748B;">
                        RUT Empresa: <strong id="dispRutEmpresa">78.089.686-8</strong> | Rep. Legal: <strong id="dispRepLegal">Maritza Vásquez (17.297.004-4)</strong>
                    </div>
                </div>
            </div>
            <div>
                <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important; border:1px solid #CBD5E1; font-size:0.85rem;" onclick="openCompanyModal()">
                    <i class="fas fa-cog"></i> Configurar Empresa
                </button>
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

    </main>

    <!-- Modal de Configuración de Empresa -->
    <div id="companyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#FFF; max-width:550px; width:90%; border-radius:12px; padding:2rem; box-shadow:var(--shadow-lg);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="font-weight:800; color:var(--primary); margin:0;">Datos Corporativos de la Empresa</h3>
                <button type="button" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeCompanyModal()">&times;</button>
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
                <div style="display:flex; justify-content:flex-end; gap:1rem;">
                    <button type="button" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;" onclick="closeCompanyModal()">Cancelar</button>
                    <button type="submit" class="btn-cta btn-gold">Guardar Configuración</button>
                </div>
            </form>
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
            document.getElementById('dispRepLegal').textContent = (currentCompany.rep_legal_nombre || 'Maritza Vásquez') + ' (' + (currentCompany.rep_legal_rut || '17.297.004-4') + ')';
            
            // Modal fields
            document.getElementById('cfgRazonSocial').value = currentCompany.razon_social || '';
            document.getElementById('cfgRutEmpresa').value = currentCompany.rut || '';
            document.getElementById('cfgRepNombre').value = currentCompany.rep_legal_nombre || '';
            document.getElementById('cfgRepRut').value = currentCompany.rep_legal_rut || '';
            document.getElementById('cfgRepEmail').value = currentCompany.rep_legal_email || '';
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
