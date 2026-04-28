<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
prs_template_open();

if ( is_user_logged_in() ) {
	echo do_shortcode( '[politeia_my_books render="header"]' );
	echo '<div class="wrap">';
	// Reutiliza exactamente lo que ves en el shortcode [politeia_my_books]
	echo do_shortcode( '[politeia_my_books render="content"]' );
	echo '</div>';
} else {
	echo '<div class="wrap">';
	echo '<p>' . esc_html__( 'You must be logged in to view your library.', 'politeia-reading' ) . '</p>';
	echo '</div>';
}

prs_template_close();
