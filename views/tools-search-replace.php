<?php
/**
 * Search & Replace tool view.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verify capability.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'crispy-seo' ) );
}
?>

<div class="card search-replace-card">
	<h2><?php esc_html_e( 'Database Search & Replace', 'crispy-seo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Search and replace strings across your WordPress database. This tool safely handles serialized data.', 'crispy-seo' ); ?>
	</p>

	<div class="notice notice-warning inline" style="margin: 15px 0;">
		<p>
			<strong><?php esc_html_e( 'Important:', 'crispy-seo' ); ?></strong>
			<?php esc_html_e( 'Always backup your database before performing a replace operation. Dry run is enabled by default.', 'crispy-seo' ); ?>
		</p>
	</div>

	<form id="crispy-search-replace-form" method="post">
		<?php wp_nonce_field( 'crispy_seo_search_replace', 'crispy_search_replace_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="search-term"><?php esc_html_e( 'Search for', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="search-term" name="search_term" class="regular-text" required
						   placeholder="<?php esc_attr_e( 'e.g., http://oldsite.com', 'crispy-seo' ); ?>">
					<p class="description">
						<?php esc_html_e( 'The string to search for in the database.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="replace-term"><?php esc_html_e( 'Replace with', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="replace-term" name="replace_term" class="regular-text" required
						   placeholder="<?php esc_attr_e( 'e.g., https://newsite.com', 'crispy-seo' ); ?>">
					<p class="description">
						<?php esc_html_e( 'The replacement string.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tables to search', 'crispy-seo' ); ?></th>
				<td>
					<fieldset id="table-selection">
						<label>
							<input type="checkbox" name="select_all_tables" id="select-all-tables" checked>
							<strong><?php esc_html_e( 'All tables (recommended)', 'crispy-seo' ); ?></strong>
						</label>
						<div id="table-list" style="margin-top: 10px; display: none;">
							<?php
							global $wpdb;
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$tables = $wpdb->get_col( 'SHOW TABLES' );
							foreach ( $tables as $table ) :
								// Skip sensitive tables.
								if ( strpos( $table, 'users' ) !== false ) {
									continue;
								}
								?>
								<label style="display: block; margin: 5px 0;">
									<input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table ); ?>" checked>
									<?php echo esc_html( $table ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'crispy-seo' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="dry_run" id="dry-run" checked>
							<?php esc_html_e( 'Dry run (preview changes without applying)', 'crispy-seo' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="case_sensitive" id="case-sensitive">
							<?php esc_html_e( 'Case sensitive search', 'crispy-seo' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" id="search-button" class="button button-primary">
				<?php esc_html_e( 'Search & Preview', 'crispy-seo' ); ?>
			</button>
			<button type="button" id="replace-button" class="button button-secondary" disabled>
				<?php esc_html_e( 'Apply Replacements', 'crispy-seo' ); ?>
			</button>
			<span class="spinner" style="float: none; margin-top: 0;"></span>
		</p>
	</form>
</div>

<div id="search-results" class="card" style="display: none; margin-top: 20px;">
	<h3><?php esc_html_e( 'Results', 'crispy-seo' ); ?></h3>
	<div id="results-summary"></div>
	<div id="results-table-container" style="max-height: 400px; overflow-y: auto;">
		<table class="wp-list-table widefat fixed striped" id="results-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Table', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Column', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Row ID', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Preview', 'crispy-seo' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<style>
.search-replace-card .form-table th {
	width: 150px;
	padding-top: 20px;
}
.search-replace-card .form-table td {
	padding-top: 15px;
}
#results-table .preview-old {
	background-color: #ffdddd;
	text-decoration: line-through;
}
#results-table .preview-new {
	background-color: #ddffdd;
}
.results-count {
	font-size: 14px;
	margin-bottom: 15px;
}
.results-count strong {
	color: #0073aa;
}
</style>

<script>
jQuery(document).ready(function($) {
	var searchData = null;

	// Toggle table list visibility.
	$('#select-all-tables').on('change', function() {
		if ($(this).is(':checked')) {
			$('#table-list').hide();
			$('#table-list input').prop('checked', true);
		} else {
			$('#table-list').show();
		}
	});

	// Handle search form submission.
	$('#crispy-search-replace-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $spinner = $form.find('.spinner');
		var $searchBtn = $('#search-button');
		var $replaceBtn = $('#replace-button');
		var $results = $('#search-results');

		// Collect selected tables.
		var tables = [];
		if ($('#select-all-tables').is(':checked')) {
			$('#table-list input:checkbox').each(function() {
				tables.push($(this).val());
			});
		} else {
			$('#table-list input:checkbox:checked').each(function() {
				tables.push($(this).val());
			});
		}

		var data = {
			action: 'crispy_seo_search_replace',
			nonce: $('#crispy_search_replace_nonce').val(),
			search: $('#search-term').val(),
			replace: $('#replace-term').val(),
			tables: tables,
			dry_run: $('#dry-run').is(':checked') ? 1 : 0,
			case_sensitive: $('#case-sensitive').is(':checked') ? 1 : 0
		};

		$spinner.addClass('is-active');
		$searchBtn.prop('disabled', true);
		$replaceBtn.prop('disabled', true);

		$.post(ajaxurl, data, function(response) {
			$spinner.removeClass('is-active');
			$searchBtn.prop('disabled', false);

			if (response.success) {
				searchData = response.data;
				displayResults(response.data);

				// Enable replace button if there are results and it was a dry run.
				if (response.data.total_matches > 0 && data.dry_run) {
					$replaceBtn.prop('disabled', false);
				}
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'crispy-seo' ) ); ?>');
			}
		}).fail(function() {
			$spinner.removeClass('is-active');
			$searchBtn.prop('disabled', false);
			alert('<?php echo esc_js( __( 'Request failed. Please try again.', 'crispy-seo' ) ); ?>');
		});
	});

	// Handle replace button click.
	$('#replace-button').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to apply these replacements? This cannot be undone.', 'crispy-seo' ) ); ?>')) {
			return;
		}

		$('#dry-run').prop('checked', false);
		$('#crispy-search-replace-form').submit();
	});

	function displayResults(data) {
		var $results = $('#search-results');
		var $summary = $('#results-summary');
		var $tbody = $('#results-table tbody');

		$tbody.empty();

		if (data.dry_run) {
			$summary.html(
				'<p class="results-count">' +
				'<?php echo esc_js( __( 'Found', 'crispy-seo' ) ); ?> <strong>' + data.total_matches + '</strong> ' +
				'<?php echo esc_js( __( 'occurrences in', 'crispy-seo' ) ); ?> <strong>' + data.tables_affected + '</strong> ' +
				'<?php echo esc_js( __( 'tables.', 'crispy-seo' ) ); ?> ' +
				'<em><?php echo esc_js( __( '(Dry run - no changes made)', 'crispy-seo' ) ); ?></em></p>'
			);
		} else {
			$summary.html(
				'<p class="results-count" style="color: green;">' +
				'<strong>' + data.total_matches + '</strong> ' +
				'<?php echo esc_js( __( 'replacements made successfully.', 'crispy-seo' ) ); ?></p>'
			);
		}

		if (data.preview && data.preview.length > 0) {
			$.each(data.preview, function(i, item) {
				var row = '<tr>' +
					'<td>' + escapeHtml(item.table) + '</td>' +
					'<td>' + escapeHtml(item.column) + '</td>' +
					'<td>' + escapeHtml(item.row_id) + '</td>' +
					'<td>' +
						'<span class="preview-old">' + escapeHtml(truncate(item.old_value, 100)) + '</span><br>' +
						'<span class="preview-new">' + escapeHtml(truncate(item.new_value, 100)) + '</span>' +
					'</td>' +
				'</tr>';
				$tbody.append(row);
			});
		} else if (data.total_matches === 0) {
			$tbody.append('<tr><td colspan="4"><?php echo esc_js( __( 'No matches found.', 'crispy-seo' ) ); ?></td></tr>');
		}

		$results.show();
	}

	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

	function truncate(str, maxLength) {
		if (!str) return '';
		if (str.length <= maxLength) return str;
		return str.substring(0, maxLength) + '...';
	}
});
</script>
