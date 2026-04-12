<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_subscriptions_creator_dashboard',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in.', 'politeia-payments-subscriptions' ) . '</p>';
		}

		wp_enqueue_style( 'politeia-pps' );

		$user_id = get_current_user_id();

		$notice = null;
		$error  = null;
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['politeia_pps_create_tier'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['politeia_pps_create_tier_nonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $nonce, 'politeia_pps_create_tier' ) ) {
				$error = __( 'Invalid request. Please refresh and try again.', 'politeia-payments-subscriptions' );
			} else {
				$tier_name = sanitize_text_field( wp_unslash( $_POST['tier_name'] ?? '' ) );
				$amount    = (int) ( $_POST['amount_clp'] ?? 0 );
				$period    = sanitize_text_field( wp_unslash( $_POST['period'] ?? '' ) );

				$period_map = array(
					'daily'   => 'day',
					'weekly'  => 'week',
					'monthly' => 'month',
					'yearly'  => 'year',
				);

				if ( ! $tier_name || $amount <= 0 || ! isset( $period_map[ $period ] ) ) {
					$error = __( 'Please complete all fields.', 'politeia-payments-subscriptions' );
				} else {
					$res = Politeia_PPS_Subscription_Engine::create_tier(
						$user_id,
						array(
							'tier_name'      => $tier_name,
							'tier_slug'      => $tier_name,
							'amount_minor'   => $amount, // CLP has no decimals; 1 CLP = 1 minor unit.
							'currency'       => 'CLP',
							'interval_unit'  => $period_map[ $period ],
							'interval_count' => 1,
						)
					);

					if ( is_wp_error( $res ) ) {
						$error = $res->get_error_message();
					} else {
						$notice = __( 'Tier created.', 'politeia-payments-subscriptions' );
					}
				}
			}
		}

		$tiers   = Politeia_PPS_Subscription_Engine::get_creator_tiers( $user_id );

		ob_start();
		?>
		<div class="politeia-pps politeia-pps--creator">
			<h2><?php echo esc_html__( 'My Subscription Tiers', 'politeia-payments-subscriptions' ); ?></h2>

			<?php if ( $notice ) : ?>
				<div class="politeia-pps__notice" role="status"><?php echo esc_html( $notice ); ?></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="politeia-pps__error" role="alert"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<div class="politeia-pps__actions">
				<button type="button" class="politeia-pps__btn" data-pps-toggle="create-tier">
					<?php echo esc_html__( 'Create Tier', 'politeia-payments-subscriptions' ); ?>
				</button>

				<div class="politeia-pps__dropdown" data-pps-panel="create-tier" hidden>
					<form method="post">
						<?php wp_nonce_field( 'politeia_pps_create_tier', 'politeia_pps_create_tier_nonce' ); ?>
						<input type="hidden" name="politeia_pps_create_tier" value="1" />

						<p>
							<label>
								<?php echo esc_html__( 'Name of Tier', 'politeia-payments-subscriptions' ); ?><br />
								<input type="text" name="tier_name" class="regular-text" required />
							</label>
						</p>
						<p>
							<label>
								<?php echo esc_html__( 'Amount (CLP)', 'politeia-payments-subscriptions' ); ?><br />
								<input type="number" name="amount_clp" min="1" step="1" required />
							</label>
						</p>
						<p>
							<label>
								<?php echo esc_html__( 'Period', 'politeia-payments-subscriptions' ); ?><br />
								<select name="period" required>
									<option value="monthly"><?php echo esc_html__( 'Monthly', 'politeia-payments-subscriptions' ); ?></option>
									<option value="daily"><?php echo esc_html__( 'Daily', 'politeia-payments-subscriptions' ); ?></option>
									<option value="weekly"><?php echo esc_html__( 'Weekly', 'politeia-payments-subscriptions' ); ?></option>
									<option value="yearly"><?php echo esc_html__( 'Yearly', 'politeia-payments-subscriptions' ); ?></option>
								</select>
							</label>
						</p>

						<p>
							<button type="submit" class="politeia-pps__btn politeia-pps__btn--primary">
								<?php echo esc_html__( 'Save Tier', 'politeia-payments-subscriptions' ); ?>
							</button>
						</p>
					</form>
				</div>
			</div>

			<?php if ( ! $tiers ) : ?>
				<p><?php echo esc_html__( 'No tiers yet.', 'politeia-payments-subscriptions' ); ?></p>
			<?php else : ?>
				<ul class="politeia-pps__list">
					<?php foreach ( $tiers as $tier ) : ?>
						<li>
							<strong><?php echo esc_html( $tier['tier_name'] ); ?></strong>
							<span class="politeia-pps__muted">
								<?php echo esc_html( $tier['currency'] ); ?> <?php echo esc_html( $tier['amount_minor'] ); ?>
								/ <?php echo esc_html( $tier['interval_count'] ); ?> <?php echo esc_html( $tier['interval_unit'] ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="politeia-pps__hint">
				<?php echo esc_html__( 'Tier creation is available via REST for now (politeia/v1/subscriptions/tiers).', 'politeia-payments-subscriptions' ); ?>
			</p>
		</div>
		<script>
			(function () {
				var btn = document.querySelector('[data-pps-toggle="create-tier"]');
				var panel = document.querySelector('[data-pps-panel="create-tier"]');
				if (!btn || !panel) return;
				btn.addEventListener('click', function () {
					panel.hidden = !panel.hidden;
				});
			})();
		</script>
		<?php
		return ob_get_clean();
	}
);
