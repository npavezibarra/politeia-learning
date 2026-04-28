<?php
/**
 * Reading Sessions (Core Controller)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Politeia_Reading_Sessions {

	public static function init() {
		add_action( 'wp_ajax_prs_start_reading', array( 'Politeia_Reading_Sessions_Handler', 'ajax_start' ) );
		add_action( 'wp_ajax_prs_save_reading', array( 'Politeia_Reading_Sessions_Handler', 'ajax_save' ) );
		add_action( 'wp_ajax_prs_add_manual_session', array( 'Politeia_Reading_Sessions_Handler', 'ajax_add_manual_session' ) );
		add_action( 'wp_ajax_prs_sr_heartbeat', array( 'Politeia_Reading_Sessions_Recorder', 'ajax_heartbeat' ) );
		add_action( 'wp_ajax_prs_sr_auto_stop', array( 'Politeia_Reading_Sessions_Recorder', 'ajax_auto_stop' ) );

		// Render parcial (tabla de sesiones + paginación) para AJAX
		add_action( 'wp_ajax_prs_render_sessions', array( 'Politeia_Reading_Sessions_Render', 'ajax_render_sessions' ) );

		add_filter( 'cron_schedules', array( 'Politeia_Reading_Sessions_Recorder', 'register_cron_schedule' ) );
		add_action( 'init', array( 'Politeia_Reading_Sessions_Recorder', 'schedule_autostop_cron' ) );
		add_action( Politeia_Reading_Sessions_Recorder::CRON_HOOK, array( 'Politeia_Reading_Sessions_Recorder', 'cron_autostop' ) );
	}
}

Politeia_Reading_Sessions::init();
