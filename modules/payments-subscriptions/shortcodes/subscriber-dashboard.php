<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_subscriptions_subscriber_dashboard',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in.', 'politeia-payments-subscriptions' ) . '</p>';
		}

		wp_enqueue_style( 'politeia-pps' );

		$user_id = get_current_user_id();
		$subs    = Politeia_PPS_Subscription_Engine::get_subscriptions_for_user( $user_id );

		ob_start();
		?>
		<div class="politeia-pps politeia-pps--subscriber">
			<h2><?php echo esc_html__( 'My Subscriptions', 'politeia-payments-subscriptions' ); ?></h2>

			<?php if ( ! $subs ) : ?>
				<p><?php echo esc_html__( 'No active subscriptions.', 'politeia-payments-subscriptions' ); ?></p>
			<?php else : ?>
				<table class="politeia-pps__table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Creator', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Tier', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Gateway', 'politeia-payments-subscriptions' ); ?></th>
							<th><?php echo esc_html__( 'Subscription ID', 'politeia-payments-subscriptions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subs as $sub ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $sub['creator_user_id'] ); ?></td>
								<td><?php echo esc_html( (string) $sub['tier_id'] ); ?></td>
								<td><?php echo esc_html( (string) $sub['status'] ); ?></td>
								<td><code><?php echo esc_html( (string) ( $sub['gateway'] ?? 'mercadopago' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( ( $sub['gateway'] ?? '' ) === 'flow' ? ( $sub['flow_subscription_id'] ?? '' ) : ( $sub['mp_preapproval_id'] ?? '' ) ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p class="politeia-pps__hint">
				<?php echo esc_html__( 'Cancellation is available via REST (politeia/v1/subscriptions/cancel) for Mercado Pago and Flow.', 'politeia-payments-subscriptions' ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
);
