<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for the library filters modal.
 */
?>
<div id="prs-filter-overlay" class="prs-filter-overlay" hidden></div>
<div
	id="prs-filter-dashboard"
	class="prs-filter-dashboard prs-filter-modal"
	role="dialog"
	aria-modal="true"
	aria-hidden="true"
	aria-labelledby="prs-filter-title"
	hidden
>
	<div class="prs-filter-dashboard__panel" role="document">
		<h2 id="prs-filter-title" class="prs-filter-dashboard__title"><?php esc_html_e( 'Filter Library', 'politeia-reading' ); ?></h2>
		<form id="prs-filter-form" class="prs-filter-dashboard__form">
			<div class="prs-filter-dashboard__group prs-filter-dashboard__group--owning">
				<label class="prs-filter-dashboard__label"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
				<div class="prs-filter-multi" data-filter="owning">
					<button type="button" id="prs-filter-owning-toggle" class="prs-filter-multi__toggle" data-default-label="<?php esc_attr_e( 'All owning statuses', 'politeia-reading' ); ?>" aria-expanded="false" aria-controls="prs-filter-owning-panel">
						<?php esc_html_e( 'All owning statuses', 'politeia-reading' ); ?>
					</button>
					<div id="prs-filter-owning-panel" class="prs-filter-multi__panel" hidden>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="in_shelf" data-group="owning" />
							<?php esc_html_e( 'In Shelf', 'politeia-reading' ); ?>
						</label>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="lost" data-group="owning" />
							<?php esc_html_e( 'Lost', 'politeia-reading' ); ?>
						</label>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="lent_out" data-group="owning" />
							<?php esc_html_e( 'Lent Out', 'politeia-reading' ); ?>
						</label>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="sold" data-group="owning" />
							<?php esc_html_e( 'Sold', 'politeia-reading' ); ?>
						</label>
					</div>
				</div>
			</div>
			<div class="prs-filter-dashboard__group prs-filter-dashboard__group--reading">
				<label class="prs-filter-dashboard__label"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
				<div class="prs-filter-multi" data-filter="reading">
					<button type="button" id="prs-filter-reading-toggle" class="prs-filter-multi__toggle" data-default-label="<?php esc_attr_e( 'All reading statuses', 'politeia-reading' ); ?>" aria-expanded="false" aria-controls="prs-filter-reading-panel">
						<?php esc_html_e( 'All reading statuses', 'politeia-reading' ); ?>
					</button>
					<div id="prs-filter-reading-panel" class="prs-filter-multi__panel" hidden>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="not_started" data-group="reading" />
							<?php esc_html_e( 'Not Started', 'politeia-reading' ); ?>
						</label>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="started" data-group="reading" />
							<?php esc_html_e( 'Started', 'politeia-reading' ); ?>
						</label>
						<label class="prs-filter-multi__option">
							<input type="checkbox" value="finished" data-group="reading" />
							<?php esc_html_e( 'Finished', 'politeia-reading' ); ?>
						</label>
					</div>
				</div>
			</div>
			<div class="prs-filter-dashboard__group prs-filter-dashboard__group--progress">
				<label for="prs-filter-progress-min" class="prs-filter-dashboard__label"><?php esc_html_e( 'Progress Range', 'politeia-reading' ); ?></label>
				<div class="prs-filter-range prs-filter-range--custom" data-min="0" data-max="100">
					<div class="prs-filter-range__track" id="prs-filter-progress-track">
						<span class="prs-filter-range__edge prs-filter-range__edge--left" aria-hidden="true"></span>
						<span class="prs-filter-range__edge prs-filter-range__edge--right" aria-hidden="true"></span>
						<span class="prs-filter-range__fill" id="prs-filter-progress-fill" aria-hidden="true"></span>
						<div class="prs-filter-range__thumb" id="prs-filter-progress-thumb-min" data-thumb="min" role="slider" aria-label="<?php esc_attr_e( 'Minimum progress', 'politeia-reading' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
							<span class="prs-filter-range__label">
								<span class="prs-filter-range__tick" aria-hidden="true"></span>
								<span class="prs-filter-range__value" data-display-for="prs-filter-progress-min">0%</span>
							</span>
						</div>
						<div class="prs-filter-range__thumb" id="prs-filter-progress-thumb-max" data-thumb="max" role="slider" aria-label="<?php esc_attr_e( 'Maximum progress', 'politeia-reading' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" tabindex="0">
							<span class="prs-filter-range__label">
								<span class="prs-filter-range__tick" aria-hidden="true"></span>
								<span class="prs-filter-range__value" data-display-for="prs-filter-progress-max">100%</span>
							</span>
						</div>
					</div>
					<input id="prs-filter-progress-min" class="prs-filter-range__input" type="hidden" value="0" />
					<input id="prs-filter-progress-max" class="prs-filter-range__input" type="hidden" value="100" />
				</div>
			</div>
			<div class="prs-filter-dashboard__group prs-filter-dashboard__group--order">
				<label for="prs-filter-order" class="prs-filter-dashboard__label"><?php esc_html_e( 'Order By', 'politeia-reading' ); ?></label>
				<select id="prs-filter-order" class="prs-filter-dashboard__select">
					<option value="title_asc"><?php esc_html_e( 'Title (A → Z)', 'politeia-reading' ); ?></option>
					<option value="title_desc"><?php esc_html_e( 'Title (Z → A)', 'politeia-reading' ); ?></option>
					<option value="author_asc"><?php esc_html_e( 'Author (A → Z)', 'politeia-reading' ); ?></option>
					<option value="author_desc"><?php esc_html_e( 'Author (Z → A)', 'politeia-reading' ); ?></option>
					<option value="progress_asc"><?php esc_html_e( 'Progress (Low → High)', 'politeia-reading' ); ?></option>
					<option value="progress_desc"><?php esc_html_e( 'Progress (High → Low)', 'politeia-reading' ); ?></option>
				</select>
			</div>
			<div class="prs-filter-dashboard__actions">
				<button type="submit" id="prs-filter-apply" class="button button-primary"><?php esc_html_e( 'Apply', 'politeia-reading' ); ?></button>
				<button type="button" id="prs-filter-reset" class="button button-secondary"><?php esc_html_e( 'Reset Filters', 'politeia-reading' ); ?></button>
			</div>
		</form>
	</div>
</div>
