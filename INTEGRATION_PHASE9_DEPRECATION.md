# Fase 9 — Deprecación controlada (Bookshelf shim/proxy)

Fecha: 2026-04-26

Objetivo:
- Mantener compatibilidad mientras se completa el cutover.
- Detectar “misconfiguraciones silenciosas” (cuando Learning está activo pero el flag no, y por eso Bookshelf termina booteando el módulo por proxy).
- Definir un camino claro para **retirar el proxy** sin romper nada.

---

## Estado actual (después de Fase 7/8)

- Reading Planner vive en `politeia-learning/modules/reading-planner`.
- Bookshelf ya no incluye `modules/reading-planner` (código duplicado eliminado).
- Bookshelf incluye un proxy mínimo:
  - `politeia-bookshelf/includes/learning-reading-planner-proxy.php`
  - Solo actúa si:
    - `politeia-learning` está activo (`PL_PATH`)
    - Reading Planner no está cargado aún
    - `PL_READING_PLANNER_MODULE_ENABLED` NO está habilitado
  - En ese caso, hace `require_once` del módulo portado en Learning y ejecuta `\Politeia\ReadingPlanner\Init::register()`.

---

## Logging / telemetría mínima

El proxy escribe a `error_log` **solo con `WP_DEBUG=true`**:
- `[PB][reading-planner-proxy][boot] {"reason":"learning_module_disabled"}`
- `[PB][reading-planner-proxy][bootstrap_missing] ...`

Además dispara un hook para monitoreo externo:
- `do_action('politeia_bookshelf_reading_planner_proxy_booted')`

Esto permite detectar environments donde el proxy todavía se está usando (y por ende el flag no está consistente).

---

## Kill switch

Para apagar el proxy (cuando ya no sea necesario):
```php
define('PB_READING_PLANNER_PROXY_DISABLED', true);
```

Recomendación: activar esto **solo después** de confirmar que todos los entornos tienen:
```php
define('PL_READING_PLANNER_MODULE_ENABLED', true);
```

---

## Checklist para retiro del proxy (futuro)

1) Confirmar en logs que el proxy NO se dispara (no hay eventos `[PB][reading-planner-proxy][boot]`).
2) Confirmar que el shortcode `politeia_reading_plan` funciona en páginas de Bookshelf.
3) Confirmar que `/wp-json/politeia/v1/reading-plan/*` responde (Learning).
4) Activar `PB_READING_PLANNER_PROXY_DISABLED=true` en staging.
5) Si todo OK, remover el archivo proxy y su `require_once` en Bookshelf.

