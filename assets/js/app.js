/**
 * SinNotario.cl - Motor de Aplicación y Frontend Interactivo
 */

// Catálogo de trámites prohibidos / exclusivos de Notaría (Detector Negativo)
const NOTARY_EXCLUSIVE_KEYWORDS = [
    {
        words: ['compraventa de propiedad', 'comprar casa', 'comprar departamento', 'escritura publica de casa', 'comprar terreno'],
        title: 'La Compraventa de Inmuebles Requiere Escritura Pública en Notaría',
        explanation: 'Por mandato del Artículo 1801 del Código Civil chileno, la compraventa de bienes raíces debe otorgarse solemnemente por escritura pública ante Notario e inscribirse en el Conservador de Bienes Raíces (CBR).',
        alternative: 'En SinNotario.cl puedes firmar válidamente la <strong>Promesa de Compraventa</strong> previa con firma electrónica.'
    },
    {
        words: ['testamento', 'herencia', 'legado'],
        title: 'Los Testamentos son Solemnes y Requieren Notaría',
        explanation: 'El Artículo 999 y ss. del Código Civil exige que el testamento se otorgue ante Notario y testigos hábiles.',
        alternative: 'Puedes redactar y firmar inventarios o acuerdos de distribución privados preliminares.'
    },
    {
        words: ['matrimonio', 'acuerdo de union civil', 'divorcio', 'separacion de bienes'],
        title: 'Trámite Exclusivo del Registro Civil o Tribunales',
        explanation: 'Los actos relativos al estado civil de las personas son competencia exclusiva del Servicio de Registro Civil e Identificación y de los Tribunales de Familia.',
        alternative: 'Podemos asistirte con declaraciones juradas de convivencia o residencia previas.'
    },
    {
        words: ['poder general', 'mandato judicial'],
        title: 'Los Poderes Judiciales Requieren Patrocinio y Notaría',
        explanation: 'El Artículo 6 del Código de Procedimiento Civil exige escritura pública o comparecencia ante el secretario del tribunal para representar en juicio.',
        alternative: 'En SinNotario.cl disponemos del <strong>Poder Especial Privado</strong> para trámites y gestiones administrativas simples.'
    }
];

let allTemplates = [];

document.addEventListener('DOMContentLoaded', () => {
    initSearch();
    loadTemplates();
    initFilters();
    initVerifyForm();
});

// 1. Cargar catálogo desde la API
async function loadTemplates(categoria = '') {
    const grid = document.getElementById('templateGrid');
    if (!grid) return;

    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #64748B;"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top:0.75rem;">Cargando trámites y plantillas certificadas...</p></div>';

    try {
        const url = categoria ? `api/index.php?action=get_templates&categoria=${encodeURIComponent(categoria)}` : 'api/index.php?action=get_templates';
        const res = await fetch(url);
        const json = await res.json();

        if (json.success && json.data) {
            allTemplates = json.data;
            renderTemplates(allTemplates);
        } else {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color: #EF4444;">Error al cargar las plantillas.</div>';
        }
    } catch (err) {
        console.error('Error fetching templates:', err);
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color: #EF4444;">Error de conexión con el servidor.</div>';
    }
}

