<?php
/**
 * 404 Monitor Settings page.
 *
 * @package CrispySEO
 * @since 2.1.0
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

$not_found_manager = crispy_seo()->getComponent( 'not_found' );
$stats             = $not_found_manager ? $not_found_manager->getStats() : [ 'total' => 0, 'unique_urls' => 0, 'today' => 0 ];
$top_urls          = $not_found_manager ? $not_found_manager->getTopUrls( 50 ) : [];
$recent_logs       = $not_found_manager ? $not_found_manager->getLogs( [ 'limit' => 100 ] ) : [];

// Settings.
$custom_page_id   = (int) get_option( 'crispy_seo_404_page_id', 0 );
$retention_days   = (int) get_option( 'crispy_seo_404_log_retention_days', 30 );
$log_enabled      = (bool) get_option( 'crispy_seo_404_log_enabled', true );

// Get pages for dropdown.
$pages = get_pages( [ 'post_status' => 'publish' ] );
?>

<div class="wrap crispy-seo-404-monitor">
	<h1><?php esc_html_e( '404 Monitor', 'crispy-seo' ); ?></h1>

	<!-- Statistics Cards -->
	<div class="crispy-stats-boxes" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total 404 Hits', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #0073aa;">
				<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Unique URLs', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #46b450;">
				<?php echo esc_html( number_format_i18n( $stats['unique_urls'] ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Hits Today', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #826eb4;">
				<?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?>
			</p>
		</div>
	</div>

	<!-- Settings -->
	<div class="card" style="margin-bottom: 20px; padding: 20px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Settings', 'crispy-seo' ); ?></h2>

		<form id="crispy-404-settings-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="custom-404-page"><?php esc_html_e( 'Custom 404 Page', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<select id="custom-404-page" name="page_id" class="regular-text">
							<option value="0"><?php esc_html_e( '— Use Theme Default —', 'crispy-seo' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $custom_page_id, $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select a page to display when visitors hit a 404 error. The 404 HTTP status code will be preserved.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="retention-days"><?php esc_html_e( 'Log Retention', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="number" id="retention-days" name="retention_days" value="<?php echo esc_attr( $retention_days ); ?>"
							   min="1" max="365" class="small-text"> <?php esc_html_e( 'days', 'crispy-seo' ); ?>
						<p class="description">
							<?php esc_html_e( 'Automatically delete 404 logs older than this many days.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Logging', 'crispy-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="log-enabled" name="log_enabled" value="1" <?php checked( $log_enabled ); ?>>
							<?php esc_html_e( 'Track 404 errors (bots and logged-in admins are excluded)', 'crispy-seo' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'crispy-seo' ); ?>
				</button>
				<span class="spinner" style="float: none;"></span>
			</p>
		</form>
	</div>

	<!-- Top 404 URLs -->
	<div class="card" style="margin-bottom: 20px; padding: 20px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Top 404 URLs', 'crispy-seo' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 50%;"><?php esc_html_e( 'URL Path', 'crispy-seo' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Hits', 'crispy-seo' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'Last Seen', 'crispy-seo' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Actions', 'crispy-seo' ); ?></th>
				</tr>
			</thead>
			<tbody id="top-urls-list">
				<?php if ( empty( $top_urls ) ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No 404 errors logged yet.', 'crispy-seo' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $top_urls as $url ) : ?>
						<tr data-path="<?php echo esc_attr( $url['request_path'] ); ?>">
							<td><code><?php echo esc_html( $url['request_path'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (int) $url['hit_count'] ) ); ?></td>
							<td><?php echo esc_html( human_time_diff( strtotime( $url['last_seen'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'crispy-seo' ); ?></td>
							<td>
								<button type="button" class="button button-small create-redirect-btn"
										data-path="<?php echo esc_attr( $url['request_path'] ); ?>">
									<?php esc_html_e( 'Create Redirect', 'crispy-seo' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Recent 404 Hits -->
	<div class="card" style="padding: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
			<h2 style="margin: 0;"><?php esc_html_e( 'Recent 404 Hits', 'crispy-seo' ); ?></h2>
			<button type="button" id="purge-all-logs" class="button button-secondary">
				<?php esc_html_e( 'Purge All Logs', 'crispy-seo' ); ?>
			</button>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 3%;"><input type="checkbox" id="select-all-logs"></th>
					<th style="width: 15%;"><?php esc_html_e( 'Timestamp', 'crispy-seo' ); ?></th>
					<th style="width: 30%;"><?php esc_html_e( 'URL Path', 'crispy-seo' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Referrer', 'crispy-seo' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'User Agent', 'crispy-seo' ); ?></th>
					<th style="width: 7%;"><?php esc_html_e( 'Actions', 'crispy-seo' ); ?></th>
				</tr>
			</thead>
			<tbody id="recent-logs-list">
				<?php if ( empty( $recent_logs ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No 404 errors logged yet.', 'crispy-seo' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $recent_logs as $log ) : ?>
						<tr data-id="<?php echo esc_attr( $log['id'] ); ?>">
							<td><input type="checkbox" class="log-checkbox" value="<?php echo esc_attr( $log['id'] ); ?>"></td>
							<td><?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $log['created_at'] ) ) ); ?></td>
							<td><code><?php echo esc_html( $log['request_path'] ); ?></code></td>
							<td>
								<?php if ( $log['referrer'] ) : ?>
									<a href="<?php echo esc_url( $log['referrer'] ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( wp_parse_url( $log['referrer'], PHP_URL_HOST ) ?: $log['referrer'] ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Direct', 'crispy-seo' ); ?></em>
								<?php endif; ?>
							</td>
							<td title="<?php echo esc_attr( $log['user_agent'] ); ?>">
								<?php echo esc_html( substr( $log['user_agent'] ?? '', 0, 40 ) ); ?>...
							</td>
							<td>
								<button type="button" class="button button-small delete-log-btn" data-id="<?php echo esc_attr( $log['id'] ); ?>">
									<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p style="margin-top: 15px;">
			<button type="button" id="delete-selected-logs" class="button button-secondary" disabled>
				<?php esc_html_e( 'Delete Selected', 'crispy-seo' ); ?>
			</button>
		</p>
	</div>

	<!-- Redirect Modal -->
	<div id="redirect-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000;">
		<div style="background: #fff; max-width: 500px; margin: 100px auto; padding: 20px; border-radius: 4px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Create Redirect', 'crispy-seo' ); ?></h3>
			<p><?php esc_html_e( 'Create a redirect for:', 'crispy-seo' ); ?> <code id="redirect-source-path"></code></p>

			<table class="form-table">
				<tr>
					<th><label for="redirect-target"><?php esc_html_e( 'Target URL', 'crispy-seo' ); ?></label></th>
					<td>
						<input type="text" id="redirect-target" class="large-text" placeholder="/new-page/ or https://example.com/page">
					</td>
				</tr>
				<tr>
					<th><label for="redirect-type"><?php esc_html_e( 'Redirect Type', 'crispy-seo' ); ?></label></th>
					<td>
						<select id="redirect-type">
							<option value="301"><?php esc_html_e( '301 - Permanent', 'crispy-seo' ); ?></option>
							<option value="302"><?php esc_html_e( '302 - Temporary', 'crispy-seo' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<p style="text-align: right;">
				<button type="button" id="cancel-redirect" class="button"><?php esc_html_e( 'Cancel', 'crispy-seo' ); ?></button>
				<button type="button" id="confirm-redirect" class="button button-primary"><?php esc_html_e( 'Create Redirect', 'crispy-seo' ); ?></button>
			</p>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var nonce = typeof crispySeoAdmin !== 'undefined' ? crispySeoAdmin.nonce : '';
	var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';
	var currentPath = '';

	// Save settings.
	$('#crispy-404-settings-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');
		var $spinner = $form.find('.spinner');

		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(ajaxUrl, {
			action: 'crispy_seo_save_404_settings',
			nonce: nonce,
			page_id: $('#custom-404-page').val(),
			retention_days: $('#retention-days').val(),
			log_enabled: $('#log-enabled').is(':checked') ? '1' : '0'
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error saving settings.', 'crispy-seo' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'crispy-seo' ) ); ?>');
		}).always(function() {
			$button.prop('disabled', false);
			$spinner.removeClass('is-active');
		});
	});

	// Select all checkboxes.
	$('#select-all-logs').on('change', function() {
		$('.log-checkbox').prop('checked', $(this).is(':checked'));
		updateDeleteButton();
	});

	$('.log-checkbox').on('change', function() {
		updateDeleteButton();
	});

	function updateDeleteButton() {
		var checked = $('.log-checkbox:checked').length;
		$('#delete-selected-logs').prop('disabled', checked === 0);
	}

	// Delete selected logs.
	$('#delete-selected-logs').on('click', function() {
		var ids = [];
		$('.log-checkbox:checked').each(function() {
			ids.push($(this).val());
		});

		if (ids.length === 0) return;

		if (!confirm('<?php echo esc_js( __( 'Delete selected log entries?', 'crispy-seo' ) ); ?>')) {
			return;
		}

		$.post(ajaxUrl, {
			action: 'crispy_seo_delete_404_logs',
			nonce: nonce,
			ids: ids
		}, function(response) {
			if (response.success) {
				ids.forEach(function(id) {
					$('tr[data-id="' + id + '"]').fadeOut(function() { $(this).remove(); });
				});
				$('#select-all-logs').prop('checked', false);
				updateDeleteButton();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error deleting logs.', 'crispy-seo' ) ); ?>');
			}
		});
	});

	// Delete single log.
	$(document).on('click', '.delete-log-btn', function() {
		var id = $(this).data('id');
		var $row = $(this).closest('tr');

		$.post(ajaxUrl, {
			action: 'crispy_seo_delete_404_logs',
			nonce: nonce,
			ids: [id]
		}, function(response) {
			if (response.success) {
				$row.fadeOut(function() { $(this).remove(); });
			}
		});
	});

	// Purge all logs.
	$('#purge-all-logs').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'This will delete ALL 404 logs. Are you sure?', 'crispy-seo' ) ); ?>')) {
			return;
		}

		if (!confirm('<?php echo esc_js( __( 'This action cannot be undone. Continue?', 'crispy-seo' ) ); ?>')) {
			return;
		}

		$.post(ajaxUrl, {
			action: 'crispy_seo_purge_404_logs',
			nonce: nonce
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error purging logs.', 'crispy-seo' ) ); ?>');
			}
		});
	});

	// Open redirect modal.
	$(document).on('click', '.create-redirect-btn', function() {
		currentPath = $(this).data('path');
		$('#redirect-source-path').text(currentPath);
		$('#redirect-target').val('');
		$('#redirect-modal').show();
	});

	// Close redirect modal.
	$('#cancel-redirect').on('click', function() {
		$('#redirect-modal').hide();
	});

	// Create redirect.
	$('#confirm-redirect').on('click', function() {
		var targetUrl = $('#redirect-target').val();
		var redirectType = $('#redirect-type').val();

		if (!targetUrl) {
			alert('<?php echo esc_js( __( 'Please enter a target URL.', 'crispy-seo' ) ); ?>');
			return;
		}

		// Find the first log ID for this path.
		var $row = $('tr[data-path="' + currentPath + '"]');

		$.post(ajaxUrl, {
			action: 'crispy_seo_save_redirect',
			nonce: nonce,
			source: currentPath,
			target: targetUrl,
			type: redirectType
		}, function(response) {
			if (response.success) {
				alert('<?php echo esc_js( __( 'Redirect created successfully!', 'crispy-seo' ) ); ?>');
				$('#redirect-modal').hide();
				$row.fadeOut(function() { $(this).remove(); });
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error creating redirect.', 'crispy-seo' ) ); ?>');
			}
		});
	});
});
</script>
