<?php
/**
 * Internal Links admin page.
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

$internal_links = crispy_seo()->getComponent( 'internal_links' );
$stats          = $internal_links ? $internal_links->getStats() : [];
?>

<div class="wrap crispy-seo-internal-links">
	<h1><?php esc_html_e( 'Internal Links', 'crispy-seo' ); ?></h1>

	<div class="crispy-stats-boxes" style="display: flex; gap: 20px; margin: 20px 0;">
		<div class="card" style="flex: 1; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total Keywords', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #0073aa;">
				<?php echo esc_html( number_format_i18n( $stats['total_keywords'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Active Keywords', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #46b450;">
				<?php echo esc_html( number_format_i18n( $stats['enabled_keywords'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Indexed Posts', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #826eb4;">
				<?php echo esc_html( number_format_i18n( $stats['indexed_posts'] ?? 0 ) ); ?>
			</p>
		</div>
	</div>

	<div class="card" style="max-width: 800px; margin-bottom: 20px;">
		<h2><?php esc_html_e( 'Add New Keyword', 'crispy-seo' ); ?></h2>
		<form id="add-keyword-form">
			<?php wp_nonce_field( 'crispy_seo_internal_links', 'internal_links_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="keyword"><?php esc_html_e( 'Keyword', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="text" id="keyword" name="keyword" class="regular-text" required
							   placeholder="<?php esc_attr_e( 'e.g., artificial intelligence', 'crispy-seo' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The keyword or phrase to automatically link.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="target-post"><?php esc_html_e( 'Target Post', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<select id="target-post" name="target_post_id" class="regular-text" required style="width: 100%; max-width: 400px;">
							<option value=""><?php esc_html_e( 'Select a post...', 'crispy-seo' ); ?></option>
							<?php
							$posts = get_posts(
								[
									'post_type'      => [ 'post', 'page' ],
									'post_status'    => 'publish',
									'posts_per_page' => 100,
									'orderby'        => 'title',
									'order'          => 'ASC',
								]
							);
							foreach ( $posts as $post ) :
								?>
								<option value="<?php echo esc_attr( $post->ID ); ?>">
									<?php echo esc_html( $post->post_title ); ?> (<?php echo esc_html( $post->post_type ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="anchor-text"><?php esc_html_e( 'Anchor Text', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="text" id="anchor-text" name="anchor_text" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Leave empty to use keyword', 'crispy-seo' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Custom anchor text. If empty, the keyword will be used.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="max-links"><?php esc_html_e( 'Max Links Per Post', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="number" id="max-links" name="max_links" value="3" min="1" max="20" class="small-text">
						<p class="description">
							<?php esc_html_e( 'Maximum number of links to add for this keyword per post.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="priority"><?php esc_html_e( 'Priority', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="number" id="priority" name="priority" value="10" min="1" max="100" class="small-text">
						<p class="description">
							<?php esc_html_e( 'Higher priority keywords are linked first.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'crispy-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="case_sensitive" id="case-sensitive">
							<?php esc_html_e( 'Case sensitive matching', 'crispy-seo' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add Keyword', 'crispy-seo' ); ?>
				</button>
				<span class="spinner" style="float: none;"></span>
			</p>
		</form>
	</div>

	<div class="card">
		<h2 style="display: flex; justify-content: space-between; align-items: center;">
			<?php esc_html_e( 'Keywords', 'crispy-seo' ); ?>
			<button type="button" id="rebuild-index" class="button">
				<?php esc_html_e( 'Rebuild Index', 'crispy-seo' ); ?>
			</button>
		</h2>

		<table class="wp-list-table widefat fixed striped" id="keywords-table">
			<thead>
				<tr>
					<th style="width: 20%;"><?php esc_html_e( 'Keyword', 'crispy-seo' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Target Post', 'crispy-seo' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Anchor Text', 'crispy-seo' ); ?></th>
					<th style="width: 8%;"><?php esc_html_e( 'Max Links', 'crispy-seo' ); ?></th>
					<th style="width: 8%;"><?php esc_html_e( 'Priority', 'crispy-seo' ); ?></th>
					<th style="width: 8%;"><?php esc_html_e( 'Status', 'crispy-seo' ); ?></th>
					<th style="width: 16%;"><?php esc_html_e( 'Actions', 'crispy-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr class="loading-row">
					<td colspan="7" style="text-align: center; padding: 20px;">
						<span class="spinner is-active" style="float: none;"></span>
						<?php esc_html_e( 'Loading keywords...', 'crispy-seo' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="tablenav bottom" style="margin-top: 10px;">
			<div class="tablenav-pages" id="pagination"></div>
		</div>
	</div>
</div>

<style>
.crispy-seo-internal-links .form-table th {
	padding-top: 15px;
	width: 150px;
}
.crispy-seo-internal-links .form-table td {
	padding-top: 10px;
}
#keywords-table .status-enabled {
	color: #46b450;
}
#keywords-table .status-disabled {
	color: #dc3232;
}
#keywords-table .row-actions {
	visibility: hidden;
}
#keywords-table tr:hover .row-actions {
	visibility: visible;
}
</style>

<script>
jQuery(document).ready(function($) {
	var nonce = $('#internal_links_nonce').val();
	var currentPage = 1;

	// Load keywords on page load.
	loadKeywords();

	// Add keyword form submission.
	$('#add-keyword-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $spinner = $form.find('.spinner');
		var $button = $form.find('button[type="submit"]');

		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		$.post(ajaxurl, {
			action: 'crispy_seo_add_keyword',
			nonce: nonce,
			keyword: $('#keyword').val(),
			target_post_id: $('#target-post').val(),
			anchor_text: $('#anchor-text').val(),
			max_links: $('#max-links').val(),
			priority: $('#priority').val(),
			case_sensitive: $('#case-sensitive').is(':checked') ? '1' : '0'
		}, function(response) {
			$spinner.removeClass('is-active');
			$button.prop('disabled', false);

			if (response.success) {
				$form[0].reset();
				loadKeywords();
				alert(response.data.message);
			} else {
				alert(response.data.message);
			}
		});
	});

	// Rebuild index.
	$('#rebuild-index').on('click', function() {
		var $button = $(this);

		if (!confirm('<?php echo esc_js( __( 'Rebuild the link index? This may take a moment for large sites.', 'crispy-seo' ) ); ?>')) {
			return;
		}

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Rebuilding...', 'crispy-seo' ) ); ?>');

		$.post(ajaxurl, {
			action: 'crispy_seo_rebuild_link_index',
			nonce: nonce
		}, function(response) {
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Rebuild Index', 'crispy-seo' ) ); ?>');

			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert(response.data.message);
			}
		});
	});

	function loadKeywords(page) {
		page = page || 1;
		currentPage = page;

		var $tbody = $('#keywords-table tbody');

		$tbody.html('<tr class="loading-row"><td colspan="7" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none;"></span> <?php echo esc_js( __( 'Loading keywords...', 'crispy-seo' ) ); ?></td></tr>');

		$.post(ajaxurl, {
			action: 'crispy_seo_get_keywords',
			nonce: nonce,
			page: page
		}, function(response) {
			if (response.success) {
				renderKeywords(response.data.keywords);
				renderPagination(response.data.pages, response.data.current);
			} else {
				$tbody.html('<tr><td colspan="7"><?php echo esc_js( __( 'Error loading keywords.', 'crispy-seo' ) ); ?></td></tr>');
			}
		});
	}

	function renderKeywords(keywords) {
		var $tbody = $('#keywords-table tbody');
		$tbody.empty();

		if (keywords.length === 0) {
			$tbody.html('<tr><td colspan="7" style="text-align: center; padding: 20px;"><?php echo esc_js( __( 'No keywords found. Add your first keyword above.', 'crispy-seo' ) ); ?></td></tr>');
			return;
		}

		$.each(keywords, function(i, keyword) {
			var statusClass = keyword.enabled == 1 ? 'status-enabled' : 'status-disabled';
			var statusText = keyword.enabled == 1 ? '<?php echo esc_js( __( 'Active', 'crispy-seo' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'crispy-seo' ) ); ?>';
			var toggleText = keyword.enabled == 1 ? '<?php echo esc_js( __( 'Disable', 'crispy-seo' ) ); ?>' : '<?php echo esc_js( __( 'Enable', 'crispy-seo' ) ); ?>';

			var row = '<tr data-id="' + keyword.id + '">' +
				'<td><strong>' + escapeHtml(keyword.keyword) + '</strong>' +
					(keyword.case_sensitive == 1 ? ' <small>(<?php echo esc_js( __( 'case sensitive', 'crispy-seo' ) ); ?>)</small>' : '') +
				'</td>' +
				'<td>' + escapeHtml(keyword.post_title) + '</td>' +
				'<td>' + (keyword.anchor_text ? escapeHtml(keyword.anchor_text) : '<em><?php echo esc_js( __( '(same as keyword)', 'crispy-seo' ) ); ?></em>') + '</td>' +
				'<td>' + keyword.max_links_per_page + '</td>' +
				'<td>' + keyword.priority + '</td>' +
				'<td class="' + statusClass + '">' + statusText + '</td>' +
				'<td>' +
					'<span class="row-actions">' +
						'<a href="#" class="toggle-keyword" data-id="' + keyword.id + '" data-enabled="' + keyword.enabled + '">' + toggleText + '</a> | ' +
						'<a href="#" class="delete-keyword" data-id="' + keyword.id + '" style="color: #dc3232;"><?php echo esc_js( __( 'Delete', 'crispy-seo' ) ); ?></a>' +
					'</span>' +
				'</td>' +
			'</tr>';

			$tbody.append(row);
		});
	}

	function renderPagination(totalPages, current) {
		var $pagination = $('#pagination');
		$pagination.empty();

		if (totalPages <= 1) return;

		var html = '<span class="pagination-links">';

		if (current > 1) {
			html += '<a class="prev-page button" href="#" data-page="' + (current - 1) + '">&laquo;</a> ';
		}

		html += '<span class="paging-input">' + current + ' <?php echo esc_js( __( 'of', 'crispy-seo' ) ); ?> <span class="total-pages">' + totalPages + '</span></span>';

		if (current < totalPages) {
			html += ' <a class="next-page button" href="#" data-page="' + (current + 1) + '">&raquo;</a>';
		}

		html += '</span>';
		$pagination.html(html);
	}

	// Pagination clicks.
	$(document).on('click', '#pagination a', function(e) {
		e.preventDefault();
		loadKeywords($(this).data('page'));
	});

	// Toggle keyword.
	$(document).on('click', '.toggle-keyword', function(e) {
		e.preventDefault();

		var $link = $(this);
		var id = $link.data('id');
		var newEnabled = $link.data('enabled') == 1 ? '0' : '1';

		$.post(ajaxurl, {
			action: 'crispy_seo_update_keyword',
			nonce: nonce,
			keyword_id: id,
			enabled: newEnabled
		}, function(response) {
			if (response.success) {
				loadKeywords(currentPage);
			} else {
				alert(response.data.message);
			}
		});
	});

	// Delete keyword.
	$(document).on('click', '.delete-keyword', function(e) {
		e.preventDefault();

		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this keyword?', 'crispy-seo' ) ); ?>')) {
			return;
		}

		var id = $(this).data('id');

		$.post(ajaxurl, {
			action: 'crispy_seo_delete_keyword',
			nonce: nonce,
			keyword_id: id
		}, function(response) {
			if (response.success) {
				loadKeywords(currentPage);
			} else {
				alert(response.data.message);
			}
		});
	});

	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}
});
</script>
