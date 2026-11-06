# 📝 BITÁCORA DE DESPLIEGUES A PRODUCCIÓN: SINNOTARIO.CL

### [2026-11-06 15:48 UTC-3] - SOL-0037
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0037`
- **Alcance de los Cambios:**
  - **Declaración Jurada Simple de Residencia y Declaración Jurada de Ingresos Informales:**
    1. **Paso 1 y Paso 3 con Selectores Interactivos para Ambos Documentos:**
       - **Declaración Jurada Simple de Residencia:**
         - *Opción A: Estándar Notarial con Apercibimiento Penal Art. 210 CP (4 Cláusulas Solemnes - Recomendado).* Declaración solemne bajo juramento ante la ley, señalamiento de morada habitual con tiempo de residencia ininterrumpida, destino específico del instrumento (bancos, casas comerciales, colegios) y conocimiento expreso de sanciones de presidio menor y multas contempladas en el Art. 210 del Código Penal por falso testimonio ante la autoridad.
         - *Opción B: Familiar / Acreditación de Carga para Becas y Asignaciones (5 Cláusulas).* Fijación solemne del domicilio familiar, tiempo de residencia continua, acreditación de cargas familiares (cónyuge, hijos menores de edad inscritos en Registro Social de Hogares) que cohabitan bajo el mismo techo, y destino exclusivo para postulación a beneficios estatales (FUAS, Gratuidad, jardines infantiles Junji/Integra y subsidio habitacional MINVU).
         - *Opción C: Independiente con Respaldo de Movimientos y Arriendo (5 Cláusulas).* Acreditación de morada efectiva, calidad de ocupación (arrendatario con contrato vigente y cuentas de servicios básicos pagadas a su nombre), destino bancario/financiero para evaluación crediticia y fijación de domicilio para notificaciones.
       - **Declaración Jurada de Ingresos (Actividades Informales / Independientes):**
         - *Opción A: Estándar Notarial con Apercibimiento Penal Art. 210 CP (4 Cláusulas Solemnes - Recomendado).* Declaración bajo juramento, actividad u oficio lícito, cuantificación mensual promedio de ingresos líquidos percibidos sin relación de dependencia laboral, institución de destino y apercibimiento penal expreso del Art. 210 CP por perjurio.
         - *Opción B: Familiar / Acreditación de Carga para Becas y Asignaciones (5 Cláusulas).* Origen de fondos independientes con antigüedad, promedio mensual líquido, destinación prioritaria al sustento familiar, salud y educación de cargas familiares, y finalidad socioeconómica para postulación a beneficios estudiantiles (FUAS / Gratuidad).
         - *Opción C: Independiente con Respaldo de Movimientos y Transferencias (5 Cláusulas).* Actividad y trayectoria comercial, flujo bruto de depósitos y transferencias bancarias electrónicas, costos operativos deducibles y margen neto disponible resultante, para acreditación de solvencia ante bancos o contratos de arriendo.
    2. **Previsualizador en Vivo y Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización completa de `renderClauses()` para ambos documentos en sus 3 opciones con código QR dinámico, Hash SHA-256 inalterable y resguardo en Cloudflare R2.
    3. **Emisión de Borradores (`create_draft`):**
       - Persistencia de los metadatos específicos (`f_res_modalidad`, `f_res_antiguedad`, `f_res_cargas`, `f_res_programa`, `f_res_servicios`, `f_res_banco`, `f_ing_modalidad`, `f_ing_antiguedad`, `f_ing_aporte_familiar`, `f_ing_programa_estatal`, `f_ing_depositos`, `f_ing_costos`) en el payload del formulario.
    4. **Cache-Busting:** Bump a `?v=20261106_15`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke tests HTTP en producción:
    - `curl -I "https://sinnotario.cl/redactar.php?slug=declaracion-jurada-residencia"` -> `HTTP/2 200 OK`.
    - `curl -I "https://sinnotario.cl/redactar.php?slug=declaracion-jurada-ingresos-informales"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:43 UTC-3] - SOL-0036
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0036`
- **Alcance de los Cambios:**
  - **Mandato y Poder Especial Privado (Arts. 2116 y ss. del Código Civil):**
    1. **Paso 1 y 2 Personalizados:**
       - Paso 1 adaptado para "Mandante (Quien Otorga el Poder)" (Nombre, RUT, Profesión y Domicilio).
       - Paso 2 adaptado para "Mandatario / Apoderado" (Nombre, RUT, Profesión y Domicilio).
    2. **Paso 3 con Selector Interactivo de las 3 Modalidades:**
       - *Opción A: Mandato Administrativo / Trámites y Encomiendas (5 Cláusulas - Recomendado).* Objeto y encargo de mandato, facultades específicas delimitadas (retiro de encomiendas y correspondencia certificada en Correos de Chile, Chilexpress, Starken; cobro bancario de mesón y vales vista menores a $1.500.000; gestiones municipales y sanitarias; trámites ante empresas de telecomunicaciones), vigencia acotada (3 a 12 meses o única gestión), régimen de no delegación y rendición de cuentas con culpa leve (Art. 2129 CC).
       - *Opción B: Poder para Representación en Asambleas de Copropietarios (Ley 21.442 - 4 Cláusulas).* Individualización del departamento/bodega/estacionamiento y condominio, concesión solemne de derecho a voz y voto para asambleas ordinarias y extraordinarias, aprobación de balances, elección de Comité de Administración y fijación de gastos comunes, vigencia, revocabilidad y suscripción de actas de asamblea.
       - *Opción C: Poder para Trámites de Vehículos Motorizados (Registro Civil / PRT - 5 Cláusulas).* Individualización del vehículo (marca, modelo, año y Placa Patente Única PPU inscrita en el RVM), facultades para obtención de duplicados de padrón/CAV y placas patentes en el Registro Civil, ingreso a Planta de Revisión Técnica (PRT), pago de Permiso de Circulación y contratación de SOAP en Direcciones de Tránsito, con cláusula taxativa de prohibición de disposición patrimonial (no autoriza venta, cesión ni gravamen sobre el móvil).
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización del método `renderClauses()` para emitir las cláusulas contractuales específicas de cada una de las 3 opciones en el PDF descargable con QR dinámico, Hash SHA-256 y resguardo en Cloudflare R2.
    4. **Emisión de Borradores (`create_draft`):**
       - Inclusión de los campos específicos del mandato (`f_poder_modalidad`, `f_poder_facultades`, `f_poder_vigencia`, `f_poder_delegar`, `f_poder_cuentas`, `f_poder_edificio`, `f_poder_unidad`, `f_poder_asamblea_tipo`, `f_poder_auto_patente`, `f_poder_auto_datos`, `f_poder_auto_tramites`) en el payload del formulario.
    5. **Cache-Busting:** Bump a `?v=20261106_14`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP en producción: `curl -I "https://sinnotario.cl/redactar.php?slug=poder-especial-privado"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:40 UTC-3] - SOL-0035
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0035`
- **Alcance de los Cambios:**
  - **Declaración Jurada de Traslado de Enseres / Mudanza (Ley N° 20.227):**
    1. **Paso 1 Personalizado:**
       - Paso 1 adaptado para "Declarante / Propietario de Enseres" (Nombre, RUT, Profesión y Domicilio).
    2. **Paso 3 con Selector Interactivo de las 3 Modalidades:**
       - *Opción A: Formato Ley 20.227 con Acreditación de Dominio / No Impedimento (5 Cláusulas - Recomendado).* Individualización del declarante bajo fe de juramento, calidad jurídica (propietario o arrendatario al día en rentas y servicios), fijación de domicilios de origen y destino, fecha de la mudanza, medio de transporte y conductor/empresa de flete, y declaración formal de no existir orden judicial, embargo, precautoria ni deuda de arriendo impaga, sirviendo de salvoconducto habilitante ante Carabineros de Chile.
       - *Opción B: Interregional de Larga Distancia con Declaración de Carga (6 Cláusulas).* Traslado entre regiones: incorpora itinerario vial de ruta carretera, desglose detallado de bultos mayores, muebles y electrodomésticos de valor, y estipulación de cumplimiento fitosanitario y aduanas sanitarias del SAG (carga libre de especies agrícolas restringidas) para controles de Carabineros y fiscalizadores de transporte.
       - *Opción C: Mudanza Corporativa / Traslado de Oficinas y Bienes de Capital (5 Cláusulas).* Diseñado para personas jurídicas y empresas: personería del representante legal, identificación de sucursales de origen y destino, inventario categorizado de infraestructura TI (laptops, servidores en rack, monitores, impresoras), archivo contable y tributario bajo precinto, porteo con chofer y Guía de Despacho de Traslado Interno sin venta del SII, y declaración de inexistencia de gravámenes.
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización del método `renderClauses()` para emitir las cláusulas legales y técnicas según la modalidad seleccionada en el PDF descargable con código QR de verificación, Hash SHA-256 y respaldo en Cloudflare R2.
    4. **Emisión de Borradores (`create_draft`):**
       - Inclusión de los campos específicos de mudanza (`f_mud_modalidad`, `f_mud_origen`, `f_mud_destino`, `f_mud_fecha`, `f_mud_calidad_inmueble`, `f_mud_vehiculo`, `f_mud_conductor`, `f_mud_impedimentos`, `f_mud_bultos`, `f_mud_ruta`, `f_mud_sag`, `f_mud_empresa_datos`, `f_mud_ti_equipos`, `f_mud_archivos`) en el payload del formulario.
    5. **Cache-Busting:** Bump a `?v=20261106_13`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP en producción: `curl -I "https://sinnotario.cl/redactar.php?slug=salvoconducto-mudanza"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:37 UTC-3] - SOL-0034
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0034`
- **Alcance de los Cambios:**
  - **Autorización de Domicilio Tributario ante el SII (Circular N° 31/2007 SII):**
    1. **Paso 1 y 2 Adaptativos:**
       - Paso 1 configurado para "Propietario / Autorizante" (Nombre, RUT, Profesión y Domicilio).
       - Paso 2 dinámico: adaptado para "Empresa / Sociedad Contribuyente" en Opciones A y B; y ocultado automáticamente en formulario y visualizador si se selecciona la Opción C (Declaración Unipersonal).
    2. **Paso 3 con Selector Interactivo de las 3 Modalidades:**
       - *Opción A: Comodato Gratuito Inmobiliario - Formato Estándar SII Art. 2174 CC (5 Cláusulas - Recomendado).* Comparecencia bilateral (Dueño y Representante Legal de la empresa), individualización del inmueble y Rol de Avalúo Fiscal SII, entrega formal en comodato precario gratuito sin renta (Art. 2174 Código Civil), destino exclusivo (comercial, tributario, postal y administrativo), vigencia (indefinida o anual) y procedimiento de restitución con aviso previo de 60 días corridos para que la empresa traslade su domicilio tributario ante el SII.
       - *Opción B: Autorización por Subarrendamiento / Espacio Co-Work (6 Cláusulas).* Diseñado para centros de negocios y espacios de co-working: comparecencia del operador y acreditación de facultades de subarriendo o cesión de espacios según contrato matriz y estatutos, provisión de servicios de oficina virtual (recepción postal del SII y salas de reuniones), destino tributario, inspección de funcionarios del SII (verificación de actividad) y rescisión con traslado.
       - *Opción C: Declaración Jurada Simple Unipersonal del Propietario (4 Cláusulas).* Redacción en formato solemne unipersonal del dueño del inmueble, individualización del Rol de Avalúo Fiscal, autorización libre e irrevocable a favor de la empresa para trámites en el portal web del SII (Mi SII) bajo fe de juramento y expreso apercibimiento de responsabilidad penal por perjurio conforme al Artículo 210 del Código Penal.
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización del método `renderClauses()` para emitir las 5, 6 o 4 cláusulas/declaraciones según la modalidad elegida, con QR dinámico y Hash SHA-256 en Cloudflare R2.
    4. **Emisión de Borradores (`create_draft`):**
       - Ajuste del discriminador `isActuallyUnipersonal` para que en Opción C se envíe un solo firmante (declarante), y en A/B se envíen ambas partes. Inclusión de metadatos del inmueble y modalidad en el payload.
    5. **Cache-Busting:** Bump a `?v=20261106_12`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP en producción: `curl -I "https://sinnotario.cl/redactar.php?slug=autorizacion-domicilio-tributario-sii"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:30 UTC-3] - SOL-0033
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0033`
- **Alcance de los Cambios:**
  - **Acta de Entrega Material e Inventario de Inmuebles:**
    1. **Paso 1 y 2 Personalizados:**
       - Paso 1 adaptado para "Propietario / Arrendador" (Nombre, RUT, Profesión y Domicilio).
       - Paso 2 adaptado para "Arrendatario / Receptor" (Nombre, RUT, Profesión y Domicilio).
    2. **Paso 3 con Selector Dinámico de las 3 Modalidades:**
       - *Opción A: Notarial Inmobiliaria Exhaustiva (6 Secciones - Recomendado).* Entrega física de llaves (principales, bodega, portón), lecturas oficiales de medidores (electricidad kWh, agua potable m3 y gas), inspección detallada por recintos (muros, pisos, pintura y grifería), tabla de inventario de artefactos y bienes muebles, deber legal de custodia y firma de plena conformidad probatoria.
       - *Opción B: Recepción Provisoria con Lista de Observaciones / "Snag List" (5 Secciones).* Recepción en calidad provisoria, lectura de medidores y entrega de llaves, tabla estructurada de observaciones menores pendientes por subsanar, plazo perentorio de reparación a cargo del propietario (ej: 10 días corridos) y conversión de pleno derecho en recepción definitiva.
       - *Opción C: Desocupación y Restitución Final con Liquidación de Garantía (5 Secciones).* Restitución material formal desocupada de moradores, devolución de llaves y consumos de salida, inspección física de estado de entrega, liquidación matemática de garantía (Garantía original entregada - Descuentos por reparaciones/cuentas = Saldo Líquido a devolver al arrendatario), plazo de pago y finiquito recíproco y liberatorio del contrato de arriendo.
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización para emitir las cláusulas y secciones específicas de cada una de las 3 modalidades en el PDF final con código QR y Hash SHA-256 en Cloudflare R2.
    4. **Cache-Busting:** Bump a `?v=20261106_11`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP en producción: `curl -I "https://sinnotario.cl/redactar.php?slug=acta-entrega-llaves-e-inventario"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:26 UTC-3] - SOL-0032
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0032`
- **Alcance de los Cambios:**
  - **Reconocimiento de Deuda y Compromiso de Pago (Ley N° 18.010):**
    1. **Paso 1 y 2 Personalizados:**
       - Paso 1 adaptado para "Acreedor / Beneficiario" (Razón Social o Nombre, RUT, Giro, Domicilio y Representante Legal).
       - Paso 2 adaptado para "Deudor Principal" (Nombre, RUT, Profesión y Domicilio).
    2. **Paso 3 con Selector Dinámico de las 3 Opciones:**
       - *Opción A: Notarial Bancario con Cláusula de Aceleración y Pagaré (6 Cláusulas - Recomendado).* Reconocimiento líquido de deuda, origen lícito, calendario de cuotas, interés penal al máximo convencional CMF (Ley 18.010), Cláusula de Aceleración ipso facto ante simple retardo con renuncia expresa a requerimiento judicial previo y pagaré notarial paralelo.
       - *Opción B: Con Codeudor Solidario / Fiador y Aval (8 Cláusulas).* Incorpora la comparecencia de un tercero garante como fiador, aval y codeudor solidario de todas las obligaciones, intereses y costas, con renuncia formal a los beneficios de orden y excusión.
       - *Opción C: Transaccional Judicial / Pre-Demanda con Confesión de Deuda (7 Cláusulas).* Formato de transacción extrajudicial conforme al Art. 2446 del Código Civil con valor de cosa juzgada en última instancia (Art. 2460 CC) y sometimiento procesal expreso a la gestión preparatoria de la vía ejecutiva de reconocimiento de firma y confesión de deuda (Art. 434 N° 5 del Código de Procedimiento Civil) con habilitación de embargo inmediato y notificación electrónica (Ley 20.886).
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Actualización de `renderClauses()` para emitir las cláusulas contractuales y procesales de cada variante con QR dinámico y Hash SHA-256 en Cloudflare R2.
    4. **Cache-Busting:** Bump a `?v=20261106_10`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores de sintaxis en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP: `curl -I "https://sinnotario.cl/redactar.php?slug=reconocimiento-deuda-pagare-privado"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:22 UTC-3] - SOL-0031
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0031`
- **Alcance de los Cambios:**
  - **Contrato de Prestación de Servicios Profesionales (Art. 2006 Código Civil):**
    1. **Paso 1 y 2 Adaptativos:**
       - Paso 1 personalizado para "Mandante / Empresa Contratante" (Razón Social, RUT, Giro, Representante Legal y Domicilio).
       - Paso 2 personalizado para "Profesional Independiente" (Nombre, RUT, Profesión y Domicilio).
    2. **Paso 3 con Selector Interactivo de las 3 Opciones:**
       - *Opción A: Civil Profesional con Blindaje Anti-Laboralidad (8 Cláusulas - Recomendado).* Exclusión taxativa de subordinación y dependencia (Art. 7 y 8 Código del Trabajo), libertad de horarios, ausencia de jefatura jerárquica y de potestad disciplinaria, sin exclusividad, provisión de medios propios, propiedad intelectual (Ley 17.336), secreto profesional y término anticipado por desahucio escrito de 15 a 30 días.
       - *Opción B: Por Hito / Proyecto Cerrado (7 Cláusulas).* Desglose de fases con criterios de aceptación escrita, garantía técnica y soporte correctivo post-entrega (60 días), transferencia de titularidad patrimonial contra pago de hito final y liquidación pro-rata en caso de término.
       - *Opción C: SLA Corporativo / Servicios Continuos con Penalizaciones (9 Cláusulas).* Acuerdos de nivel de servicio (SLA) con tiempos de respuesta categorizados (crítico 2h / normal 24h), auditorías mensuales con penalizaciones escalonadas deducibles de los honorarios (hasta 20%), continuidad operativa con sustitución de personal clave en máximo 5 días hábiles y deber de indemnidad patrimonial.
    3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
       - Sincronización del método `renderClauses()` para emitir las cláusulas contractuales específicas de cada una de las 3 modalidades en el PDF final con QR dinámico y Hash SHA-256 en Cloudflare R2.
    4. **Cache-Busting:** Bump a `?v=20261106_9`.
- **Pruebas Validadas:**
  - Verificación sintáctica remota PHP Lint en Hostinger: 0 errores detectados en `redactar.php` y `DocumentPdfService.php`.
  - Smoke test HTTP: `curl -I "https://sinnotario.cl/redactar.php?slug=prestacion-servicios-honorarios"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 15:19 UTC-3] - SOL-0030
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0030`
- **Alcance de los Cambios:**
  1. **Finiquito Laboral por Mutuo Acuerdo (3 Modalidades Integradas):**
     - **Paso 3 con Selector Dinámico e Interactivo:**
       - *Opción 1: Estándar Dirección del Trabajo (Portal Mi DT).* Formato de 6 cláusulas para ratificación directa con ClaveÚnica.
       - *Opción 2: Blindaje Corporativo / Patronal.* Formato de 9 cláusulas con acreditación de pago previsional íntegro bajo la Ley Bustos (Ley N° 19.631) y declaración de indemnidad por accidentes laborales y enfermedades profesionales (Ley N° 16.744 y Art. 184 CdT).
       - *Opción 3: Transaccional Extrajudicial con Reserva de Derechos.* Formato de 8 cláusulas con valor de transacción (Art. 2446 Código Civil) y campo de texto condicional para estipular la "Reserva de Derechos" del trabajador según doctrina de la Corte Suprema.
     - Cálculo y desglose automático de haberes: días y remuneraciones pendientes + feriado proporcional / vacaciones + indemnización voluntaria = Total Líquido a Pagar.
  2. **Acuerdo de Confidencialidad y No Divulgación (NDA Bilateral Corporativo):**
     - **Paso 3 y Estructura Dogmática de 8 Cláusulas:**
       - Definición amplia de Información Confidencial (código fuente, modelos financieros, diagramas de sistemas, algoritmos y clientes).
       - Estándar calificado de custodia y diligencia sin ingeniería inversa.
       - Excepciones al deber de reserva (dominio público, orden judicial perentoria).
       - Inexistencia de licencia sobre propiedad intelectual (Leyes 17.336 y 19.039).
       - Vigencia parametrizable (2, 3, 5 años o indefinida para secretos industriales).
       - Cláusula Penal tasada de 500 UF ante contravención (Art. 1535 CC).
       - Fijación de domicilio y competencia en Tribunales Ordinarios de Santiago.
  3. **Motor PDF Criptográfico (`DocumentPdfService.php`):**
     - Sincronización para emitir tanto las 3 variantes del finiquito como las 8 cláusulas del NDA en el PDF con QR dinámico y Hash SHA-256.
  4. **Cache-Busting:** Bump a `?v=20261106_8`.
- **Pruebas Validadas:**
  - PHP Lint remoto en Hostinger: 0 errores sintácticos en `redactar.php` y `DocumentPdfService.php`.
  - Smoke tests HTTP en producción:
    - `GET https://sinnotario.cl/redactar.php?slug=finiquito-laboral-mutuo-acuerdo` -> `HTTP/2 200 OK`.
    - `GET https://sinnotario.cl/redactar.php?slug=acuerdo-confidencialidad-nda` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


