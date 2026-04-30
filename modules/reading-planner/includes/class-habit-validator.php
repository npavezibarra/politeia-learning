<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
	exit;
}

class Habit_Validator
{
	private const CRON_HOOK = 'politeia_reading_plan_habit_validate';
	private const LOCK_TRANSIENT = 'politeia_reading_plan_habit_validate_lock';
	private const CURSOR_OPTION = 'politeia_reading_plan_habit_validate_cursor';
	private const RESCHEDULE_OPTION = 'politeia_reading_plan_habit_validate_reschedule';
	private const LAST_RUN_OPTION = 'politeia_reading_plan_habit_validate_last_run';

	public static function init(): void
	{
		add_action('init', array(__CLASS__, 'register_schedule'));
		add_action('init', array(__CLASS__, 'schedule'));
		add_action(self::CRON_HOOK, array(__CLASS__, 'run'));
	}

	public static function register_schedule(): void
	{
		add_filter(
			'cron_schedules',
			static function ($schedules) {
				if (!is_array($schedules)) {
					$schedules = array();
				}
				if (!isset($schedules['politeia_reading_plan_5min'])) {
					$schedules['politeia_reading_plan_5min'] = array(
						'interval' => 5 * MINUTE_IN_SECONDS,
						'display'  => 'Every 5 minutes (Politeia Reading Planner)',
					);
				}
				return $schedules;
			}
		);
	}

