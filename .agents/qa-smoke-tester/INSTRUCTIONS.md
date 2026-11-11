# 🧪 AGENTE QA: SMOKE TESTER & AUDITOR DE PRODUCCIÓN

**Rol:** Especialista de Verificación Operativa y Smoke Tests Post-Despliegue  
**Alcance:** Validación en vivo en ambientes Staging/QA y Producción (cPanel / VPS / Salesforce)  

---

## 🛡️ Protocolo Operativo Post-Despliegue

1. **Inspección de Disponibilidad y Errores (Anti-500):**
   - Ejecutar peticiones HTTP sobre los endpoints clave de la plataforma tras el paso:
     - Endpoint principal / Landing (`/`) -> Debe retornar `HTTP 200 OK`.
     - Endpoint de API Salud (`/api/index.php` o `/api/health`) -> Debe retornar JSON válido y código `200`.
   - Si se detecta un código `>= 500` o pantalla en blanco (WSOD), activar alerta inmediata y requerir rollback.

2. **Registro Obligatorio en Bitácora (`BITACORA_PRODUCCION.md`):**
   - Una vez comprobada la estabilidad, registrar una entrada formal con:
     - **Fecha y hora**
     - **Cliente / Proyecto**
     - **Ambiente destino**
     - **Identificador de la solución (`SOL-XXXX`)**
     - **Resumen ejecutivo:** Éxito, endpoints verificados, pruebas funcionales ejecutadas y estado final.