// 2. Renderizar tarjetas de plantillas
function renderTemplates(templates) {
    const grid = document.getElementById('templateGrid');
    if (!grid) return;

    if (templates.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 2.5rem; color: #64748B;">No se encontraron trámites en esta categoría.</div>';
        return;
    }

    grid.innerHTML = templates.map(t => {
        const precioFormatted = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(t.precio_base);
        return `
            <div class="template-card" data-slug="${t.slug}">
                <div>
                    <div class="card-badge-row">
                        <span class="card-category ${t.categoria}">${escapeHtml(t.categoria)}</span>
                        <span class="card-time"><i class="far fa-clock"></i> ~${t.tiempo_estimado_min} min</span>
                    </div>
                    <h3 class="card-title">${escapeHtml(t.nombre)}</h3>
                    <p class="card-desc">${escapeHtml(t.descripcion)}</p>
                </div>
                <div class="card-footer">
                    <div class="card-price">
                        ${precioFormatted}
                        <span>Firma Electrónica Incluida</span>
                    </div>
                    <button class="btn-cta" onclick="openWizard('${t.slug}')">
                        Crear <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// 3. Buscador inteligente con Detector Negativo
function initSearch() {
    const searchInput = document.getElementById('mainSearchInput');
    const alertBox = document.getElementById('negativeAlertBox');
    if (!searchInput) return;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();

        if (query.length === 0) {
            if (alertBox) alertBox.style.display = 'none';
            renderTemplates(allTemplates);
            return;
        }

        // Revisar si coincide con trámites exclusivos de notaría
        let notaryHit = null;
        for (const item of NOTARY_EXCLUSIVE_KEYWORDS) {
            for (const word of item.words) {
                if (query.includes(word) || word.includes(query)) {
                    notaryHit = item;
                    break;
                }
            }
            if (notaryHit) break;
        }

        if (notaryHit && alertBox) {
            alertBox.style.display = 'block';
            alertBox.innerHTML = `
                <h4><i class="fas fa-exclamation-triangle"></i> ${notaryHit.title}</h4>
                <p style="margin-bottom: 0.5rem;">${notaryHit.explanation}</p>
                <div style="background: rgba(255,255,255,0.7); padding: 0.5rem 0.75rem; border-radius: 6px; font-weight: 600;">
                    💡 Alternativa SinNotario: ${notaryHit.alternative}
                </div>
            `;
        } else if (alertBox) {
            alertBox.style.display = 'none';
        }

        // Filtrar plantillas disponibles
        const filtered = allTemplates.filter(t => 
            t.nombre.toLowerCase().includes(query) ||
            t.descripcion.toLowerCase().includes(query) ||
            t.categoria.toLowerCase().includes(query)
        );
        renderTemplates(filtered);
    });
}

// 4. Filtros de categoría
function initFilters() {
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const cat = btn.getAttribute('data-cat');
            loadTemplates(cat === 'todos' ? '' : cat);
        });
    });
}

// 5. Verificador de documentos (/verificar)
function initVerifyForm() {
    const btn = document.getElementById('btnSubmitVerify');
    const input = document.getElementById('verifyCodeInput');
    const resultDiv = document.getElementById('verifyResultDiv');
    if (!btn || !input || !resultDiv) return;

    btn.addEventListener('click', async () => {
        const code = input.value.trim().toUpperCase();
        if (!code) {
            alert('Por favor ingresa un código de verificación (Ej: SN-AB12-CD34)');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        resultDiv.style.display = 'none';

        try {
            const res = await fetch(`api/index.php?action=verify_document&code=${encodeURIComponent(code)}`);
            const json = await res.json();

            resultDiv.style.display = 'block';
            if (json.success && json.data) {
                const doc = json.data.document;
                const signers = json.data.signers || [];
                resultDiv.innerHTML = `
                    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 12px; padding: 1.5rem; color: #065F46;">
                        <div style="display:flex; align-items:center; gap: 0.75rem; margin-bottom: 1rem;">
                            <i class="fas fa-check-circle fa-2x" style="color:#059669;"></i>
                            <div>
                                <h3 style="font-size:1.15rem; font-weight:800; color:#065F46;">Documento Auténtico y Cifrado</h3>
                                <p style="font-size:0.85rem; color:#047857;">Certificado bajo Ley N° 19.799 en bóveda Cloudflare R2</p>
                            </div>
                        </div>
                        <div style="background: white; border-radius:8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.9rem;">
                            <p><strong>Título:</strong> ${escapeHtml(doc.titulo)}</p>
                            <p><strong>Código Único:</strong> ${escapeHtml(doc.codigo_verificacion)}</p>
                            <p><strong>Estado:</strong> <span style="font-weight:700; color:#059669;">${escapeHtml(doc.estado)}</span></p>
                            <p><strong>Fecha Emisión:</strong> ${escapeHtml(doc.created_at)}</p>
                        </div>
                        <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.5rem;">Firmantes Validados:</h4>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            ${signers.map(s => `
                                <div style="background:white; border-radius:6px; padding:0.6rem 0.85rem; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                                    <div><strong>${escapeHtml(s.nombre_completo)}</strong> (${escapeHtml(s.rut)}) - <span style="color:#64748B;">${escapeHtml(s.rol_firmante)}</span></div>
                                    <span style="background:#DEF7EC; color:#03543F; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:4px;"><i class="fas fa-signature"></i> ${escapeHtml(s.metodo_firma)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 1.5rem; color: #991B1B; text-align: center;">
                        <i class="fas fa-times-circle fa-2x" style="color:#DC2626; margin-bottom:0.5rem;"></i>
                        <h3 style="font-size:1.1rem; font-weight:700;">Documento No Encontrado</h3>
                        <p style="font-size:0.88rem; margin-top:0.25rem;">El código <strong>${escapeHtml(code)}</strong> no coincide con ningún documento emitido o custodiado en la plataforma SinNotario.cl.</p>
                    </div>
                `;
            }
        } catch (err) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div style="color:#DC2626; text-align:center;">Error de conexión al consultar el validador.</div>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Validar Documento';
        }
    });
}

// 6. Asistente / Wizard modal
function openWizard(slug) {
    const tpl = allTemplates.find(t => t.slug === slug);
    if (!tpl) return;

    const modal = document.getElementById('wizardModal');
    const modalBody = document.getElementById('wizardModalBody');
    if (!modal || !modalBody) return;

    modalBody.innerHTML = `
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <span class="card-category ${tpl.categoria}">${escapeHtml(tpl.categoria)}</span>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem;">${escapeHtml(tpl.nombre)}</h2>
            <p style="font-size: 0.9rem; color: #64748B;">Configura los datos del documento y los firmantes en menos de 5 minutos</p>
        </div>

        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.88rem;">
            <p><strong><i class="fas fa-shield-alt" style="color:var(--accent);"></i> Validez Legal:</strong> ${escapeHtml(tpl.alerta_legal)}</p>
            <p style="margin-top:0.35rem;"><strong><i class="fas fa-lock" style="color:var(--primary);"></i> Custodia:</strong> Bóveda Cloudflare R2 con sellado de tiempo y código de verificación QR.</p>
        </div>

        <form id="wizardQuickForm" onsubmit="submitWizard(event, '${tpl.slug}')">
            <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--primary);">1. Datos del Titular / Solicitante</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: #475569;">Tu Nombre Completo *</label>
                    <input type="text" id="w_nombre" required class="search-input" style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:0.5rem;" placeholder="Ej: Juan Pérez González">
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: #475569;">Tu RUT *</label>
                    <input type="text" id="w_rut" required class="search-input" style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:0.5rem;" placeholder="Ej: 15.345.678-9">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: #475569;">Correo Electrónico *</label>
                    <input type="email" id="w_email" required class="search-input" style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:0.5rem;" placeholder="juan@ejemplo.cl">
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 600; color: #475569;">Teléfono WhatsApp *</label>
                    <input type="tel" id="w_tel" required class="search-input" style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:0.5rem;" placeholder="+56 9 8765 4321">
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E2E8F0; padding-top: 1.25rem;">
                <div>
                    <span style="font-size: 0.8rem; color: #64748B;">Total a Pagar</span>
                    <h3 style="font-size: 1.3rem; color: var(--primary); font-weight: 800;">${new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(tpl.precio_base)}</h3>
                </div>
                <button type="submit" class="btn-cta btn-gold">
                    Continuar al Contrato <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </form>
    `;

    modal.style.display = 'flex';
}

function closeWizard() {
    const modal = document.getElementById('wizardModal');
    if (modal) modal.style.display = 'none';
}

async function submitWizard(e, slug) {
    e.preventDefault();
    const nombre = document.getElementById('w_nombre').value;
    const rut = document.getElementById('w_rut').value;
    const email = document.getElementById('w_email').value;
    const tel = document.getElementById('w_tel').value;

    const payload = {
        template_slug: slug,
        signers: [
            { rol: 'TITULAR', nombre: nombre, rut: rut, email: email, telefono: tel }
        ],
        form_data: {
            titular_nombre: nombre,
            titular_rut: rut,
            titular_email: email,
            titular_telefono: tel
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
            alert(`¡Borrador creado exitosamente!\nCódigo de Verificación: ${json.data.codigo_verificacion}\nListo para proceder al firmado.`);
            closeWizard();
        } else {
            alert('Error al crear el documento: ' + (json.error || 'Error desconocido'));
        }
    } catch (err) {
        alert('Error de conexión al procesar el trámite.');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
