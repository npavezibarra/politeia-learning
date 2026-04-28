<?php
if (!defined('ABSPATH')) {
	exit;
}

// Reuse the Learni dashboard styling for consistent sidebar/layout.
$creator_css_abs_path = defined('PL_PATH')
	? PL_PATH . 'modules/learni/assets/dashboard/css/creator-dashboard.css'
	: WP_PLUGIN_DIR . '/politeia-learning/modules/learni/assets/dashboard/css/creator-dashboard.css';
$creator_css_url = defined('PL_URL')
	? PL_URL . 'modules/learni/assets/dashboard/css/creator-dashboard.css'
	: WP_PLUGIN_URL . '/politeia-learning/modules/learni/assets/dashboard/css/creator-dashboard.css';
if (file_exists($creator_css_abs_path)) {
	wp_enqueue_style('pcg-creator-css', $creator_css_url, array(), filemtime($creator_css_abs_path));
}
wp_enqueue_style('dashicons');

prs_template_open();

if (!is_user_logged_in()) {
	echo '<div class="wrap"><p>' . esc_html__('You must be logged in.', 'politeia-reading') . '</p></div>';
	prs_template_close();
	return;
}

$plan_id = intval(get_query_var('plan_id'));
$allowed_sections = array('calendar', 'barchart');
$current_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'calendar';
if (!in_array($current_section, $allowed_sections, true)) {
	$current_section = 'calendar';
}

$section_titles = array(
	'calendar' => 'CALENDAR',
	'barchart' => 'BAR CHART',
);
$current_section_title = $section_titles[$current_section];
$base_url = home_url('/my-plan/' . max(0, $plan_id) . '/');

$plan_cover_url = '';
$plan_cover_title = '';
$plan_cover_author = '';
$plan_type = '';
$plan_goal_kind = 'complete_books';
$is_habit_plan = false;
$is_failed_plan = false;

$resolve_cover_url = static function ($raw_cover, $url_fallback = '') {
	$resolved_url = '';
	$raw_cover = trim((string) $raw_cover);
	$url_fallback = trim((string) $url_fallback);

	if ($url_fallback) {
		$resolved_url = esc_url_raw($url_fallback);
	}

	if ($raw_cover !== '') {
		$attachment_id = 0;
		$parsed_url = '';

		if (class_exists('PRS_Cover_Upload_Feature') && method_exists('PRS_Cover_Upload_Feature', 'parse_cover_value')) {
			$parsed_cover = PRS_Cover_Upload_Feature::parse_cover_value($raw_cover);
			$attachment_id = isset($parsed_cover['attachment_id']) ? (int) $parsed_cover['attachment_id'] : 0;
			$parsed_url = isset($parsed_cover['url']) ? esc_url_raw((string) $parsed_cover['url']) : '';
		}

		if (!$attachment_id && !$parsed_url) {
			$json = json_decode($raw_cover, true);
			if (is_array($json)) {
				if (!empty($json['attachment_id'])) {
					$attachment_id = (int) $json['attachment_id'];
				} elseif (!empty($json['id'])) {
					$attachment_id = (int) $json['id'];
				}
				if (!empty($json['url'])) {
					$parsed_url = esc_url_raw((string) $json['url']);
				}
			}
		}

		if (!$attachment_id && !$parsed_url) {
			if (is_numeric($raw_cover) && (int) $raw_cover > 0) {
				$attachment_id = (int) $raw_cover;
			} elseif (0 === strpos($raw_cover, 'attachment:')) {
				$maybe_id = (int) trim(substr($raw_cover, strlen('attachment:')));
				if ($maybe_id > 0) {
					$attachment_id = $maybe_id;
				}
			} elseif (0 === strpos($raw_cover, 'url:')) {
				$parsed_url = esc_url_raw(substr($raw_cover, 4));
			} elseif (filter_var($raw_cover, FILTER_VALIDATE_URL)) {
				$parsed_url = esc_url_raw($raw_cover);
			}
		}

		if ($parsed_url) {
			$resolved_url = $parsed_url;
		} elseif ($attachment_id > 0) {
			$attachment_url = wp_get_attachment_image_url($attachment_id, 'medium');
			if (!$attachment_url) {
				$attachment_url = wp_get_attachment_image_url($attachment_id, 'full');
			}
			if ($attachment_url) {
				$resolved_url = $attachment_url;
			}
		}
	}

	return $resolved_url;
};

