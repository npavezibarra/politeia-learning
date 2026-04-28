# Payments Subscriptions (Politeia Learning module)

This module implements **creator-defined monthly memberships** and **Mercado Pago recurring billing** with:

- A single monthly tier per creator (`tier_slug = monthly`)
- Mercado Pago subscriptions (preapproval) + optional plan (direct flow)
- Webhook ingestion + async processing
- Internal ledger (`transaction_ledger`) + platform commission/IVA breakdown
- Integration with `PL_Relationships` (`TYPE_SUBSCRIBE`) to unlock subscriber access

> Note: The old standalone plugin `politeia-payments-subscriptions` is a shim/no-op. The active code lives in this module.

## What users do

### 1) Creator sets monthly price

UI: `Center-2 → Perfil → Membresía`

- The creator can only set a **monthly CLP amount**.
- On save, we upsert a single tier in DB using:
  - `Politeia_PPS_Subscription_Engine::upsert_creator_monthly_tier($creator_user_id, $amount_minor, 'CLP')`

Source:
- UI panel: `modules/learni/templates/dashboard/sections/profile/panel-membership.php`
- Handler: `modules/learni/includes/Dashboard/class-creator-dashboard.php` (`admin_post_pl_cc_save_membership_tier`)

### 2) Viewer subscribes to a creator

Public profile shows a **Suscribirme** CTA when:

- Viewer is logged in
- Viewer is not the creator
- Mercado Pago access token is configured
- The creator has a `monthly` tier

The CTA points to an `admin-post.php` action that calls:

- `Politeia_PPS_Subscription_Engine::subscribe($subscriber_user_id, $tier_id)`

Source:
- Profile CTA filter: `modules/payments-subscriptions/includes/class-profile-subscribe.php`
- Profile template uses: `apply_filters('pl_subscribe_checkout_url', ...)`

## Mercado Pago flows

Configured in WP Admin: **Politeia Learning → Pagos**.

### Hosted (redirect)

- Creates a Mercado Pago **preapproval** without an associated plan
- Opens checkout to collect payment method
- Returns `redirect_url` (sandbox or production init point)

### Direct (card token) (not enabled yet)

The Direct/tokenized flow requires a dedicated card-tokenization UI (`card_token_id`) on the public subscribe CTA.
That UI is not implemented yet, so the engine currently **forces Hosted Checkout**.

Source:
- Engine: `modules/payments-subscriptions/includes/class-subscription-engine.php`
- MP client: `modules/payments-subscriptions/includes/class-mercadopago-client.php`

## Data model (DB tables)

Created/updated by `Politeia_PPS_Activator`:

- `wp_politeia_subscription_meta` (tiers)
  - `external_reference` is unique and identifies the tier in MP:
    - Format: `pps:{creator_user_id}:{tier_slug}`
  - For memberships: `tier_slug = monthly`
- `wp_politeia_subscriptions` (subscriptions)
  - One row per MP preapproval (`mp_preapproval_id`)
- `wp_politeia_transaction_ledger` (ledger)
  - One row per payment event (`mp_payment_id`) with fee/tax breakdown
- `wp_politeia_mp_webhook_events` (webhook inbox)
  - Stores raw webhook payloads and marks them processed

## Webhooks

Endpoint:

- `POST /wp-json/politeia/v1/mercadopago/webhook`

Behavior:

1) Verifies signature (if `Webhook Secret` is configured).
2) Stores the raw event in `wp_politeia_mp_webhook_events` (processed=0).
3) Processes immediately only if the filter `politeia_pps_process_webhook_immediately` returns true.
4) Otherwise processes asynchronously via WP-Cron.

### Signature verification (Mercado Pago)

Mercado Pago sends `X-Signature` like:

- `ts=...,v1=...`

Current implementation validates:

- Legacy/dev: `v1 = HMAC_SHA256(secret, raw_body)`
- MP current: `v1 = HMAC_SHA256(secret, "id:{data.id};request-id:{x-request-id};ts:{ts};")`
- Back-compat attempt: `v1 = HMAC_SHA256(secret, "{ts}.{raw_body}")`

Source:
- `modules/payments-subscriptions/includes/class-webhooks.php`

### Processing logic

When processing a stored event:

- **Preapproval events** (`preapproval*` / `subscription*`):
  - Fetches `/preapproval/{id}` from MP
  - Updates `wp_politeia_subscriptions.status` and `current_period_end` (best-effort)
  - Emits `politeia_pps_subscription_status_changed`

