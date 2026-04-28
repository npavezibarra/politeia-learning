# Fase 0 — Inventario y guardrails (Bookshelf → Learning / Reading Planner)

Fecha: 2026-04-26

Objetivo de esta fase:
- Tener un inventario **estático** (por código) de rutas, handlers y flujos de invites actuales.
- Identificar colisiones potenciales al portar `reading-planner` como módulo de `politeia-learning`.
- Agregar logging mínimo (solo en `WP_DEBUG`) para medir create/accept sin cambiar comportamiento.

---

## 1) Rutas REST existentes (por plugin)

### 1.1 `politeia-learning` (`politeia/v1`)

Fuente: `.../includes/class-rest-partnerships.php`, `.../modules/payments-subscriptions/...`

- `GET  /wp-json/politeia/v1/friends/search?q=...`
- `POST /wp-json/politeia/v1/partnerships/add`
- `POST /wp-json/politeia/v1/partnerships/invite`
- `POST /wp-json/politeia/v1/partnerships/revoke`
- `POST /wp-json/politeia/v1/subscriptions/subscribe`
- `GET  /wp-json/politeia/v1/subscriptions/me`
- `POST /wp-json/politeia/v1/subscriptions/cancel`
- `GET  /wp-json/politeia/v1/subscriptions/tiers`
- `POST /wp-json/politeia/v1/subscriptions/tiers`
- `POST /wp-json/politeia/v1/mercadopago/webhook`

Notas:
- El REST de partnerships en Learning actualmente **solo soporta** `object_type=course`.
- La aceptación de invitación de course partner **no** es un endpoint REST: se maneja por ruta web `/accept-invite?token=...` (ver Handlers).

### 1.2 `politeia-learning` (`learni/v1`)

Fuente: `.../modules/learni/includes/Rest/Routes.php`

- Varias rutas bajo `/wp-json/learni/v1/...` (binomial, cross-eval, attempts, restart, etc.).
- No colisionan con `politeia/v1`, pero sí consumen partnerships (course partner) para lógica de partner/cross-eval.

### 1.3 `politeia-bookshelf` (`politeia/v1`)

#### Reading Planner
Fuente: `.../modules/reading-planner/includes/class-rest.php`

- `POST /wp-json/politeia/v1/reading-plan`
- `GET  /wp-json/politeia/v1/reading-plan/session-recorder`
- `POST /wp-json/politeia/v1/reading-plan/book`
- `GET  /wp-json/politeia/v1/reading-plan/(?P<plan_id>\\d+)`
- `POST /wp-json/politeia/v1/reading-plan/(?P<plan_id>\\d+)/session`
- `PUT  /wp-json/politeia/v1/reading-plan/(?P<plan_id>\\d+)/session/(?P<date>[\\d-]+)`
- `DELETE /wp-json/politeia/v1/reading-plan/(?P<plan_id>\\d+)/session/(?P<date>[\\d-]+)`
- `POST /wp-json/politeia/v1/reading-plan/(?P<plan_id>\\d+)/participants/invite`
- `GET  /wp-json/politeia/v1/reading-plan/invites/respond/(?P<token>[a-fA-F0-9]{64})`

#### Reading (otros)
Fuentes: `.../modules/reading/includes/class-rest.php`, `.../modules/reading/modules/post-reading/class-post-reading-rest.php`

- `POST /wp-json/politeia/v1/user-books/(?P<id>\\d+)`
- `POST /wp-json/politeia/v1/post-reading/toggle`
- `POST /wp-json/politeia/v1/post-reading/start`
- `POST /wp-json/politeia/v1/post-reading/finish`
- `GET  /wp-json/politeia/v1/post-reading/status?post_id=...`

---

## 2) Colisiones potenciales al portar Reading Planner a Learning

Si `politeia-learning` registra el módulo `reading-planner`, hay riesgo de doble registro (si Bookshelf sigue activo) en:
- `/wp-json/politeia/v1/reading-plan...`
- `/wp-json/politeia/v1/reading-plan/invites/respond/...`