if ($plan_id > 0) {
	global $wpdb;
	$plans_table = $wpdb->prefix . 'politeia_plans';
	$plan_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, user_id, plan_type, name, status FROM {$plans_table} WHERE id = %d LIMIT 1",
			$plan_id
		)
	);

	$plan_type = $plan_row ? strtolower((string) $plan_row->plan_type) : '';
	$plan_status_raw = $plan_row ? strtolower(trim((string) $plan_row->status)) : '';
	$is_habit_plan = in_array($plan_type, array('habit', 'form_habit'), true);
	$plan_goal_kind = $is_habit_plan ? 'habit' : 'complete_books';
	$is_failed_plan = in_array($plan_status_raw, array('failed', 'desisted', 'abandoned', 'cancelled'), true);

	// Cover resolution: for now reuse cover_reference if present via user_books where available.
	if (!$is_habit_plan) {
		$user_books_table = $wpdb->prefix . 'politeia_user_books';
		$finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
		$user_book_id = (int) $wpdb->get_var($wpdb->prepare("SELECT user_book_id FROM {$finish_book_table} WHERE plan_id = %d LIMIT 1", $plan_id));
		if ($user_book_id > 0) {
			$user_book_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT pages, cover_reference, cover_url FROM {$user_books_table} WHERE id = %d LIMIT 1",
					$user_book_id
				)
			);
			if ($user_book_row) {
				$plan_cover_url = $resolve_cover_url((string) $user_book_row->cover_reference, (string) $user_book_row->cover_url);
			}
		}
	}
}

$current_month = wp_date('Y-m', current_time('timestamp'), wp_timezone());
$current_month_label = wp_date('F Y', current_time('timestamp'), wp_timezone());
$today_key = current_time('Y-m-d');
?>

