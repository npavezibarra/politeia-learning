# Flow Gateway (Payments Subscriptions submodule)

This folder is the **Flow payment gateway implementation** for the Politeia Learning module:

`payments-subscriptions` → `flow/`

Purpose:

- Keep **all Flow-specific code** (API client, signatures, settings, webhooks, subscription lifecycle, admin tools) contained in a single place.
- Provide a clean alternative to Mercado Pago for **monthly memberships** (recurring billing).
- Preserve the existing platform semantics:
  - Local DB tables remain the source of truth (`wp_politeia_subscription_meta`, `wp_politeia_subscriptions`, `wp_politeia_transaction_ledger`, `wp_politeia_*webhook*`).
  - Access control continues to be granted/revoked through `PL_Relationships::TYPE_SUBSCRIBE`.

## Why this exists

Mercado Pago recurring subscriptions (`preapproval`) introduced operational friction during real-payments testing and is not a reliable single dependency for Politeia’s membership product.

Flow is a Chile-first provider with explicit subscription APIs, including cancellation options such as:

- Cancel immediately
- Cancel at period end (`at_period_end`)

This submodule is where we will implement the full Flow subscription lifecycle.

## Operational requirement (Flow contract)

Flow recurring subscriptions require the merchant/commerce to have the **Cobro Automático / Suscripciones** contract enabled.

If the commerce does not have it enabled, Flow can return errors like:

- `code=7001` `"Commerce has not automatic charge contract"`

In that scenario, Flow can still show plans/customers in the dashboard, but **card registration and recurring charges will fail** until Flow enables the contract.

## Folder contract (expected structure)

The exact structure will evolve, but the intention is:

- `includes/`
  - `class-flow-client.php` (HTTP client, signing, idempotency, error handling)
  - `class-flow-engine.php` (business logic: create plan, create subscription, cancel, reconcile)
  - `class-flow-webhooks.php` (webhook ingestion + verification + processing)
  - `class-flow-settings.php` (WP Admin settings fields + validation)
  - `class-flow-mapper.php` (maps Flow statuses → local statuses)
- `assets/`
  - Optional UI assets (admin screens, subscriber screens if needed)

## Data mapping (first pass)

We will represent the Flow identifiers in our existing tables:

- Tier / plan:
  - `wp_politeia_subscription_meta.flow_plan_id` (new column) or a dedicated meta table if we want to avoid schema changes.
- Subscription:
  - `wp_politeia_subscriptions.flow_subscription_id` (new column)

For cancellations:

- Store:
  - `cancel_at_period_end`
  - `cancelled_at`
  - `flow_cancelled_at`
  - `cancellation_reason`

## Implementation plan (phases)

This is a phased rollout plan to reduce risk and keep production safe.

### Phase 0 — Discovery & contract

- Confirm which Flow subscription endpoints we will use (plan/subscription/cancel/webhooks).
- Define the gateway interface in `payments-subscriptions` (provider-agnostic).
- Decide schema strategy (new columns vs meta table).
- Define status model + mapping (Flow → local).

### Phase 1 — Settings + connectivity

- Add Flow credentials in WP Admin (separate TEST/LIVE).
- Add a “Test connection” button (server-side).
- Add robust logging (request id, signature verification results, retry strategy).

### Phase 2 — Create plan (tier sync)

- When creator saves monthly membership tier:
  - Create/update Flow plan for that creator’s monthly amount.
  - Persist `flow_plan_id` against `tier_slug=monthly`.
- Add reconciliation job to ensure local tier ↔ Flow plan stay consistent.

### Phase 3 — Subscribe flow

- Implement subscriber checkout flow for Flow:
  - Create Flow subscription for the selected tier.
  - Redirect user to Flow checkout (or Flow-hosted step).
  - Confirm subscription activation on return + via webhook.
- Create local `wp_politeia_subscriptions` row early, then finalize on webhook confirmation.

### Phase 4 — Webhooks + ledger

- Implement Flow webhooks:
  - Verify signature
  - Store raw event
  - Process asynchronously
