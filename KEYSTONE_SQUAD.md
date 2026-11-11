# 🤖 ESCUADRÓN DE AGENTES IA & GUÍA DE INVOCACIÓN (SIN NOTARIO)
> **Dominio Gateway:** `https://gateway.keystoneadvisors.cl`  
> **Proyecto:** `Sin Notario (SN)` | **ID:** `prj_sn_d342` | **Tecnología:** `PHP / Web / SQLite / Webpay`

Bienvenido al entorno de desarrollo asistido por IA de **Keystone Advisors**. Este proyecto cuenta con un escuadrón de agentes especializados que colaboran contigo respetando las reglas de gobernanza, pruebas y calidad.

---

## ⚡ ¿Cómo invocar a los Agentes en tu IDE?

Puedes invocar a cualquier especialista de 2 formas muy sencillas en la ventana de Chat de tu IDE:

1. **Mencionándolo con `@`:** Escribe `@` seguido del rol del agente (ej: `@dev-php`, `@qa-smoke-tester`).
2. **Escribiendo el comando de ayuda:** Escribe **`/squad`** o *"¿qué agentes tengo?"* en cualquier momento para ver esta cartelera.

---

## 📋 Catálogo de Especialistas Activos para este Proyecto

| Especialista | Rol Operativo | Ejemplo de Instrucción en el Chat |
| :--- | :--- | :--- |
| **`@dev-php`** | Backend PHP, APIs REST, PDO preparado y sintaxis `php -l` | *"@dev-php crea un endpoint en /api/ para consultar el estado del certificado PDF"* |
| **`@dev-frontend`** | UI/UX responsiva, maquetación HTML/CSS y blindaje anti-autofill | *"@dev-frontend maqueta el nuevo modal de firma con carga asíncrona"* |
| **`@product-architect`** | Historias de usuario, desglose técnico de pedidos y DoD | *"@product-architect desglosa este requerimiento de firma masiva en historias"* |
| **`@db-migration-architect`** | Modelado SQL, migraciones idempotentes y scripts de rollback | *"@db-migration-architect genera la migración para agregar la columna fecha_firma"* |
| **`@integrations-security`** | Pasarelas de pago (Webpay/MercadoPago), webhooks y HMAC | *"@integrations-security valida la firma de retorno del webhook de pago"* |
| **`@qa-code-reviewer`** | Revisión de código pre-commit, Quality Gates y buenas prácticas | *"@qa-code-reviewer audita los cambios que acabo de hacer en login.php"* |
| **`@qa-smoke-tester`** | Pruebas de vida HTTP 200 post-deploy y detección de caídas 500 | *"@qa-smoke-tester ejecuta un smoke test sobre https://qa.sinnotario.cl"* |
| **`@qa-test-data-generator`** | Generación de fixtures, RUTs válidos y casos borde (Happy/Unhappy) | *"@qa-test-data-generator sintetiza 5 documentos de prueba con RUTs chilenos"* |
| **`@qa-visual-e2e`** | Pruebas visuales completas de flujos de usuario y capturas | *"@qa-visual-e2e planifica el test e2e para el flujo de carga y firma de PDF"* |
| **`@secops-guardian`** | Auditoría SAST OWASP Top 10 y prevención de fugas de secretos | *"@secops-guardian analiza si hay variables de entorno o llaves expuestas"* |
| **`@correlative-guardian`** | Custodio de correlativos atómicos `SOL-XXXX` | *"@correlative-guardian asígname el siguiente número correlativo para este cambio"* |
| **`@release-manager`** | Gatekeeper de pases: Git branches, PRs y manifiestos de entrega | *"@release-manager prepara el Pull Request de develop hacia qa"* |
| **`@doc-tech-writer`** | Documentación viva de arquitectura, OpenAPI y Release Notes | *"@doc-tech-writer documenta el endpoint de verificación de documentos"* |

> ℹ️ *Nota de Gobernanza:* Los agentes de Salesforce (`@dev-salesforce` y `@salesforce-integrations`) están automáticamente desactivados por el Kill-Switch de KeyHub (**SOL-0198**) por no corresponder a la pila tecnológica de este proyecto.

---

## 🛡️ Reglas de Oro Obligatorias (Quality Gates)

1. **Correlativo Atómico:** Todo commit y PR debe iniciar con su correlativo asignado (ej: `feat(SOL-0201): ...`).
2. **PHP Lint:** Antes de subir cualquier cambio, ejecuta siempre `php -l <archivo.php>`.
3. **Flujo de Ramas:**
   - Codifica en ramas creadas desde `develop` (ej: `feat/SOL-XXXX-nombre`).
   - El código se integra a `develop` y se promueve a `qa` mediante Pull Request.
   - **PROHIBIDO el push directo a `main` (Producción).** Producción solo se actualiza vía PR aprobado desde `qa`.