	public static function schedule(): void
	{
		$force_reschedule = (string) get_option(self::RESCHEDULE_OPTION, '') === '1';
		if ($force_reschedule && function_exists('wp_clear_scheduled_hook')) {
			wp_clear_scheduled_hook(self::CRON_HOOK);
			delete_option(self::RESCHEDULE_OPTION);
		}

		if (!wp_next_scheduled(self::CRON_HOOK)) {
			// Prefer small, frequent batches over an hourly spike.
			wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'politeia_reading_plan_5min', self::CRON_HOOK);
		}
	}

	public static function run(): void
	{
		$run_started_at = microtime(true);
		$log_enabled = (bool) apply_filters('politeia_reading_plan_cron_log_enabled', true, self::CRON_HOOK);
		$slow_plan_threshold_sec = (float) apply_filters('politeia_reading_plan_cron_slow_plan_threshold_sec', 1.5);

		if (get_transient(self::LOCK_TRANSIENT)) {
			return;
		}
		set_transient(self::LOCK_TRANSIENT, '1', 10 * MINUTE_IN_SECONDS);

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		$cursor = (int) get_option(self::CURSOR_OPTION, 0);
		$batch_size = (int) apply_filters('politeia_reading_plan_settlement_batch_size', 25);
		if ($batch_size <= 0) {
			$batch_size = 25;
		}

		$stats = array(
			'hook'                 => self::CRON_HOOK,
			'started_at_gmt'       => function_exists('current_time') ? (string) current_time('mysql', true) : gmdate('Y-m-d H:i:s'),
			'started_at_local'     => function_exists('current_time') ? (string) current_time('mysql', false) : date('Y-m-d H:i:s'),
			'cursor_in'            => $cursor,
			'batch_size'           => $batch_size,
			'plans_fetched'        => 0,
			'plans_skipped_no_stale' => 0,
			'plans_settled'        => 0,
			'plans_failed'         => 0,
			'cursor_out'           => $cursor,
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, plan_type
				 FROM {$plans_table}
				 WHERE status IN ('active', 'accepted')
				   AND id > %d
				 ORDER BY id ASC
				 LIMIT %d",
				$cursor,
				$batch_size
			),
			ARRAY_A
		);

		if (empty($rows)) {
			// Reset cursor after a full pass.
			update_option(self::CURSOR_OPTION, 0, false);
			$stats['cursor_out'] = 0;
			$stats['plans_fetched'] = 0;
			$stats['duration_ms'] = (int) round((microtime(true) - $run_started_at) * 1000);
			$stats['wpdb_num_queries'] = isset($wpdb->num_queries) ? (int) $wpdb->num_queries : null;
			update_option(self::LAST_RUN_OPTION, $stats, false);
			if ($log_enabled) {
				error_log('[ReadingPlanner][cron] ' . wp_json_encode($stats));
			}
			delete_transient(self::LOCK_TRANSIENT);
			return;
		}

		$stats['plans_fetched'] = count($rows);

		foreach ($rows as $row) {
			$plan_id = isset($row['id']) ? (int) $row['id'] : 0;
			$owner_user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
			$plan_type = strtolower((string) ($row['plan_type'] ?? ''));

			if ($plan_id <= 0 || $owner_user_id <= 0) {
				continue;
			}

			try {
				// Fast pre-check: only run settlement when there are stale planned sessions for this plan.
				$user_tz_str = get_user_meta($owner_user_id, 'timezone', true);
				if (!$user_tz_str) {
					$timezone = wp_timezone();
				} else {
					try {
						$timezone = new \DateTimeZone((string) $user_tz_str);
					} catch (\Exception $e) {
						$timezone = wp_timezone();
					}
				}

				$today_str = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
				$cutoff_dt = $today_str . ' 00:00:00';
				$has_stale = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1
						 FROM {$sessions_table}
						 WHERE plan_id = %d
						   AND status = 'planned'
						   AND planned_start_datetime < %s
						 LIMIT 1",
						$plan_id,
						$cutoff_dt
					)
				);
				if ($has_stale !== 1) {
					$stats['plans_skipped_no_stale'] = (int) $stats['plans_skipped_no_stale'] + 1;
					$cursor = max($cursor, $plan_id);
					continue;
				}

				$plan_started_at = microtime(true);
				if (in_array($plan_type, array('habit', 'form_habit'), true) && class_exists('\\Politeia\\ReadingPlanner\\HabitSettlementEngine')) {
					\Politeia\ReadingPlanner\HabitSettlementEngine::settle($plan_id, $owner_user_id);
				} elseif (class_exists('\\Politeia\\ReadingPlanner\\PlanSettlementEngine')) {
					\Politeia\ReadingPlanner\PlanSettlementEngine::settle($plan_id, $owner_user_id);
				}
				$stats['plans_settled'] = (int) $stats['plans_settled'] + 1;

				$plan_elapsed = microtime(true) - $plan_started_at;
				if ($log_enabled && $slow_plan_threshold_sec > 0 && $plan_elapsed >= $slow_plan_threshold_sec) {
					error_log(
						'[ReadingPlanner][cron][slow_plan] ' . wp_json_encode(
							array(
								'hook'             => self::CRON_HOOK,
								'plan_id'          => $plan_id,
								'user_id'          => $owner_user_id,
								'plan_type'        => $plan_type,
								'elapsed_ms'       => (int) round($plan_elapsed * 1000),
								'started_at_gmt'   => function_exists('current_time') ? (string) current_time('mysql', true) : gmdate('Y-m-d H:i:s'),
								'started_at_local' => function_exists('current_time') ? (string) current_time('mysql', false) : date('Y-m-d H:i:s'),
							)
						)
					);
				}
			} catch (\Throwable $e) {
				$stats['plans_failed'] = (int) $stats['plans_failed'] + 1;
				error_log(sprintf('[ReadingPlanner] Settlement cron failed for plan %d: %s', $plan_id, $e->getMessage()));
			}

			$cursor = max($cursor, $plan_id);
		}

		update_option(self::CURSOR_OPTION, $cursor, false);
		$stats['cursor_out'] = $cursor;
		$stats['duration_ms'] = (int) round((microtime(true) - $run_started_at) * 1000);
		$stats['wpdb_num_queries'] = isset($wpdb->num_queries) ? (int) $wpdb->num_queries : null;
		update_option(self::LAST_RUN_OPTION, $stats, false);
		if ($log_enabled) {
			error_log('[ReadingPlanner][cron] ' . wp_json_encode($stats));
		}
		delete_transient(self::LOCK_TRANSIENT);
	}
}

Habit_Validator::init();
