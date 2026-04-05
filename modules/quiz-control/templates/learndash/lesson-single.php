<?php
/**
 * LearnDash lesson single template for Politeia.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

pl_template_open();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$lesson_id    = get_the_ID();
		$user_id      = get_current_user_id();
		$lesson_title = get_the_title( $lesson_id );
		$lesson_url   = get_permalink( $lesson_id );

		$course_id = function_exists( 'learndash_get_course_id' ) ? (int) learndash_get_course_id( $lesson_id ) : 0;
		$course_title = $course_id > 0 ? get_the_title( $course_id ) : '';
		$course_url   = $course_id > 0 ? get_permalink( $course_id ) : home_url( '/' );

		$course_lessons = ( $course_id > 0 && function_exists( 'learndash_get_course_lessons_list' ) ) ? (array) learndash_get_course_lessons_list( $course_id, $user_id, array( 'num' => -1 ) ) : array();
		$lesson_count   = count( $course_lessons );

		$active_index      = 0;
		$completed_count   = 0;
		$is_current_done   = false;
		$previous_lesson   = '';
		$next_lesson       = '';
		$first_lesson_url  = '';

		foreach ( $course_lessons as $index => $lesson_item ) {
			$lesson_post = $lesson_item['post'] ?? null;
			if ( ! $lesson_post instanceof WP_Post ) {
				continue;
			}

			$current_lesson_id = (int) $lesson_post->ID;
			$current_url       = ! empty( $lesson_item['permalink'] ) ? (string) $lesson_item['permalink'] : (string) get_permalink( $current_lesson_id );
			$current_status    = (string) ( $lesson_item['status'] ?? '' );

			if ( '' === $first_lesson_url ) {
				$first_lesson_url = $current_url;
			}

			if ( 'completed' === $current_status ) {
				$completed_count++;
			}

			if ( $current_lesson_id === $lesson_id ) {
				$active_index    = (int) $index;
				$is_current_done = ( 'completed' === $current_status );
				$previous_lesson = isset( $course_lessons[ $index - 1 ] ) ? ( ! empty( $course_lessons[ $index - 1 ]['permalink'] ) ? (string) $course_lessons[ $index - 1 ]['permalink'] : (string) get_permalink( (int) ( $course_lessons[ $index - 1 ]['post']->ID ?? 0 ) ) ) : '';
				$next_lesson     = isset( $course_lessons[ $index + 1 ] ) ? ( ! empty( $course_lessons[ $index + 1 ]['permalink'] ) ? (string) $course_lessons[ $index + 1 ]['permalink'] : (string) get_permalink( (int) ( $course_lessons[ $index + 1 ]['post']->ID ?? 0 ) ) ) : '';
			}
		}

		$progress_percent = $lesson_count > 0 ? (int) round( ( $completed_count / $lesson_count ) * 100 ) : 0;

		$lesson_access_from = 0;
		if ( $course_id > 0 && function_exists( 'ld_lesson_access_from' ) ) {
			$lesson_access_from = (int) ld_lesson_access_from( $lesson_id, $user_id, $course_id );
		}

		$lesson_available_after = '';
		if ( $lesson_access_from > 0 ) {
			$lesson_available_after = function_exists( 'learndash_adjust_date_time_display' )
				? (string) learndash_adjust_date_time_display( $lesson_access_from )
				: date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lesson_access_from );
		}

		$lesson_is_scheduled = $lesson_access_from > 0;
		$video_enabled = '';
		$video_url = '';

		if ( function_exists( 'learndash_get_setting' ) ) {
			$video_enabled = (string) learndash_get_setting( $lesson_id, 'lesson_video_enabled' );
			$video_url     = (string) learndash_get_setting( $lesson_id, 'lesson_video_url' );
		}

		if ( '' === trim( $video_url ) ) {
			$lesson_meta = get_post_meta( $lesson_id, '_sfwd-lessons', true );
			if ( is_array( $lesson_meta ) ) {
				if ( isset( $lesson_meta['sfwd-lessons_lesson_video_url'] ) && is_scalar( $lesson_meta['sfwd-lessons_lesson_video_url'] ) ) {
					$video_url = (string) $lesson_meta['sfwd-lessons_lesson_video_url'];
				} elseif ( isset( $lesson_meta['lesson_video_url'] ) && is_scalar( $lesson_meta['lesson_video_url'] ) ) {
					$video_url = (string) $lesson_meta['lesson_video_url'];
				}

				if ( '' === $video_enabled && isset( $lesson_meta['sfwd-lessons_lesson_video_enabled'] ) && is_scalar( $lesson_meta['sfwd-lessons_lesson_video_enabled'] ) ) {
					$video_enabled = (string) $lesson_meta['sfwd-lessons_lesson_video_enabled'];
				}
			}
		}

		$video_url         = trim( $video_url );
		$video_is_enabled  = ( '' !== $video_enabled && 'off' !== $video_enabled && '0' !== $video_enabled );
		$video_embed_html  = '';
		$video_settings    = function_exists( 'learndash_get_setting' ) ? (array) learndash_get_setting( $lesson_id ) : array();
		$video_settings['lesson_video_enabled'] = 'on';
		$video_settings['lesson_video_url']     = $video_url;

		if ( ! $lesson_is_scheduled && $video_is_enabled && '' !== $video_url && class_exists( 'Learndash_Course_Video' ) ) {
			$video_embed_html = trim( (string) \Learndash_Course_Video::get_instance()->add_video_to_content( '', $post, $video_settings ) );
		}

		$mark_complete_html = '';
		if ( shortcode_exists( 'learndash_mark_complete' ) ) {
			$mark_complete_html = trim( (string) do_shortcode( '[learndash_mark_complete]' ) );
		}

		$lesson_content = apply_filters( 'the_content', (string) get_post_field( 'post_content', $lesson_id ) );
		$lesson_status  = function_exists( 'learndash_is_lesson_complete' ) ? (bool) learndash_is_lesson_complete( $user_id, $lesson_id, $course_id ) : $is_current_done;
		$back_url       = $course_id > 0 ? $course_url : home_url( '/' );

		$prev_url = $previous_lesson !== '' ? $previous_lesson : $lesson_url;
		$next_url = $next_lesson !== '' ? $next_lesson : $lesson_url;

		$video_shell_classes = 'pl-ld-lesson-video';
		if ( '' !== $video_embed_html ) {
			$video_shell_classes .= ' has-video';
		}

		?>

		<style>
			.pl-ld-lesson-page {
				max-width: var(--wp--style--global--wide-size, 1280px);
				margin: 0 auto;
				padding: clamp(24px, 4vw, 48px);
				color: var(--wp--preset--color--contrast, #111827);
			}

			.pl-ld-lesson-layout {
				display: grid;
				grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
				gap: 28px;
				align-items: start;
			}

			.pl-ld-lesson-sidebar,
			.pl-ld-lesson-main {
				background: var(--wp--preset--color--base, #ffffff);
				border: 1px solid rgba(15, 23, 42, 0.08);
				border-radius: 24px;
				box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
			}

			.pl-ld-lesson-sidebar {
				position: sticky;
				top: 24px;
				overflow: hidden;
			}

			.pl-ld-lesson-sidebar__inner,
			.pl-ld-lesson-main__inner {
				padding: clamp(22px, 3vw, 32px);
			}

			.pl-ld-lesson-back {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				font-size: 12px;
				font-weight: 700;
				letter-spacing: .14em;
				text-transform: uppercase;
				text-decoration: none;
				color: rgba(15, 23, 42, 0.62);
				margin-bottom: 18px;
			}

			.pl-ld-lesson-back:hover,
			.pl-ld-lesson-back:focus {
				color: rgba(15, 23, 42, 0.9);
			}

			.pl-ld-lesson-course-title,
			.pl-ld-lesson-title {
				margin: 0;
				letter-spacing: -.03em;
				line-height: 1.05;
			}

			.pl-ld-lesson-course-title {
				font-size: 1.3rem;
				margin-bottom: 20px;
			}

			.pl-ld-lesson-title {
				font-size: clamp(2rem, 4vw, 3.6rem);
			}

			.pl-ld-progress {
				display: grid;
				gap: 8px;
				margin: 0 0 22px;
			}

			.pl-ld-progress__row {
				display: flex;
				justify-content: space-between;
				font-size: 12px;
				font-weight: 700;
				letter-spacing: .08em;
				text-transform: uppercase;
				color: rgba(15, 23, 42, 0.56);
			}

			.pl-ld-progress__bar {
				height: 8px;
				border-radius: 999px;
				background: rgba(15, 23, 42, 0.08);
				overflow: hidden;
			}

			.pl-ld-progress__fill {
				height: 100%;
				border-radius: 999px;
				background: linear-gradient(135deg, #111827, #8A6B1E);
			}

			.pl-ld-lesson-list {
				margin: 0;
				padding: 0;
				list-style: none;
			}

			.pl-ld-lesson-item {
				display: flex;
				justify-content: space-between;
				gap: 14px;
				padding: 14px 0;
				border-top: 1px solid rgba(15, 23, 42, 0.08);
				color: inherit;
				text-decoration: none;
			}

			.pl-ld-lesson-item:first-child {
				border-top: 0;
				padding-top: 0;
			}

			.pl-ld-lesson-item:hover,
			.pl-ld-lesson-item:focus {
				color: inherit;
			}

			.pl-ld-lesson-item.is-active .pl-ld-lesson-item__title {
				font-weight: 700;
			}

			.pl-ld-lesson-item__title {
				margin: 0;
				font-size: 14px;
				line-height: 1.45;
			}

			.pl-ld-lesson-item__meta {
				margin: 4px 0 0;
				font-size: 12px;
				opacity: .68;
			}

			.pl-ld-lesson-pill {
				flex: 0 0 auto;
				padding: 6px 10px;
				border-radius: 999px;
				background: rgba(15, 23, 42, 0.05);
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: .08em;
			}

			.pl-ld-lesson-pill.is-complete {
				background: rgba(34, 197, 94, 0.12);
				color: rgb(21, 128, 61);
			}

			.pl-ld-lesson-topbar {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 18px;
				flex-wrap: wrap;
				margin-bottom: 20px;
			}

			.pl-ld-lesson-nav {
				display: inline-flex;
				align-items: center;
				gap: 10px;
			}

			.pl-ld-lesson-nav a,
			.pl-ld-lesson-complete a,
			.pl-ld-lesson-complete button {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				padding: 12px 14px;
				border-radius: 12px;
				border: 1px solid rgba(15, 23, 42, 0.08);
				background: var(--wp--preset--color--base, #ffffff);
				color: inherit;
				text-decoration: none;
				font-size: 13px;
				font-weight: 700;
			}

			.pl-ld-lesson-nav a:hover,
			.pl-ld-lesson-nav a:focus {
				background: rgba(15, 23, 42, 0.03);
			}

			.pl-ld-lesson-complete .learndash_mark_complete_button {
				background: var(--wp--preset--color--contrast, #111827);
				color: var(--wp--preset--color--base, #ffffff);
				border-color: transparent;
			}

			.pl-ld-lesson-complete .learndash_mark_complete_button:hover,
			.pl-ld-lesson-complete .learndash_mark_complete_button:focus {
				color: var(--wp--preset--color--base, #ffffff);
			}

			.pl-ld-lesson-notice {
				padding: 22px;
				border-radius: 18px;
				background: rgba(15, 23, 42, 0.03);
				border: 1px solid rgba(15, 23, 42, 0.08);
				margin: 24px 0;
			}

			.pl-ld-lesson-notice strong {
				display: block;
				margin-bottom: 8px;
			}

			.pl-ld-lesson-video {
				border-radius: 22px;
				overflow: hidden;
				background: #0b1220;
				margin: 24px 0;
				min-height: 260px;
			}

			.pl-ld-lesson-video.has-video {
				aspect-ratio: 16 / 9;
			}

			.pl-ld-lesson-content {
				font-size: 16px;
				line-height: 1.8;
			}

			.pl-ld-lesson-content > *:first-child {
				margin-top: 0;
			}

			.pl-ld-lesson-content > *:last-child {
				margin-bottom: 0;
			}

			@media (max-width: 960px) {
				.pl-ld-lesson-layout {
					grid-template-columns: 1fr;
				}

				.pl-ld-lesson-sidebar {
					position: static;
				}
			}
		</style>

		<div class="pl-ld-lesson-page">
			<div class="pl-ld-lesson-layout">
				<aside class="pl-ld-lesson-sidebar">
					<div class="pl-ld-lesson-sidebar__inner">
						<a class="pl-ld-lesson-back" href="<?php echo esc_url( $back_url ); ?>">
							<span aria-hidden="true">←</span>
							<span><?php echo esc_html__( 'Volver al curso', 'politeia-learning' ); ?></span>
						</a>

						<?php if ( '' !== $course_title ) : ?>
							<h2 class="pl-ld-lesson-course-title"><?php echo esc_html( $course_title ); ?></h2>
						<?php endif; ?>

						<div class="pl-ld-progress">
							<div class="pl-ld-progress__row">
								<span><?php echo esc_html__( 'Progreso', 'politeia-learning' ); ?></span>
								<span><?php echo esc_html( $progress_percent . '%' ); ?></span>
							</div>
							<div class="pl-ld-progress__bar">
								<div class="pl-ld-progress__fill" style="<?php echo esc_attr( 'width:' . $progress_percent . '%' ); ?>"></div>
							</div>
						</div>

						<?php if ( $lesson_count > 0 ) : ?>
							<nav aria-label="<?php echo esc_attr__( 'Lecciones del curso', 'politeia-learning' ); ?>">
								<ul class="pl-ld-lesson-list">
									<?php foreach ( $course_lessons as $lesson_item ) : ?>
										<?php
										$lesson_post = $lesson_item['post'] ?? null;
										if ( ! $lesson_post instanceof WP_Post ) {
											continue;
										}

										$item_id      = (int) $lesson_post->ID;
										$item_title   = get_the_title( $item_id );
										$item_url     = ! empty( $lesson_item['permalink'] ) ? (string) $lesson_item['permalink'] : (string) get_permalink( $item_id );
										$is_active    = ( $item_id === $lesson_id );
										$is_completed = ( 'completed' === (string) ( $lesson_item['status'] ?? '' ) );
										?>
										<li>
											<a class="pl-ld-lesson-item <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>">
												<div>
													<p class="pl-ld-lesson-item__title"><?php echo esc_html( $item_title ); ?></p>
													<p class="pl-ld-lesson-item__meta">
														<?php echo esc_html( $is_active ? __( 'Lección actual', 'politeia-learning' ) : __( 'Lección del curso', 'politeia-learning' ) ); ?>
													</p>
												</div>
												<span class="pl-ld-lesson-pill <?php echo $is_completed ? 'is-complete' : ''; ?>">
													<?php echo esc_html( $is_completed ? __( 'Hecha', 'politeia-learning' ) : __( 'Abrir', 'politeia-learning' ) ); ?>
												</span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</nav>
						<?php endif; ?>
					</div>
				</aside>

				<main class="pl-ld-lesson-main" role="main">
					<div class="pl-ld-lesson-main__inner">
						<div class="pl-ld-lesson-topbar">
							<div class="pl-ld-lesson-nav">
								<a href="<?php echo esc_url( $prev_url ); ?>" aria-label="<?php echo esc_attr__( 'Lección anterior', 'politeia-learning' ); ?>">
									←
								</a>
								<a href="<?php echo esc_url( $next_url ); ?>" aria-label="<?php echo esc_attr__( 'Siguiente lección', 'politeia-learning' ); ?>">
									→
								</a>
							</div>

							<div class="pl-ld-lesson-complete">
								<?php if ( $lesson_status ) : ?>
									<button type="button" disabled><?php echo esc_html__( 'Finalizada', 'politeia-learning' ); ?></button>
								<?php elseif ( '' !== $mark_complete_html ) : ?>
									<?php echo $mark_complete_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</div>
						</div>

						<header>
							<h1 class="pl-ld-lesson-title"><?php echo esc_html( $lesson_title ); ?></h1>
						</header>

						<?php if ( $lesson_is_scheduled && '' !== $lesson_available_after ) : ?>
							<div class="pl-ld-lesson-notice">
								<strong><?php echo esc_html__( 'Lección programada', 'politeia-learning' ); ?></strong>
								<p><?php echo esc_html( sprintf( __( 'Disponible después de %s', 'politeia-learning' ), $lesson_available_after ) ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( ! $lesson_is_scheduled && '' !== $video_embed_html ) : ?>
							<div class="<?php echo esc_attr( $video_shell_classes ); ?>">
								<?php echo $video_embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

						<?php if ( ! $lesson_is_scheduled ) : ?>
							<div class="pl-ld-lesson-content">
								<?php echo $lesson_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</div>
				</main>
			</div>
		</div>
		<?php
	endwhile;
endif;

pl_template_close();
