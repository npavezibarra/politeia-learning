<?php
/**
 * View: Group Content.
 *
 * Politeia override:
 * - When there is a single tab, render its content as a standalone section (no ld-tab-bar wrapper).
 * - Keep the default tabs UI when multiple tabs exist.
 *
 * @since 4.22.0
 * @version 4.22.0
 *
 * @var bool     $has_access Whether the user has access to the group.
 * @var Tabs     $tabs       Tabs.
 * @var Template $this       Current Instance of template engine rendering this template.
 *
 * @package LearnDash\Core
 */

use LearnDash\Core\Template\Tabs\Tabs;
use LearnDash\Core\Template\Template;

?>
<main class="ld-layout__content">
	<?php if ( isset( $tabs ) && $tabs instanceof Tabs && ! $tabs->is_empty() ) : ?>
		<?php if ( $tabs->count() === 1 ) : ?>
			<?php $tab = $tabs->current(); ?>
			<section class="pcg-ld-group-description">
				<div class="pcg-ld-group-description__inner">
					<h2><?php esc_html_e( 'Sobre la especialización', 'politeia-learning' ); ?></h2>
					<?php
					if ( empty( $tab->get_template() ) ) {
						echo $tab->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						$this->template( $tab->get_template() );
					}
					?>
				</div>
			</section>
		<?php else : ?>
			<?php $this->template( 'modern/components/tabs' ); ?>
		<?php endif; ?>
	<?php endif; ?>
</main>