### [2026-11-06 14:38 UTC-3] - SOL-0029
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0029`
- **Alcance de los Cambios:**
  - **Plantilla Oficial de Contrato Individual de Trabajo (Art. 10 Código del Trabajo de Chile):**
    1. **Wizard 60/40 Dinámico (`redactar.php`):**
       - Paso 1 personalizado para "Empleador / Representante Legal" (Razón Social, RUT empresa, Giro, Domicilio, Comuna y Representante Legal).
       - Paso 2 personalizado para "Trabajador" (Nombre, RUT Módulo 11, Nacionalidad, Estado Civil, Fecha Nacimiento, Profesión, Domicilio, Email y Teléfono).
       - Paso 3 estructurado en 4 secciones funcionales:
         a) Cargo, funciones principales del puesto (enunciativas) y lugar específico de prestación de servicios.
         b) Jornada de trabajo: Ordinaria con sujeción a horario (Ley de 40 Horas / Ley N° 21.561, minutos de colación y sistema de control de asistencia digital/biométrico autorizado por la DT) vs. Exenta de jornada (Art. 22 inc. 2° del Código del Trabajo).
         c) Remuneraciones y beneficios: Sueldo base en $ CLP, gratificación legal (Artículo 50 con tope de 4,75 IMM vs. Artículo 47 con 30% utilidad líquida), asignación no remuneracional de colación, asignación no remuneracional de movilización, fecha/día de pago de remuneraciones y datos bancarios para transferencia.
         d) Previsión, salud y duración: Institución previsional (AFP), institución de salud (FONASA / ISAPRE), modalidad de duración (Indefinida, Plazo Fijo o Faena) y fecha de inicio de labores.
    2. **Previsualizador en Vivo (40%):**
       - Renderizado dogmático con las 12 cláusulas contractuales completas con variables dinámicas resaltadas en vivo:
         - PRIMERO: Naturaleza de los servicios y cargo (funciones principales).
         - SEGUNDO: Lugar de prestación de servicios y aplicación del ius variandi (Art. 12 Código del Trabajo).
         - TERCERO: Jornada de trabajo (40 horas ordinarias con colación y control DT vs. Exención Art. 22 inc. 2°).
         - CUARTO: Remuneración y beneficios (sueldo base, gratificación Art. 50/47, colación, movilización y cuenta bancaria).
         - QUINTO: Descuentos legales y previsionales (AFP, FONASA/ISAPRE, Seguro de Cesantía Ley 19.728 y Art. 58).
         - SEXTO: Duración del contrato (Indefinida, Plazo Fijo o Faena).
         - SÉPTIMO: Obligaciones y prohibiciones (lealtad, reglamento interno, EPP).
         - OCTAVO: Confidencialidad, propiedad intelectual/industrial (Leyes 17.336 y 19.039) y protección de datos personales.
         - NOVENO: Prevención, acoso y convivencia laboral (tolerancia cero).
         - DÉCIMO: Terminación del contrato (Artículos 159, 160 y 161).
         - DÉCIMO PRIMERO: Domicilio y jurisdicción (Tribunales del Trabajo competentes).
         - DÉCIMO SEGUNDO: Registro Electrónico Laboral (REL en portal Mi DT / Ley 21.327) y validez probatoria de firma electrónica (Ley 19.799).
    3. **Motor de Generación PDF y Bóveda Cifrada R2 (`src/Services/DocumentPdfService.php`):**
       - Emisión estructurada de las 12 cláusulas completas para el PDF oficial con código QR y Hash SHA-256.
    4. **Cache-Busting:**
       - Actualizado a `?v=20261106_7` para forzar refresco inmediato en navegadores.
- **Pruebas Validadas:**
  - Verificación remota PHP Lint en Hostinger: `No syntax errors detected in domains/sinnotario.cl/public_html/redactar.php` y `No syntax errors detected in domains/sinnotario.cl/public_html/src/Services/DocumentPdfService.php`.
  - Smoke test HTTP en producción: `curl -I "https://sinnotario.cl/redactar.php?slug=contrato-individual-trabajo"` -> `HTTP/2 200 OK`.
- **Estado Final:** ✅ Éxito Total / 100% Operativo en Producción y sincronizado en Git.


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

---

### Entrada: 2026-11-06 10:18:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0024`
- **Resumen de la Ejecución:**
  - **Términos y Condiciones (`terminos-condiciones.php`):** Elaboración y publicación del marco contractual y de adhesión digital conforme a la Ley N° 19.496 de Protección a los Consumidores y Ley N° 19.799. Delimita expresamente la exclusión de trámites solemnes reservados a Notaría, la validez probatoria de la firma electrónica, deberes del usuario, pasarelas de pago y jurisdicción en Santiago de Chile.
  - **Política de Privacidad (`politica-privacidad.php`):** Marco regulatorio estricto bajo la Ley N° 19.628 de Protección de la Vida Privada. Establece el compromiso de no comercialización de datos, finalidades de tratamiento para emisión de contratos y firma, cifrado de archivos en reposo en Cloudflare R2 (AES-256 / TLS 1.3), y mecanismo formal para el ejercicio de derechos ARCO (Acceso, Rectificación, Cancelación y Oposición) vía `privacidad@sinnotario.cl`.
  - **Vinculación en Frontend (`index.php`):** Enlace de ambos documentos al pie de página inferior.
  - **Pruebas Validadas:**
    - Carga directa con HTTP/2 200 OK en `https://sinnotario.cl/terminos-condiciones.php` y `https://sinnotario.cl/politica-privacidad.php`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 10:55:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0025`
- **Resumen de la Ejecución:**
  - **Diagnóstico y Corrección de Condición de Carrera en Wizard (`redactar.php`):**
    1. Se diagnosticó que al cargar `<script src="assets/js/app.js"></script>` al final del `<body>`, en navegadores modernos el evento `DOMContentLoaded` ya había disparado (`document.readyState === 'interactive' | 'complete'`), lo que impedía que los listeners de formateo de RUT, actualización del preview y botones de navegación inicializaran.
    2. Se implementó la rutina resiliente `initUniversalWizard()`, la cual valida si `document.readyState === 'loading'` para suscribirse al evento o se autoejecuta de inmediato en caso afirmativo.
    3. Se expuso `window.RutHelper` y `window.escapeHtml` de forma global en `app.js` y se añadió una función local `escapeHtml()` de resguardo en `redactar.php` para prevenir cualquier excepción `ReferenceError` que bloquee el renderizado dinámico de la cláusula central (`#pv_dynamic_body_content`).
    4. En `nextUniversalStep()`, se incorporó autoformateo defensivo previo a la validación para admitir tanto RUTs sin puntos ni guión (`109930318`) como formateados (`10.993.031-8`).
    5. Se extendió el switch del Wizard y el previsualizador para cubrir explícitamente el Contrato de Arriendo Residencial Ley N° 21.461 (`contrato-arriendo-ley-21461`), Promesa de Compraventa (`promesa-compraventa-inmueble`) y la totalidad de los 13 trámites del catálogo.
    6. Se inyectó versión de cache-busting `?v=20261106_4` tanto en `app.js` como en `style.css` para invalidar cachés agresivas en el servidor y navegadores.
  - **Pruebas Validadas:**
    - Verificación sintáctica remota (`php -l redactar.php` -> 0 errores).
    - Carga directa con HTTP/2 200 OK en `https://sinnotario.cl/redactar.php?slug=promesa-compraventa-inmueble` y `https://sinnotario.cl/redactar.php?slug=contrato-arriendo-ley-21461`.
    - Validación de flujo dinámico en consola JS simulada sin excepciones.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 11:00:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0026`
- **Resumen de la Ejecución:**
  - **Reestructuración Modular de la Consola de Administración (`admin.php`):**
    1. Se implementó una arquitectura de navegación multipestaña interactiva (SPA) que permite alternar instantáneamente entre todas las opciones del menú lateral sin recargar la pantalla:
       - **Dashboard General (`#dashboard`):** KPIs consolidados de emisión, catálogo de plantillas, estado de Cloudflare R2 y WhatsApp Gateway, junto con tabla resumen de trámites recientes.
       - **Monitor y Auditoría de Contratos (`#documentos`):** Trazabilidad exhaustiva de todos los documentos en base de datos con desglose de firmantes y roles (RUT, teléfono, email, estado de firma), cálculo de montos y visor de hash criptográfico SHA-256 inalterable, incorporando buscador interactivo en vivo.
       - **Instancias WhatsApp (`#whatsapp`):** Panel de control de la pasarela Evolution API v2 (`keystone-general` en `5.183.9.148:8080`), parámetros de notificación de enlaces de firma y pruebas de conexión.
       - **Bóveda Cloudflare R2 (`#vault`):** Monitor de objetos custodiados en `sinnotario-vault-prod`, claves de objeto, hash forense y enlace de validación pública.
       - **Telemetría UX (`#ux`):** Embudo de conversión paso a paso del Wizard, trámites más demandados y registro del detector negativo de consultas notariales exclusivas.
    2. Se enlazó la reactividad al historial y hash de la URL (`#dashboard`, `#documentos`, `#whatsapp`, `#vault`, `#ux`) para que la recarga de página o enlaces directos abran exactamente la pestaña deseada.
  - **Pruebas Validadas:**
    - Sintaxis remota verificada con `php -l admin.php` arrojando cero errores.
    - Carga directa con HTTP/2 200 OK en `https://sinnotario.cl/admin.php`.
    - Conmutación fluida entre pestañas validada.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 12:45:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0027`
- **Resumen de la Ejecución:**
  - **Robustecimiento Profesional de Plantillas Legales y Generador PDF:**
    1. Se contrastaron los modelos de referencia de Keystone Advisors (KeyHub) y normativa civil/laboral chilena (Dirección del Trabajo, Ley 21.561 de 40 Horas, Ley 21.461 Devuélveme Mi Casa, Ley 20.227 de Salvoconducto y Ley 18.010 de Operaciones de Crédito) para elevar el estándar técnico y la solidez probatoria de las 13 plantillas del catálogo.
    2. En `redactar.php`: Se reemplazaron formularios genéricos por interfaces ricas con variables legales especializadas:
       - **Contrato Individual de Trabajo:** Jornada semanal ajustada a la Ley 40 Horas, gratificación legal (Art. 47 y 50), tipo de contrato y pacto de teletrabajo/lugar de funciones.
       - **Reconocimiento de Deuda y Convenio de Pago:** Detalle de cuotas, causa de la obligación, Cláusula de Aceleración ipso facto y estipulación de Pagaré con interés máximo convencional.
       - **Acta de Entrega e Inventario:** Número de llaves, lecturas de medidores de luz y agua potable, checklist de pintura e inventario de bienes empotrados.
       - **Autorización de Domicilio Tributario SII:** Comodato gratuito según Art. 2174 del Código Civil con inclusión del Rol de Avalúo Fiscal.
       - **Poder Especial Privado:** Facultades administrativas tasadas, vigencia definida y cláusula de delegación/sustitución.
       - **Declaración Jurada de Ingresos:** Especificación de rubro independiente y apercibimiento penal por falso testimonio (Art. 210 CP).
    3. En `DocumentPdfService.php`: Se incorporó la redacción dogmática formal de cláusulas estructuradas para cada instrumento al momento de emitir y estampar el PDF criptográfico con QR en Cloudflare R2.
    4. Se actualizó la versión de cache-busting a `?v=20261106_5`.
  - **Pruebas Validadas:**
    - Verificación sintáctica remota (`php -l redactar.php` y `php -l DocumentPdfService.php` -> 0 errores).
    - Carga directa con HTTP/2 200 OK en wizards de `contrato-individual-trabajo`, `reconocimiento-deuda-pagare-privado` y `autorizacion-domicilio-tributario-sii`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Entrada: 2026-11-06 14:30:00 UTC-3
- **Cliente / Proyecto:** Keystone Advisors / SinNotario.cl
- **Ambiente Destino:** Producción (`https://sinnotario.cl`)
- **Identificador de Solución:** `SOL-0028`
- **Resumen de la Ejecución:**
  - **Rediseño Exhaustivo de Promesa de Compraventa bajo Estándar Notarial Chileno:**
    1. Se analizó el documento notarial de referencia de 6 páginas provisto por el usuario (`corredordepropiedades.cl/wp-content/uploads/2020/09/PROMESA-DE-COMPRAVENTA.pdf`).
    2. Se contrastó contra el formulario y previsualizador previo (el cual solo contenía dos cláusulas breves) para dotarlo de la profundidad y rigor dogmático requerido por el Artículo 1554 del Código Civil.
    3. **Formulario Enriquecido (Pasos 1, 2 y 3):**
       - **Comparecencia Notarial Completa:** Incorporación de nacionalidad, estado civil y profesión u oficio tanto para la Parte Promitente Vendedora como para la Parte Promitente Compradora.
       - **Títulos y Singularización Registral:** Fojas, Número, Año y Conservador de Bienes Raíces respectivo, más Rol de Avalúo Fiscal del SII.
       - **Condiciones Financieras y Operativas:** Desglose de arras penitenciales (Art. 1803 CC), forma de pago del saldo (contado con fondos propios vs crédito hipotecario), fecha límite perentoria, oportunidad de entrega material (contra inscripción CBR o firma de escritura), distribución de gastos notariales/CBR y honorarios de corretaje.
    4. **Previsualizador en Vivo y Generador (9 Cláusulas Notariales Formales):**
       - CLÁUSULA PRIMERA: Individualización del Inmueble y Títulos de Dominio.
       - CLÁUSULA SEGUNDA: Promesa Recíproca de Compraventa (Art. 1554 CC).
       - CLÁUSULA TERCERA: Estado de Conservación ad corpus, Libre de Gravámenes y Saneamiento de Evicción.
       - CLÁUSULA CUARTA: Precio Definitivo, Arras y Forma de Pago con Instrucciones Notariales.
       - CLÁUSULA QUINTA: Plazo, Condiciones Suspensivas y Mora Automática (Art. 1552 CC).
       - CLÁUSULA SEXTA: Oportunidad de Entrega Material.
       - CLÁUSULA SÉPTIMA: Cláusula Penal Tasada y Perjuicios Convencionales por Desistimiento.
       - CLÁUSULA OCTAVA: Gastos de Escrituración e Inscripciones en el CBR.
       - CLÁUSULA NOVENA: Honorarios de Intermediación y Corretaje de Propiedades.
    5. Inyección de cache-busting `?v=20261106_6` en scripts y estilos.
  - **Pruebas Validadas:**
    - Verificación sintáctica remota (`php -l redactar.php` -> 0 errores).
    - Carga directa con HTTP/2 200 OK en `https://sinnotario.cl/redactar.php?slug=promesa-compraventa-inmueble`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.

