<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider-agnostic gateway contract for paid memberships.
 *
 * Notes:
 * - This interface is intentionally minimal for Phase 0.
 * - Implementations should be pure-PHP and return structured arrays suitable for logging.
 */
interface Politeia_PPS_Gateway_Interface {
	/**
	 * Gateway key used in DB / settings (e.g. "mercadopago", "flow").
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Create or update the recurring plan for a creator tier.
	 *
	 * Implementations should be idempotent:
	 * - If the remote plan already exists, update it to match local tier state.
	 * - Persist remote plan id back to `wp_politeia_subscription_meta`.
	 *
	 * @param array $tier Row from `wp_politeia_subscription_meta`.
	 * @return array Result payload (provider response + local side-effects).
	 * @throws Exception on irrecoverable failures.
	 */
	public function upsert_tier_plan( array $tier );

	/**
	 * Create a subscription for a subscriber to a tier.
	 *
	 * @param int   $subscriber_user_id WP user id (payer).
	 * @param array $tier Row from `wp_politeia_subscription_meta`.
	 * @param array $args Provider-specific optional arguments (e.g. redirect URLs, card token).
	 * @return array Result payload.
	 * @throws Exception on irrecoverable failures.
	 */
	public function create_subscription( $subscriber_user_id, array $tier, array $args = array() );

	/**
	 * Cancel a subscription.
	 *
	 * Implementations must support:
	 * - immediate cancellation
	 * - cancel at period end (best-effort depending on provider)
	 *
	 * @param array $subscription Row from `wp_politeia_subscriptions`.
	 * @param array $args         e.g. ['at_period_end' => true, 'reason' => '...']
	 * @return array Result payload.
	 * @throws Exception on irrecoverable failures.
	 */
	public function cancel_subscription( array $subscription, array $args = array() );
}

