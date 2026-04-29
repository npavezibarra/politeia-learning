<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_PPS_Settings {
	const OPTION_KEY = 'politeia_pps_settings';
	const TEST_RESULT_TRANSIENT = 'politeia_pps_mp_test_result';
	const MENU_PARENT = 'politeia-learning';
	const MENU_SLUG   = 'pl-payment-gateways';

	private static function mask_value_for_display( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( strlen( $value ) <= 4 ) {
			return '••••';
		}
		return '••••' . substr( $value, -4 );
	}

	public static function init() {
		// Priority 20 ensures the parent "Politeia Learning" menu is registered first.
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_politeia_pps_test_mp', array( __CLASS__, 'handle_test_mp' ) );
		add_action( 'wp_ajax_politeia_pps_test_mp_token', array( __CLASS__, 'ajax_test_mp_token' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function defaults() {
		return array(
			'mode'                     => 'test', // test|live
			'subscription_flow'        => 'hosted', // hosted|direct
			// General redirects (optional; used as defaults in gateway flows).
			'success_url'              => '',
			'cancel_url'               => '',
			// Optional override to force payer_email when creating subscriptions.
			// Useful when testing with Mercado Pago "test users" where payer/collector must match.
			'payer_email_override'      => '',
			'mp_public_key_test'       => '',
			'mp_access_token_test'     => '',
			'mp_public_key_live'       => '',
			'mp_access_token_live'     => '',
			'mp_webhook_secret'        => '',
			'expected_seller_user_id'  => '',
			'expected_site_id'         => 'MLC',
			// Flow (Chile) credentials.
			'flow_api_key_test'        => '',
			'flow_secret_test'         => '',
			'flow_commerce_id_test'    => '',
			'flow_api_key_live'        => '',
			'flow_secret_live'         => '',
			'flow_commerce_id_live'    => '',
			'flow_webhook_secret'      => '',
			'platform_commission_rate' => 0.10,
			'iva_rate'                 => 0.19,
			'mp_fee_includes_iva'       => true,
			'exchange_rate_provider'   => '',
			'exchange_rate_api_key'    => '',
		);
	}

	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Back-compat: migrate old keys into the TEST slot if present.
		if ( ! empty( $stored['mp_access_token'] ) && empty( $stored['mp_access_token_test'] ) ) {
			$stored['mp_access_token_test'] = $stored['mp_access_token'];
		}
		if ( ! empty( $stored['mp_public_key'] ) && empty( $stored['mp_public_key_test'] ) ) {
			$stored['mp_public_key_test'] = $stored['mp_public_key'];
		}

		return array_merge( self::defaults(), $stored );
	}

	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function get_mode() {
		$mode = (string) self::get( 'mode', 'test' );
		return in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';
	}

	public static function get_access_token( $mode = null ) {
		$mode = $mode ? $mode : self::get_mode();
		return 'live' === $mode ? (string) self::get( 'mp_access_token_live', '' ) : (string) self::get( 'mp_access_token_test', '' );
	}

	public static function get_public_key( $mode = null ) {
		$mode = $mode ? $mode : self::get_mode();
		return 'live' === $mode ? (string) self::get( 'mp_public_key_live', '' ) : (string) self::get( 'mp_public_key_test', '' );
	}

	public static function register_menu() {
		add_submenu_page(
			self::MENU_PARENT,
			__( 'Pagos', 'politeia-learning' ),
			__( 'Pagos', 'politeia-learning' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'politeia_pps',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'politeia_pps_env', __( 'Environment', 'politeia-payments-subscriptions' ), '__return_false', 'politeia-pps' );
		add_settings_field(
			'mode',
			__( 'Mode', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_mode' ),
			'politeia-pps',
			'politeia_pps_env',
			array( 'key' => 'mode' )
		);

		add_settings_section( 'politeia_pps_general', __( 'General', 'politeia-payments-subscriptions' ), '__return_false', 'politeia-pps' );
		add_settings_field(
			'success_url',
			__( 'Success URL (optional)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_general',
			array(
				'key'  => 'success_url',
				'help' => __( 'Default redirect after a successful checkout/confirmation. Leave empty to use the site home.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'cancel_url',
			__( 'Cancel URL (optional)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_general',
			array(
				'key'  => 'cancel_url',
				'help' => __( 'Default redirect when a user cancels checkout. Leave empty to use the site home.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'payer_email_override',
			__( 'Payer Email Override (optional)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_general',
			array(
				'key'         => 'payer_email_override',
				'placeholder' => 'buyer_test_xxx@testuser.com',
				'help'        => __( 'If set, this email is sent as payer_email when creating Mercado Pago subscriptions. Leave empty to use the logged-in user email.', 'politeia-payments-subscriptions' ),
			)
		);

		add_settings_section( 'politeia_pps_mp', __( 'Mercado Pago', 'politeia-payments-subscriptions' ), '__return_false', 'politeia-pps' );
		add_settings_field(
			'subscription_flow',
			__( 'Subscription Flow', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_subscription_flow' ),
			'politeia-pps',
			'politeia_pps_mp',
			array( 'key' => 'subscription_flow' )
		);
		add_settings_field(
			'mp_public_key_test',
			__( 'Public Key (TEST)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'mp_public_key_test',
				'placeholder' => 'APP_USR-... or TEST-...',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
				'test_mode'   => 'test',
			)
		);
		add_settings_field(
			'mp_access_token_test',
			__( 'Access Token (TEST)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'mp_access_token_test',
				'placeholder' => 'APP_USR-... or TEST-...',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
				'test_mode'   => 'test',
			)
		);
		add_settings_field(
			'mp_public_key_live',
			__( 'Public Key (LIVE)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'mp_public_key_live',
				'placeholder' => 'APP_USR-...',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
				'test_mode'   => 'live',
			)
		);
		add_settings_field(
			'mp_access_token_live',
			__( 'Access Token (LIVE)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'mp_access_token_live',
				'placeholder' => 'APP_USR-...',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
				'test_mode'   => 'live',
			)
		);
		add_settings_field(
			'expected_seller_user_id',
			__( 'Expected Seller User ID', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'expected_seller_user_id',
				'placeholder' => 'e.g. 3231100226',
			)
		);
		add_settings_field(
			'expected_site_id',
			__( 'Expected Site ID', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'expected_site_id',
				'placeholder' => 'MLC',
			)
		);
		add_settings_field(
			'mp_webhook_secret',
			__( 'Webhook Secret', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_mp',
			array(
				'key'         => 'mp_webhook_secret',
				'placeholder' => 'Optional: signature secret',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);

		add_settings_section( 'politeia_pps_flow', __( 'Flow (Chile)', 'politeia-payments-subscriptions' ), '__return_false', 'politeia-pps' );
		add_settings_field(
			'flow_api_key_test',
			__( 'API Key (TEST)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_api_key_test',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'flow_secret_test',
			__( 'Secret (TEST)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_secret_test',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'flow_commerce_id_test',
			__( 'Commerce ID (TEST)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_commerce_id_test',
				'placeholder' => 'e.g. 123456',
			)
		);
		add_settings_field(
			'flow_api_key_live',
			__( 'API Key (LIVE)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_api_key_live',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'flow_secret_live',
			__( 'Secret (LIVE)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_secret_live',
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'flow_commerce_id_live',
			__( 'Commerce ID (LIVE)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_text' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_commerce_id_live',
				'placeholder' => 'e.g. 123456',
			)
		);
		add_settings_field(
			'flow_webhook_secret',
			__( 'Webhook Secret (optional)', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_secret' ),
			'politeia-pps',
			'politeia_pps_flow',
			array(
				'key'         => 'flow_webhook_secret',
				'placeholder' => __( 'Optional', 'politeia-payments-subscriptions' ),
				'help'        => __( 'Hidden. Leave blank to keep current value.', 'politeia-payments-subscriptions' ),
			)
		);

		add_settings_section( 'politeia_pps_fees', __( 'Fees & Taxes', 'politeia-payments-subscriptions' ), '__return_false', 'politeia-pps' );
		add_settings_field(
			'platform_commission_rate',
			__( 'Platform Commission Rate', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_number' ),
			'politeia-pps',
			'politeia_pps_fees',
			array(
				'key'   => 'platform_commission_rate',
				'step'  => '0.01',
				'min'   => '0',
				'max'   => '1',
				'help'  => __( 'Default: 0.10 (10%)', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'iva_rate',
			__( 'IVA (VAT) Rate', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_number' ),
			'politeia-pps',
			'politeia_pps_fees',
			array(
				'key'  => 'iva_rate',
				'step' => '0.01',
				'min'  => '0',
				'max'  => '1',
				'help' => __( 'Default: 0.19 (19%)', 'politeia-payments-subscriptions' ),
			)
		);
		add_settings_field(
			'mp_fee_includes_iva',
			__( 'Apply IVA over MP Fee', 'politeia-payments-subscriptions' ),
			array( __CLASS__, 'field_checkbox' ),
			'politeia-pps',
			'politeia_pps_fees',
			array(
				'key'  => 'mp_fee_includes_iva',
				'help' => __( 'If enabled, IVA is calculated as (mp_fee * iva_rate).', 'politeia-payments-subscriptions' ),
			)
		);
	}

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$existing = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			$value = is_array( $input ) && array_key_exists( $key, $input ) ? $input[ $key ] : $default;

			if ( in_array(
				$key,
				array(
					'mp_public_key_test',
					'mp_access_token_test',
					'mp_public_key_live',
					'mp_access_token_live',
					'mp_webhook_secret',
					'flow_api_key_test',
					'flow_secret_test',
					'flow_api_key_live',
					'flow_secret_live',
					'flow_webhook_secret',
				),
				true
			) ) {
				$trimmed = is_string( $value ) ? trim( $value ) : '';
				if ( '' === $trimmed && isset( $existing[ $key ] ) && is_string( $existing[ $key ] ) ) {
					$out[ $key ] = $existing[ $key ];
				} else {
					$out[ $key ] = $trimmed;
				}
				continue;
			}
			if ( in_array( $key, array( 'flow_commerce_id_test', 'flow_commerce_id_live' ), true ) ) {
				$out[ $key ] = preg_replace( '/[^0-9]/', '', (string) $value );
				continue;
			}
			if ( in_array( $key, array( 'success_url', 'cancel_url' ), true ) ) {
				$out[ $key ] = is_string( $value ) ? trim( $value ) : '';
				continue;
			}
			if ( in_array( $key, array( 'payer_email_override' ), true ) ) {
				$email = is_string( $value ) ? trim( $value ) : '';
				$out[ $key ] = $email ? sanitize_email( $email ) : '';
				continue;
			}
			if ( in_array( $key, array( 'exchange_rate_provider', 'exchange_rate_api_key' ), true ) ) {
				$out[ $key ] = is_string( $value ) ? trim( $value ) : '';
				continue;
			}
			if ( in_array( $key, array( 'mode' ), true ) ) {
				$mode       = is_string( $value ) ? trim( $value ) : 'test';
				$out[ $key ] = in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';
				continue;
			}
			if ( in_array( $key, array( 'subscription_flow' ), true ) ) {
				$flow = is_string( $value ) ? trim( $value ) : 'hosted';
				$out[ $key ] = in_array( $flow, array( 'hosted', 'direct' ), true ) ? $flow : 'hosted';
				continue;
			}
			if ( in_array( $key, array( 'expected_seller_user_id' ), true ) ) {
				$out[ $key ] = preg_replace( '/[^0-9]/', '', (string) $value );
				continue;
			}
			if ( in_array( $key, array( 'expected_site_id' ), true ) ) {
				$out[ $key ] = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $value ) );
				continue;
			}
			if ( in_array( $key, array( 'platform_commission_rate', 'iva_rate' ), true ) ) {
				$out[ $key ] = (float) $value;
				continue;
			}
			if ( in_array( $key, array( 'mp_fee_includes_iva' ), true ) ) {
				$out[ $key ] = (bool) $value;
				continue;
			}
			$out[ $key ] = $default;
		}

		return $out;
	}

	public static function enqueue_admin_assets( $hook ) {
		$allowed = array(
			'politeia-learning_page_' . self::MENU_SLUG,
			// Back-compat if the old options page is still present.
			'settings_page_politeia-pps',
		);
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		wp_enqueue_script(
			'politeia-pps-admin',
			POLITEIA_PPS_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			POLITEIA_PPS_VERSION,
			true
		);

		wp_localize_script(
			'politeia-pps-admin',
			'PoliteiaPPSAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'politeia_pps_admin' ),
			)
		);
	}

	public static function field_mode( $args ) {
		$val = self::get_mode();
		?>
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mode]" value="test" <?php checked( $val, 'test' ); ?> />
			<?php echo esc_html__( 'Sandbox (TEST)', 'politeia-payments-subscriptions' ); ?>
		</label>
		&nbsp;&nbsp;
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mode]" value="live" <?php checked( $val, 'live' ); ?> />
			<?php echo esc_html__( 'Production (LIVE)', 'politeia-payments-subscriptions' ); ?>
		</label>
		<p class="description">
			<?php echo esc_html__( 'Controls which Mercado Pago credentials are used for API calls and checkout redirects.', 'politeia-payments-subscriptions' ); ?>
		</p>
		<?php
	}

	public static function field_subscription_flow( $args ) {
		$val = (string) self::get( 'subscription_flow', 'hosted' );
		if ( ! in_array( $val, array( 'hosted', 'direct' ), true ) ) {
			$val = 'hosted';
		}
		?>
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[subscription_flow]" value="hosted" <?php checked( $val, 'hosted' ); ?> />
			<?php echo esc_html__( 'Hosted Checkout (redirect)', 'politeia-payments-subscriptions' ); ?>
		</label>
		<br />
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[subscription_flow]" value="direct" <?php checked( $val, 'direct' ); ?> />
			<?php echo esc_html__( 'Card token (authorized) (no redirect)', 'politeia-payments-subscriptions' ); ?>
		</label>
		<br />
		<p class="description">
			<?php echo esc_html__( 'Hosted opens Mercado Pago checkout. Card token uses Mercado Pago JS to create card_token_id and creates a preapproval with status=authorized.', 'politeia-payments-subscriptions' ); ?>
		</p>
		<?php
	}

	public static function field_text( $args ) {
		$key         = $args['key'];
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$value       = self::get( $key, '' );
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	public static function field_secret( $args ) {
		$key         = $args['key'];
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		$help        = isset( $args['help'] ) ? $args['help'] : '';
		$test_mode   = isset( $args['test_mode'] ) ? $args['test_mode'] : '';
		$current     = self::get( $key, '' );

		// Show stored value in the field for debugging (user requested).
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" autocomplete="off" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $current ),
			esc_attr( $placeholder )
		);

		if ( $test_mode ) {
			printf(
				' <button type="button" class="button button-secondary politeia-pps-test-token" data-pps-mode="%s" data-pps-key="%s">%s</button>',
				esc_attr( (string) $test_mode ),
				esc_attr( (string) $key ),
				esc_html__( 'Test', 'politeia-payments-subscriptions' )
			);
			echo ' <span class="description politeia-pps-test-result" data-pps-result-for="' . esc_attr( (string) $key ) . '"></span>';
		}

		if ( $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	public static function field_number( $args ) {
		$key  = $args['key'];
		$min  = isset( $args['min'] ) ? $args['min'] : '0';
		$max  = isset( $args['max'] ) ? $args['max'] : '1';
		$step = isset( $args['step'] ) ? $args['step'] : '0.01';
		$help = isset( $args['help'] ) ? $args['help'] : '';
		$val  = (string) self::get( $key, '' );
		printf(
			'<input type="number" name="%1$s[%2$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $val ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step )
		);
		if ( $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	public static function field_checkbox( $args ) {
		$key  = $args['key'];
		$help = isset( $args['help'] ) ? $args['help'] : '';
		$val  = (bool) self::get( $key, false );
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $val, true, false ),
			esc_html( $help )
		);
	}

	public static function render_page() {
		$public_key_set  = (bool) self::get_public_key();
		$access_set      = (bool) self::get_access_token();
		$webhook_set     = (bool) self::get( 'mp_webhook_secret', '' );
		$test_result     = get_transient( self::TEST_RESULT_TRANSIENT );
		$webhooks_notice = isset( $_GET['pps_webhooks_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['pps_webhooks_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Payment Gateways', 'politeia-learning' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Configura credenciales y reglas para pagos recurrentes usados por las membresías (Flow, Mercado Pago, etc).', 'politeia-learning' ); ?>
			</p>
			<div class="notice notice-info">
				<p>
					<strong><?php echo esc_html__( 'Mercado Pago status:', 'politeia-payments-subscriptions' ); ?></strong>
					<?php
					echo esc_html__( 'Public Key', 'politeia-payments-subscriptions' ) . ': ' . ( $public_key_set ? esc_html__( 'configured', 'politeia-payments-subscriptions' ) : esc_html__( 'missing', 'politeia-payments-subscriptions' ) ) . ' — ';
					echo esc_html__( 'Access Token', 'politeia-payments-subscriptions' ) . ': ' . ( $access_set ? esc_html__( 'configured', 'politeia-payments-subscriptions' ) : esc_html__( 'missing', 'politeia-payments-subscriptions' ) ) . ' — ';
					echo esc_html__( 'Webhook Secret', 'politeia-payments-subscriptions' ) . ': ' . ( $webhook_set ? esc_html__( 'configured', 'politeia-payments-subscriptions' ) : esc_html__( 'not set', 'politeia-payments-subscriptions' ) );
					?>
				</p>
				<p class="description">
					<?php echo esc_html__( 'Keys are intentionally not shown in the fields after saving. Use the status above to confirm they are stored.', 'politeia-payments-subscriptions' ); ?>
				</p>
				<p>
					<?php if ( $access_set ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=politeia_pps_test_mp' ), 'politeia_pps_test_mp' ) ); ?>">
							<?php echo esc_html__( 'Test Mercado Pago Connection', 'politeia-payments-subscriptions' ); ?>
						</a>
					<?php else : ?>
						<span class="description"><?php echo esc_html__( 'Add an Access Token to enable connection testing.', 'politeia-payments-subscriptions' ); ?></span>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( is_array( $test_result ) && ! empty( $test_result['message'] ) ) : ?>
				<div class="notice <?php echo ! empty( $test_result['ok'] ) ? 'notice-success' : 'notice-error'; ?>">
					<p><strong><?php echo esc_html__( 'Mercado Pago Test:', 'politeia-payments-subscriptions' ); ?></strong> <?php echo esc_html( (string) $test_result['message'] ); ?></p>
					<?php if ( ! empty( $test_result['details'] ) ) : ?>
						<pre style="white-space:pre-wrap;max-width:100%;overflow:auto;"><?php echo esc_html( wp_json_encode( $test_result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'politeia_pps' );
				do_settings_sections( 'politeia-pps' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Webhooks & Ledger', 'politeia-payments-subscriptions' ); ?></h2>

			<?php if ( $webhooks_notice === 'processed' ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html__( 'Webhook queue processed.', 'politeia-payments-subscriptions' ); ?></p></div>
			<?php elseif ( $webhooks_notice === 'event_processed' ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html__( 'Webhook event processed.', 'politeia-payments-subscriptions' ); ?></p></div>
			<?php endif; ?>

			<?php
			global $wpdb;
			$pending_count = 0;
			$events        = array();
			$ledger        = array();
			if ( $wpdb && class_exists( 'Politeia_PPS_Subscription_Engine' ) ) {
				$events_table = Politeia_PPS_Subscription_Engine::webhook_events_table();
				$ledger_table = Politeia_PPS_Subscription_Engine::ledger_table();
				$pending_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table} WHERE processed = 0" );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$events = $wpdb->get_results(
					"SELECT id, event_type, resource_id, processed, received_at, processed_at
					 FROM {$events_table}
					 ORDER BY received_at DESC
					 LIMIT 25",
					ARRAY_A
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$ledger = $wpdb->get_results(
					"SELECT id, creator_user_id, subscriber_user_id, tier_id, mp_payment_id, mp_preapproval_id, mp_status, currency, gross_amount_minor, occurred_at
					 FROM {$ledger_table}
					 ORDER BY occurred_at DESC
					 LIMIT 25",
					ARRAY_A
				);
			}
			?>

			<p>
				<strong><?php echo esc_html__( 'Pending webhook events:', 'politeia-payments-subscriptions' ); ?></strong>
				<?php echo esc_html( (string) $pending_count ); ?>
				&nbsp;&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . Politeia_PPS_Webhooks::ADMIN_ACTION_PROCESS_ALL ), 'politeia_pps_process_webhooks' ) ); ?>">
					<?php echo esc_html__( 'Process Now', 'politeia-payments-subscriptions' ); ?>
				</a>
			</p>

			<h3><?php echo esc_html__( 'Recent Webhook Events', 'politeia-payments-subscriptions' ); ?></h3>
			<table class="widefat striped" style="max-width: 1100px;">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Resource', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Processed', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Received', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'politeia-payments-subscriptions' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $events ) : ?>
					<tr><td colspan="6"><?php echo esc_html__( 'No webhook events yet.', 'politeia-payments-subscriptions' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( (array) $events as $ev ) : ?>
						<?php
						$ev_id     = (int) ( $ev['id'] ?? 0 );
						$processed = (int) ( $ev['processed'] ?? 0 ) === 1;
						$process_url = wp_nonce_url(
							add_query_arg(
								array(
									'action'   => Politeia_PPS_Webhooks::ADMIN_ACTION_PROCESS_EVENT,
									'event_id' => $ev_id,
								),
								admin_url( 'admin-post.php' )
							),
							'politeia_pps_process_webhook_event'
						);
						$reprocess_url = wp_nonce_url(
							add_query_arg(
								array(
									'action'   => Politeia_PPS_Webhooks::ADMIN_ACTION_PROCESS_EVENT,
									'event_id' => $ev_id,
									'force'    => '1',
								),
								admin_url( 'admin-post.php' )
							),
							'politeia_pps_process_webhook_event'
						);
						?>
						<tr>
							<td><?php echo esc_html( (string) $ev_id ); ?></td>
							<td><code><?php echo esc_html( (string) ( $ev['event_type'] ?? '' ) ); ?></code></td>
							<td><code><?php echo esc_html( (string) ( $ev['resource_id'] ?? '' ) ); ?></code></td>
							<td><?php echo $processed ? esc_html__( 'yes', 'politeia-payments-subscriptions' ) : esc_html__( 'no', 'politeia-payments-subscriptions' ); ?></td>
							<td><?php echo esc_html( (string) ( $ev['received_at'] ?? '' ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $process_url ); ?>"><?php echo esc_html__( 'Process', 'politeia-payments-subscriptions' ); ?></a>
								<?php if ( $processed ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $reprocess_url ); ?>"><?php echo esc_html__( 'Reprocess', 'politeia-payments-subscriptions' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h3 style="margin-top: 18px;"><?php echo esc_html__( 'Recent Ledger Entries', 'politeia-payments-subscriptions' ); ?></h3>
			<table class="widefat striped" style="max-width: 1100px;">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Creator', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Subscriber', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Payment', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Preapproval', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Amount', 'politeia-payments-subscriptions' ); ?></th>
						<th><?php echo esc_html__( 'Occurred', 'politeia-payments-subscriptions' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $ledger ) : ?>
					<tr><td colspan="8"><?php echo esc_html__( 'No ledger entries yet.', 'politeia-payments-subscriptions' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( (array) $ledger as $l ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $l['id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $l['creator_user_id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $l['subscriber_user_id'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $l['mp_payment_id'] ?? '' ) ); ?></code></td>
							<td><code><?php echo esc_html( (string) ( $l['mp_preapproval_id'] ?? '' ) ); ?></code></td>
							<td><code><?php echo esc_html( (string) ( $l['mp_status'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( (string) ( $l['currency'] ?? '' ) . ' ' . (string) ( $l['gross_amount_minor'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $l['occurred_at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function handle_test_mp() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'politeia_pps_test_mp' );

		$client = new Politeia_PPS_MercadoPago_Client();
		$res    = $client->get_me();

		$result = array(
			'ok'      => false,
			'message' => '',
			'details' => null,
		);

		if ( is_wp_error( $res ) ) {
			$result['ok']      = false;
			$result['message'] = $res->get_error_message();
			$result['details'] = $res->get_error_data();
		} else {
			$result['ok'] = true;
			$id           = isset( $res['id'] ) ? (string) $res['id'] : '';
			$site         = isset( $res['site_id'] ) ? (string) $res['site_id'] : '';
			$email        = isset( $res['email'] ) ? (string) $res['email'] : '';
			$result['message'] = sprintf( 'Connected. user_id=%s site_id=%s email=%s', $id, $site, $email );
			$result['details'] = $res;
		}

		set_transient( self::TEST_RESULT_TRANSIENT, $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	public static function ajax_test_mp_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'politeia_pps_admin', 'nonce' );

		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'test';
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$token = isset( $_POST['token'] ) ? trim( (string) wp_unslash( $_POST['token'] ) ) : '';

		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) {
			$mode = 'test';
		}

		// Public keys can't be verified via /users/me; validate format only.
		if ( $key && false !== strpos( $key, 'mp_public_key_' ) ) {
			$pk = $token;
			if ( '' === $pk ) {
				$pk = self::get_public_key( $mode );
			}
			$is_valid = (bool) preg_match( '/^(APP|TEST)_[A-Z]{3}-[A-Za-z0-9\\-]{10,}$/', (string) $pk );
			wp_send_json_success(
				array(
					'ok'       => $is_valid,
					'kind'     => 'public_key',
					'key'      => $key,
					'mode'     => $mode,
					'message'  => $is_valid ? 'OK (public key format looks valid)' : 'Invalid public key format',
					'user_id'  => '',
					'site_id'  => '',
					'email'    => '',
					'warnings' => $is_valid ? array( 'Public keys cannot be tested via API. Use the Access Token test.' ) : array(),
				)
			);
		}

		// If admin didn't type a token, test the saved one for that mode.
		if ( '' === $token ) {
			$token = self::get_access_token( $mode );
		}

		$client = new Politeia_PPS_MercadoPago_Client( $token );
		$res    = $client->get_me();

		if ( is_wp_error( $res ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'key'     => $key,
					'mode'    => $mode,
					'message' => $res->get_error_message(),
					'details' => $res->get_error_data(),
				)
			);
		}

		$user_id = isset( $res['id'] ) ? (string) $res['id'] : '';
		$site_id = isset( $res['site_id'] ) ? (string) $res['site_id'] : '';

		$expected_user_id = (string) self::get( 'expected_seller_user_id', '' );
		$expected_site_id = (string) self::get( 'expected_site_id', '' );

		$warnings = array();
		if ( $expected_user_id && $user_id && $expected_user_id !== $user_id ) {
			$warnings[] = sprintf( 'Expected seller user_id=%s but token is user_id=%s', $expected_user_id, $user_id );
		}
		if ( $expected_site_id && $site_id && $expected_site_id !== $site_id ) {
			$warnings[] = sprintf( 'Expected site_id=%s but token is site_id=%s', $expected_site_id, $site_id );
		}

		wp_send_json_success(
			array(
				'ok'       => true,
				'key'      => $key,
				'mode'     => $mode,
				'user_id'  => $user_id,
				'site_id'  => $site_id,
				'email'    => isset( $res['email'] ) ? (string) $res['email'] : '',
				'warnings' => $warnings,
			)
		);
	}
}
