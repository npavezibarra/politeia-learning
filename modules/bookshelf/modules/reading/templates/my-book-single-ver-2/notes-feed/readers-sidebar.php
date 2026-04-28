<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $other_readers */
?>
<aside class="prs-notes-feed__sidebar">
	<h2><?php esc_html_e( 'Other Readers', 'politeia-reading' ); ?></h2>
	<div class="prs-notes-feed__readers">
		<?php foreach ( $other_readers as $reader ) : ?>
			<?php
			$avatar_url  = get_avatar_url( (int) $reader->user_id, array( 'size' => 48 ) );
			$reader_user = get_userdata( (int) $reader->user_id );
			$profile_url = $reader_user ? home_url( '/members/' . $reader_user->user_login . '/' ) : '';
			?>
			<?php if ( $avatar_url && $profile_url ) : ?>
				<a class="prs-notes-feed__reader-avatar" href="<?php echo esc_url( $profile_url ); ?>">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Reader avatar', 'politeia-reading' ); ?>">
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</aside>
