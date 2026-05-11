# Payments Subscriptions (Politeia Learning module)

This module implements **creator-defined monthly memberships** and **Mercado Pago recurring billing** with:

- A single monthly tier per creator (`tier_slug = monthly`)
- Mercado Pago subscriptions (preapproval)
- Webhook ingestion + async processing
- Internal ledger (`transaction_ledger`) + platform commission/IVA breakdown
- Integration with `PL_Relationships` (`TYPE_SUBSCRIBE`) to unlock subscriber access

Notes:
- The settings screen contains **Flow (Chile)** credential fields. Flow gateway code lives in `modules/payments-subscriptions/flow/` and is now wired for subscription enrollment (card registration + subscription create).

> Note: The old standalone plugin `politeia-payments-subscriptions` is a shim/no-op. The active code lives in this module.

## Gateways

- Mercado Pago
- Flow: see `modules/payments-subscriptions/flow/README.md`

## What users do

### 1) Creator sets monthly price

UI: `Center-2 → Perfil → Membresía`

- The creator can only set a **monthly CLP amount**.
- The creator also defines what subscribers can access (MVP): which tabs are visible on the public profile (`/profile/{username}`) via the `pl_policy_subscribe` relationship policy.
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

The CTA points to an `admin-post.php` action that (hosted option) calls:

- `Politeia_PPS_Subscription_Engine::subscribe($subscriber_user_id, $tier_id)`

Source:
- Profile CTA filter: `modules/payments-subscriptions/includes/class-profile-subscribe.php`
- Profile template uses: `apply_filters('pl_subscribe_checkout_url', ...)`

## Mercado Pago flows

Configured in WP Admin: **Politeia Learning → Pagos**.

### Hosted (redirect)

- Creates a Mercado Pago **preapproval** (subscription) **without an associated plan**
- Opens checkout to collect payment method
- Returns `redirect_url` (prefers `sandbox_init_point` in TEST; otherwise `init_point`)
- Uses `success_url` as `back_url` for Mercado Pago. If `success_url`/`cancel_url` are empty, the module auto-creates:
  - `/subscription-success/`
  - `/subscription-cancel/`
  and fills the settings on next admin load.

### Card (tokenized) (no MP account required)

The public **Suscribirme** button opens a modal with two options:

1) **Pagar con tarjeta**: tokenizes the card in the browser using Mercado Pago JS v2 and creates a subscription via REST:
   - `POST /wp-json/politeia/v1/subscriptions/subscribe`
   - sends `card_token_id` (plus optional `payment_method_id` / `issuer_id`) and sets `status=authorized`
   - no redirect required
2) **Usar mi cuenta Mercado Pago**: continues with Hosted checkout (redirect).

Source:
- Modal JS: `modules/payments-subscriptions/assets/js/profile-subscribe-modal.js`
- Tier id propagation: `modules/payments-subscriptions/includes/class-profile-subscribe.php` (`tier_id` query param)

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
  - Price changes: when a creator updates the monthly amount, existing subscribers are scheduled to cancel at period end (best-effort) and must re-subscribe to the new price.
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

## Production checklist (to make it work end-to-end)

1) **Configure credentials**
   - WP Admin → **Politeia Learning → Pagos**
   - Set `Mode` (TEST/LIVE) + matching `Access Token` + `Public Key`.
   - Set `Expected Seller User ID` to the Mercado Pago `collector_id` for the same environment (helps catch token mixups).

2) **Set return URLs**
   - Ensure `success_url` / `cancel_url` are valid public URLs.
   - If left blank, the module auto-creates `/subscription-success/` and `/subscription-cancel/` and stores them in settings.

3) **Register webhook in Mercado Pago**
   - Point MP webhooks to:
     - `POST /wp-json/politeia/v1/mercadopago/webhook`
   - Set `Webhook Secret` in WP settings (recommended) and ensure MP sends `X-Signature` + `X-Request-Id`.