- **Payment events** (`payment*`):
  - Fetches `/v1/payments/{id}` from MP
  - Resolves `mp_preapproval_id` from:
    - `payment.preapproval_id`, `subscription_id`, `metadata`, etc.
    - `external_reference` + payer email → local tier/subscription lookup
    - `merchant_order` fallback (when available)
  - Writes a ledger entry (commission + IVA) using `Politeia_PPS_Commission::breakdown()`
  - Renews access (relationship) on `payment.status = approved`

## Relationship (access) integration

We grant/revoke platform access using `PL_Relationships`:

- Grants on:
  - Subscription created and MP status is `authorized|active|approved`
  - Payment webhook status is `approved`
- Revokes on:
  - Subscription status `cancelled/paused/suspended/expired` (webhook/cancel)

This is done by emitting the standard action:

- `do_action('pl_subscription_payment_completed', $subscriber_user_id, $creator_user_id, null, $context)`

Source:
- `modules/payments-subscriptions/includes/class-relationships-bridge.php`
- `includes/class-relationships.php`

## Admin tooling

WP Admin → **Politeia Learning → Pagos**

Section **Webhooks & Ledger** provides:

- Pending webhook count + "Process Now"
- Recent webhook events + "Process/Reprocess"
- Recent ledger entries

## Background jobs (WP-Cron)

- `politeia_pps_process_pending_webhooks`: processes webhook inbox every 5 minutes
- `politeia_pps_reconcile_payments`: daily reconciliation safety net
  - For active subscriptions, searches payments by `external_reference` and backfills missing ledger entries

Source:
- `modules/payments-subscriptions/includes/class-webhooks.php`

## What’s still missing / TODO (production readiness)

1) **Stable public webhook URL**
   - Quick tunnels (`trycloudflare.com`) are not stable and should not be used in production.
   - Production should use a fixed domain + HTTPS.

2) **Real end-to-end LIVE test**
   - Creator creates tier → viewer subscribes → MP confirms → webhooks arrive → ledger entry created → viewer becomes subscribed.

3) **Subscription lifecycle UX**
   - A viewer “Manage subscription” page (cancel, status, next payment date) and a creator dashboard for subscribers/revenue.

4) **Hardening / observability**
   - Better error surfaces in admin (show last error per event, payload viewer, retry/backoff).
   - Alerts/monitoring for webhook failures in production.

5) **Policy confirmation**
   - Decide the canonical access rule:
     - access by `payment.approved` only, or
     - access by preapproval active + period end

## Local testing notes

- Mercado Pago developer “Simular notificación” often uses dummy ids (e.g. `123456`).
  - Webhook will validate and store/process, but MP fetch (`/v1/payments/{id}`) will return 404, so ledger won’t be created.
- To see ledger entries, you need a **real payment id** (by performing a real transaction in TEST or LIVE).

### Sandbox gotchas (TEST)

1) Use the correct TEST credentials
- In **Politeia Learning → Pagos** set:
  - `Mode = Sandbox (TEST)`
  - `Access Token (TEST)` must start with `TEST-...`
  - `Public Key (TEST)` should match the same TEST environment
- A token that starts with `APP_USR-...` is a LIVE/app token and will lead to inconsistent errors in TEST (400 payer/collector mismatch, random 500s).

2) Mercado Pago TEST requires `X-scope: stage`
- The module automatically adds `X-scope: stage` when the access token starts with `TEST-`.
- When testing with curl, you must include it too, otherwise `/users/me` may return:
  - `403` with `PA_UNAUTHORIZED_RESULT_FROM_POLICIES`

Example:
```
curl -i \
  -H "Authorization: Bearer TEST-..." \
  -H "X-scope: stage" \
  https://api.mercadopago.com/users/me
```

3) Hosted checkout + TEST buyers
- Hosted flow is the only supported flow right now (Direct/tokenized is not enabled).
- For sandbox testing, set `Payer Email Override` to a **buyer test user** email (`...@testuser.com`).

4) Sandbox instability on `/preapproval`
- Sometimes Mercado Pago sandbox returns `503 Service Unavailable` (empty body) when creating preapprovals:
  - `POST https://api.mercadopago.com/preapproval`
- When that happens, the only workaround is to retry later. If you must validate end-to-end immediately, test in LIVE (real charge) using LIVE credentials.
