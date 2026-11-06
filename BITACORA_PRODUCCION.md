# 📝 BITÁCORA DE DESPLIEGUES A PRODUCCIÓN: SINNOTARIO.CL

### [2026-11-06 02:22 UTC-3] - SOL-0005
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0005`
- **Componentes Desplegados:**
  - `index.php`: Portal web principal y responsive con diseño corporativo SinNotario (Azul Marino `#0F2A4A` y Dorado Legal `#D4AF37`).
  - `assets/css/style.css` y `assets/js/app.js`: Estilos modernos y motor interactivo (buscador en tiempo real, detector negativo de trámites notariales exclusivos, catálogo interactivo con filtros y modal wizard).
  - `database/Database.php`: Base de datos SQLite3 relacional con auto-inicialización de esquema (`users`, `templates`, `documents`, `document_signers`, `audit_logs`) y sembrado inicial de 13 plantillas legales.
  - `api/index.php`: Router REST con endpoints (`get_templates`, `get_template_by_slug`, `verify_document`, `create_draft`, `health`).
  - `api/adapters/StorageR2Adapter.php`: Conexión nativa de alta velocidad hacia Cloudflare R2 (`sinnotario-vault-prod`).
- **Pruebas y Smoke Tests Validados:**
  - `GET https://sinnotario.cl/` -> HTTP 200 OK (Renderizado completo).
  - `GET https://sinnotario.cl/api/index.php?action=get_templates` -> HTTP 200 OK con 13 plantillas activas.
  - `GET https://sinnotario.cl/api/index.php?action=health` -> `status: operational`, `database: SQLite 3 connected`, `r2_vault: Ready`.
  - PHP Lint: `0` errores de sintaxis en el servidor remoto.
- **Estado Final:** ✅ Éxito Total / 100% Operativo.

### [2026-11-06 02:32 UTC-3] - SOL-0006
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0006`
- **Alcance de los Cambios:**
  1. **Tipografía y Menús:** Limpieza visual de la barra de navegación con fuente de sistema refinada y tracking equilibrado.
  2. **Identidad Visual:** Removido el texto duplicado `[SinNotario.cl Plataforma LegalTech Digital]` al costado del logo para que el isotipo/logo oficial destaque limpio.
  3. **Enfoque 100% Online (Fase 1):** Retiradas las menciones a cabinas físicas y sucursales presenciales para reservarlas a la Fase 2.
  4. **Paginación / Carga Progresiva de Trámites:** El catálogo ahora muestra 3 documentos destacados y un botón *"Ver más trámites (X restantes)"* que despliega lotes adicionales de 3 en 3 con transición suave.
  5. **Módulo 11 de RUT:** Formateo automático en vivo (`12.345.678-K`) y validación estricta con algoritmo Módulo 11 en el formulario y wizard.
- **Pruebas Validadas:**
  - Sintaxis PHP: 0 errores detectados.
  - Validación de RUT: Borde verde al ingresar RUT válido, alerta roja si el dígito verificador es incorrecto e impedimento de submit si no es válido.
  - Carga progresiva funcional: Muestra 3 tarjetas iniciales y despliega las siguientes al pulsar *"Ver más trámites"*.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 02:38 UTC-3] - SOL-0007
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0007`
- **Alcance de los Cambios:**
  - Incorporación en el bloque B2B del botón interactivo `Conocer Solución Empresas` con icono dinámico.
  - Al hacer clic, se despliega una grilla corporativa (`b2b-features-grid`) con las 6 bondades estratégicas:
    1. **Carga Masiva desde Excel:** Smart Bulk Wizard con mapeo y validación de RUT Módulo 11 en segundos.
    2. **WhatsApp Oficial Dedicado:** Notificaciones desde el número y foto de la propia empresa cliente (Evolution API White-Label con chip prepago).
    3. **Firma en 1 Clic del Representante Legal:** Firma por lotes con una sola autenticación ClaveÚnica.
    4. **Cumplimiento Dirección del Trabajo (DT):** Exportación codificada para el portal Mi DT según la Ley N° 21.327.
    5. **Expediente Digital del Trabajador:** Historial cronológico de contratos, anexos (40 Horas), EPP y finiquitos.
    6. **Bolsas Prepagadas con Descuento:** Tarifas preferenciales por volumen desde $1.990 y facturación exenta.
