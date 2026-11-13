# 🌐 REGLA GLOBAL: CADENA DE CUSTODIA GIT OBLIGATORIA (PRE-DEPLOY & PROMOCIÓN)

> Aplica obligatoriamente a TODOS los agentes, proyectos, repositorios y despliegues en Keystone Advisors.

---

### 1. 🚫 Prohibición Estricta de Despliegues "Al Vuelo" sin Commit y Push Git
- **Trazabilidad de Código Fuente:** Queda terminantemente PROHIBIDO transferir, copiar por SCP, sincronizar por rsync o promover código hacia **QA**, **STAGING** o **PRODUCCIÓN** que no esté previamente respaldado en un **Commit Git formal** y con **Push exitoso hacia el repositorio remoto oficial en GitHub / GitLab**.
- **Auditoría de Integridad:** Un archivo en el servidor que no tenga correspondencia exacta con un commit de Git es considerado código fantasma no auditado. Todo despliegue ejecutado saltándose el control de versiones viola la gobernanza institucional de Keystone.

---

### 2. 🔄 Protocolo Mandatorio del Release Manager (@release-manager)
Antes de transferir o autorizar cualquier promoción de código:
1. **Verificación de Estado Limpio (`git status`):**
   - El árbol de trabajo debe quedar limpio. No pueden quedar cambios sin stagear (`working tree clean`).
2. **Commit Atómico Formal:**
   - Todo cambio debe incluirse en un commit que referencie el correlativo institucional:
     `feat(SOL-XXXX): descripción clara del requerimiento` o `fix(SOL-XXXX): ...`
3. **Push a Ramas Oficiales:**
   - El commit debe ser empujado a la rama de desarrollo (`develop`) o rama de feature.
   - Para despliegues en ambiente de pruebas, la rama correspondiente (ej. `qa`) debe estar sincronizada o actualizada mediante merge/fast-forward con dicho commit.
4. **Declaración del Hash en Cadena de Custodia:**
   - Toda notificación de despliegue a QA o Prod en la tarjeta de KeyHub (`card_comments`) o en `BITACORA_PRODUCCION.md` DEBE incluir obligatoriamente el **Hash SHA del Commit** (ej: `commit f74ad66`) y el repositorio remoto de GitHub asociado.

---

### 3. 🚨 Cláusula de Bloqueo Inmediato
Si un desarrollador humano o agente de IA solicita al `@release-manager` promover a QA o Producción con archivos modificados sin commitear, el `@release-manager` debe:
- **Detenerse de inmediato.**
- Ejecutar o exigir la ejecución del ciclo Git: `git add .`, `git commit -m "feat(SOL-XXXX): ..."` y `git push origin <branch>`.
- Solo tras confirmar el push remoto exitoso, autorizar la sincronización hacia el servidor.
