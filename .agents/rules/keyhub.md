---
trigger: always_on
---

# 🚀 REGLAS DE WORKSPACE: KEYHUB (KEYSTONE ADVISORS)

> Aplica cuando se trabaje en proyectos, código o archivos bajo `Keystone Advisors/KeyHub/`.

### 1. 🔢 Ciclo Estricto de Soluciones y Correlativos (`SOL-XXXX`)
- **Consulta Previa Obligatoria**: Antes de iniciar o nombrar cualquier cambio, consultar `CONTROL_CORRELATIVOS.md` para tomar el correlativo consecutivo inmediato.
- **Registro Inmediato**: Asentar cada solución en la tabla de `CONTROL_CORRELATIVOS.md` indicando fecha, alcance exacto y estado.
- **Bitácora de Producción**: Todo despliegue a producción debe quedar registrado en `BITACORA_PRODUCCION.md` con su fecha, ambiente, `SOL-XXXX` y un párrafo resumen del resultado funcional y pruebas validadas.
- **Verificación Operativa Post-Paso (Smoke Test Anti-500)**: Inmediatamente después de completar un paso a producción, es MANDATORIO verificar que la plataforma se encuentre 100% operativa (inspeccionar respuestas HTTP 200, cargar la vista principal y probar un endpoint clave de la API) para descartar caídas, errores de sintaxis o fallos de base de datos que deriven en Error 500 (Internal Server Error).

### 2. 🏗️ Arquitectura de Software y Separación de Capas (PHP / JS / CSS)
- **Capa Backend (`api/index.php`)**:
  - Toda la lógica de negocio, endpoints REST y acceso a datos residen exclusivamente en `/api/` y `Database.php`. Prohibido incrustar consultas SQL en `index.php` (frontend) o archivos de vista.
  - La respuesta de la API siempre debe ser JSON estándar estructurado: `{"success": true, "data": ...}` o `{"success": false, "error": "Mensaje claro"}` con su código HTTP correspondiente.
- **Capa Frontend (`assets/js/app.js` & `index.php`)**:
  - Toda interacción asíncrona se maneja vía `fetch()` en `app.js` controlando estados de carga (`spinner`/`disabled`) y manejo de excepciones.
  - En formularios y componentes de búsqueda/filtro, respetar los blindajes anti-autofill implementados (ej. `contenteditable role=textbox` o atributos no estándar para evitar que gestores de contraseñas corrompan los inputs).
- **Estilos (`assets/css/style.css`)**:
  - Mantener coherencia con la paleta oscura/clara corporativa, z-index ordenados (evitar colisiones en modales superpuestos) y diseño responsive.

### 3. 🗄️ Base de Datos SQLite y Migraciones Seguras (`database/Database.php`)
- **Migraciones Idempotentes**: Toda modificación al esquema de base de datos debe añadirse en `Database::initSchema()` con sintaxis segura (`CREATE TABLE IF NOT EXISTS`, o validaciones previas antes de `ALTER TABLE ADD COLUMN`) para ejecutarse sin romper bases de datos existentes en caliente.
- **Integridad de Datos y Prepared Statements**:
  - Uso obligatorio y estricto de sentencias preparadas con PDO (`prepare` / `execute` con bind parameters) para el 100% de las consultas con parámetros dinámicos. Prohibida la concatenación de variables en SQL.
  - Habilitar siempre `PRAGMA foreign_keys = ON;` para preservar la integridad referencial.
- **Respaldo Previo**: Ante migraciones de esquema complejas o ingestas masivas de datos, asegurar o generar respaldo de `database/keyhub.sqlite`.

### 4. 📄 Módulos Críticos: Contratos, Horas y Tablero Kanban
- **Módulo de Contratos y Legal**:
  - Cuidar los parámetros de sustitución dinámica (`{{NOMBRE_COMPLETO}}`, `{{RUT_DNI}}`, `{{DIRECCION_LEGAL}}`, `{{REPRESENTANTE_LEGAL}}`, etc.) en las plantillas y adendas.
  - Respetar la integridad de las cláusulas de blindaje: NDA agravado, Cláusula Penal, No Competencia y validez de firma digital multinacional.
- **Horas e Imputaciones**:
  - Garantizar la trazabilidad del usuario, moneda (`USD` vs `CLP`), cálculo de totales líquidos y compatibilidad con vistas divididas (privada vs directores).
- **Tablero Kanban**:
  - Asegurar la persistencia del estado en recarga (`localStorage` y parámetro `?board=`), preservación del scroll post-guardado y trazabilidad en la cadena de custodia/historial de tarjetas.

### 5. 🔍 Verificación Sintáctica y Control de Versiones
- **PHP Lint Obligatorio**: Ejecutar `php -l <archivo.php>` en cada script PHP tocado antes de dar por cerrada la tarea o subir a producción.
- **Git Hygiene**: Los cambios deben quedar ordenados, sin dejar archivos temporales (`.bak`, `.tmp`) ni credenciales expuestas en el repositorio.