- **Pruebas Validadas:**
  - Sintaxis PHP: 0 errores detectados.
  - Smoke test HTTP 200 OK en producción (`sinnotario.cl`).
  - Despliegue interactivo fluido y responsivo en móviles y escritorio.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 02:45 UTC-3] - SOL-0009
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl/redactar.php`)
- **Identificador de Solución:** `SOL-0009`
- **Componentes Desplegados:**
  - `redactar.php`: Pantalla completa del Wizard de Redacción de Contrato de Arriendo Residencial Ley N° 21.461 en arquitectura Split View (60/40).
  - Formulario paso a paso (5 etapas con barra de progreso interactiva):
    1. *Arrendador:* Nombre, RUT Módulo 11, Email, WhatsApp, Domicilio.
    2. *Arrendatario & Aval:* Nombre, RUT Módulo 11, Email, WhatsApp y casilla dinámica para incorporar Codeudor Solidario (Aval).
    3. *Identificación del Inmueble:* Dirección, depto/casa, comuna, bodega y estacionamiento.
    4. *Condiciones Económicas y Plazos:* Renta en CLP, día límite de pago, garantía, periodicidad de reajuste IPC (anual/semestral), fecha de inicio y sumisión formal a la Ley N° 21.461 ("Devuélveme Mi Casa").
    5. *Resumen y Orden de Despacho:* Desglose transparente ($9.990 CLP con 2 firmas ClaveÚnica y custodia Cloudflare R2 incluidas).
  - `Live Document Preview (40%)`: Previsualizador lateral sincronizado en tiempo real con marca de agua *"BORRADOR"* en diagonal y resaltado de variables dinámicas.
  - Conexión con `api/index.php?action=create_draft` para guardar los datos y generar el código de verificación `SN-XXXX-XXXX`.
- **Pruebas Validadas:**
  - PHP Lint: 0 errores de sintaxis en el servidor remoto.
  - Smoke test: HTTP 200 OK en `https://sinnotario.cl/redactar.php?slug=contrato-arriendo-ley-21461`.
  - Validación de RUT: Bloqueo de avance en pasos 1 y 2 si los RUTs no superan la validación Módulo 11.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 02:59 UTC-3] - SOL-0010
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0010`
- **Alcance del Cambio:**
  - Actualización del titular del Hero a mayúsculas completas:
    `TUS CONTRATOS Y TRÁMITES LEGALES 100% ONLINE Y SIN PISAR UNA NOTARÍA`
- **Pruebas Validadas:**
  - PHP Lint: 0 errores de sintaxis en `index.php`.
  - Smoke test: HTTP 200 OK en producción.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 03:05 UTC-3] - SOL-0013
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0013`
- **Alcance del Cambio:**
  - Se modificó la regla `.filter-btn.active` en `assets/css/style.css`.
  - Ahora, al seleccionar cualquiera de los botones de filtro de categorías (*Todos*, *Inmobiliario*, *Laboral*, *Comercial*, *Declaraciones*), el botón adopta el color amarillo institucional / dorado legal exacto de la plataforma (`#D4AF37`) con texto oscuro (`#0A1C33`), generando un contraste perfecto frente al fondo azul marino del Hero.