- Write transaction ledger entries for successful recurring payments.
- Grant/renew `PL_Relationships` on successful charge.

### Phase 5 — Cancellation (immediate vs period-end)

- Add “Cancel subscription” in UI:
  - immediate cancellation
  - cancel at period end
- Sync cancellation state with Flow and handle late webhooks safely.

### Phase 6 — Migration / fallback strategy

- Decide how to handle existing Mercado Pago subscribers:
  - keep as-is
  - migrate on next renewal
  - force re-subscribe
- Add admin tools for reconciliation and manual remediation.

## TODO (current gaps)

Status note (as of 2026-05-11):

- Phases 0–2 are implemented and verified (settings connectivity + tier → Flow plan sync).
- Phase 3 is implemented and **verified up to the Flow-hosted card registration redirect** (i.e. `/customer/register` returns `url + token` and the user reaches Flow’s disclaimer/registration screen).
- Phases 4–5 are implemented but **not verified end-to-end** (needs a complete LIVE run: register card → return callback → create subscription → webhook/ledger/relationship).
- Phase 6 is not implemented.

### Remaining work

- **End-to-end LIVE verification**
  - Complete a full LIVE run: register card → create subscription → first charge/webhook → ledger → relationship.
  - Verify that the return URL/callback reliably finalizes the pending subscription row.

- **Phase 4 verification + payload mapping**
  - Confirm Flow callback token resolution (`payment/getStatusExtended`) includes a stable `subscriptionId` (or equivalent).
  - Adjust extraction/mapping logic if Flow uses different keys/structure for subscription payment events.
  - Confirm fee/amount fields and currency mapping into `wp_politeia_transaction_ledger`.
  - Confirm access renewal via `pl_subscription_payment_completed` is correct for recurring events.

- **Phase 5 verification (cancellation)**
  - Validate immediate cancel vs `at_period_end=1` behavior in LIVE.
  - Ensure local fields (`cancel_at_period_end`, `cancelled_at`, `gateway_cancelled_at`, `cancellation_reason`) mirror Flow correctly.
  - Validate late callbacks after scheduled cancellation do not re-grant access incorrectly.

- **Phase 6 (migration / fallback from Mercado Pago)**
  - Implement automatic fallback when Flow is selected but unavailable (e.g. `code=7001`), and/or a UI toggle that only offers configured providers.
  - Decide policy for existing Mercado Pago subscribers (keep, re-subscribe, migrate-on-renewal).
  - Add admin reconciliation tools for cross-gateway auditing (active subs, cancelled, ledger completeness).

## Troubleshooting (known gotchas)

### `code=7001` from Flow (`/customer/register`)

If Flow returns:

- `{"code":7001,"message":"Commerce has not automatic charge contract"}`

then the commerce does not have **Cobro Automático / Suscripciones** enabled. Enable it in Flow dashboard (or via Flow commercial support) and retry.

### WordPress REST vs Flow API

- Flow endpoints are called **server-side** by this plugin (e.g. `/customer/register`).
- The browser calls the WordPress REST route:
  - `POST /wp-json/politeia/v1/subscriptions/subscribe`
  - Requires being logged-in + a valid `wp_rest` nonce (`X-WP-Nonce`).
  - A browser `GET` to that URL may return `rest_no_route` because the route only registers `POST`.

### DB schema: `mp_preapproval_id` NOT NULL (legacy)

Some production DBs may have `wp_politeia_subscriptions.mp_preapproval_id` as **NOT NULL**.

For Flow subscriptions we do not have an MP preapproval id, so the implementation stores an empty string (`''`) for `mp_preapproval_id` on the **pending Flow row** to avoid insert failures.

## Approval gate

We should implement phases in order, shipping each phase behind feature flags when needed.

**Before writing code**, confirm:

- Whether we will deprecate Mercado Pago subscriptions entirely or keep it as a fallback.
- Which Flow mode to support first: TEST-only or directly LIVE-ready.
