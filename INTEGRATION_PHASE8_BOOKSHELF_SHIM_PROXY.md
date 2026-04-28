# Fase 8 — Adapters/Proxy de endpoints legacy (Bookshelf → Learning)

Fecha: 2026-04-26

Objetivo:
- `politeia-bookshelf` deja de contener Reading Planner, pero sigue existiendo código legacy que llama a:
  - shortcode `politeia_reading_plan`
  - clases `\Politeia\ReadingPlanner\*` (por ejemplo `PlanSessionDeriver`)
  - rutas REST bajo `politeia/v1/reading-plan/*`
- La solución de bajo riesgo es un **adapter/proxy** en Bookshelf que “asegura” que el módulo portado en `politeia-learning` esté booteado **lo suficientemente temprano** (antes de `init`/`rest_api_init`) para que sus propios hooks registren shortcodes y rutas.

---

## Implementación

Archivo shim:
- `app/public/wp-content/plugins/politeia-bookshelf/includes/learning-reading-planner-proxy.php`

Se ejecuta en `plugins_loaded` con prioridad `1` y:
- Detecta si `politeia-learning` está activo (`PL_PATH` definido).
- Si Reading Planner ya está cargado (`POLITEIA_READING_PLAN_PATH` o `\Politeia\ReadingPlanner\Rest`), no hace nada.
- Si el toggle “normal” está activo (`PL_READING_PLANNER_MODULE_ENABLED === true`), no hace nada (Learning ya se encarga).
- Si el toggle está apagado (misconfiguración), **bootea directamente** el módulo portado de Learning:
  - incluye `PL_PATH . 'modules/reading-planner/bookshelf-init.php'`
  - llama `\Politeia\ReadingPlanner\Init::register()`

Resultado:
- No se duplican handlers: el código “real” sigue viviendo en `politeia-learning/modules/reading-planner`.
- Se mantiene compatibilidad con templates legacy en Bookshelf que siguen usando el shortcode/clases.

---

## Notas de seguridad

- Este proxy es “best-effort” y solo actúa si Learning está activo.
- No cambia esquemas de BD.
- Una vez que el flag `PL_READING_PLANNER_MODULE_ENABLED` esté presente en todos los ambientes (y validado), este proxy se puede remover.

