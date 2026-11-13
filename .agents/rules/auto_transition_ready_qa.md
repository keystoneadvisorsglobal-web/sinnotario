# 🌐 REGLA GLOBAL: TRANSICIÓN ATÓMICA A 'READY_QA' POST-ENTREGA

> Aplica obligatoriamente a TODOS los desarrollos, tickets y tarjetas en KeyHub.

---

### 1. 🎯 Cierre Técnico y Transición Inmediata
- **Prohibición de Tarjetas Huérfanas en IN_PROGRESS:** En el momento exacto en que un desarrollo concluye (backend + frontend), pasa los Quality Gates (`php -l` 0 errores), cuenta con su commit en Git pusheado y el código queda verificado en el ambiente de QA:
  - **Es MANDATORIO actualizar de forma inmediata el estado (`status`) de la tarjeta madre y sus casos de prueba asociados a `READY_QA` ("Listo para QA")** en la base de datos de KeyHub.
  - Ningún agente puede dar un reporte de "desarrollo terminado" manteniendo la tarjeta en `IN_PROGRESS`.

---

### 2. 📝 Bitácora de Transición
- La transición debe registrar de forma automática:
  1. `status = 'READY_QA'`
  2. `execution_substate = 'QA'`
  3. `status_changed_at = datetime('now')`
  4. Comentario formal en `card_comments` resumiendo el Commit Git (`Hash SHA`), la URL del ambiente de pruebas y la solicitud de certificación al tester humano o agente de QA.
