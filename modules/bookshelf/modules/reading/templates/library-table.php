<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template for the library books table and pagination.
 * Expects $context array from Politeia_Reading_Shortcode_My_Books.
 */
?>
<div class="prs-library">
	<table id="prs-library" class="prs-table">
		<tbody>
			<?php
			foreach ( (array) $context['books'] as $r ) {
				if ( function_exists( 'prs_render_book_row' ) ) {
					echo prs_render_book_row(
						$r,
						array(
							'user_id'       => $context['user_id'],
							'owning_labels' => $context['owning_labels'],
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			?>
		</tbody>
	</table>
	<div id="prs-library-no-results" class="prs-library__no-results" hidden>
		<?php
		$no_results_message = ( 0 === strpos( determine_locale(), 'es' ) )
			? 'No hay libros que coincidan con tu título o autor'
			: 'No books matches your title or author';
		$no_results_icon = POLITEIA_READING_URL . 'assets/svg/no_sim_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg';
		?>
		<img class="prs-library__no-results-icon" src="<?php echo esc_url( $no_results_icon ); ?>" alt="" aria-hidden="true" />
		<span class="prs-library__no-results-text"><?php echo esc_html( $no_results_message ); ?></span>
	</div>

	<?php if ( function_exists( 'prs_render_owning_overlay' ) ) { prs_render_owning_overlay(); } ?>

	<?php wp_nonce_field( 'prs_update_user_book', 'prs_update_user_book_nonce' ); ?>
</div>

<?php if ( ! empty( $context['pagination'] ) ) : ?>
	<nav class="prs-pagination-sheet" aria-label="<?php esc_attr_e( 'Library pagination', 'politeia-reading' ); ?>">
		<div class="prs-pagination-sheet__inner">
			<div class="prs-pagination-sheet__numbers">
				<?php
				foreach ( (array) $context['pagination'] as $link ) {
					$label = trim( wp_strip_all_tags( $link ) );
					if ( ! is_numeric( $label ) ) {
						continue;
					}

					$is_current = strpos( $link, 'current' ) !== false;
					if ( $is_current ) {
						printf(
							'<span class="prs-pagination-sheet__page is-current">%1$s</span>',
							esc_html( $label )
						);
						continue;
					}

					if ( preg_match( '/href="([^"]+)"/', $link, $matches ) ) {
						printf(
							'<a class="prs-pagination-sheet__page" href="%1$s">%2$s</a>',
							esc_url( $matches[1] ),
							esc_html( $label )
						);
					}
				}
				?>
			</div>
		</div>
	</nav>
<?php endif; ?>

<script>
(function() {
	function getStrings() {
		return (window.PRS_LIBRARY && window.PRS_LIBRARY.strings) ? window.PRS_LIBRARY.strings : {};
	}
	function text(key, fallback) {
		var strings = getStrings();
		return strings && strings[key] ? strings[key] : fallback;
	}
	function bookLabel(count) {
		return count === 1 ? text('book_single', 'book') : text('book_plural', 'books');
	}

	function getAjaxUrl() {
		if (typeof ajaxurl !== 'undefined') {
			return ajaxurl;
		}

		if (window.PRS_LIBRARY && window.PRS_LIBRARY.ajax_url) {
			return window.PRS_LIBRARY.ajax_url;
		}

		return '';
	}

	function updateBookCount(options) {
		var table = document.querySelector('#prs-library tbody');
		var counter = document.getElementById('prs-book-count');
		if (!table || !counter) {
			return;
		}

		options = options || {};

		var total = parseInt(counter.getAttribute('data-total') || '', 10);
		var filterActive = typeof options.filterActive === 'boolean'
			? options.filterActive
			: counter.getAttribute('data-filter-active') === '1';
		var filteredCount = typeof options.filteredCount === 'number'
			? options.filteredCount
			: parseInt(counter.getAttribute('data-filtered-count') || '', 10);

		if (!filterActive) {
			counter.setAttribute('data-filter-active', '0');
			counter.removeAttribute('data-filtered-count');
			if (!Number.isNaN(total)) {
				counter.textContent = total + ' ' + bookLabel(total);
				return;
			}
		}

		if (Number.isNaN(filteredCount)) {
			var rows = table.querySelectorAll('tr');
			var count = 0;
			rows.forEach(function(row) {
				if (row.style.display === 'none' || row.hidden || row.getAttribute('data-empty') === '1') {
					return;
				}
				count++;
			});
			filteredCount = count;
		}

		counter.setAttribute('data-filter-active', filterActive ? '1' : '0');
		counter.setAttribute('data-filtered-count', String(filteredCount));

		if (!Number.isNaN(total)) {
			counter.textContent = filteredCount + '/' + total + ' ' + bookLabel(total);
			return;
		}

		counter.textContent = filteredCount + ' ' + bookLabel(filteredCount);
	}

	async function loadLibraryPage(page) {
		if (typeof page === 'undefined') {
			page = 1;
		}

		var endpoint = getAjaxUrl();
		if (!endpoint) {
			return;
		}

		try {
			var response = await fetch(endpoint + '?action=prs_get_books_page&page=' + encodeURIComponent(page));
			var data = await response.text();
			var tbody = document.querySelector('#prs-library tbody');

			if (tbody) {
				tbody.innerHTML = data;
			}

			updateNoResultsMessage('');
			updateBookCount();
		} catch (err) {
			console.error('Error loading library page:', err);
		}
	}

	function updateNoResultsMessage(query) {
		var message = document.getElementById('prs-library-no-results');
		var tbody = document.querySelector('#prs-library tbody');
		if (!message || !tbody) {
			return;
		}
		if (!query) {
			message.hidden = true;
			return;
		}
		var visibleRows = Array.from(tbody.querySelectorAll('tr')).filter(function(row) {
			if (row.style.display === 'none' || row.hidden || row.getAttribute('data-empty') === '1') {
				return false;
			}
			return true;
		});
		message.hidden = visibleRows.length !== 0;
	}

	async function filterLibrary() {
		var input = document.getElementById('my-library-search');
		var query = input && input.value ? input.value.trim().toLowerCase() : '';
		var counter = document.getElementById('prs-book-count');

		if (query === '') {
			if (counter) {
				counter.setAttribute('data-filter-active', '0');
				counter.removeAttribute('data-filtered-count');
			}
			await loadLibraryPage(1);
			updateNoResultsMessage('');
			updateBookCount({ filterActive: false });
			return;
		}

		var endpoint = getAjaxUrl();
		if (!endpoint) {
			console.warn(text('ajax_unavailable', 'Ajax URL not available for library search.'));
			return;
		}

		try {
			var response = await fetch(endpoint + '?action=prs_get_all_books');
			var data = await response.text();
			var tbody = document.querySelector('#prs-library tbody');

			if (tbody) {
				tbody.innerHTML = data;

				var rows = tbody.querySelectorAll('tr');
				rows.forEach(function(row) {
					var text = row.textContent ? row.textContent.toLowerCase() : '';
					row.style.display = text.includes(query) ? '' : 'none';
				});
			}

			updateNoResultsMessage(query);
			updateBookCount({ filterActive: true });
		} catch (err) {
			console.error('Error fetching all books:', err);
			if (counter) {
				counter.textContent = text('error_loading_results', 'Error loading results');
			}
		}
	}

	window.updateBookCount = updateBookCount;
	window.filterLibrary = filterLibrary;
	window.loadLibraryPage = loadLibraryPage;

	var tableBody = document.querySelector('#prs-library tbody');
	if (tableBody && 'MutationObserver' in window) {
		var observer = new MutationObserver(updateBookCount);
		observer.observe(tableBody, { childList: true, subtree: true });
	}

	function onReady() {
		updateBookCount();

		var applyButtons = document.querySelectorAll('.prs-filter-apply, #prs-filter-apply');
		var resetButtons = document.querySelectorAll('.prs-filter-reset, #prs-filter-reset');

		applyButtons.forEach(function(button) {
			button.addEventListener('click', updateBookCount);
		});

		resetButtons.forEach(function(button) {
			button.addEventListener('click', updateBookCount);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})();
</script>
