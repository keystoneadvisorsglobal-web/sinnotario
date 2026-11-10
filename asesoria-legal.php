<?php
/**
 * SinNotario.cl - Motor de Asesoría Legal IA
 * 1. Smart Legal Check: Diagnóstico y semáforo legal de contratos
 * 2. Smart Legal Drafter: Redacción a medida bajo libre albedrío
 */
require_once __DIR__ . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asesoría Legal IA y Redacción Libre | SinNotario.cl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/logos/cropped-sinnotario-logo2-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20261110_3">
    <style>
        .ai-container {
            max-width: 1200px;
            margin: 2rem auto 4rem;
            padding: 0 1.5rem;
        }

        .tab-nav {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #E2E8F0;
            margin-bottom: 2rem;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 0.85rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #64748B;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--accent);
            font-weight: 700;
        }

        .ai-card {
            background: #FFFFFF;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 2.2rem;
            box-shadow: var(--shadow-sm);
        }

        .result-box {
            display: none;
            margin-top: 2rem;
            border-radius: 12px;
            padding: 1.75rem;
            animation: fadeIn 0.3s ease;
        }

        .alert-item {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-red { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .alert-yellow { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .alert-green { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }

        .human-lawyer-banner {
            background: linear-gradient(135deg, #0F2A4A 0%, #1E3A8A 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
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
                <li><a href="index.php">Trámites</a></li>
                <li><a href="asesoria-legal.php" class="active">Asesoría Legal IA</a></li>
                <li><a href="index.php#verificar">Verificar Documento</a></li>
            </ul>
            <div>
                <a href="index.php" class="btn-cta" style="background:#F1F5F9; color:#334155 !important;">
                    <i class="fas fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
        </div>
    </header>

    <main class="ai-container">
        
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <span class="card-category" style="background:#EFF6FF; color:#1D4ED8;">Inteligencia Artificial Jurídica</span>
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-top: 0.5rem; letter-spacing: -0.02em;">
                Asistente Legal & Creación de Contratos con IA
            </h1>
            <p style="color: #64748B; font-size: 1rem; max-width: 650px; margin: 0 auto;">
                Audita contratos existentes con nuestro semáforo de riesgos o describe libremente cualquier acuerdo para que la IA lo redacte con blindaje legal chileno.
            </p>
        </div>

        <!-- Pestañas de Navegación -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('check')">
                <i class="fas fa-stethoscope"></i> Diagnóstico de Contratos (Smart Legal Check)
            </button>
            <button class="tab-btn" onclick="switchTab('draft')">
                <i class="fas fa-magic"></i> Creación de Libre Albedrío (Smart Drafter)
            </button>
        </div>

        <!-- TAB 1: SMART LEGAL CHECK -->
        <div id="tabCheck" class="ai-card">
            <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                Audita tu Contrato antes de Firmarlo
            </h2>
            <p style="font-size: 0.9rem; color: #64748B; margin-bottom: 1.5rem;">
                Pega el texto de tu contrato de arriendo, trabajo o comercial. El motor analizará si cumple con la legislación chilena (Leyes 19.799, 21.461 y Código Civil).
            </p>

            <form id="formCheck" onsubmit="runSmartCheck(event)">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.85rem; color: #334155;">Pega el texto del documento a evaluar *</label>
                    <textarea id="txtContractToAudit" rows="9" class="form-control" style="font-family:monospace; font-size:0.85rem;" placeholder="Pega aquí el contenido de tu contrato de arriendo, prestación de servicios, finiquito..." required>CONTRATO DE ARRENDAMIENTO
En Santiago, Don Juan Pérez da en arriendo a Doña María Soto la propiedad ubicada en Av. Providencia 1200, por la suma de $500.000 mensuales, por plazo de 1 año. No se menciona reajuste por IPC. Se acuerda firma digital.</textarea>
                </div>

                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.8rem; color: #64748B;">
                    <i class="fas fa-shield-alt" style="color:var(--accent);"></i> <strong>Aviso Legal SERNAC:</strong> Este análisis automatizado es de carácter pedagógico y orientativo; no constituye patrocinio de abogado ni dictamen vinculante.
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" id="btnRunAudit" class="btn-cta btn-gold" style="padding: 0.75rem 1.6rem;">
                        <i class="fas fa-search"></i> Iniciar Diagnóstico Legal IA
                    </button>
                </div>
            </form>

            <div id="checkResult" class="result-box" style="background:#F8FAFC; border:1px solid var(--border);"></div>
        </div>

        <!-- TAB 2: SMART DRAFTER (LIBRE ALBEDRÍO) -->
        <div id="tabDraft" class="ai-card" style="display:none;">
            <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem;">
                Describe lo que necesitas acordar en tus propias palabras
            </h2>
            <p style="font-size: 0.9rem; color: #64748B; margin-bottom: 1.5rem;">
                Bajo el principio de <strong>Autonomía de la Voluntad (Art. 1545 Código Civil)</strong>, puedes acordar préstamos de cosas, convenios entre amigos, sociedades preliminares o pactos de pago flexibles.
            </p>

            <form id="formDraft" onsubmit="runSmartDrafter(event)">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.85rem; color: #334155;">¿Qué acuerdo deseas formalizar? *</label>
                    <textarea id="txtPromptDrafter" rows="5" class="form-control" placeholder="Ejemplo: Necesito prestarle mi generador eléctrico a un vecino contratista durante 60 días para una obra en Rancagua, cobrándole $300.000 y que si se rompe o no lo devuelve me pague el valor nuevo..." required>Le presto una retroexcavadora y herramientas a Don Roberto Tapia durante 45 días para una faena en Buin. Me pagará $800.000 mensuales y si ocurre un daño debe responder con el costo de reparación.</textarea>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" id="btnRunDrafter" class="btn-cta btn-gold" style="padding: 0.75rem 1.6rem;">
                        <i class="fas fa-magic"></i> Redactar Contrato con IA
                    </button>
                </div>
            </form>

            <div id="draftResult" class="result-box" style="background:#FFFFFF; border:1px solid var(--border);"></div>
        </div>

        <!-- Banner de Conversión: Asesoría con Abogado Humano Keystone -->
        <div class="human-lawyer-banner">
            <div>
                <span class="card-category" style="background:rgba(212,175,55,0.25); color:var(--accent);">Respaldo Jurídico Titulado</span>
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-top: 0.35rem; color: white;">¿Prefieres que un abogado titulado revise y vise tu contrato?</h3>
                <p style="color: #CBD5E1; font-size: 0.9rem; margin-top: 0.25rem;">
                    Revisión profesional con amparo de secreto profesional y responsabilidad civil por <strong>$19.990 CLP</strong>.
                </p>
            </div>
            <div>
                <a href="https://wa.me/56984180479?text=Hola,%20solicito%20revisión%20jurídica%20formal%20de%20un%20abogado%20titulado%20en%20SinNotario.cl" target="_blank" class="btn-cta btn-gold" style="padding: 0.85rem 1.6rem;">
                    <i class="fab fa-whatsapp"></i> Hablar con Abogado Keystone
                </a>
            </div>
        </div>

    </main>

    <footer class="site-footer">
        <div class="footer-bottom">
            <div>SinNotario.cl — Plataforma de Asesoría Legaltech y Firma Digital Keystone Advisors SpA.</div>
            <div>Ley N° 19.799 & Ley N° 21.461</div>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
    <script>
        function switchTab(tab) {
            const check = document.getElementById('tabCheck');
            const draft = document.getElementById('tabDraft');
            const btns = document.querySelectorAll('.tab-btn');

            btns.forEach(b => b.classList.remove('active'));

            if (tab === 'check') {
                check.style.display = 'block';
                draft.style.display = 'none';
                btns[0].classList.add('active');
            } else {
                check.style.display = 'none';
                draft.style.display = 'block';
                btns[1].classList.add('active');
            }
        }

        async function runSmartCheck(e) {
            e.preventDefault();
            const text = document.getElementById('txtContractToAudit').value.trim();
            const btn = document.getElementById('btnRunAudit');
            const resBox = document.getElementById('checkResult');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auditando bajo Ley Chilena...';
            resBox.style.display = 'none';

            try {
                const res = await fetch('api/index.php?action=ai_audit_contract', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contract_text: text })
                });
                const json = await res.json();

                if (json.success && json.data) {
                    const audit = json.data.audit;
                    let html = `<h3 style="font-size:1.15rem; font-weight:800; color:var(--primary); margin-bottom:1rem;">Resultado del Diagnóstico Legal</h3>`;

                    if (audit.is_notary_mandatory) {
                        html += `
                            <div class="alert-item alert-red">
                                <i class="fas fa-times-circle fa-lg"></i>
                                <div><strong>Trámite Exclusivo de Notaría:</strong> ${escapeHtml(audit.notary_warning)}</div>
                            </div>
                        `;
                    }

                    if (audit.critical_risks && audit.critical_risks.length > 0) {
                        audit.critical_risks.forEach(r => {
                            html += `
                                <div class="alert-item alert-red">
                                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                                    <div><strong>Riesgo Crítico / Omisión Legal:</strong> ${escapeHtml(r)}</div>
                                </div>
                            `;
                        });
                    }

                    if (audit.warnings && audit.warnings.length > 0) {
                        audit.warnings.forEach(w => {
                            html += `
                                <div class="alert-item alert-yellow">
                                    <i class="fas fa-lightbulb fa-lg"></i>
                                    <div><strong>Recomendación Preventiva:</strong> ${escapeHtml(w)}</div>
                                </div>
                            `;
                        });
                    }

                    if (audit.solid_points && audit.solid_points.length > 0) {
                        audit.solid_points.forEach(s => {
                            html += `
                                <div class="alert-item alert-green">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                    <div><strong>Cláusula Sólida:</strong> ${escapeHtml(s)}</div>
                                </div>
                            `;
                        });
                    }

                    html += `<p style="font-size:0.85rem; color:#64748B; margin-top:1rem;">${escapeHtml(audit.summary)}</p>`;
                    resBox.innerHTML = html;
                    resBox.style.display = 'block';
                } else {
                    alert('Error en diagnóstico: ' + (json.error || 'Error interno'));
                }
            } catch (err) {
                alert('Error de conexión al consultar el motor de IA.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Iniciar Diagnóstico Legal IA';
            }
        }

        async function runSmartDrafter(e) {
            e.preventDefault();
            const prompt = document.getElementById('txtPromptDrafter').value.trim();
            const btn = document.getElementById('btnRunDrafter');
            const resBox = document.getElementById('draftResult');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redactando contrato formal...';
            resBox.style.display = 'none';

            try {
                const res = await fetch('api/index.php?action=ai_draft_contract', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt: prompt })
                });
                const json = await res.json();

                if (json.success && json.data) {
                    const c = json.data.contract;
                    let html = `
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E2E8F0; padding-bottom:1rem; margin-bottom:1.5rem;">
                            <div>
                                <span class="card-category comercial">Borrador IA a Medida</span>
                                <h3 style="font-size:1.25rem; font-weight:800; color:var(--primary); margin-top:0.35rem;">${escapeHtml(c.title)}</h3>
                            </div>
                            <button class="btn-cta btn-gold" onclick="alert('Borrador listo. Se enviará a firma digital.')">
                                <i class="fas fa-file-signature"></i> Continuar a la Firma
                            </button>
                        </div>
                    `;

                    c.clauses.forEach(cl => {
                        html += `
                            <div style="margin-bottom:1rem;">
                                <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary);">${escapeHtml(cl.title)}</h4>
                                <p style="font-size:0.88rem; color:#334155; line-height:1.6; margin-top:0.25rem;">${escapeHtml(cl.text)}</p>
                            </div>
                        `;
                    });

                    resBox.innerHTML = html;
                    resBox.style.display = 'block';
                } else {
                    alert('Error en redacción: ' + (json.error || 'Error interno'));
                }
            } catch (err) {
                alert('Error de conexión con el redactor de IA.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i> Redactar Contrato con IA';
            }
        }
    </script>
</body>
</html>

