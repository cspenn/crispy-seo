<?php
/**
 * Redirects Settings page.
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

$redirect_manager = crispy_seo()->getComponent( 'redirect_manager' );
$redirects        = $redirect_manager ? $redirect_manager->getRedirects() : [];
$stats            = $redirect_manager ? $redirect_manager->getStats() : [];
?>

<div class="wrap crispy-seo-redirects">
	<div class="crispy-stats-boxes" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total Redirects', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #0073aa;">
				<?php echo esc_html( number_format_i18n( $stats['total'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Active', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #46b450;">
				<?php echo esc_html( number_format_i18n( $stats['active'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 150px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total Hits', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #826eb4;">
				<?php echo esc_html( number_format_i18n( $stats['hits'] ?? 0 ) ); ?>
			</p>
		</div>
	</div>

	<div class="card" style="margin-bottom: 20px; padding: 20px;">
		<h2 style="margin-top: 0;" id="form-heading"><?php esc_html_e( 'Add Redirect', 'crispy-seo' ); ?></h2>

		<form id="add-redirect-form" method="post">
			<input type="hidden" id="redirect-id" name="id" value="0">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="redirect-source"><?php esc_html_e( 'Source URL', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="text" id="redirect-source" name="source" class="large-text"
							   placeholder="/old-page/" required>
						<p class="description">
							<?php esc_html_e( 'Relative URL path (e.g., /old-page/). Use regex: prefix for regex patterns.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="redirect-target"><?php esc_html_e( 'Target URL', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="text" id="redirect-target" name="target" class="large-text"
							   placeholder="/new-page/ or https://example.com/page">
						<p class="description">
							<?php esc_html_e( 'Where to redirect. Leave empty for 410/451 status codes.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="redirect-type"><?php esc_html_e( 'Redirect Type', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<select id="redirect-type" name="type">
							<option value="301"><?php esc_html_e( '301 - Permanent', 'crispy-seo' ); ?></option>
							<option value="302"><?php esc_html_e( '302 - Temporary', 'crispy-seo' ); ?></option>
							<option value="307"><?php esc_html_e( '307 - Temporary (Strict)', 'crispy-seo' ); ?></option>
							<option value="308"><?php esc_html_e( '308 - Permanent (Strict)', 'crispy-seo' ); ?></option>
							<option value="410"><?php esc_html_e( '410 - Content Deleted', 'crispy-seo' ); ?></option>
							<option value="451"><?php esc_html_e( '451 - Unavailable for Legal Reasons', 'crispy-seo' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" id="cancel-edit" class="button" style="display: none; margin-right: 10px;">
					<?php esc_html_e( 'Cancel', 'crispy-seo' ); ?>
				</button>
				<button type="submit" id="submit-redirect" class="button button-primary">
					<?php esc_html_e( 'Add Redirect', 'crispy-seo' ); ?>
				</button>
				<span class="spinner" style="float: none;"></span>
			</p>
		</form>
	</div>

	<div class="card" style="padding: 20px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Existing Redirects', 'crispy-seo' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Source', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Target', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Type', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'crispy-seo' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'crispy-seo' ); ?></th>
				</tr>
			</thead>
			<tbody id="redirects-list">
				<?php if ( empty( $redirects ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No redirects configured.', 'crispy-seo' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $redirects as $redirect ) : ?>
						<tr data-id="<?php echo esc_attr( $redirect['id'] ); ?>">
							<td><code><?php echo esc_html( $redirect['source_path'] ); ?></code></td>
							<td><?php echo esc_html( $redirect['target_url'] ?: '—' ); ?></td>
							<td><?php echo esc_html( $redirect['redirect_type'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $redirect['hit_count'] ) ); ?></td>
							<td>
								<button type="button" class="button button-small edit-redirect"
										data-id="<?php echo esc_attr( $redirect['id'] ); ?>"
										data-source="<?php echo esc_attr( $redirect['source_path'] ); ?>"
										data-target="<?php echo esc_attr( $redirect['target_url'] ); ?>"
										data-type="<?php echo esc_attr( $redirect['redirect_type'] ); ?>">
									<?php esc_html_e( 'Edit', 'crispy-seo' ); ?>
								</button>
								<button type="button" class="button button-small delete-redirect">
									<?php esc_html_e( 'Delete', 'crispy-seo' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var nonce = typeof crispySeoAdmin !== 'undefined' ? crispySeoAdmin.nonce : '';
	var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';

	// Add/Edit redirect form handler.
	$('#add-redirect-form').on('submit', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');
		var $spinner = $form.find('.spinner');
		var id = $('#redirect-id').val();

		var data = {
			action: 'crispy_seo_save_redirect',
			nonce: nonce,
			source: $('#redirect-source').val(),
			target: $('#redirect-target').val(),
			type: $('#redirect-type').val()
		};

		// Include ID if updating (existing redirect).
		if (id && id !== '0') {
			data.id = id;
		}

		if (!data.source) {
			alert('<?php echo esc_js( __( 'Source URL is required.', 'crispy-seo' ) ); ?>');
			return;
		}

		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(ajaxUrl, data, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error saving redirect.', 'crispy-seo' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'crispy-seo' ) ); ?>');
		}).always(function() {
			$button.prop('disabled', false);
			$spinner.removeClass('is-active');
		});
	});

	// Edit redirect handler.
	$(document).on('click', '.edit-redirect', function() {
		var $btn = $(this);
		var id = $btn.data('id');
		var source = $btn.data('source');
		var target = $btn.data('target');
		var type = $btn.data('type');

		// Populate form fields.
		$('#redirect-id').val(id);
		$('#redirect-source').val(source);
		$('#redirect-target').val(target);
		$('#redirect-type').val(type);

		// Change form state to "Edit".
		$('#form-heading').text('<?php echo esc_js( __( 'Edit Redirect', 'crispy-seo' ) ); ?>');
		$('#submit-redirect').text('<?php echo esc_js( __( 'Update Redirect', 'crispy-seo' ) ); ?>');
		$('#cancel-edit').show();

		// Scroll to form smoothly.
		$('html, body').animate({
			scrollTop: $('#add-redirect-form').offset().top - 50
		}, 500);

		// Focus on source field for better UX.
		$('#redirect-source').focus();
	});

	// Cancel edit handler.
	$('#cancel-edit').on('click', function() {
		// Reset form to add mode.
		$('#redirect-id').val('0');
		$('#redirect-source').val('');
		$('#redirect-target').val('');
		$('#redirect-type').val('301');
		$('#form-heading').text('<?php echo esc_js( __( 'Add Redirect', 'crispy-seo' ) ); ?>');
		$('#submit-redirect').text('<?php echo esc_js( __( 'Add Redirect', 'crispy-seo' ) ); ?>');
		$(this).hide();
	});

	// Delete redirect handler.
	$(document).on('click', '.delete-redirect', function() {
		if (!confirm('<?php echo esc_js( __( 'Delete this redirect?', 'crispy-seo' ) ); ?>')) {
			return;
		}

		var $row = $(this).closest('tr');
		var id = $row.data('id');

		var data = {
			action: 'crispy_seo_delete_redirect',
			nonce: nonce,
			id: id
		};

		$.post(ajaxUrl, data, function(response) {
			if (response.success) {
				$row.fadeOut(function() { $(this).remove(); });
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error deleting redirect.', 'crispy-seo' ) ); ?>');
			}
		});
	});
});
</script>
