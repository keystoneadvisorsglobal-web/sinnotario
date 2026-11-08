import os
import docx
from docx import Document
from docx.shared import Pt, Inches, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import qn, nsdecls

def create_document():
    doc = Document()

    # 1. Configuración de Márgenes (1 pulgada = 2.54 cm)
    for section in doc.sections:
        section.top_margin = Inches(1.0)
        section.bottom_margin = Inches(1.0)
        section.left_margin = Inches(1.0)
        section.right_margin = Inches(1.0)
        
        # Header & Footer
        footer = section.footer
        p_footer = footer.paragraphs[0]
        p_footer.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        p_footer.paragraph_format.space_before = Pt(0)
        p_footer.paragraph_format.space_after = Pt(0)
        run_footer = p_footer.add_run("SinNotario.cl | Documento de Diseño Funcional y Modelo Híbrido | Confidencial")
        run_footer.font.name = "Arial"
        run_footer.font.size = Pt(8.5)
        run_footer.font.color.rgb = RGBColor(128, 128, 128)

    # 2. Configurar Estilo Normal
    style_normal = doc.styles['Normal']
    style_normal.font.name = 'Arial'
    style_normal.font.size = Pt(12)
    style_normal.font.color.rgb = RGBColor(51, 51, 51) # #333333
    style_normal.paragraph_format.line_spacing = 1.15
    style_normal.paragraph_format.space_after = Pt(4)

    # Helper para bordes de párrafo
    def add_p_border_bottom(p, color_hex="003366", sz="12"):
        pPr = p._p.get_or_add_pPr()
        pBdr = OxmlElement('w:pBdr')
        bottom = OxmlElement('w:bottom')
        bottom.set(qn('w:val'), 'single')
        bottom.set(qn('w:sz'), sz)
        bottom.set(qn('w:space'), '4')
        bottom.set(qn('w:color'), color_hex)
        pBdr.append(bottom)
        pPr.append(pBdr)

    # Helper para encabezados con keep_with_next
    def add_h1(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(14)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(14)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0, 51, 102) # #003366 corporativo
        add_p_border_bottom(p, "003366", "12")
        return p

    def add_h2(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(12)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0, 102, 153) # #006699
        return p

    def add_h3(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(6)
        p.paragraph_format.space_after = Pt(2)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(10.5)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0, 51, 102)
        return p

    def add_body(text, bold_prefix=""):
        p = doc.add_paragraph()
        if bold_prefix:
            r_bold = p.add_run(bold_prefix)
            r_bold.font.name = "Arial"
            r_bold.font.size = Pt(12)
            r_bold.font.bold = True
            r_bold.font.color.rgb = RGBColor(51, 51, 51)
        r_text = p.add_run(text)
        r_text.font.name = "Arial"
        r_text.font.size = Pt(12)
        r_text.font.color.rgb = RGBColor(51, 51, 51)
        return p

    def add_bullet(text, bold_prefix=""):
        p = doc.add_paragraph(style='List Bullet')
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.line_spacing = 1.15
        if bold_prefix:
            r_bold = p.add_run(bold_prefix)
            r_bold.font.name = "Arial"
            r_bold.font.size = Pt(12)
            r_bold.font.bold = True
            r_bold.font.color.rgb = RGBColor(51, 51, 51)
        r_text = p.add_run(text)
        r_text.font.name = "Arial"
        r_text.font.size = Pt(12)
        r_text.font.color.rgb = RGBColor(51, 51, 51)
        return p

    def add_callout(text, title="NOTA ESTRATÉGICA"):
        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        cell = tbl.cell(0, 0)
        
        tcPr = cell._tc.get_or_add_tcPr()
        shd = parse_xml(r'<w:shd {} w:fill="F0F4F8"/>'.format(nsdecls('w')))
        tcPr.append(shd)
        
        borders = parse_xml(r'''
            <w:tcBorders {} >
                <w:top w:val="none"/>
                <w:left w:val="single" w:sz="24" w:space="0" w:color="003366"/>
                <w:bottom w:val="none"/>
                <w:right w:val="none"/>
            </w:tcBorders>
        '''.format(nsdecls('w')))
        tcPr.append(borders)
        
        p = cell.paragraphs[0]
        p.paragraph_format.space_before = Pt(4)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.line_spacing = 1.15
        
        r_t = p.add_run(title + ": ")
        r_t.font.name = "Arial"
        r_t.font.size = Pt(9.5)
        r_t.font.bold = True
        r_t.font.color.rgb = RGBColor(0, 51, 102)
        
        r_b = p.add_run(text)
        r_b.font.name = "Arial"
        r_b.font.size = Pt(9.5)
        r_b.font.color.rgb = RGBColor(51, 51, 51)
        
        p_sp = doc.add_paragraph()
        p_sp.paragraph_format.space_before = Pt(0)
        p_sp.paragraph_format.space_after = Pt(2)

    def set_cell_margins(cell, top=100, bottom=100, left=140, right=140):
        tcPr = cell._tc.get_or_add_tcPr()
        tcMar = parse_xml(r'''
            <w:tcMar {} >
                <w:top w:w="{}" w:type="dxa"/>
                <w:bottom w:w="{}" w:type="dxa"/>
                <w:left w:w="{}" w:type="dxa"/>
                <w:right w:w="{}" w:type="dxa"/>
            </w:tcMar>
        '''.format(nsdecls('w'), top, bottom, left, right))
        tcPr.append(tcMar)

    def add_styled_table(headers, data, col_widths=None):
        table = doc.add_table(rows=len(data) + 1, cols=len(headers))
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        
        hdr_cells = table.rows[0].cells
        for i, header_text in enumerate(headers):
            hdr_cells[i].text = ""
            p = hdr_cells[i].paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_before = Pt(4)
            p.paragraph_format.space_after = Pt(4)
            run = p.add_run(header_text)
            run.font.name = "Arial"
            run.font.size = Pt(9.5)
            run.font.bold = True
            run.font.color.rgb = RGBColor(255, 255, 255)
            
            tcPr = hdr_cells[i]._tc.get_or_add_tcPr()
            shd = parse_xml(r'<w:shd {} w:fill="003366"/>'.format(nsdecls('w')))
            tcPr.append(shd)
            set_cell_margins(hdr_cells[i], top=120, bottom=120, left=140, right=140)

        for r_idx, row_data in enumerate(data):
            row_cells = table.rows[r_idx + 1].cells
            bg_color = "F4F6F9" if r_idx % 2 == 1 else "FFFFFF"
            for c_idx, cell_value in enumerate(row_data):
                row_cells[c_idx].text = ""
                p = row_cells[c_idx].paragraphs[0]
                p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                p.paragraph_format.space_before = Pt(3)
                p.paragraph_format.space_after = Pt(3)
                p.paragraph_format.line_spacing = 1.15
                run = p.add_run(str(cell_value))
                run.font.name = "Arial"
                run.font.size = Pt(8.5)
                run.font.color.rgb = RGBColor(51, 51, 51)
                
                tcPr = row_cells[c_idx]._tc.get_or_add_tcPr()
                shd = parse_xml(r'<w:shd {} w:fill="{}"/>'.format(nsdecls('w'), bg_color))
                tcPr.append(shd)
                
                borders = parse_xml(r'''
                    <w:tcBorders {} >
                        <w:top w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>
                        <w:bottom w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>
                        <w:left w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>
                        <w:right w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>
                    </w:tcBorders>
                '''.format(nsdecls('w')))
                tcPr.append(borders)
                set_cell_margins(row_cells[c_idx], top=70, bottom=70, left=140, right=140)

        if col_widths:
            for row in table.rows:
                for idx, w in enumerate(col_widths):
                    row.cells[idx].width = Inches(w)
                    
        p_sp = doc.add_paragraph()
        p_sp.paragraph_format.space_before = Pt(0)
        p_sp.paragraph_format.space_after = Pt(4)

    # PORTADA
    p_title = doc.add_paragraph()
    p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_title.paragraph_format.space_before = Pt(36)
    p_title.paragraph_format.space_after = Pt(8)
    r_tit = p_title.add_run("PROYECTO SINNOTARIO.CL")
    r_tit.font.name = "Arial"
    r_tit.font.size = Pt(22)
    r_tit.font.bold = True
    r_tit.font.color.rgb = RGBColor(0, 51, 102)

    p_sub = doc.add_paragraph()
    p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_sub.paragraph_format.space_before = Pt(0)
    p_sub.paragraph_format.space_after = Pt(24)
    r_sub = p_sub.add_run("DOCUMENTO DE DISEÑO FUNCIONAL, ARQUITECTURA TÉCNICA Y MODELO HÍBRIDO (DIGITAL + OFICINA FÍSICA)")
    r_sub.font.name = "Arial"
    r_sub.font.size = Pt(13)
    r_sub.font.bold = True
    r_sub.font.color.rgb = RGBColor(0, 102, 153)

    add_styled_table(
        ["Metadato", "Detalle"],
        [
            ["Cliente / Proyecto", "Keystone Advisors — SinNotario.cl"],
            ["Tipo de Solución", "SaaS Legaltech + Red de Atención Presencial y Asesoría Legal"],
            ["Benchmarking Directo", "MuySimple.cl y DocLegal.cl (Inspección en Vivo)"],
            ["Marco Regulatorio", "Ley N° 19.799 (Firma Electrónica) / Ley N° 21.461 / Código Civil"],
            ["Estado del Documento", "Versión 1.3 — Incorpora FAQ Legal y Flujo Dual Kiosko/Móvil"]
        ],
        [2.5, 4.0]
    )

    doc.add_page_break()

    # ÍNDICE DE CONTENIDOS
    p_toc_head = doc.add_paragraph()
    p_toc_head.paragraph_format.space_before = Pt(12)
    p_toc_head.paragraph_format.space_after = Pt(6)
    r_toc = p_toc_head.add_run("ÍNDICE DE CONTENIDOS")
    r_toc.font.name = "Arial"
    r_toc.font.size = Pt(14)
    r_toc.font.bold = True
    r_toc.font.color.rgb = RGBColor(0, 51, 102)
    add_p_border_bottom(p_toc_head, "003366", "12")

    p_toc = doc.add_paragraph()
    p_toc.paragraph_format.space_after = Pt(16)
    r_toc_fld = p_toc.add_run()
    fldChar1 = OxmlElement('w:fldChar')
    fldChar1.set(qn('w:fldCharType'), 'begin')
    instrText = OxmlElement('w:instrText')
    instrText.set(qn('xml:space'), 'preserve')
    instrText.text = r'TOC \o "1-3" \h \z \u'
    fldChar2 = OxmlElement('w:fldChar')
    fldChar2.set(qn('w:fldCharType'), 'separate')
    fldChar3 = OxmlElement('w:fldChar')
    fldChar3.set(qn('w:fldCharType'), 'end')
    p_toc._p.append(fldChar1)
    p_toc._p.append(instrText)
    p_toc._p.append(fldChar2)
    p_toc._p.append(fldChar3)

    add_callout("Este índice utiliza campos dinámicos nativos de Word (TOC). En Microsoft Word, haga clic derecho sobre el índice y seleccione 'Actualizar campos' para reflejar automáticamente los números de página finales.", "GUÍA DEL ÍNDICE")

    # SECCIÓN 1
    add_h1("1. Introducción y Visión del Negocio")
    add_body("El proyecto SinNotario.cl nace como una respuesta ágil, moderna y accesible a la burocracia tradicional asociada a los trámites contractuales y notariales en Chile. Apoyado en la Ley N° 19.799 sobre Documentos Electrónicos y Firma Electrónica, el servicio permite a ciudadanos y pequeñas empresas redactar, tramitar y firmar digitalmente instrumentos privados con plena fuerza probatoria y certeza jurídica.")
    add_body("A diferencia de las alternativas puramente digitales existentes en el mercado nacional, SinNotario.cl introduce una propuesta de valor disruptiva de carácter híbrido: una plataforma SaaS de autogestión 100% online complementada por una red de Oficinas Físicas de Atención y Asesoría Legal Presencial. Este modelo omnicanal atiende tanto al usuario nativo digital que busca rapidez y autoservicio, como al público que carece de alfabetización digital, requiere asistencia personalizada o necesita la supervisión de un abogado para estructurar acuerdos complejos.")

    # SECCIÓN 2
    add_h1("2. Benchmarking Exhaustivo de Referentes de Mercado")
    add_body("Para concebir la arquitectura funcional de SinNotario.cl se realizó una inspección detallada de las dos plataformas líderes del segmento en Chile: MuySimple.cl y DocLegal.cl.")

    add_h2("2.1. Análisis de MuySimple.cl")
    add_bullet("Orientación a Trámites Cotidianos y de Alto Tráfico: Especialización en declaraciones juradas y contratos de arriendo residenciales/comerciales.", "Enfoque de Producto: ")
    add_bullet("Formularios dinámicos paso a paso tipo 'wizard', donde las variables del documento se van inyectando en tiempo real en la plantilla.", "Flujo de Usuario: ")
    add_bullet("Utilización de Firma Electrónica Simple (FES) para declaraciones estándar y opciones de derivación a notaría tradicional para trámites con solemnidad.", "Firma y Certificación: ")

    add_h2("2.2. Análisis de DocLegal.cl")
    add_bullet("Incorporación de herramientas generativas de Inteligencia Artificial para la asistencia en la redacción de contratos.", "Innovación Tecnológica: ")
    add_bullet("Cobro por firma unitaria fija (ej. $7.990 CLP) para Firma Electrónica Avanzada (FEA) sin atar al cliente a suscripciones mensuales.", "Modelo Tarifario: ")
    add_bullet("Validación de identidad de los comparecientes utilizando la infraestructura de ClaveÚnica con 2FA provista por IDOK (FirmaYa).", "Integración con ClaveÚnica: ")
    add_bullet("Catálogo taxativo explícito en sus Términos y FAQ donde se listan con precisión todos los documentos excluidos por ley para evitar nulidades y reclamos de clientes.", "Transparencia Legal Excepcional: ")

    # SECCIÓN 3
    add_h1("3. Arquitectura Funcional de SinNotario.cl")
    add_body("El ecosistema de SinNotario.cl se estructura sobre cuatro pilares funcionales que abarcan tanto el ciclo digital como la interacción física.")

    add_h2("3.1. Módulo 1: Catálogo y Motor de Plantillas Inteligentes")
    add_bullet("Biblioteca estructurada de contratos clasificada por áreas temáticas con inyección dinámica de variables y asistente de IA legal.", "Motor de Renderizado: ")

    add_h2("3.2. Módulo 2: Circuito de Firmas e Integración Técnica con IDOK (FirmaYa)")
    add_body("Conexión desacoplada mediante API REST con IDOK (FirmaYa / BeTrust), prestador de servicios de certificación acreditado en Chile, para emisión de Firma Electrónica Avanzada (FEA) 'one-shot' con autenticación oficial de ClaveÚnica con 2FA.")
    add_bullet("Envío programático de PDF en Base64, lista ordenada de comparecientes (RUT, nombre, correo, rol) y obtención de enlaces de firma seguros.", "Contrato de Envío (POST /api/v1/documents): ")
    add_bullet("Receptor de eventos en tiempo real con firma de seguridad HMAC (signer_signed, document_completed, document_rejected) para actualizar la línea de tiempo del expediente sin sobrecargar con consultas periódicas.", "Arquitectura Webhook (POST /api/webhooks/idok): ")

    add_h2("3.3. Módulo 3: Estrategia de Medios de Pago (Web vs. Presencial) y Optimización Financiera")
    add_body("Análisis comparativo de comisiones y plazos de abono para optimizar el margen del negocio en ambos canales.")
    add_bullet("Fintoc (Open Banking de transferencias directas a ~1.0% + IVA y abono inmediato) como opción preferente por margen, complementado con Webpay Plus de Transbank (1.49% débito y 2.29% crédito) para el 100% de tarjetas chilenas.", "Canal Web (Checkout Online): ")
    add_bullet("Súper Compraquí de BancoEstado como recomendación principal: equipo autónomo con impresora térmica propia para tickets PIN/QR, sin arriendo mensual (costo fijo zsh) y las comisiones más bajas de Chile (1.29% débito y 1.59% crédito con abono el mismo día). Como alternativa corporativa: Smart POS Getnet/Santander.", "Canal Presencial (Terminales POS en Mesón): ")

    add_h2("3.4. Módulo 4: Validador Público de Documentos y Verificación Criptográfica (/verificar)")
    add_body("Portal público en sinnotario.cl/verificar para que cualquier tercero (bancos, tribunales, corredoras, conserjerías) verifique la autenticidad e integridad del documento en tiempo real sin registro previo mediante folio alfanumérico, QR dinámico o cotejo de hash SHA-256.")

    add_h2("3.5. Módulo 5: Asistencia Contextual Inteligente y Escalado Humano en 1 Clic ('Anti-Frustración')")
    add_body("Mecanismo para eliminar la frustración clásica de los portales de autoatención cuando el usuario no sabe cómo redactar o cómo declarar personerías jurídicas.")
    add_bullet("Resuelve la confusión típica de empresas: distingue entre los datos de la sociedad (Razón Social/RUT empresa) y la persona natural que comparece (Representante Legal con su propia ClaveÚnica personal).", "Modo Representante Legal Guiado: ")
    add_bullet("Calculador de firmas en tiempo real que desglosa cuántos comparecientes deben firmar y el costo exacto antes de pagar, eliminando cobros sorpresa posteriores.", "Transparencia de Firmas: ")
    add_bullet("Botón de rescate que transfiere al usuario a un ejecutivo real por WhatsApp con el enlace exacto al borrador en edición para asistencia inmediata.", "Escalado Humano en WhatsApp con Contexto: ")
    add_bullet("Opción 'Guardar y Terminar en Sucursal' que permite al cliente frustrado acudir a la oficina física, donde el ejecutivo del mesón retoma el borrador desde su RUT.", "Puente Web a Sucursal Física: ")

    add_h2("3.6. Módulo 6: Motor de Auditoría Legal con IA (Smart Legal Check) y Blindaje de Responsabilidad")
    add_body("Motor RAG conectado a la legislación chilena que analiza archivos PDF subidos por el cliente antes de firmar, detectando cláusulas abusivas, vacíos legales o exigencias notariales con consentimiento previo (Clickwrap) y cláusula de exoneración de responsabilidad.")

    add_h2("3.7. Módulo 7: Almacenamiento Criptográfico, Retención Legal y Control Cero-Confianza")
    add_body("Estrategia de optimización de costos cloud y blindaje de privacidad bajo la Ley N° 19.628 de Protección de Datos Personales.")
    add_bullet("Ciclo de vida automatizado (Tiering): Hot Storage en Cloudflare R2 (cero costo de egreso) los primeros 6 meses, migración a Warm Storage hasta los 5 años y archivo frío Glacier (zsh.0036 USD/GB) hasta los 10 años, manteniendo el costo de almacenamiento por debajo de  USD mensual para miles de contratos.", "Optimización de Costos Cloud: ")
    add_bullet("Custodia de 5 años para contratos ejecutivos (Art. 2515 Código Civil) y laborales (Art. 510 Código del Trabajo), y 6 años para contratos comerciales (Art. 200 Código Tributario).", "Plazos de Retención Legal en Chile: ")
    add_bullet("Cifrado militar en reposo (AES-256) y tránsito (TLS 1.3), descargas exclusivas mediante URLs firmadas temporales de 15 minutos (Presigned URLs), y barrera de privacidad en el validador QR (desafío de 4 últimos dígitos del RUT) para impedir que extraños vean o descarguen contratos ajenos.", "Seguridad Cero-Confianza (Zero-Knowledge): ")
    add_bullet("Tres vías sin fricción para recuperar contratos 1, 2 o 5 años después: 1) Portal web 'Mi Bóveda' con autenticación ClaveÚnica o Magic Link; 2) Bot oficial de WhatsApp con despacho en 30 segundos; y 3) En sucursales físicas con cédula de identidad en mesón y reimpresión con sello seco.", "Recuperación de Documentos a Largo Plazo: ")

    add_h2("3.8. Módulo 8: Especificación Técnica de Arquitectura, Hosting y Conectores")
    add_body("Definición del stack de ingeniería para máxima compatibilidad con el hosting actual en Hostinger LiteSpeed y alineado a la infraestructura probada de Keystone Advisors.")
    add_bullet("Backend en PHP 8.2+ RESTful modular (despliegue nativo en Hostinger con tiempos <80ms en LiteSpeed LSCache, sin pagar servidores VPS adicionales), Frontend semántico con Vanilla JS (ES6+) y TailwindCSS, y Base de Datos MariaDB/MySQL transaccional con Audit Log.", "Stack y Lenguaje de Programación: ")
    add_bullet("Cloudflare R2 Object Storage (compatible con S3 API) como recomendación definitiva: cero costo por tráfico de descarga (Zero Egress Fees) y tarifa plana de $0.015 USD/GB, manteniendo la custodia de decenas de miles de contratos por centavos de dólar al mes.", "Storage Cloud (Cloudflare R2 vs AWS S3): ")
    add_bullet("Desacoplamiento en 6 conectores modulares: 1) IdokSignatureAdapter (FEA/ClaveÚnica); 2) FintocPaymentAdapter (Open Banking); 3) WebpayPlusAdapter (Transbank); 4) StorageR2Adapter (Presigned URLs de 15 min); 5) WhatsAppNotificationAdapter (Gateway Evolution API v2 para despachos masivos a costo $0); y 6) GeminiLegalAiAdapter (Smart Legal Check).", "Catálogo de Conectores de Integración: ")

    add_h2("3.9. Módulo 9: SinNotario Empresas & RRHH (Emisión Masiva y Firma en Lote)")
    add_body("Módulo especializado para el mercado corporativo y empresas con alta dotación laboral (retail, construcción, seguridad, logística) que necesitan agilidad en contratos y anexos masivos.")
    add_bullet("El encargado de personal sube una planilla Excel con la nómina de trabajadores y el sistema genera cientos de contratos individuales en segundos validando los RUTs automáticamente.", "Carga Masiva por Planilla Excel/CSV: ")
    add_bullet("El representante legal de la empresa no firma uno a uno; revisa el lote y con 1 solo ingreso de su ClaveÚnica o PIN de seguridad estampa la Firma Electrónica Avanzada en los 100 contratos de golpe.", "Firma en Lote (Batch Signing en 1 Clic): ")
    add_bullet("Cada trabajador en terreno o faena recibe su contrato personalizado directo al WhatsApp; pulsa el enlace y firma con su ClaveÚnica en 30 segundos sin imprimir papeles ni perder horas laborales.", "Despacho Masivo por WhatsApp a Faena/Terreno: ")
    add_bullet("Panel de control para RRHH con semáforo de estado (firmados vs pendientes), botón de recordatorio masivo en 1 clic y exportador automático de nómina en formato compatible con la Dirección del Trabajo (portal Mi DT).", "Dashboard RRHH y Cumplimiento DT: ")
    add_bullet("Portal corporativo en /empresas estructurado bajo control de acceso RBAC con 4 perfiles: 1) Admin/Rep. Legal (firma masiva en lote con ClaveÚnica y compras); 2) Operador RRHH (cargas Excel, envíos WhatsApp y seguimiento); 3) Auditor Externo (compliance en solo lectura); y 4) Colaborador (portal móvil personal).", "Diseño de Interfaces Web y Perfiles RBAC: ")
    add_bullet("Arquitectura multi-instancia de WhatsApp dedicada por empresa mediante Evolution API: cada empresa puede vincular su propio número corporativo con un chip prepago básico escaneando un QR en pantalla. El trabajador recibe el contrato desde el WhatsApp oficial de su propio empleador, elevando la tasa de apertura y resolviendo dudas internas en su propio teléfono sin costo adicional.", "WhatsApp White-Label Dedicado por Empresa: ")

    add_h2("3.10. Módulo 10: Diseño Exhaustivo de Interfaces Web (UI/UX) y Mapa del Sitio")
    add_body("Especificación de la experiencia visual y funcional en cada pantalla clave del portal web SinNotario.cl para asegurar máxima conversión comercial y cero fricción.")
    add_bullet("Header institucional con acceso a Bóveda Digital con ClaveÚnica, titular directo, buscador inteligente con detección negativa de trámites notariales, bifurcación clara ('redactar desde cero' vs 'subir PDF propio') y bloque de presentación de la red de oficinas físicas.", "Home Principal (/): ")
    add_bullet("Layout 60/40 en dos columnas: formulario guiado a la izquierda (con autovalidación de RUTs) y previsualizador dinámico a la derecha donde el usuario ve cómo se va armando su contrato en caliente, con botones flotantes de rescate humano y derivación a sucursal.", "Asistente de Redacción (Smart Wizard): ")
    add_bullet("Desglose transparente del folio y firmantes, sin cobros ocultos posteriores, con selección directa entre Transferencia Rápida Fintoc (prioritaria) y Webpay Plus.", "Checkout Transparente: ")
    add_bullet("Línea de tiempo interactiva post-pago que indica quién ya firmó y quién está pendiente, con botón de reenvío de enlace por WhatsApp en un clic.", "Live Tracker de Firmas: ")
    add_bullet("Interfaz formal orientada a bancos y juzgados para validar documentos por Folio o cálculo forense de Hash SHA-256 de archivos PDF subidos.", "Validador Público (/verificar): ")

    add_h2("3.13. Módulo 13: Motor de Facturación Electrónica Automática SII (Boletas y Facturas DTE)")
    add_body("Cumplimiento normativo estricto ante el Servicio de Impuestos Internos (SII) de Chile con emisión inmediata de comprobantes tributarios electrónicos tras la confirmación de pago.")
    add_bullet("Paso previo al pago donde el cliente decide entre: A) Boleta Electrónica (DTE 39) para personas naturales con prellenado de RUT/email; o B) Factura Electrónica Afecta con 19% IVA (DTE 33) para empresas, capturando RUT, Razón Social, Giro Comercial, Dirección Tributaria, Comuna y Correo DTE.", "Bifurcación Tributaria Obligatoria en Checkout: ")
    add_bullet("Doble alternativa técnica: Integración ágil vía API DTE homologada (SimpleBoleta, LibreDTE, Haulmer/OpenFactura) o Conexión directa SOAP al SII con Certificado Digital del Representante Legal (.pfx/.p12) y timbrado CAF.", "Arquitectura de Conexión y Timbrado SII: ")
    add_bullet("Entrega automática del archivo XML legal y PDF con Timbre Electrónico del SII (TED/PDF417) al correo del cliente y disponibilidad permanente en 'Mi Bóveda' (/mi-cuenta).", "Despacho y Resguardo Tributario: ")

    add_h2("3.14. Módulo 14: Infraestructura de Correo Transaccional Oficial (Titan Mail SMTP & Casillas Operativas)")
    add_body("Infraestructura de correo empresarial de alta reputación para erradicar el spam y asegurar entrega garantizada en bandeja principal de entrada.")
    add_bullet("notificaciones@sinnotario.cl (casilla operativa principal para despachos de firma, tokens de seguridad, contratos timbrados y DTEs); contacto@sinnotario.cl (buzón de respuesta y atención general configurado en Reply-To); soporte@sinnotario.cl (mesa de ayuda técnica); y legal@sinnotario.cl (visación de abogados).", "Casillas Oficiales del Ecosistema: ")
    add_bullet("Implementación en PHP nativo de alta velocidad (KeystoneSmtpClient) con conexión cifrada SSL (puerto 465) a smtp.titan.email, autenticación AUTH LOGIN, cabeceras MIME blindadas y registros de ciberseguridad DNS (SPF, DKIM 2048-bit y DMARC).", "Cliente Socket SMTP con Titan Mail: ")
    add_bullet("Módulo de supervisión en /admin para editar en vivo credenciales SMTP, buzón emisor y visualizar bitácora de respuestas SMTP en tiempo real sin reiniciar servidores.", "Consola de Gestión Administrativa en Caliente: ")

    add_h2("3.15. Módulo 15: Suite de Inteligencia Financiera, Control de Costos de Firma y Conciliación Bancaria (/admin/finanzas)")
    add_body("Consola de tesorería y analítica financiera blindada con doble factor de autenticación (PIN maestro o token OTP de directores) para control exhaustivo del flujo de caja y rentabilidad unitaria.")
    add_bullet("Capa de seguridad restringida exclusiva para directores con expiración de sesión a los 15 minutos de inactividad y registro forense inalterable en audit_logs de cada consulta o exportación contable.", "Seguridad Reforzada (Dual-Lock): ")
    add_bullet("Ingresos brutos acumulados, base gravable neta (sin IVA), ticket promedio por instrumento y margen bruto unitario real tras deducir costos directos de pasarelas y firmas.", "Dashboard de Ventas y Márgenes en Vivo: ")
    add_bullet("Cálculo y previsión en tiempo real de la pre-factura devengada a pagar al PSC (IDOK/FirmaYa), desglosando firmas simples (FES con OTP) vs firmas avanzadas (FEA con ClaveÚnica), con alertas preventivas de saldo mayorista.", "Control de Egresos y Liquidación de Proveedores de Firma: ")
    add_bullet("Motor de cuadre a 3 vías que cruza automáticamente la recaudación web (Transbank Webpay y transferencias Fintoc) y de sucursales contra la cartola bancaria de la cuenta corriente de Keystone Advisors SpA, alertando discrepancias.", "Conciliación Bancaria Automática: ")
    add_bullet("Reporte automatizado de Débito Fiscal IVA (19%) por boletas y facturas emitidas versus Crédito Fiscal recuperable para la preparación ágil del Formulario 29 mensual ante el SII.", "Previsión Tributaria F29: ")

    # SECCIÓN 4
    add_h1("4. Modelo Híbrido: Plataforma Digital + Red de Oficinas Físicas")
    add_body("La Oficina Física de SinNotario.cl humaniza la tecnología y atiende la brecha digital mediante interfaces operativas especializadas para el personal de sucursal, cabinas de firma privada en tablets seguras y salas de asesoría legal presencial con abogados.")

    add_h2("4.1. Módulo de Ejecutivo de Atención (/sucursal/ejecutivo)")
    add_bullet("Buscador por RUT y captura mediante lector óptico de cédula de identidad para evitar errores de digitación en mesón.", "Captura Rápida de Datos: ")
    add_bullet("Monitor secundario orientado al cliente ('Customer Facing Display') para que el usuario supervise la redacción del contrato en tiempo real.", "Previsualización en Doble Pantalla: ")
    add_bullet("Módulo de caja presencial (POS Transbank, efectivo, transferencia) con emisión inmediata de boleta de honorarios/servicios.", "Cobro Integrado: ")

    add_h2("4.2. Cabinas y Dispositivos de Firma Privada en Sala (/sucursal/estacion-firma)")
    add_bullet("Salas privadas equipadas con tablets táctiles en modo Kiosko seguro (pantalla bloqueada, teclado protegido y limpieza de sesión automática).", "Hardware Seguro en Kiosko: ")
    add_bullet("El anfitrión guía al usuario hasta la pantalla de firma; en el momento de ingresar la ClaveÚnica o el código 2FA, se preserva la estricta privacidad de sus credenciales.", "Firma Asistida con Privacidad: ")

    add_h2("4.3. Sistema de Habilitación y Desbloqueo Post-Pago en Caja Presencial")
    add_bullet("El ejecutivo genera el borrador en estado 'DRAFT_PENDING_PAYMENT'. El cliente paga en mesón mediante POS, efectivo o transferencia.", "1. Pre-Orden y Cobro en Caja: ")
    add_bullet("La caja emite un comprobante térmico con un PIN temporal de 4 dígitos o un Código QR de activación válido por 30 minutos.", "2. Ticket de Activación Seguro: ")
    add_bullet("El cliente tiene libertad total de elegir dónde firmar tras pagar en caja: A) En la Cabina Privada de la sucursal (Tablet táctil asistida); o B) Directamente en su propio Dispositivo Móvil (Smartphone) escaneando el QR del comprobante o abriendo el enlace directo en su WhatsApp.", "3. Firma Omnicanal (Cabina o Celular Propio): ")
    add_bullet("El usuario valida su identidad con su ClaveÚnica (2FA) o biometría en estricta privacidad; al completarse la firma, el sistema alerta a la impresora de seguridad.", "4. Firma y Limpieza Automática: ")
    add_bullet("Impresión láser en papel de seguridad foliado, aplicación de sello seco en relieve (embossing) institucional y despacho automático de copia PDF al WhatsApp del cliente.", "5. Entrega de Copia Certificada: ")

    add_h2("4.4. Sala de Abogados y Asesoría Jurídica Integral (/sucursal/abogado)")
    add_bullet("Bandeja de interconsultas para que el ejecutivo de mesón derive contratos que requieran cláusulas a medida o resolución de discrepancias entre las partes.", "Derivación en Vivo: ")
    add_bullet("Espacio reservado donde un abogado titulado orienta a las partes sobre el impacto legal del contrato y visa el documento institucionalmente.", "Consultoría Presencial: ")

    # SECCIÓN 5
    add_h1("5. Blindaje Legal, Regulación y Preguntas Frecuentes")

    add_h2("5.3. Catálogo Taxativo de Documentos: Permitidos por Ley vs. Reservados a Notaría")
    add_body("Matriz completa de actos y contratos contrastada con la regulación chilena y las directrices vigentes de DocLegal.cl y MuySimple.cl:")

    add_h3("A. Documentos que SÍ se pueden firmar en SinNotario.cl (100% Válidos por Ley)")
    add_styled_table(
        ["Categoría", "Documento Específico", "Firma", "Marco Legal y Mérito"],
        [
            ["Inmobiliario", "Contrato de Arriendo de Inmuebles", "FEA Obligatoria", "Ley N° 21.461 ('Devuélveme mi casa'): Mérito ejecutivo y desalojo expedito."],
            ["Inmobiliario", "Subarriendo y Cesión de Arriendo", "FEA", "Validez contractual plena civil."],
            ["Inmobiliario", "Acta de Entrega / Recepción y Llaves", "FES o FEA", "Respaldo material del estado de la propiedad."],
            ["Inmobiliario", "Carta de Desahucio / Término de Arriendo", "FEA", "Notificación fehaciente con fecha cierta."],
            ["Inmobiliario", "Promesa de Compraventa Privada", "FEA", "Válida al tenor del Art. 1554 Código Civil."],
            ["Laboral", "Contrato de Trabajo y Anexos", "FES o FEA", "Dictámenes Dirección del Trabajo autorizan firma electrónica."],
            ["Laboral", "Carta de Aviso de Término Laboral", "FEA", "Comunicación laboral fehaciente."],
            ["Laboral", "Finiquito Laboral de Mutuo Acuerdo", "FEA", "Art. 177 Código del Trabajo: Válido de mutuo acuerdo con FEA acreditada."],
            ["Comercial", "Contrato de Servicios / Honorarios", "FES o FEA", "Art. 1437 y 1438 Código Civil."],
            ["Comercial", "Acuerdo de Confidencialidad (NDA)", "FES o FEA", "Fuerza obligatoria plena entre las partes."],
            ["Comercial", "Reconocimiento de Deuda y Convenio de Pago", "FEA", "Art. 434 N° 4 CPC: Título ejecutivo directo."],
            ["Comercial", "Cesión de Derechos Personales y Créditos", "FEA", "Notificación y aceptación contractual válida."],
            ["Trámites", "Declaración Jurada Simple de Residencia", "FES o FEA", "Aceptada por municipalidades, bancos y empleadores."],
            ["Trámites", "Declaración Jurada de Ingresos", "FES o FEA", "Respaldo privado de solvencia."],
            ["Trámites", "Declaración y Autorización de Mudanza", "FES o FEA", "Salvoconducto privado ante copropiedades."],
            ["Trámites", "Poder Especial Privado (Mandato)", "FEA", "Código Civil Art. 2116 (trámites no solemnes / correos / servicios)."],
            ["Trámites", "Autorización de Domicilio Tributario (SII)", "FEA", "Aceptado para inicio de actividades ante el SII."]
        ],
        [1.1, 1.8, 1.0, 2.6]
    )

    add_h3("B. Catálogo Exhaustivo de Documentos que NO se pueden firmar (Reservados a Notaría)")
    add_styled_table(
        ["Familia de Trámite", "Trámite Específico Excluido", "Motivo Legal de Exclusión (Notaría Obligatoria)"],
        [
            ["Inmobiliario", "Compraventa de Bienes Raíces (Casas/Terrenos)", "Art. 1801 inc. 2° Código Civil: Exige solemnidad de Escritura Pública."],
            ["Inmobiliario", "Hipotecas y Gravámenes sobre Inmuebles", "Art. 2409 Código Civil: Solemnidad de Escritura Pública."],
            ["Inmobiliario", "Arriendo para Inscribir en Hipotecas CBR", "Art. 1962 CC: Para oponibilidad a acreedores hipotecarios exige Escritura Pública."],
            ["Inmobiliario", "Autorización Enajenar Bienes Conyugales", "Art. 1749 CC: Autorización de la mujer casada exige Escritura Pública o Notario."],
            ["Inmobiliario", "Renuncia a Gananciales", "Solemnidad legal expresa reservada a Escritura Pública."],
            ["Vehículos", "Compraventa o Transferencia de Vehículos", "Art. 35 Ley de Tránsito: Exige contrato ante Notario o Registro Civil."],
            ["Vehículos", "Poderes para Vender/Transferir Vehículos", "El Registro Civil rechaza mandatos privados para cambio de dueño en el RNVM."],
            ["Vehículos", "Autorización Salida Vehículos al Extranjero", "Aduanas exige poder notarial protocolizado para paso fronterizo."],
            ["Vehículos", "Poder Retiro Vehículos Corrales Municipales", "Direcciones de Tránsito exigen mandato notarial presencial."],
            ["Familia", "Autorización Salida de Menores del País", "Ley de Menores y PDI: Exclusivo ante Notario Público o Registro Civil."],
            ["Familia", "Testamentos Solemnes (Abiertos o Cerrados)", "Art. 1011 CC: Exige Notario y testigos hábiles."],
            ["Familia", "Reconocimiento de un Hijo", "Art. 187 CC: Exige Escritura Pública o Registro Civil."],
            ["Familia", "Posesión Efectiva y Mandatos Asociados", "Tramitación intestada o testada exige patrocinio y mandatos solemnes."],
            ["Familia", "Mandato para Contraer Matrimonio Civil", "Ley de Matrimonio Civil exige Escritura Pública especial."],
            ["Laboral", "Carta de Renuncia Voluntaria del Trabajador", "Art. 177 Código del Trabajo: Exige ratificación ante Notario, DT o sindicato."],
            ["Laboral", "Poderes para Cobro de Finiquitos o Pensiones AFP", "Las instituciones pagadoras rechazan mandatos no autorizados ante Notario."],
            ["Bancario", "Pagarés y Letras de Cambio Autorizadas", "Ley N° 18.092: Para mérito ejecutivo de título de crédito exige firma ante Notario."],
            ["Bancario", "Mandatos y Poderes Bancarios Generales", "Los bancos exigen poderes autorizados ante notario por políticas de riesgo."],
            ["Societario", "Sociedades Tradicionales (Constitución)", "Exige Escritura Pública e inscripción en Comercio (salvo Empresa en un Día)."],
            ["Electoral", "Declaraciones Juradas para el SERVEL", "Ley Electoral exige ratificación ante Notario Público."],
            ["Internacional", "Documentos para Apostilla o Legalizaciones", "Cancillería exige documento emanado de ministro de fe público."]
        ],
        [1.2, 2.0, 3.3]
    )

    add_h2("5.4. Preguntas Frecuentes Oficiales (FAQ) para Clientes y Terceros")
    add_bullet("Los documentos suscritos mediante Firma Electrónica Avanzada (FEA) se rigen por la Ley N° 19.799. Los Arts. 4 y 5 otorgan el mismo valor probatorio y presunción de autoría que un instrumento privado otorgado ante Notario Público.", "¿Tienen validez legal los documentos firmados en SinNotario.cl?: ")
    add_bullet("No. SinNotario.cl es una plataforma tecnológica y de asesoría jurídica privada. La ley chilena permite que contratos civiles y comerciales (arriendos, declaraciones, finiquitos de mutuo acuerdo) se celebren válidamente entre privados sin pisar una notaría.", "¿SinNotario es una notaría en línea?: ")
    add_bullet("Se utiliza Firma Electrónica Avanzada (FEA) emitida por un prestador acreditado (IDOK / FirmaYa), validando la identidad de cada firmante mediante la ClaveÚnica oficial del Estado de Chile con Segundo Factor (2FA).", "¿Qué tipo de firma se utiliza?: ")
    add_bullet("Totalmente. Al estar firmado con FEA, el contrato cuenta con mérito ejecutivo y se somete al procedimiento monitorio exprés de la Ley N° 21.461 ('Devuélveme mi casa'), permitiendo demandar y solicitar desalojo judicial rápido.", "¿Sirve el contrato de arriendo para desalojar con la Ley Devuélveme mi Casa?: ")
    add_bullet("Cualquier banco, tribunal o corredor puede ingresar a sinnotario.cl/verificar, escanear el QR o subir el PDF. El sistema valida el Hash SHA-256, el sello de tiempo TSA y la cadena de firmantes de forma instantánea.", "¿Cómo comprueba un tercero que el documento es auténtico?: ")
    add_bullet("Sí. Si el usuario no tiene celular o le cuesta la tecnología, en nuestras sucursales un ejecutivo lo asiste y puede firmar en cabinas privadas con tablets táctiles seguras, llevándose su copia impresa con sello seco.", "¿Se puede firmar de forma asistida en la sucursal física?: ")

    # SECCIÓN 6
    add_h1("6. Roadmap de Implementación y Fases de Despliegue")
    add_styled_table(
        ["Fase", "Plazo Estimado", "Alcance Principal", "Entregables Clave"],
        [
            ["Fase 1: MVP Digital", "Meses 1 a 3", "Plataforma SaaS web, Catálogo base de 20 plantillas, API ClaveÚnica (IDOK), Webpay y WhatsApp.", "Portal web funcional, flujo de firma online y verificación QR."],
            ["Fase 2: Sucursal Piloto", "Meses 4 a 6", "Apertura de la 1° oficina física, tótems táctiles, hardware biométrico y sala de asesoría legal.", "Oficina física operativa, caja/digitación y atención presencial."],
            ["Fase 3: Expansión y B2B", "Meses 7 a 12", "Puntos de atención en comunas estratégicas, convenios corporativos con inmobiliarias y red franquiciable.", "Integración B2B, portal corporativo y red de sucursales."]
        ],
        [1.3, 1.1, 2.5, 1.6]
    )

    output_path = "Keystone Advisors/SinNotario/docs/Diseno_Funcional_SinNotario_Keystone.docx"
    doc.save(output_path)
    print(f"Documento Word actualizado exitosamente en: {output_path}")

if __name__ == "__main__":
    create_document()
