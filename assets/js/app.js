/**
 * SinNotario.cl - Motor de Aplicación y Frontend Interactivo
 */

// Utilidad Oficial: Validación y Formato de RUT Chileno (Módulo 11)
window.RutHelper = {
    clean(rut) {
        return typeof rut === 'string' ? rut.replace(/[^0-9kK]/g, '').toUpperCase() : '';
    },
    format(rut) {
        const clean = this.clean(rut);
        if (clean.length < 2) return clean;
        const cuerpo = clean.slice(0, -1);
        const dv = clean.slice(-1);
        let cuerpoFormateado = '';
        let count = 0;
        for (let i = cuerpo.length - 1; i >= 0; i--) {
            cuerpoFormateado = cuerpo[i] + cuerpoFormateado;
            count++;
            if (count === 3 && i !== 0) {
                cuerpoFormateado = '.' + cuerpoFormateado;
                count = 0;
            }
        }
        return `${cuerpoFormateado}-${dv}`;
    },
    validate(rut) {
        const clean = this.clean(rut);
        if (clean.length < 8 || clean.length > 9) return false;
        const cuerpo = clean.slice(0, -1);
        const dv = clean.slice(-1);
        
        let suma = 0;
        let multiplo = 2;
        for (let i = cuerpo.length - 1; i >= 0; i--) {
            suma += parseInt(cuerpo[i], 10) * multiplo;
            multiplo = multiplo < 7 ? multiplo + 1 : 2;
        }
        
        const dvEsperadoCalculado = 11 - (suma % 11);
        let dvEsperado = '';
        if (dvEsperadoCalculado === 11) dvEsperado = '0';
        else if (dvEsperadoCalculado === 10) dvEsperado = 'K';
        else dvEsperado = dvEsperadoCalculado.toString();
        
        return dv === dvEsperado;
    },
    attachToInput(inputElement, errorElement) {
        if (!inputElement) return;
        
        const handler = (e) => {
            const raw = e.target.value;
            const formatted = this.format(raw);
            if (e.type === 'blur' || e.type === 'change' || (raw && raw.length >= 8)) {
                e.target.value = formatted;
            }
            
            const clean = this.clean(e.target.value);
            if (clean.length >= 8) {
                const isValid = this.validate(clean);
                if (isValid) {
                    inputElement.classList.remove('rut-invalid');
                    inputElement.classList.add('rut-valid');
                    if (errorElement) errorElement.style.display = 'none';
                } else {
                    inputElement.classList.remove('rut-valid');
                    inputElement.classList.add('rut-invalid');
                    if (errorElement) {
                        errorElement.innerText = 'RUT inválido (dígito verificador no coincide)';
                        errorElement.style.display = 'block';
                    }
                }
            } else {
                inputElement.classList.remove('rut-valid', 'rut-invalid');
                if (errorElement) errorElement.style.display = 'none';
            }
        };

        inputElement.addEventListener('input', handler);
        inputElement.addEventListener('blur', handler);
        inputElement.addEventListener('change', handler);
    }
};
const RutHelper = window.RutHelper;

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
let currentFilteredTemplates = [];
let visibleCount = 3; // Mostrar inicialmente 3 y permitir "Cargar más"
const PAGE_SIZE = 3;

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
            currentFilteredTemplates = allTemplates;
            visibleCount = 3;
            renderTemplates();
        } else {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color: #EF4444;">Error al cargar las plantillas.</div>';
        }
    } catch (err) {
        console.error('Error fetching templates:', err);
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color: #EF4444;">Error de conexión con el servidor.</div>';
    }
}

// 2. Renderizar tarjetas de plantillas con botón "Cargar Más"
function renderTemplates() {
    const grid = document.getElementById('templateGrid');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    if (!grid) return;

    if (currentFilteredTemplates.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 2.5rem; color: #64748B;">No se encontraron trámites en esta categoría.</div>';
        if (loadMoreContainer) loadMoreContainer.style.display = 'none';
        return;
    }

    const itemsToDisplay = currentFilteredTemplates.slice(0, visibleCount);

    grid.innerHTML = itemsToDisplay.map(t => {
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
                    <button class="btn-card-create" onclick="openWizard('${t.slug}')">
                        Crear <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    // Control de visibilidad del botón "Cargar Más"
    if (loadMoreContainer) {
        if (visibleCount < currentFilteredTemplates.length) {
            loadMoreContainer.style.display = 'block';
            const remaining = currentFilteredTemplates.length - visibleCount;
            const btn = document.getElementById('btnLoadMore');
            if (btn) btn.innerHTML = `Ver más trámites (${remaining} restantes) <i class="fas fa-chevron-down"></i>`;
        } else {
            loadMoreContainer.style.display = 'none';
        }
    }
}

function loadMoreTemplates() {
    visibleCount += PAGE_SIZE;
    renderTemplates();
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
            currentFilteredTemplates = allTemplates;
            visibleCount = 3;
            renderTemplates();
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
        currentFilteredTemplates = allTemplates.filter(t => 
            t.nombre.toLowerCase().includes(query) ||
            t.descripcion.toLowerCase().includes(query) ||
            t.categoria.toLowerCase().includes(query)
        );
        visibleCount = 3;
        renderTemplates();
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

// 6. Asistente / Wizard modal con redirección universal al Wizard Split View 60/40 para todos los documentos
function openWizard(slug) {
    window.location.href = `redactar.php?slug=${encodeURIComponent(slug)}`;
}

window.escapeHtml = function(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};
function escapeHtml(text) {
    return window.escapeHtml(text);
}