- **Pruebas Validadas:**
  - CSS Lint & Smoke test en producción (`https://sinnotario.cl`).
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 03:20 UTC-3] - SOL-0014
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl/redactar.php`)
- **Identificador de Solución:** `SOL-0014`
- **Alcance del Despliegue:**
  - Se extendió el motor de `redactar.php` a una **arquitectura universal polimórfica** que cubre las 13 plantillas del catálogo legal:
    1. **Contratos Inmobiliarios:** Arriendo Ley 21.461, Promesa de Compraventa (arras, UF, plazos) y Acta de Entrega de Llaves.
    2. **Contratos Laborales:** Finiquito por Mutuo Acuerdo (causales legales, montos y liquidación) y Contrato Individual de Trabajo.
    3. **Contratos Comerciales:** Reconocimiento de Deuda / Pagaré privado, Acuerdo de Confidencialidad (NDA) y Prestación de Servicios a Honorarios.
    4. **Declaraciones y Trámites Unipersonales:** Declaración de Residencia, Declaración de Ingresos, Salvoconducto de Mudanza Ley 20.227, Poder Especial y Domicilio Tributario SII (oculta automáticamente los campos de contraparte innecesarios).
  - El **Live Document Preview** a la derecha (40%) renderiza en tiempo real las cláusulas y variables específicas del tipo de documento seleccionado con marca de agua *"BORRADOR"*.
  - Validación de RUT Módulo 11 en tiempo real para todas las partes comparecientes.
- **Pruebas Validadas:**
  - PHP Lint: 0 errores de sintaxis en el servidor remoto.
  - Smoke tests verificados con HTTP 200 OK en las 13 variantes de URL en producción.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 03:39 UTC-3] - SOL-0015 & SOL-0016
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0015` & `SOL-0016`
- **Componentes Desplegados:**
  - `src/Services/DocumentPdfService.php`: Motor en PHP puro (FPDF + PHP QR Code) que construye el PDF oficial del contrato con:
    - Encabezado formal con folio alfanumérico único (`SN-XXXX-XXXX`).
    - Código QR dinámico de alta resolución incrustado al pie izquierdo de cada página (apunta a la URL de validación oficial).
    - Sellado de tiempo y cálculo criptográfico de Hash SHA-256 sobre el binario.
    - Cuadro de estampa de firmas digitales de cada compareciente con fecha y método.
  - Subida directa en milisegundos a la bóveda Cloudflare R2 (`sinnotario-vault-prod`) vía `StorageR2Adapter`.
  - Integración nativa en `api/index.php?action=create_draft`.
  - **Módulo 12 (Diseño Funcional y Word):** Área Privada de Clientes (`/mi-cuenta`), Login con Gmail/Google OAuth 2.0, registro con correo y código OTP, ClaveÚnica y Bóveda Personal de contratos.
- **Pruebas Validadas:**
  - Emisión de prueba: Documento `SN-C17A-CE54` generado y vaulting verificado en Cloudflare R2 con Hash SHA-256 `58b3e2cc57a4b02ab260c2570b7210f172d54dcf8232838119fb21185f632b76` (`r2_uploaded: true`).
  - Consulta en `/api/index.php?action=verify_document&code=SN-C17A-CE54` retornando HTTP 200 OK y todos los metadatos de custodia.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

### [2026-11-06 03:45 UTC-3] - SOL-0017, SOL-0018 & SOL-0019
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificadores:** `SOL-0017`, `SOL-0018`, `SOL-0019`
- **Componentes Desplegados:**
  1. **Motor de Asesoría Legal IA (`asesoria-legal.php` & `GeminiLegalService.php`):**
     - Pestaña *Smart Legal Check*: Diagnóstico heurístico y con IA de contratos existentes con semáforo tricolor (alertas críticas rojas, sugerencias amarillas y puntos sólidos verdes).
     - Pestaña *Smart Drafter (Libre Albedrío)*: Generación de acuerdos y contratos atípicos a partir de lenguaje natural bajo el Art. 1545 del Código Civil.
     - Banner de conversión para visación formal con abogado titulado ($19.990 CLP).
  2. **Consola Administrativa Operativa (`admin.php`):**
     - Acceso restringido por usuario y contraseña maestra.
     - Dashboard de KPIs: Documentos emitidos, facturación acumulada, plantillas activas, monitor de Cloudflare R2 y estado del Gateway WhatsApp (Evolution API).
     - Tabla en vivo de contratos con sus folios, estados de pago y hashes SHA-256 custodiados.
  3. **Suite B2B Empresas (`empresas.php`):**
     - *Smart Bulk Wizard*: Carga masiva de planillas de colaboradores o arriendos vía drag-and-drop de Excel/CSV.
     - Tabla de pre-visualización y validación de nóminas con Módulo 11 de RUT.
     - Botón maestro de Firma en 1 Clic con ClaveÚnica y exportación estandarizada para el portal Mi DT de la Dirección del Trabajo (Ley N° 21.327).
- **Pruebas Validadas:**
  - PHP Lint: 0 errores en todos los scripts nuevos.
  - Smoke tests HTTP 200 OK en `https://sinnotario.cl/asesoria-legal.php`, `https://sinnotario.cl/admin.php` y `https://sinnotario.cl/empresas.php`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y Git.

---

