# 🌐 REGLA GLOBAL: ESCUADRÓN MULTIDISCIPLINARIO Y COMPLECIÓN INTEGRAL DE HU

> Aplica obligatoriamente a TODOS los desarrollos, features y tickets (`SOL-XXXX` / `HDU`) en Keystone Advisors.

---

### 1. 🤝 Colaboración Obligatoria entre Especialistas (@dev-php, @dev-frontend, @db-migration)
- **Prohibición de Entregas Fragmentadas:** Ningún especialista del backend (`@dev-php`) puede dar por concluida una tarjeta si la Historia de Usuario especifica cambios en la interfaz de usuario (UI/UX) o en el flujo de interacción del cliente.
- **Invocación Mandatoria del Especialista Frontend:** Cuando una HU implique vistas (`.php` con HTML/JS, `.vue`, `.jsx`, LWC o CSS):
  1. `@dev-php` debe orquestar o implementar la totalidad del ciclo: base de datos, endpoints de API y las vistas interactivas del frontend junto a `@dev-frontend`.
  2. No se autoriza el cierre o pase a QA si la UI no cuenta con los controles visuales, estados de carga y validaciones especificadas en los criterios de aceptación (DoD).

---

### 2. 📋 Verificación Cruzada de Criterios de Aceptación (DoD)
- Antes de emitir cualquier reporte de avance o solicitar el paso a `READY_QA`, el agente líder debe cotejar punto por punto cada Escenario Gherkin de la tarjeta madre:
  - ¿Está la base de datos migrada?
  - ¿Están los adaptadores y servicios backend operativos?
  - ¿Están las vistas frontend implementadas y responsivas para el usuario final?
  - ¿Se ejecutaron los Quality Gates (`php -l`, linter, tests)?
