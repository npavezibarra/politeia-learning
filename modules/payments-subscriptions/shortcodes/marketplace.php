<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_subscriptions_marketplace',
	function ( $atts ) {
		wp_enqueue_style( 'politeia-pps' );
		wp_enqueue_script( 'politeia-pps-marketplace' );

		wp_localize_script(
			'politeia-pps-marketplace',
			'PoliteiaPPSMarketplace',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'politeia/v1/subscriptions/subscribe' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
				'flow'     => (string) Politeia_PPS_Settings::get( 'subscription_flow', 'hosted' ),
				'mode'     => (string) Politeia_PPS_Settings::get_mode(),
				'publicKey'=> (string) Politeia_PPS_Settings::get_public_key(),
				'i18n'     => array(
					'loginRequired' => __( 'Please log in to subscribe.', 'politeia-payments-subscriptions' ),
					'processing'    => __( 'Processing…', 'politeia-payments-subscriptions' ),
					'error'         => __( 'Something went wrong.', 'politeia-payments-subscriptions' ),
				),
			)
		);

		global $wpdb;
		$table = $wpdb->prefix . 'politeia_subscription_meta';
		$tiers = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY created_at DESC", ARRAY_A );
		$current_user_id = get_current_user_id();

		ob_start();
		?>
		<div class="politeia-pps politeia-pps--marketplace">
			<h2><?php echo esc_html__( 'Subscription Marketplace', 'politeia-payments-subscriptions' ); ?></h2>
			<div class="politeia-pps__marketplace-status" aria-live="polite" style="display:none"></div>

			<?php if ( ! $tiers ) : ?>
				<p><?php echo esc_html__( 'No tiers available yet.', 'politeia-payments-subscriptions' ); ?></p>
			<?php else : ?>
				<table class="politeia-pps__table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Creator', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Tier', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Price', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Period', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Action', 'politeia-payments-subscriptions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tiers as $tier ) : ?>
							<?php
							$creator      = get_user_by( 'id', (int) $tier['creator_user_id'] );
							$creator_name = $creator ? $creator->display_name : (string) $tier['creator_user_id'];
							$is_own_tier  = $current_user_id && ( (int) $tier['creator_user_id'] === (int) $current_user_id );
							?>
							<tr>
								<td><?php echo esc_html( $creator_name ); ?></td>
								<td><?php echo esc_html( (string) $tier['tier_name'] ); ?></td>
								<td><?php echo esc_html( (string) $tier['currency'] . ' ' . (string) $tier['amount_minor'] ); ?></td>
								<td><?php echo esc_html( (string) $tier['interval_count'] . ' ' . (string) $tier['interval_unit'] ); ?></td>
								<td>
									<?php if ( $is_own_tier ) : ?>
										<button class="politeia-pps__btn" type="button" disabled>
											<?php echo esc_html__( 'Your tier', 'politeia-payments-subscriptions' ); ?>
										</button>
									<?php else : ?>
										<button class="politeia-pps__btn politeia-pps__btn--primary" type="button" data-pps-subscribe data-tier-id="<?php echo esc_attr( (string) $tier['id'] ); ?>">
											<?php echo esc_html__( 'Subscribe', 'politeia-payments-subscriptions' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
);