### Entrada: 2026-11-06 10:05:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0020`
- **Resumen de la Ejecución:**
  - **Aumento de Tamaño del Logotipo:** Se incrementó la dimensión del logotipo corporativo de SinNotario en un 30% en el encabezado de navegación principal (`.brand-logo img` de 44px a 58px de altura), mejorando sustancialmente el reconocimiento de marca.
  - **Jerarquización y Negrita en Menú de Navegación:** Se actualizaron los estilos de los enlaces del menú (`.nav-links a`), aumentando su peso visual a `font-weight: 700`, color `#1E293B` y `font-size: 0.96rem` (con `font-weight: 800` en estados hover/active y WhatsApp en `font-weight: 700`), logrando una lectura nítida y destacada contra la barra superior.
  - **Pruebas Validadas:**
    - Carga de hoja de estilos en producción: HTTP 200 OK (`https://sinnotario.cl/assets/css/style.css`).
    - Smoke test del portal home: HTTP 200 OK (`https://sinnotario.cl/`).
    - Verificación de renderizado de selectores `.brand-logo img` y `.nav-links a`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 10:08:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0021`
- **Resumen de la Ejecución:**
  - **Detección y Causa Raíz:** Se detectó un error sintáctico (`Unexpected token '}'`) en `assets/js/app.js` originado por código residual de un modal modal legado tras la migración al Wizard universal Split View 60/40 (`redactar.php`). Esto causaba que el navegador abortara el análisis de JavaScript, impidiendo que `DOMContentLoaded` ejecutara `loadTemplates()` y dejando los botones de filtro inoperativos.
  - **Corrección Aplicada:** Se limpió la función `openWizard(slug)` eliminando el bloque huérfano, se validó la sintaxis completa con Node.js (`node -c assets/js/app.js` -> 0 errores) y se agregó control de versionado (*cache-busting*) `?v=20261106_2` en los enlaces de `app.js` y `style.css` dentro de `index.php`.
  - **Pruebas Validadas:**
    - Carga de API `api/index.php?action=get_templates`: HTTP 200 OK con el catálogo completo de 13 plantillas.
    - Carga de `app.js?v=20261106_2`: HTTP 200 OK y sintaxis validada.
    - Smoke test funcional: HTTP 200 OK en `https://sinnotario.cl/`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 10:10:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0022`
- **Resumen de la Ejecución:**
  - **Ampliación de Logotipo (+40% adicional):** Se aumentó la dimensión del logotipo de SinNotario en `.brand-logo img` pasando de `58px` a **`82px`** de altura, confiriéndole un protagonismo visual nítido y balanceado con los enlaces en negrita y el botón CTA del menú superior.
  - **Inyección de Cache-Busting:** Se actualizó `index.php` a la versión `style.css?v=20261106_3` para asegurar la recarga inmediata de la regla CSS en clientes y proxys.
  - **Pruebas Validadas:**
    - Carga directa del CSS con cabeceras `HTTP/2 200` y confirmación de `height: 82px;`.
    - Smoke test del sitio en vivo: `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 10:14:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0023`
- **Resumen de la Ejecución:**
  - **Construcción del Módulo Marco Legal (`marco-legal.php`):** Se desarrolló una página de aterrizaje interactiva y doctrinal para cada normativa legal vinculada al footer del portal:
    1. **Ley N° 19.799:** Principio de equivalencia del soporte electrónico, firma simple vs avanzada y valor probatorio en tribunales. Enlace oficial: BCN Norma N° 196640.
    2. **Ley N° 21.461 ("Devuélveme Mi Casa"):** Procedimiento monitorio de cobro de rentas, plazo perentorio de 10 días y medida precautoria de restitución anticipada. Enlace oficial: BCN Norma N° 1178044.
    3. **Ley N° 18.101:** Régimen general de arrendamiento urbano, irrenunciabilidad de derechos y presunciones legales. Enlace oficial: BCN Norma N° 29526.
    4. **Código del Trabajo Art. 177:** Formalización del finiquito laboral, ministros de fe, ratificación electrónica con ClaveÚnica (Mi DT) y acreditación de cotizaciones previsionales. Enlace oficial: BCN Norma N° 207436.
    5. **Bóveda Cifrada Cloudflare R2:** Criptografía SHA-256 inmutable, código QR y cadena de custodia digital forense.
  - **Vinculación en Frontend (`index.php`):** Se actualizaron todos los hipervínculos del pie de página para dirigir a `marco-legal.php?ley=...`.
  - **Pruebas Validadas:**
    - Carga directa con HTTP 200 OK de todas las vistas legales (`ley-19799`, `ley-21461`, etc.).
    - Validación de redirección de enlaces a `bcn.cl / leychile.cl`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.
