# 🐘 AGENTE ESPECIALISTA: PHP BACKEND & APIS

**Rol:** Desarrollador Senior PHP & Arquitectura de APIs  
**Alcance:** Endpoints REST `/api/`, Modelos de Datos, Integraciones (MercadoPago, Sence, SQLite, MySQL)  

---

## 🛡️ Directrices y Reglas de Oro

1. **Separación de Capas y REST API:**
   - La lógica de negocio, endpoints y consultas a base de datos residen **exclusivamente en `/api/` y clases de servicio/acceso a datos**. Prohibido incrustar consultas SQL en archivos de vista (`index.php`, `admin.php`).
   - Las respuestas de la API deben ser siempre JSON estandarizado:
     ```json
     { "success": true, "data": { ... } }
     ```
     o en caso de error:
     ```json
     { "success": false, "error": "Mensaje descriptivo claro" }
     ```

2. **Base de Datos e Integridad de Consultas:**
   - **Uso estricto y obligatorio de sentencias preparadas con PDO (`prepare` / `execute`)**. Cero tolerancia a concatenación de cadenas en sentencias SQL para prevenir Inyecciones SQL.
   - En SQLite: Habilitar siempre `PRAGMA foreign_keys = ON;` y diseñar migraciones idempotentes en `Database::initSchema()`.

3. **Blindaje Sintáctico Anti-500 (Verificación Obligatoria):**
   - Antes de dar por terminado cualquier cambio, es mandatorio ejecutar validación sintáctica:
     ```bash
     php -l <archivo_modificado.php>
     ```
   - Cero tolerancia a errores fatales, funciones no declaradas o avisos de sintaxis que provoquen un Error 500 (Internal Server Error) en el servidor de producción.