---

### Registro: 2026-11-06 20:46 UTC (17:46 CLT)
- **Cliente / Proyecto:** Keystone Advisors / SinNotario (`sinnotario.cl`)
- **Ambiente Destino:** Producción (`156.67.72.209:65002`)
- **Identificador de la Solución:** `SOL-0038`
- **Párrafo Resumen y Resultado de la Ejecución:**
  Se promovió el Selector de Modalidad / Variante de Plantilla al **Paso 1 («Titular / Primer Compareciente»)** dentro del Wizard Universal 60/40 en `redactar.php`, permitiendo al usuario definir de inmediato el enfoque dogmático de su documento antes de cargar la individualización de las partes. Esta mejora aplica a los 10 tipos de contratos y declaraciones con opciones múltiples: Contrato de Prestación de Servicios Profesionales (Opciones A, B, C), Contrato Individual de Trabajo (Jornada Ordinaria 40h, Art. 22 inc. 2° Exenta, y Teletrabajo/Híbrido Ley 21.220 con desconexión y asignación), Finiquito Laboral (3 opciones), Reconocimiento de Deuda (3 opciones), Acta de Entrega e Inventario (3 opciones), Autorización Domicilio SII (3 opciones), Salvoconducto Mudanza (3 opciones), Poder Especial (3 opciones), Declaración de Residencia (3 opciones) y Declaración de Ingresos (3 opciones). Se eliminaron selectores duplicados en el Paso 3 para evitar colisiones de ID, manteniendo los campos de detalle condicionales en el Paso 3 y sincronizando instantáneamente las cláusulas en la vista previa 60/40 y en el motor PDF `DocumentPdfService.php`.
- **Pruebas Validadas:**
  - Verificación sintáctica remota (`php -l` en `redactar.php` y `DocumentPdfService.php`) retornando 0 errores de sintaxis.
  - Smoke tests HTTP/2 200 OK en `https://sinnotario.cl/redactar.php?doc=prestacion-servicios-honorarios` y `https://sinnotario.cl/redactar.php?doc=contrato-individual-trabajo`.
  - Cache-busting actualizado a `?v=20261106_16`.
- **Estado Final:** ✅ Éxito Total / Desplegado en Producción y sincronizado en Git.
