<?php
/**
 * Template Part: LearnDash Group Top Bar.
 *
 * Placeholder for a future group hero/header block.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = get_the_title();
$short_description = get_post_meta( get_the_ID(), '_learndash_course_grid_short_description', true );
?>
<div class="pcg-ld-group-topbar">
	<div class="pcg-ld-group-topbar__inner">
		<?php if ( ! empty( $title ) ) : ?>
			<header class="entry-header">
				<span id="pcg-ld-group-topbar-kicker"><?php esc_html_e( 'ESPECIALIZACIÓN', 'politeia-learning' ); ?></span>
				<h1 class="entry-title"><?php echo esc_html( $title ); ?></h1>
			</header>
		<?php endif; ?>

		<?php if ( ! empty( $short_description ) ) : ?>
			<div class="pcg-ld-group-topbar__subtitle">
				<?php echo wp_kses_post( wpautop( $short_description ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