4) **Ensure background processing runs**
   - Webhooks are stored first and processed by WP-Cron (`politeia_pps_process_pending_webhooks` every 5 minutes).
   - In production, prefer a real cron (server-level) hitting `wp-cron.php` to avoid missed runs on low-traffic sites.

5) **Run a real payment test**
   - Creator sets monthly tier → subscriber subscribes (hosted or tokenized) → webhooks arrive → ledger entry created → relationship granted.

## Current status (Mercado Pago) — 2026-05-10

We reached a clear integration checkpoint while attempting to run a **real recurring charge** (monthly CLP 1000) on `https://politeia.cl`.

### What is confirmed working

- The creator’s tier is persisted correctly in `wp_politeia_subscription_meta` (`amount_minor=1000`, `currency=CLP`, `tier_slug=monthly`).
- The “membership included content” policy is persisted correctly in `wp_usermeta` under `pl_policy_subscribe` and is readable via WordPress (`get_user_meta`).
- Mercado Pago card tokenization (Brick / JS v2) can succeed in browser and returns `live_mode=true` when using an `APP_USR` public key.

### The blocker we found

Creating the Mercado Pago subscription (`POST https://api.mercadopago.com/preapproval`) fails with:

- `400 Bad Request`: `Both payer and collector must be real or test users`

We verified that our **configured LIVE access token** (stored in `politeia_pps_settings.mp_access_token_live`) identifies a **test user collector**:

- `GET https://api.mercadopago.com/users/me` returns `"tags":["test_user", ...]`

This explains the error when trying to charge a real card: Mercado Pago rejects mixing a **test collector** with a **real payer / real card**.

### Last Mercado Pago step (not yet re-tested)

To complete a real recurring charge test with Mercado Pago, we still need to:

1) Replace the LIVE credentials with a **real Mercado Pago collector** (not a `test_user`).
2) Update `Expected Seller User ID` to the real collector id.
3) Re-test `preapproval` creation and the full webhook → ledger → relationship flow.

This final step has **not been executed yet** as of 2026-05-10.

## Roadmap: Alternative subscription gateway

Even after resolving the collector/test mismatch, Mercado Pago recurring subscriptions (`preapproval`) have proven operationally fragile for our product requirements and testing workflow.

We will implement an additional payment gateway for subscriptions (monthly memberships) to reduce risk and complexity, while keeping the current data model and access control semantics:

- Keep tiers/subscriptions/ledger tables as the source of truth
- Keep `PL_Relationships::TYPE_SUBSCRIBE` for access control
- Gateways plug into the same lifecycle:
  - create subscription → confirm payment → record ledger → grant relationship → handle renewals/cancellations

Next step: define the new gateway interface + implementation plan (flows, webhooks, and cancellation sync) before writing code.

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
   - A viewer “Manage subscription” page (cancel, status, next payment date) and a creator dashboard for subscribers/revenue (the REST cancel route exists, but there’s no polished UI yet).

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
- In Mercado Pago TEST you often need a **buyer test user** email (`...@testuser.com`).
  - Easiest: set `Payer Email Override` to a buyer test user email in **Politeia Learning → Pagos**.
  - Alternative: use the **Card (tokenized)** flow and enter the payer email inside the Mercado Pago Brick.

4) Sandbox instability on `/preapproval`
- Sometimes Mercado Pago sandbox returns `503 Service Unavailable` (empty body) when creating preapprovals:
  - `POST https://api.mercadopago.com/preapproval`
- When that happens, the only workaround is to retry later. If you must validate end-to-end immediately, test in LIVE (real charge) using LIVE credentials.

5) Hosted + TEST can fail if MP does not provide `sandbox_init_point`
- Some TEST preapprovals return only `init_point` (production host), which breaks checkout with test accounts/tokens.
- When that happens the module returns `mp_missing_sandbox_init_point` and recommends using **Direct (tokenized)** or switching to LIVE for an end-to-end test.
