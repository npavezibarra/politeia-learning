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
- Phase 3 is implemented but **blocked** in production until Flow enables the commerce contract for **Cobro Automático / Suscripciones**.
- Phases 4–5 are implemented but **not verified end-to-end** because we cannot create real Flow recurring subscriptions yet.
- Phase 6 is not implemented.

### Remaining work

- **Flow contract enablement**
  - Flow must enable **Cobro Automático / Suscripciones** for the commerce (otherwise API returns `code=7001` on `customer/register`).
  - After enablement, run a full LIVE test: register card → create subscription → first charge → callback → ledger → relationship.

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

## Approval gate

We should implement phases in order, shipping each phase behind feature flags when needed.

**Before writing code**, confirm:

- Whether we will deprecate Mercado Pago subscriptions entirely or keep it as a fallback.
- Which Flow mode to support first: TEST-only or directly LIVE-ready.