<div id="single-plan-template" class="single-plan-template">
	<style>
		#single-plan-template,
		#single-plan-template *:not(.dashicons):not(.dashicons-before):not(.material-symbols-outlined) {
			font-family: 'Poppins', sans-serif !important;
		}

		.single-plan-failed-banner {
			background: #000;
			color: #fff;
			padding: 12px 18px;
			font-weight: 900;
			letter-spacing: 0.2em;
			text-transform: uppercase;
			font-size: 12px;
			border-radius: 10px;
			margin-bottom: 18px;
		}

		.single-plan-redesign-shell {
			background: #f3f4f6;
			padding: 32px 16px 48px;
		}

		.single-plan-redesign-inner {
			max-width: 1024px;
			margin: 0 auto;
			display: flex;
			flex-direction: column;
			gap: 18px;
		}

		.single-plan-topmeta {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			padding: 0 8px;
			font-size: 11px;
			font-weight: 900;
			text-transform: uppercase;
			letter-spacing: 0.16em;
			color: #71717a;
		}

		.single-plan-topmeta-left {
			display: flex;
			align-items: center;
			gap: 10px;
			flex-wrap: wrap;
		}

		.single-plan-topmeta-sep {
			color: #d4d4d8;
		}

		.single-plan-hero-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 13px;
			padding: 28px;
			box-shadow: 0 1px 2px rgba(0,0,0,0.04);
		}

		.single-plan-hero-row {
			display: flex;
			gap: 28px;
			align-items: flex-start;
		}

		.single-plan-hero-cover {
			width: 160px;
			height: 224px;
			background: #fafafa;
			border: 1px solid #e5e7eb;
			border-radius: 4px;
			position: relative;
			overflow: hidden;
			flex-shrink: 0;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.single-plan-hero-cover img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.single-plan-hero-cover::before {
			content: "";
			position: absolute;
			inset: 0;
			background: linear-gradient(135deg, #fff, #f3f4f6);
		}

		.single-plan-hero-cover-placeholder {
			position: relative;
			z-index: 1;
			color: #d4d4d8;
			font-size: 42px;
		}

		.single-plan-hero-text {
			flex: 1;
			min-width: 0;
		}

		.single-plan-hero-title {
			margin: 0 0 12px;
			font-size: 54px;
			line-height: 1.04;
			font-weight: 900;
			letter-spacing: -0.04em;
			color: #111827;
		}

		.single-plan-hero-subtitle {
			margin: 0 0 22px;
			font-size: 20px;
			line-height: 1.25;
			font-weight: 600;
			color: #6b7280;
		}

		.single-plan-hero-progress {
			max-width: 420px;
		}

		.single-plan-hero-progress-line {
			display: flex;
			align-items: baseline;
			gap: 10px;
			margin-bottom: 10px;
			font-size: 11px;
			font-weight: 900;
			letter-spacing: 0.16em;
			text-transform: uppercase;
			color: #111827;
		}

		.single-plan-hero-progress-detail {
			font-weight: 700;
			letter-spacing: 0.02em;
			color: #9ca3af;
			text-transform: none;
		}

		.single-plan-hero-bar {
			width: 100%;
			height: 12px;
			border-radius: 999px;
			background: #f3f4f6;
			overflow: hidden;
		}

		.single-plan-hero-bar-fill {
			display: block;
			height: 100%;
			border-radius: 999px;
			background: #000;
			width: 0%;
			transition: width 0.4s ease;
		}

		.single-plan-tabs {
			display: flex;
			gap: 22px;
			padding: 0 8px;
			border-bottom: 1px solid rgba(229,231,235,0.7);
		}

		.single-plan-tab-btn {
			background: none;
			border: none;
			padding: 0 0 10px;
			cursor: pointer;
			font-size: 11px;
			font-weight: 900;
			text-transform: uppercase;
			letter-spacing: 0.2em;
			color: #9ca3af;
			position: relative;
		}

		.single-plan-tab-btn.is-active {
			color: #111827;
		}

		.single-plan-tab-btn::after {
			content: "";
			position: absolute;
			left: 0;
			right: 0;
			bottom: -1px;
			height: 2px;
			background: #000;
			opacity: 0;
		}

		.single-plan-tab-btn.is-active::after {
			opacity: 1;
		}

		.single-plan-content-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 13px;
			padding: 28px;
			box-shadow: 0 1px 2px rgba(0,0,0,0.04);
			min-height: 320px;
		}

		.prs-view.is-hidden { display: none; }

		/* Keep existing calendar styles minimal */
		.prs-calendar-header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom: 12px; }
		.prs-calendar-title { font-size: 18px; text-transform: uppercase; font-weight: 800; margin: 0; }
		.prs-calendar-title-row { display:flex; align-items:center; gap: 12px; }
		.prs-calendar-nav { display:flex; gap:8px; }
		.prs-calendar-nav-btn { width:20px; height:20px; border-radius:50%; border:1px solid #d6d6d6; background:#fff; display:inline-flex; align-items:center; justify-content:center; }
		.prs-calendar-nav-btn.is-disabled { opacity: .4; pointer-events:none; }
		.prs-calendar-meta { font-size: 11px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; color: #c79f32; margin-top: 8px; }
		.single-plan-upcoming-pages { font-size: 18px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color:#8f8f8f; text-align:right; }
		.single-plan-upcoming-sub { display:block; margin-top: 4px; font-size: 11px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; color:#8f8f8f; }
		.prs-weekdays { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; font-size:9px; font-weight:800; text-transform:uppercase; opacity:.5; margin-bottom: 8px; text-align:center;}
		.prs-calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:6px; }
		.prs-day-cell { position: relative; height: 64px; display:flex; align-items:center; justify-content:center; background: #fefeff; border: 1px solid rgba(168,168,168,0.28); border-radius: 6px; }
		.prs-day-cell.prs-day-empty { background: #e5e7eb; opacity: .25; border-color: transparent; }
		.prs-day-number { position:absolute; top:6px; left:8px; font-size: 12px; font-weight: 800; opacity: .28; color:#111827; }
		.prs-day-selected { width: 28px; height: 28px; display:flex; align-items:center; justify-content:center; border-radius: 999px; background: #c79f32; color:#111827; font-weight: 900; font-size: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.10); }
		.prs-day-selected.is-missed { background: #d1d5db; color: #6b7280; box-shadow: none; }
		.prs-day-selected.is-accomplished { background: #000000; color: #c79f32; box-shadow: none; }

		/* Bar chart */
		.prs-barchart-shell { display:flex; align-items:stretch; gap: 12px; margin-top: 12px; }
		.prs-barchart-y { width: 46px; display:flex; flex-direction:column; justify-content:space-between; font-size: 9px; font-weight: 900; color: #d4d4d8; }
		.prs-barchart-bars { flex: 1; display:flex; align-items:flex-end; gap: 2px; height: 220px; border-bottom: 1px solid #f3f4f6; }
		.prs-barchart-bar { flex: 1; min-width: 2px; background: #f3f4f6; border-radius: 1px 1px 0 0; position: relative; }
		.prs-barchart-bar.is-completed { background: #c79f32; }
		.prs-barchart-bar-tooltip { position:absolute; top:-28px; left:50%; transform:translateX(-50%); background:#000; color:#fff; font-size: 9px; padding: 3px 6px; border-radius: 4px; white-space: nowrap; opacity:0; pointer-events:none; transition:opacity .15s ease; }
		.prs-barchart-bar:hover .prs-barchart-bar-tooltip { opacity:1; }
		.prs-barchart-x { margin-left: 58px; margin-top: 12px; display:flex; justify-content:space-between; font-size: 9px; font-weight: 900; color:#9ca3af; letter-spacing: .15em; text-transform: uppercase; }

		@media (max-width: 900px) {
			.single-plan-hero-row { flex-direction: column; }
			.single-plan-hero-cover { width: 128px; height: 180px; }
			.single-plan-hero-title { font-size: 38px; }
			.single-plan-hero-subtitle { font-size: 16px; }
		}
	</style>

	<div class="single-plan-redesign-shell">
		<div class="single-plan-redesign-inner">
			<?php if ($is_failed_plan) : ?>
				<div class="single-plan-failed-banner"><?php echo esc_html__('ESTE PLAN HA FALLADO.', 'politeia-reading'); ?></div>
			<?php endif; ?>

			<header class="single-plan-topmeta">
				<div class="single-plan-topmeta-left">
					<span><?php echo esc_html($current_section_title); ?></span>
					<span class="single-plan-topmeta-sep">|</span>
					<span><?php echo esc_html($is_habit_plan ? __('DESAFÍO DE HÁBITO', 'politeia-reading') : __('PLAN DE LECTURA', 'politeia-reading')); ?></span>
				</div>
				<div data-role="hero-date"><?php echo esc_html(wp_date('F j', current_time('timestamp'), wp_timezone())); ?></div>
			</header>

			<section class="single-plan-hero-card">
				<div class="single-plan-hero-row">
					<div class="single-plan-hero-cover" aria-hidden="true">
						<?php if ($plan_cover_url) : ?>
							<img src="<?php echo esc_url($plan_cover_url); ?>" alt="" />
						<?php else : ?>
							<span class="dashicons dashicons-book-alt single-plan-hero-cover-placeholder"></span>
						<?php endif; ?>
					</div>
					<div class="single-plan-hero-text">
						<h1 class="single-plan-hero-title" data-role="hero-title"><?php echo esc_html__('Cargando próximo objetivo…', 'politeia-reading'); ?></h1>
						<p class="single-plan-hero-subtitle" data-role="hero-subtitle"><?php echo esc_html__('Revisando tu calendario de lectura…', 'politeia-reading'); ?></p>

						<div class="single-plan-hero-progress">
							<div class="single-plan-hero-progress-line">
								<span data-role="hero-progress-percent">0% completado</span>
								<span class="single-plan-hero-progress-detail" data-role="hero-progress-detail">(0 de 0)</span>
							</div>
							<div class="single-plan-hero-bar" aria-hidden="true">
								<span class="single-plan-hero-bar-fill" data-role="hero-progress-fill"></span>
							</div>
						</div>
					</div>
				</div>
			</section>

			<nav class="single-plan-tabs">
				<button type="button" class="single-plan-tab-btn <?php echo 'calendar' === $current_section ? 'is-active' : ''; ?>" data-role="tab" data-tab="calendar">CALENDAR</button>
				<button type="button" class="single-plan-tab-btn <?php echo 'barchart' === $current_section ? 'is-active' : ''; ?>" data-role="tab" data-tab="barchart">BAR CHART</button>
			</nav>

			<div class="single-plan-content-card">
				<div
					class="prs-plan-card single-plan-calendar-card"
					data-plan-id="<?php echo esc_attr((string) $plan_id); ?>"
					data-goal-kind="<?php echo esc_attr($plan_goal_kind); ?>"
					data-today-key="<?php echo esc_attr($today_key); ?>"
					data-initial-month="<?php echo esc_attr($current_month); ?>"
					data-session-label="<?php echo esc_attr__('Scheduled Session', 'politeia-reading'); ?>"
					data-day-format="<?php echo esc_attr__('Day %1$s of %2$s', 'politeia-reading'); ?>"
					data-month-label="<?php echo esc_attr($current_month_label); ?>"
					data-remove-label="<?php echo esc_attr__('Remove session', 'politeia-reading'); ?>"
					data-total-pages="0"
					data-pages-read="0"
					data-sessions-label="<?php echo esc_attr__('sessions', 'politeia-reading'); ?>"
					data-pages-label="<?php echo esc_attr__('pages', 'politeia-reading'); ?>"
					data-per-session-label="<?php echo esc_attr__('per session', 'politeia-reading'); ?>"
					data-missed-label="<?php echo esc_attr__('Missed', 'politeia-reading'); ?>"
					data-completed-label="<?php echo esc_attr__('Completed', 'politeia-reading'); ?>"
					data-session-dates="[]"
					data-session-items="[]"
					data-actual-sessions="[]"
					data-habit-duration="0"
					data-start-date=""
				>
					<section class="prs-view <?php echo 'calendar' === $current_section ? '' : 'is-hidden'; ?>" data-role="calendar-view">
						<div class="prs-calendar-header">
							<div>
								<div class="prs-calendar-title-row">
									<h3 class="prs-calendar-title" data-role="calendar-title"><?php echo esc_html($current_month_label); ?></h3>
									<div class="prs-calendar-nav">
										<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-prev" aria-label="<?php esc_attr_e('Previous Month', 'politeia-reading'); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6" /></svg>
										</a>
										<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-next" aria-label="<?php esc_attr_e('Next Month', 'politeia-reading'); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
										</a>
									</div>
								</div>
								<div class="prs-calendar-meta" data-role="calendar-meta">0 sessions</div>
							</div>
							<div class="single-plan-upcoming-pages" data-role="upcoming-pages">0 pages <span class="single-plan-upcoming-sub">per session</span></div>
						</div>

						<div class="prs-weekdays">
							<div><?php esc_html_e('Mon', 'politeia-reading'); ?></div><div><?php esc_html_e('Tue', 'politeia-reading'); ?></div><div><?php esc_html_e('Wed', 'politeia-reading'); ?></div><div><?php esc_html_e('Thu', 'politeia-reading'); ?></div><div><?php esc_html_e('Fri', 'politeia-reading'); ?></div><div><?php esc_html_e('Sat', 'politeia-reading'); ?></div><div><?php esc_html_e('Sun', 'politeia-reading'); ?></div>
						</div>
						<div class="prs-calendar-grid" data-role="calendar-grid"></div>
					</section>

					<section class="prs-view <?php echo 'barchart' === $current_section ? '' : 'is-hidden'; ?>" data-role="barchart-view">
						<div class="prs-calendar-header">
							<div>
								<div class="prs-calendar-title-row">
									<h3 class="prs-calendar-title"><?php echo esc_html__('Progreso del Desafío', 'politeia-reading'); ?></h3>
								</div>
								<div class="prs-calendar-meta"><?php echo esc_html__('Visualización de 48 días', 'politeia-reading'); ?></div>
							</div>
							<div class="single-plan-upcoming-pages" data-role="barchart-summary">0 <span class="single-plan-upcoming-sub"><?php echo esc_html__('días completados', 'politeia-reading'); ?></span></div>
						</div>
						<div class="prs-barchart-shell">
							<div class="prs-barchart-y" aria-hidden="true">
								<div data-role="barchart-y-max">0</div>
								<div data-role="barchart-y-mid">0</div>
								<div data-role="barchart-y-min">0</div>
							</div>
							<div class="prs-barchart-bars" data-role="barchart-bars"></div>
						</div>
						<div class="prs-barchart-x" aria-hidden="true" data-role="barchart-x"></div>
					</section>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	(function () {
		const root = document.getElementById('single-plan-template');
		if (!root) return;

		const card = root.querySelector('.prs-plan-card.single-plan-calendar-card');
		if (!card) return;

		const calendarView = card.querySelector('[data-role="calendar-view"]');
		const barView = card.querySelector('[data-role="barchart-view"]');
		const tabs = root.querySelectorAll('[data-role="tab"]');

		const heroDate = root.querySelector('[data-role="hero-date"]');
		const heroTitle = root.querySelector('[data-role="hero-title"]');
		const heroSubtitle = root.querySelector('[data-role="hero-subtitle"]');
		const heroProgressPercent = root.querySelector('[data-role="hero-progress-percent"]');
		const heroProgressDetail = root.querySelector('[data-role="hero-progress-detail"]');
		const heroProgressFill = root.querySelector('[data-role="hero-progress-fill"]');

		const grid = card.querySelector('[data-role="calendar-grid"]');
		const metaLabel = card.querySelector('[data-role="calendar-meta"]');
		const titleLabel = card.querySelector('[data-role="calendar-title"]');
		const upcomingPagesLabel = card.querySelector('[data-role="upcoming-pages"]');
		const btnPrevMonth = card.querySelector('[data-role="month-prev"]');
		const btnNextMonth = card.querySelector('[data-role="month-next"]');

		const barContainer = card.querySelector('[data-role="barchart-bars"]');
		const barSummary = card.querySelector('[data-role="barchart-summary"]');
		const yMax = card.querySelector('[data-role="barchart-y-max"]');
		const yMid = card.querySelector('[data-role="barchart-y-mid"]');
		const yMin = card.querySelector('[data-role="barchart-y-min"]');
		const xAxis = card.querySelector('[data-role="barchart-x"]');

		let totalPages = 0;
		let pagesRead = 0;
		let progressPercent = 0;
		let goalKind = card.dataset.goalKind || '';
		let habitDuration = 0;
		let habitStartDate = '';

		const todayKey = card.dataset.todayKey || '';
		let sessionDates = [];
		let sessionItems = [];
		let currentMonthKey = card.dataset.initialMonth || '';
		let minMonthKey = '';
		let maxMonthKey = '';

		const locale = document.documentElement.lang || 'es-ES';
		const pad2 = (value) => String(value).padStart(2, '0');
		const monthKey = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`;
		const parseMonthKey = (key) => {
			const parts = String(key).split('-');
			const year = parseInt(parts[0], 10);
			const month = parseInt(parts[1], 10) - 1;
			return new Date(year, month, 1);
		};
		const compareMonthKey = (a, b) => (a === b ? 0 : (a < b ? -1 : 1));
		const formatMonthYear = (date) => new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
		const formatMonthName = (date) => new Intl.DateTimeFormat(locale, { month: 'long' }).format(date);
		const dateKeyToUtc = (dateKey) => {
			const parts = String(dateKey || '').split('-').map((part) => parseInt(part, 10));
			if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) return null;
			return Date.UTC(parts[0], parts[1] - 1, parts[2]);
		};
		const daysBetweenKeys = (fromKey, toKey) => {
			const fromUtc = dateKeyToUtc(fromKey);
			const toUtc = dateKeyToUtc(toKey);
			if (null === fromUtc || null === toUtc) return 0;
			return Math.round((toUtc - fromUtc) / 86400000);
		};

		const numberFormat = (value) => {
			try { return new Intl.NumberFormat(locale).format(value); } catch { return String(value); }
		};

		const formatTopbarDate = (date) => {
			const parts = new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric' }).formatToParts(date);
			const monthPart = parts.find((part) => part.type === 'month');
			const dayPart = parts.find((part) => part.type === 'day');
			const month = monthPart && monthPart.value ? monthPart.value : '';
			const day = dayPart && dayPart.value ? dayPart.value : '';
			if (!month || !day) return '';
			return `${month.charAt(0).toUpperCase()}${month.slice(1)} ${day}`;
		};

		const refreshMonthBounds = () => {
			if (!sessionDates.length) {
				if (!currentMonthKey) currentMonthKey = monthKey(new Date());
				minMonthKey = currentMonthKey;
				maxMonthKey = currentMonthKey;
				return;
			}
			const monthKeys = sessionDates.map((d) => d.slice(0, 7)).sort();
			minMonthKey = monthKeys[0];
			maxMonthKey = monthKeys[monthKeys.length - 1];
			if (!currentMonthKey) currentMonthKey = minMonthKey;
			if (compareMonthKey(currentMonthKey, minMonthKey) < 0) currentMonthKey = minMonthKey;
			if (compareMonthKey(currentMonthKey, maxMonthKey) > 0) currentMonthKey = maxMonthKey;
		};

		const getStatusByDate = (dateStr) => {
			const item = sessionItems.find((entry) => entry && entry.date === dateStr);
			return item && item.status ? item.status : 'planned';
		};

		const getMonthSessions = (monthKeyValue) => sessionDates.filter((d) => d.startsWith(monthKeyValue)).sort();

		const getPlannedRangeByDate = (dateStr) => {
			const item = sessionItems.find((entry) => entry && entry.date === dateStr);
			if (!item) return null;
			const start = typeof item.planned_start_page === 'number' ? item.planned_start_page : null;
			const end = typeof item.planned_end_page === 'number' ? item.planned_end_page : null;
			if (!start || !end || end < start) return null;
			return { start, end };
		};

		const getExpectedPagesForSession = (item) => {
			if (!item) return 0;
			if (goalKind === 'habit' && typeof item.expectedPages === 'number' && item.expectedPages > 0) return item.expectedPages;
			const range = getPlannedRangeByDate(item.date);
			return range ? Math.max(0, range.end - range.start + 1) : 0;
		};

		const updateHero = () => {
			if (heroDate) {
				const renderedDate = formatTopbarDate(new Date());
				if (renderedDate) heroDate.textContent = renderedDate;
			}

			const safeProgress = Math.max(0, Math.min(100, parseInt(progressPercent, 10) || 0));
			if (heroProgressPercent) heroProgressPercent.textContent = `${safeProgress}% completado`;

			if (heroProgressDetail) {
				if (goalKind === 'habit') {
					heroProgressDetail.textContent = `(${numberFormat(Math.max(0, pagesRead))} de ${numberFormat(Math.max(0, totalPages))})`;
				} else {
					heroProgressDetail.textContent = `(${numberFormat(Math.max(0, pagesRead))} de ${numberFormat(Math.max(0, totalPages))})`;
				}
			}
			if (heroProgressFill) heroProgressFill.style.width = `${safeProgress}%`;

			if (!heroTitle || !heroSubtitle) return;

			const nextPlanned = sessionItems
				.filter((entry) => entry && entry.status === 'planned' && entry.date && entry.date >= (todayKey || ''))
				.sort((a, b) => String(a.date).localeCompare(String(b.date)))[0] || null;

			if (!nextPlanned) {
				heroTitle.textContent = 'Sin próximas sesiones';
				heroSubtitle.textContent = 'Aún no hay sesiones programadas para este plan.';
				return;
			}

			const daysUntil = daysBetweenKeys(todayKey, nextPlanned.date);
			let prefix = 'Hoy';
			if (daysUntil === 1) prefix = 'Mañana';
			else if (daysUntil > 1) prefix = `En ${daysUntil} días más`;

			const expectedPages = getExpectedPagesForSession(nextPlanned);
			heroTitle.textContent = expectedPages > 0 ? `${prefix}: ${numberFormat(expectedPages)} páginas` : `${prefix}: sesión`;

			const range = goalKind === 'habit' ? null : getPlannedRangeByDate(nextPlanned.date);
			if (range && range.start && range.end) {
				heroSubtitle.textContent = `de la página ${numberFormat(range.start)} a la ${numberFormat(range.end)}`;
			} else {
				heroSubtitle.textContent = 'Sigue tu próxima sesión desde el calendario de abajo.';
			}
		};

		const updateMeta = () => {
			if (!metaLabel) return;
			const sessionCount = getMonthSessions(currentMonthKey).length;
			metaLabel.textContent = `${sessionCount} sessions`;
		};

		const updateTitle = () => {
			if (!titleLabel) return;
			titleLabel.textContent = formatMonthYear(parseMonthKey(currentMonthKey));
		};

		const updateNavState = () => {
			if (btnPrevMonth) btnPrevMonth.classList.toggle('is-disabled', compareMonthKey(currentMonthKey, minMonthKey) <= 0);
			if (btnNextMonth) btnNextMonth.classList.toggle('is-disabled', compareMonthKey(currentMonthKey, maxMonthKey) >= 0);
		};

		const updateUpcomingPages = () => {
			if (!upcomingPagesLabel) return;
			const nextPlanned = sessionItems
				.filter((entry) => entry && entry.status === 'planned' && entry.date && entry.date >= (todayKey || ''))
				.sort((a, b) => String(a.date).localeCompare(String(b.date)))[0] || null;
			const expectedPages = getExpectedPagesForSession(nextPlanned);
			const valueLabel = expectedPages > 0 ? `${expectedPages} pages` : `0 pages`;
			upcomingPagesLabel.innerHTML = `${valueLabel}<span class="single-plan-upcoming-sub">per session</span>`;
		};

		const fetchPlanDetails = () => {
			const planId = card.dataset.planId;
			if (!planId) return Promise.resolve();
			const timestamp = new Date().getTime();
			return fetch(`/wp-json/politeia/v1/reading-plan/${planId}?t=${timestamp}`, { headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' } })
				.then((res) => res.json())
				.then((data) => {
					if (!data || !data.success) return;
					totalPages = parseInt(data.total_pages, 10) || 0;
					pagesRead = parseInt(data.pages_read, 10) || 0;
					progressPercent = parseInt(data.progress, 10) || 0;
					sessionDates = Array.isArray(data.session_dates) ? data.session_dates : [];
					sessionItems = Array.isArray(data.session_items) ? data.session_items : [];
					if (data.goal_kind) goalKind = String(data.goal_kind);
					habitDuration = parseInt(data.habit_duration, 10) || 0;
					habitStartDate = data.start_date ? String(data.start_date) : '';
					refreshMonthBounds();
					updateMeta();
					updateTitle();
					updateNavState();
					updateUpcomingPages();
					updateHero();
					renderCalendar();
					renderBarChart();
				})
				.catch(() => {});
		};

		const renderCalendar = () => {
			if (!grid) return;
			refreshMonthBounds();
			const viewDate = parseMonthKey(currentMonthKey);
			const daysCount = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
			const startOffset = (new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay() + 6) % 7;
			grid.innerHTML = '';
			const monthSessions = getMonthSessions(currentMonthKey);
			const sortedDays = monthSessions.map((d) => parseInt(d.split('-')[2], 10));

			updateMeta();
			updateTitle();
			updateNavState();

			for (let i = 0; i < startOffset; i++) {
				const empty = document.createElement('div');
				empty.className = 'prs-day-cell prs-day-empty';
				grid.appendChild(empty);
			}

			for (let day = 1; day <= daysCount; day++) {
				const cell = document.createElement('div');
				cell.className = 'prs-day-cell';
				cell.dataset.day = String(day);

				const label = document.createElement('span');
				label.className = 'prs-day-number';
				label.textContent = String(day);
				cell.appendChild(label);

				if (sortedDays.includes(day)) {
					const targetDate = `${currentMonthKey}-${pad2(day)}`;
					const status = getStatusByDate(targetDate);
					const mark = document.createElement('div');
					mark.className = `prs-day-selected${status === 'missed' ? ' is-missed' : ''}${status === 'accomplished' ? ' is-accomplished' : ''}`;
					mark.textContent = status === 'missed' ? '' : String(sortedDays.indexOf(day) + 1);
					cell.appendChild(mark);
				}

				grid.appendChild(cell);
			}
		};

		const renderBarChart = () => {
			if (!barContainer) return;
			const duration = habitDuration > 0 ? habitDuration : 48;
			const completedDays = sessionItems.filter((it) => it && it.status === 'accomplished').length;
			if (barSummary) {
				barSummary.innerHTML = `${completedDays} <span class="single-plan-upcoming-sub">días completados</span>`;
			}

			const maxPages = Math.max(1, ...sessionItems.map((it) => (it && typeof it.expectedPages === 'number' ? it.expectedPages : 0)));
			if (yMax) yMax.textContent = `${maxPages} PAG`;
			if (yMid) yMid.textContent = `${Math.round(maxPages / 2)} PAG`;
			if (yMin) yMin.textContent = `0 PAG`;

			barContainer.innerHTML = '';
			for (let i = 1; i <= duration; i++) {
				const item = sessionItems[i - 1] || null;
				const expected = item && typeof item.expectedPages === 'number' ? item.expectedPages : 0;
				const isCompleted = item && item.status === 'accomplished';
				const height = expected > 0 ? Math.max(6, Math.round((expected / maxPages) * 100)) : 6;

				const bar = document.createElement('div');
				bar.className = `prs-barchart-bar${isCompleted ? ' is-completed' : ''}`;
				bar.style.height = `${height}%`;

				const tip = document.createElement('div');
				tip.className = 'prs-barchart-bar-tooltip';
				tip.textContent = `Día ${i}: ${Math.round(expected)} pág`;
				bar.appendChild(tip);

				barContainer.appendChild(bar);
			}

			if (xAxis) {
				xAxis.innerHTML = '<span>DÍA 01</span><span>DÍA 12</span><span>DÍA 24</span><span>DÍA 36</span><span>DÍA 48</span>';
			}
		};

		const setTab = (tab) => {
			tabs.forEach((btn) => btn.classList.toggle('is-active', btn.dataset.tab === tab));
			if (calendarView) calendarView.classList.toggle('is-hidden', tab !== 'calendar');
			if (barView) barView.classList.toggle('is-hidden', tab !== 'barchart');

			const url = new URL(window.location.href);
			url.searchParams.set('section', tab);
			window.history.replaceState({}, '', url.toString());
		};

		tabs.forEach((btn) => {
			btn.addEventListener('click', () => setTab(btn.dataset.tab));
		});

		if (btnPrevMonth) {
			btnPrevMonth.addEventListener('click', (event) => {
				event.preventDefault();
				if (compareMonthKey(currentMonthKey, minMonthKey) <= 0) return;
				const d = parseMonthKey(currentMonthKey);
				d.setMonth(d.getMonth() - 1);
				currentMonthKey = monthKey(d);
				renderCalendar();
				updateMeta();
				updateTitle();
				updateNavState();
			});
		}
		if (btnNextMonth) {
			btnNextMonth.addEventListener('click', (event) => {
				event.preventDefault();
				if (compareMonthKey(currentMonthKey, maxMonthKey) >= 0) return;
				const d = parseMonthKey(currentMonthKey);
				d.setMonth(d.getMonth() + 1);
				currentMonthKey = monthKey(d);
				renderCalendar();
				updateMeta();
				updateTitle();
				updateNavState();
			});
		}

		setTab('<?php echo esc_js($current_section); ?>');
		fetchPlanDetails();
	})();
</script>

<?php
prs_template_close();
