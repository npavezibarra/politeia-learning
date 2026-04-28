# Fase 1 — Shim + dual-write de invites (Reading Planner)

Fecha: 2026-04-26

Objetivo de esta fase:
- Mantener `politeia-bookshelf` activo (Reading + ChatGPT) pero permitir que **Reading Planner** sea “owned” por `politeia-learning` cuando se habilite un flag.
- Evitar colisiones de rutas REST / shortcodes por doble bootstrap.
- Comenzar a poblar `wp_politeia_user_object_partnerships` también con **invites** de `reading_plan` (best-effort), manteniendo `wp_politeia_plan_participant_invites` como fuente de verdad durante la migración.

---

## 1) Ownership toggle (flag)

En `politeia-learning/modules/reading-planner/bootstrap.php`:
- El módulo solo hace bootstrap si `PL_READING_PLANNER_MODULE_ENABLED === true`.
- Si detecta que Bookshelf ya cargó Reading Planner (`POLITEIA_READING_PLAN_PATH` o `\Politeia\ReadingPlanner\Rest`) no registra nada (guardrail anti-doble bootstrap).

En `wp-config.php`:
```php
define('PL_READING_PLANNER_MODULE_ENABLED', true);
```

---

## 2) Shim en Bookshelf

Archivo: `politeia-bookshelf/politeia-bookshelf.php`

- Se removió el bootstrap “hardcoded” de Reading Planner.
- Se agregó un loader en `plugins_loaded`:
  - Si `PL_READING_PLANNER_MODULE_ENABLED` está activo **y** `politeia-learning` está cargado (`PL_PATH` o `PL_Module_Loader`), Bookshelf **no** carga Reading Planner.
  - En cualquier otro caso, Bookshelf carga Reading Planner como antes.

Activation hook:
- Se agregó `politeia_bookshelf_activate_reading_planner()` para mantener el `dbDelta()`/installer ejecutable aunque el bootstrap haya quedado condicional.

---

## 3) Dual-write de invites a la tabla unificada

Archivos:
- `politeia-learning/modules/reading-planner/includes/class-rest.php`
- `politeia-bookshelf/modules/reading-planner/includes/class-rest.php`

Cambios:
- Al crear invite en `wp_politeia_plan_participant_invites`, se hace mirror best-effort a `wp_politeia_user_object_partnerships` como `status=pending` con `invitation_token_hash` (si la tabla + columnas existen).
  - Importante: **multi-invite safe** → solo se revoca/reemplaza pending para el mismo `(object_type, object_id, invitee_email, role)` (no “single-slot”).
- Al aceptar/declinar/expirar el invite por `/wp-json/politeia/v1/reading-plan/invites/respond/{token}`, se refleja status a la tabla unificada (si existe).

Nota:
- La fuente de verdad del flujo sigue siendo `wp_politeia_plan_participant_invites` + `wp_politeia_plan_participants`.
- La tabla unificada se completa gradualmente para poder mover el “source of truth” en fases posteriores.

---

## 4) Próximos pasos (Fase 2 sugerida)

- Mover la lógica de “invite lifecycle” (create/respond/expire) a un service compartido en `politeia-learning` para evitar duplicación entre módulos.
- Empezar a leer “pending invites” desde `wp_politeia_user_object_partnerships` cuando haya cobertura suficiente (manteniendo fallback).

---

## Update 2026-04-26 — Fase 2 (parcial)

Se creó un helper compartido en Learning para evitar duplicación del “mirror” de invites:
- `politeia-learning/includes/Partnerships/class-partnership-invite-mirror.php`

Y ambos copies de Reading Planner (`learning` y `bookshelf`) ahora llaman a:
- `PL_Partnership_Invite_Mirror::mirror_pending_invite(...)`
- `PL_Partnership_Invite_Mirror::mirror_invite_status(...)`

Además, se unificó la respuesta (accept/decline) de invites de Reading Planner en un único método:
- `PL_Partnership_Manager::respond_to_reading_plan_invite_for_user(...)`

Y `politeia-learning/modules/reading-planner/includes/class-rest.php` ahora usa ese método, evitando inconsistencias entre:
- `wp_politeia_plan_participant_invites`
- `wp_politeia_user_object_partnerships`

Nota: la creación del invite (`invite_participant`) todavía inserta en la tabla legacy y luego hace mirror best-effort.

## Update 2026-04-26 — Fase 3 (create pipeline)

Se unificó también la creación de invites de Reading Planner en:
- `PL_Partnership_Manager::create_reading_plan_invite_for_user(...)`

Con esto, en ambientes donde `politeia-learning` está activo:
- Learning Reading Planner usa el pipeline unificado.
- Bookshelf Reading Planner (si todavía se carga) también lo usa cuando detecta Learning.

Resultado: menos lógica duplicada y menos riesgo de inconsistencias entre legacy/unificada.

## Update 2026-04-26 — Fase 4 (lectura/autoridad de partnerships)

Se agregó un “kill switch” para dejar de depender de `wp_politeia_plan_participants` en lectura/listados cuando la cobertura en `wp_politeia_user_object_partnerships` ya sea suficiente.

Flag:
```php
define('PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE', true);
```

Efectos cuando está activo:
- `get_plan()` usa `wp_politeia_user_object_partnerships` para observers; si no hay relación activa, NO hace fallback a `plan_participants`.
- `my-plans-ver-2` lista planes observados solo vía tabla unificada (si existe); el join a `plan_participants` se omite.
- `create_reading_plan_invite_for_user()` deja de considerar `plan_participants` como autoridad para el “already_participant” (solo mira partnerships).

Por defecto el flag NO está definido (fallback legacy sigue activo).

## Update 2026-04-26 — Fase 5 (backfill para activar autoridad)

Se agregó un backfill para poblar partnerships activos (`reading_plan` / `observer`) desde la tabla legacy:
- `politeia-learning/includes/Partnerships/class-partnership-backfill.php`

WP-CLI:
```bash
wp politeia partnerships:backfill-reading-plans --dry-run
wp politeia partnerships:backfill-reading-plans --batch-size=500
```

Esto permite activar `PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE` con menor riesgo (cubre observers antiguos creados antes de dual-write).

Backfill adicional (invites `pending` que predatean el mirror dual-write):

WP-CLI:
```bash
wp politeia partnerships:backfill-reading-plan-invites --dry-run
wp politeia partnerships:backfill-reading-plan-invites --batch-size=500
```

### Runbook sugerido (cutover seguro)

1) Ejecutar backfills (primero `--dry-run`, luego real):
- `wp politeia partnerships:backfill-reading-plans`
- `wp politeia partnerships:backfill-reading-plan-invites`

2) Activar ownership del módulo en Learning:
```php
define('PL_READING_PLANNER_MODULE_ENABLED', true);
```

3) Validar flujos críticos (crear plan, invitar, aceptar/declinar) usando los endpoints legacy.

4) Activar autoridad de partnerships:
```php
define('PL_READING_PLANNER_PARTNERSHIPS_AUTHORITATIVE', true);
```

5) Confirmar que `politeia-bookshelf` queda como shim (Reading + ChatGPT) sin cargar Reading Planner cuando el flag de Learning está activo.
