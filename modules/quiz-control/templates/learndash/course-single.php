<?php
/**
 * LearnDash LD30 Displays a course
 *
 * Politeia version modeled on the Red Cultural course page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

pl_template_open();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		global $post;

		$course_id   = (int) get_the_ID();
		$user_id     = (int) get_current_user_id();
		$title       = (string) get_the_title( $course_id );
		$course_url  = (string) get_permalink( $course_id );
		$author_id   = (int) get_post_field( 'post_author', $course_id );
		$author_name = $author_id ? pl_get_user_full_name_or_display_name( $author_id, (string) get_the_author_meta( 'display_name', $author_id ) ) : '';

		$header_image = get_the_post_thumbnail_url( $course_id, 'full' );
		$card_image   = get_the_post_thumbnail_url( $course_id, 'large' );
		$fallback_img = 'https://images.unsplash.com/photo-1548013146-72479768bbaa?auto=format&fit=crop&q=80&w=2000';
		$fallback_card = 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&q=80&w=1000';

		$header_image_url = $header_image ? (string) $header_image : '';
		$card_image_url   = $card_image ? (string) $card_image : ( $header_image ? (string) $header_image : $fallback_card );
		$raw_desc = (string) get_the_excerpt( $course_id );
		if ( '' === $raw_desc ) {
			$raw_desc = (string) wp_strip_all_tags( (string) $post->post_content );
		}
		$desc  = (string) wp_trim_words( $raw_desc, 70, '…' );
		$intro = (string) apply_filters( 'the_content', (string) $post->post_content );

		$enrolled = function_exists( 'sfwd_lms_has_access' ) ? (bool) sfwd_lms_has_access( $course_id, $user_id ) : false;

		$related_product_id = 0;
		if ( class_exists( 'WooCommerce' ) ) {
			$product_ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( (array) $product_ids as $pid ) {
				$raw_related = get_post_meta( (int) $pid, '_related_course', true );
				$related_data = maybe_unserialize( $raw_related );

				if ( is_array( $related_data ) ) {
					$related_data = array_map( 'intval', $related_data );
					if ( in_array( $course_id, $related_data, true ) ) {
						$related_product_id = (int) $pid;
						break;
					}
				} elseif ( (int) $related_data === $course_id ) {
					$related_product_id = (int) $pid;
					break;
				} elseif ( is_string( $raw_related ) && $raw_related !== '' ) {
					$raw_related = trim( $raw_related );
					if ( ctype_digit( $raw_related ) && (int) $raw_related === $course_id ) {
						$related_product_id = (int) $pid;
						break;
					}
				}
			}
		}

		$product_obj = $related_product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $related_product_id ) : null;
		$price       = $product_obj ? wp_strip_all_tags( (string) $product_obj->get_price_html() ) : '';

		$lessons = function_exists( 'learndash_get_course_lessons_list' ) ? (array) learndash_get_course_lessons_list( $course_id, $user_id, array( 'num' => -1 ) ) : array();
		$lesson_count = count( $lessons );

		$rcp_first_lesson_date = 0;
		$rcp_last_lesson_date  = 0;
		foreach ( $lessons as $l_item ) {
			$l_access = (int) ( $l_item['lesson_access_from'] ?? 0 );
			if ( $l_access <= 0 ) {
				continue;
			}
			if ( 0 === $rcp_first_lesson_date || $l_access < $rcp_first_lesson_date ) {
				$rcp_first_lesson_date = $l_access;
			}
			if ( $l_access > $rcp_last_lesson_date ) {
				$rcp_last_lesson_date = $l_access;
			}
		}
		$rcp_show_date_range = ( $rcp_first_lesson_date > 0 && $rcp_last_lesson_date > 0 && $rcp_first_lesson_date !== $rcp_last_lesson_date );

		$rcp_has_any_purchase_access = false;
		$rcp_first_accessible_lesson_url = $course_url;

		if ( $user_id > 0 ) {
			$rcp_first_accessible_url  = '';
			$rcp_first_incomplete_url  = '';

			foreach ( $lessons as $lesson_item ) {
				$lesson_post = $lesson_item['post'] ?? null;
				if ( ! $lesson_post instanceof WP_Post ) {
					continue;
				}

				$l_id = (int) $lesson_post->ID;
				$lesson_url = ! empty( $lesson_item['permalink'] ) ? (string) $lesson_item['permalink'] : (string) get_permalink( $l_id );
				$has_access = $enrolled;

				if ( $has_access ) {
					$rcp_has_any_purchase_access = true;

					if ( '' === $rcp_first_accessible_url ) {
						$rcp_first_accessible_url = $lesson_url;
					}

					if ( '' === $rcp_first_incomplete_url ) {
						$is_complete = function_exists( 'learndash_is_lesson_complete' )
							? (bool) learndash_is_lesson_complete( $user_id, $l_id, $course_id )
							: false;
						if ( ! $is_complete ) {
							$rcp_first_incomplete_url = $lesson_url;
						}
					}
				}
			}

			if ( '' !== $rcp_first_incomplete_url ) {
				$rcp_first_accessible_lesson_url = $rcp_first_incomplete_url;
			} elseif ( '' !== $rcp_first_accessible_url ) {
				$rcp_first_accessible_lesson_url = $rcp_first_accessible_url;
			}
		}

		if ( $enrolled ) {
			$rcp_has_any_purchase_access = true;
		}

		$rcp_show_go_to_course = ( $user_id > 0 ) && $rcp_has_any_purchase_access;
		$payment_button_html   = '';
		if ( ! $enrolled && class_exists( 'Learndash_Payment_Button' ) ) {
			try {
				$btn = new \Learndash_Payment_Button( $course_id );
				$payment_button_html = (string) $btn->map();
			} catch ( \Throwable $e ) {
				$payment_button_html = '';
			}
		}

		$course_status = function_exists( 'learndash_course_status' ) ? (string) learndash_course_status( $course_id, $user_id, true ) : '';

		$header_style = $header_image_url !== ''
			? 'background-image:url("' . esc_url( $header_image_url ) . '");'
			: 'background-image:none;background-color:#111827;';
		$hero_overlay_style = $header_image_url !== ''
			? ''
			: 'background:linear-gradient(135deg, rgba(17,24,39,.98), rgba(30,41,59,.94));';
		?>

		<script src="https://cdn.tailwindcss.com"></script>
		<script src="https://unpkg.com/lucide@latest"></script>

		<style>
			body.single-sfwd-courses {
				background: #f9fafb;
				color: #111827;
			}

			body.single-sfwd-courses header.wp-block-template-part,
			body.single-sfwd-courses #masthead,
			body.single-sfwd-courses #header {
				position: sticky !important;
				top: 0 !important;
				z-index: 50 !important;
				background: #fff !important;
				border-bottom: 1px solid #e5e7eb !important;
				box-shadow: none !important;
			}

			.rcp-wide {
				max-width: var(--wp--style--global--wide-size, 1200px);
			}

			#red-cultural-course-hero-content {
				padding: 30px 0 30px 0;
				z-index: 0 !important;
			}

			@media (max-width: 1400px) {
				#red-cultural-course-hero-content {
					padding: 30px 0 30px 0;
					z-index: 0 !important;
				}
			}

			@media (min-width: 1024px) {
				#red-cultural-course-sidebar {
					margin-top: var(--rcp-sidebar-offset, 0px);
					padding-top: 30px;
				}
			}

			#red-cultural-course-content {
				padding: 90px 0px;
			}

			#red-cultural-course-summary {
				font-size: 16px;
			}

			#red-cultural-course-intro-text {
				font-size: 18px;
			}

			#red-cultural-course-hero-content .max-w-2xl {
				max-width: 60%;
			}

			@media (max-width: 1023px) {
				#red-cultural-course-hero-content .max-w-2xl {
					max-width: 100%;
				}
			}

			@media (max-width: 1023px) {
				#red-cultural-course-main {
					padding: 30px;
				}
			}

			@media (min-width: 1400px) {
				#red-cultural-course-main {
					padding: 0;
				}
			}

			#red-cultural-course-hero-content .max-w-xl {
				max-width: 100%;
			}

			.bg-black\/65 {
				background-color: rgb(0 0 0 / 0.80);
			}

			#red-cultural-course-sidebar {
				max-width: 360px;
			}

			#btn-join {
				padding: 10px 28px;
				font-weight: 700;
			}

			#red-cultural-course-lessons-list h3.font-medium.text-gray-800 {
				font-size: 16px;
				font-weight: 700;
			}

			#red-cultural-course-lessons h2.text-xs.font-bold.text-gray-400.tracking-widest.uppercase.mb-6 {
				color: #000;
				font-weight: 900;
				font-size: 16px;
			}

			#rcp-btn-join {
				display: block;
				width: 100%;
				margin-bottom: 12px;
			}

			.rcp-btn-cta,
			button#rcil-buy-course {
				margin-bottom: 10px !important;
				padding: 12px !important;
				display: flex;
				align-items: center;
				justify-content: center;
				letter-spacing: 2px;
				font-size: 13px;
				font-weight: 700;
				transition: all 0.2s ease;
				border-radius: 6px;
			}

			#red-cultural-course-intro {
				background: none !important;
			}

			@media (max-width: 1240px) {
				#red-cultural-course-hero-content {
					padding: 30px !important;
					z-index: 0 !important;
				}

				#red-cultural-course-content {
					padding: 90px 0px 0px !important;
				}
			}

			#red-cultural-course-sidebar {
				position: relative;
				z-index: 100 !important;
			}

			#red-cultural-course-hero-content {
				z-index: 0 !important;
			}
		</style>

		<header id="red-cultural-course-hero" class="relative w-full overflow-hidden">
			<div class="absolute inset-0 bg-cover bg-center" style="<?php echo esc_attr( $header_style ); ?>">
				<div class="absolute inset-0 bg-black/65" style="<?php echo esc_attr( $hero_overlay_style ); ?>"></div>
			</div>

			<div id="red-cultural-course-hero-content" class="relative z-10 rcp-wide mx-auto flex flex-col justify-start text-white pt-[30px] pb-[30px] px-0">
				<div class="max-w-2xl text-white">
					<span class="uppercase tracking-widest text-xs font-semibold mb-3 block opacity-80">
						<?php echo esc_html__( 'Curso', 'politeia-learning' ); ?>
					</span>
					<h1 id="red-cultural-course-title" class="text-4xl md:text-5xl font-bold mb-6 leading-tight">
						<?php echo esc_html( $title ); ?>
					</h1>

					<?php if ( '' !== $author_name ) : ?>
						<?php $author_avatar = (string) get_avatar_url( $author_id, array( 'size' => 100 ) ); ?>
						<a id="red-cultural-course-author" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>" class="flex items-center space-x-3 no-underline hover:opacity-80 transition-opacity mb-6">
							<div class="w-10 h-10 rounded-full bg-gray-400 flex items-center justify-center overflow-hidden border-2 border-white/20">
								<img src="<?php echo esc_url( $author_avatar ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" class="w-full h-full object-cover">
							</div>
							<span id="rc-author-display-name-header" class="text-sm font-medium text-white"><?php echo esc_html( $author_name ); ?></span>
						</a>
					<?php endif; ?>

					<p id="red-cultural-course-summary" class="text-sm leading-relaxed mb-4 opacity-90 max-w-xl">
						<?php echo esc_html( $desc ); ?>
					</p>

					<?php if ( $rcp_show_date_range ) : ?>
						<p id="red-cultural-course-dates" class="text-sm opacity-75 mb-8 max-w-xl flex items-center gap-2">
							<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-70 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
							<span>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %1$s = start date, %2$s = end date */
										__( 'Este curso inicia el %1$s y finaliza el %2$s', 'politeia-learning' ),
										'<strong>' . esc_html( date_i18n( 'j \d\e F, Y', $rcp_first_lesson_date ) ) . '</strong>',
										'<strong>' . esc_html( date_i18n( 'j \d\e F, Y', $rcp_last_lesson_date ) ) . '</strong>'
									),
									array( 'strong' => array() )
								);
								?>
							</span>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<main id="red-cultural-course-main" class="rcp-wide mx-auto px-6 pb-20 -mt-16 relative z-40">
			<div id="red-cultural-course-grid" class="grid grid-cols-1 lg:grid-cols-3 gap-12">

				<div id="red-cultural-course-content" class="lg:col-span-2 pt-16">
					<?php if ( '' !== $intro ) : ?>
						<div id="red-cultural-course-intro" class="bg-white/50 p-1 rounded-xl mb-12">
							<p class="text-gray-600 leading-relaxed text-[15px]" id="red-cultural-course-intro-text">
								<?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
						</div>
					<?php endif; ?>

					<section id="red-cultural-course-lessons">
						<h2 class="text-xs font-bold text-gray-400 tracking-widest uppercase mb-6">
							<?php echo esc_html__( 'Lecciones del curso', 'politeia-learning' ); ?>
						</h2>

						<div id="red-cultural-course-lessons-list" class="space-y-4">
							<?php if ( $lesson_count === 0 ) : ?>
								<div class="bg-white border border-gray-100 rounded-lg p-5 shadow-sm">
									<?php echo esc_html__( 'No hay lecciones publicadas todavía.', 'politeia-learning' ); ?>
								</div>
							<?php else : ?>
								<?php foreach ( $lessons as $lesson_item ) : ?>
									<?php
									$lesson_post = $lesson_item['post'] ?? null;
									if ( ! $lesson_post instanceof WP_Post ) {
										continue;
									}

									$lesson_id = (int) $lesson_post->ID;
									$lesson_title = (string) get_the_title( $lesson_id );
									$lesson_status = (string) ( $lesson_item['status'] ?? '' );
									$lesson_access_from = (int) ( $lesson_item['lesson_access_from'] ?? 0 );

									$right_text = '';
									$icon       = 'clock';
									if ( 'completed' === $lesson_status ) {
										$right_text = (string) __( 'Completado', 'politeia-learning' );
										$icon = 'check-circle';
									} elseif ( 'notavailable' === $lesson_status && $lesson_access_from > 0 && function_exists( 'learndash_adjust_date_time_display' ) ) {
										$right_text = sprintf(
											/* translators: %s is a date. */
											(string) __( 'Disponible en %s', 'politeia-learning' ),
											(string) learndash_adjust_date_time_display( $lesson_access_from )
										);
										$icon = 'clock';
									} else {
										$right_text = (string) __( 'Disponible', 'politeia-learning' );
										$icon = 'clock';
									}

									$lesson_url = ! empty( $lesson_item['permalink'] ) ? (string) $lesson_item['permalink'] : (string) get_permalink( $lesson_id );
									?>
									<div
										class="rcp-lesson-card bg-white border border-gray-100 rounded-lg p-5 flex flex-col sm:flex-row sm:items-center justify-between shadow-sm hover:shadow-md transition-shadow group cursor-pointer"
										data-rcp-href="<?php echo esc_url( $lesson_url ); ?>"
										role="link"
										tabindex="0"
									>
										<div class="flex-1 mb-3 sm:mb-0">
											<div class="flex items-center space-x-2">
												<h3 class="font-medium text-gray-800">
													<span class="rcil-rcp-lesson-title"><?php echo esc_html( $lesson_title ); ?></span>
												</h3>
											</div>
										</div>

										<div class="flex items-center justify-between sm:justify-end sm:space-x-6">
											<div class="flex items-center text-[11px] text-amber-600/70 font-medium">
												<i data-lucide="<?php echo esc_attr( $icon ); ?>" class="w-3 h-3 mr-1.5"></i>
												<?php echo esc_html( $right_text ); ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</section>
				</div>

				<div id="red-cultural-course-sidebar" class="lg:col-span-1">
					<div id="red-cultural-course-sidebar-sticky" class="sticky-card">
						<div id="red-cultural-course-card" class="bg-white rounded-xl shadow-2xl shadow-black/10 overflow-hidden">
							<div class="h-48 overflow-hidden">
								<img
									src="<?php echo esc_url( $card_image_url ); ?>"
									alt="<?php echo esc_attr( $title ); ?>"
									class="w-full h-full object-cover"
								/>
							</div>

							<div id="red-cultural-course-card-body" class="p-8 flex flex-col items-center text-center">
								<?php if ( '' !== $price ) : ?>
									<div id="red-cultural-course-price" class="w-full text-left text-[22px] font-bold text-gray-900 mb-5">
										<?php echo esc_html( $price ); ?>
									</div>
								<?php endif; ?>

								<?php if ( $rcp_show_go_to_course ) : ?>
									<a id="rc-cta-main-go" class="rcp-btn-cta w-full bg-black text-white px-6 py-3 no-underline shadow-sm hover:opacity-90 transition-all" href="<?php echo esc_url( $rcp_first_accessible_lesson_url ); ?>">
										<?php echo esc_html__( 'IR AL CURSO', 'politeia-learning' ); ?>
									</a>
								<?php elseif ( $related_product_id > 0 && class_exists( 'WooCommerce' ) ) : ?>
									<?php
									if ( is_user_logged_in() ) :
										$buy_url = add_query_arg( 'add-to-cart', $related_product_id, wc_get_checkout_url() );
										?>
										<a id="rc-cta-main-buy" class="rcp-btn-cta w-full bg-black text-white px-6 py-3 no-underline shadow-sm hover:opacity-90 transition-all font-bold" href="<?php echo esc_url( $buy_url ); ?>">
											<?php echo esc_html__( 'COMPRAR CURSO', 'politeia-learning' ); ?>
										</a>
									<?php else :
										$buy_url = add_query_arg( 'add-to-cart', $related_product_id, wc_get_checkout_url() );
										?>
										<a id="rc-cta-main-buy-guest" class="rcp-btn-cta w-full bg-black text-white px-6 py-3 no-underline shadow-sm hover:opacity-90 transition-all font-bold" href="<?php echo esc_url( $buy_url ); ?>" data-no-modal="1">
											<?php echo esc_html__( 'COMPRAR CURSO', 'politeia-learning' ); ?>
										</a>
									<?php endif; ?>
								<?php elseif ( '' !== $payment_button_html ) : ?>
									<div id="rc-cta-main-payment" class="rcp-btn-cta w-full mb-6 relative z-10">
										<?php echo $payment_button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								<?php endif; ?>

								<div id="red-cultural-course-includes" class="w-full space-y-4 border-t border-gray-100 pt-6 text-left">
									<h4 class="text-[10px] font-bold text-gray-800 uppercase tracking-widest mb-4">
										<?php echo esc_html__( 'Curso incluye', 'politeia-learning' ); ?>
									</h4>
									<div class="flex items-center text-sm text-gray-600">
										<i data-lucide="book-open" class="w-4 h-4 mr-3 text-gray-400"></i>
										<span><?php echo esc_html( sprintf( _n( '%d Lección', '%d Lecciones', $lesson_count, 'politeia-learning' ), $lesson_count ) ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<button id="red-cultural-course-students" class="w-full mt-6 bg-gray-100 text-gray-800 py-4 rounded-full font-bold text-[11px] tracking-widest uppercase hover:bg-gray-200 transition-colors" type="button">
								<?php echo esc_html__( 'Lista de alumnos', 'politeia-learning' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

			</div>
		</main>

		<script>
			(function () {
				function navigateLessonCard(card) {
					if (!card) return;
					var href = card.getAttribute('data-rcp-href');
					if (!href) return;
					window.location.href = href;
				}

				document.addEventListener('click', function (e) {
					var target = e.target;
					if (!target) return;
					if (target.closest && target.closest('a')) return;
					var card = target.closest ? target.closest('.rcp-lesson-card') : null;
					if (card) {
						navigateLessonCard(card);
					}
				});

				document.addEventListener('keydown', function (e) {
					if (e.key !== 'Enter' && e.key !== ' ') return;
					var target = e.target;
					if (!target) return;
					var card = target.closest ? target.closest('.rcp-lesson-card') : null;
					if (!card) return;
					e.preventDefault();
					navigateLessonCard(card);
				});

				function syncSidebarOffset() {
					var sidebar = document.getElementById('red-cultural-course-sidebar');
					var header = document.querySelector('header.wp-block-template-part, #masthead, #header');
					if (!sidebar || !header) return;

					if (window.innerWidth < 1024) {
						sidebar.style.setProperty('--rcp-sidebar-offset', '0px');
						return;
					}

					sidebar.style.setProperty('--rcp-sidebar-offset', '0px');
					var headerBottom = header.getBoundingClientRect().bottom;
					var sidebarTop = sidebar.getBoundingClientRect().top;
					var delta = headerBottom - sidebarTop;
					sidebar.style.setProperty('--rcp-sidebar-offset', Math.round(delta) + 'px');
				}

				syncSidebarOffset();
				window.addEventListener('load', syncSidebarOffset);
				window.addEventListener('resize', syncSidebarOffset);
				setTimeout(syncSidebarOffset, 50);

				if (window.lucide && typeof window.lucide.createIcons === 'function') {
					window.lucide.createIcons();
				}
			})();
		</script>
		<?php
	endwhile;
endif;

pl_template_close();
