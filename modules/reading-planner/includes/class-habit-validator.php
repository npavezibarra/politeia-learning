<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
	exit;
}

class Habit_Validator
{
	private const CRON_HOOK = 'politeia_reading_plan_habit_validate';

	public static function init(): void
	{
		add_action('init', array(__CLASS__, 'schedule'));
		add_action(self::CRON_HOOK, array(__CLASS__, 'run'));
	}

	public static function schedule(): void
	{
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
		}
	}

	public static function run(): void
	{
		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$rows = $wpdb->get_results(
			"SELECT id, user_id, plan_type
			 FROM {$plans_table}
			 WHERE status IN ('active', 'accepted')",
			ARRAY_A
		);

		if (empty($rows)) {
			return;
		}

		foreach ($rows as $row) {
			$plan_id = isset($row['id']) ? (int) $row['id'] : 0;
			$owner_user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
			$plan_type = strtolower((string) ($row['plan_type'] ?? ''));

			if ($plan_id <= 0 || $owner_user_id <= 0) {
				continue;
			}

			try {
				if (in_array($plan_type, array('habit', 'form_habit'), true) && class_exists('\\Politeia\\ReadingPlanner\\HabitSettlementEngine')) {
					\Politeia\ReadingPlanner\HabitSettlementEngine::settle($plan_id, $owner_user_id);
				} elseif (class_exists('\\Politeia\\ReadingPlanner\\PlanSettlementEngine')) {
					\Politeia\ReadingPlanner\PlanSettlementEngine::settle($plan_id, $owner_user_id);
				}
			} catch (\Throwable $e) {
				error_log(sprintf('[ReadingPlanner] Settlement cron failed for plan %d: %s', $plan_id, $e->getMessage()));
			}
		}
	}
}

Habit_Validator::init();