Mitigación (Fase 2):
- Hacer que Bookshelf sea shim y/o que el módulo en Learning no registre nada si Bookshelf está activo.

---

## 3) Flujos de invites actuales (y “puertas” de compatibilidad)

### 3.1 Course partner (Learning)

Creación:
- REST: `POST /politeia/v1/partnerships/invite`
  - `PL_Rest_Partnerships::invite_partner()`
  - llama `PL_Partnership_Manager::create_invite('course', courseId, email, 'partner')`

Persistencia (según soporte de columnas):
- Preferente: `wp_politeia_user_object_partnerships` con `status=pending` + `invitation_token_hash`.
- Fallback legacy: `wp_politeia_plan_participant_invites` (reutilizada) con `token_hash`.

Aceptación por token (puerta web):
- URL: `/accept-invite?token=<raw>`
  - `PL_Partnership_Handlers::handle_invite_accept()` → `PL_Partnership_Manager::accept_invite_for_user()`

Aceptación/Rechazo por “requests” (puerta admin-post):
- `admin_post pl_course_partner_invite_respond`
  - `PL_Partnership_Handlers::handle_course_partner_invite_respond()`

Side effects:
- Si `object_type=course` y `role=partner`: auto-enroll en Learni (`wp_learni_enrollments`) con source/provider de partner_invite.

### 3.2 Reading plan participant (Bookshelf)

Creación:
- REST: `POST /politeia/v1/reading-plan/{plan_id}/participants/invite`
  - `Politeia\\ReadingPlanner\\Rest::invite_participant()` → `create_invite('reading_plan', planId, email, 'observer')`
  - Persiste en `wp_politeia_plan_participant_invites`.

Aceptación/Rechazo:
- REST: `GET /politeia/v1/reading-plan/invites/respond/{token}?action=accept|decline`
  - `respond_to_invite()`
  - Al aceptar: `upsert_participant()` en `wp_politeia_plan_participants`
  - Además: dual-write best-effort a `wp_politeia_user_object_partnerships` (`reading_plan`, role `observer`).

Lectura de permisos:
- Prefiere `wp_politeia_user_object_partnerships` si hay filas para el plan (autoridad por presencia).
- Si no hay filas, fallback a `wp_politeia_plan_participants`.

---

## 4) Logging mínimo agregado (solo `WP_DEBUG`)

### Learning
- `PL_Partnership_Utils::debug_log()`:
  - `.../includes/Partnerships/class-partnership-utils.php`
- Eventos:
  - `invite_created` (source `partnerships|legacy`)
  - `invite_accept_attempt`
  - `invite_accept_failed` (razón: not_found, expired, email_mismatch)
  - `invite_accepted` (source `partnerships|legacy`)

Notas:
- No se loggea email ni token raw; se loggea `email_sha1` y `token_tail`.

### Bookshelf (Reading Planner)
- Logs en:
  - `.../modules/reading-planner/includes/class-rest.php`
- Eventos:
  - `invite_created`
  - `invite_respond_attempt`
  - `invite_declined`
  - `invite_accepted`

---

## 5) Checklist “no romper” (compat)

- Links existentes:
  - `/accept-invite?token=...` debe seguir funcionando.
  - `/wp-json/politeia/v1/reading-plan/invites/respond/{token}` debe seguir funcionando.
- Tokens existentes en:
  - `wp_politeia_user_object_partnerships.invitation_token_hash`
  - `wp_politeia_plan_participant_invites.token_hash`
  deben poder aceptarse en el futuro aunque se centralice la lógica.

---

## 6) Cómo habilitar el módulo portado (Fase 1)

El port de Reading Planner vive en:
- `politeia-learning/modules/reading-planner/`

Por defecto **no hace nada**. Para habilitarlo:
- Definir `PL_READING_PLANNER_MODULE_ENABLED` como `true` (por ejemplo en `wp-config.php`).

Guardrail:
- Si `politeia-bookshelf` ya cargó Reading Planner (detectado por `POLITEIA_READING_PLAN_PATH` o `\\Politeia\\ReadingPlanner\\Rest`),
  el bootstrap del módulo en Learning **no registra rutas** para evitar colisiones.
