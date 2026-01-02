<?php
/**
 * Media Replace meta box.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get post object from global.
global $post;

if ( ! $post instanceof WP_Post ) {
	return;
}

$media_replacer = crispy_seo()->getComponent( 'media_replacer' );
$has_backup     = $media_replacer ? $media_replacer->hasBackup( $post->ID ) : false;
$replaced_at    = get_post_meta( $post->ID, '_crispy_seo_replaced_at', true );
?>

<div class="crispy-media-replace-box">
	<?php wp_nonce_field( 'crispy_seo_media_replace', 'crispy_media_replace_nonce' ); ?>

	<p class="description">
		<?php esc_html_e( 'Replace this file with a new one while keeping all links and references intact.', 'crispy-seo' ); ?>
	</p>

	<div class="crispy-upload-area" style="margin: 15px 0;">
		<input type="file" id="crispy-replacement-file" name="crispy_replacement_file" style="width: 100%;">
	</div>

	<p>
		<button type="button" id="crispy-replace-btn" class="button button-primary" style="width: 100%;">
			<?php esc_html_e( 'Replace File', 'crispy-seo' ); ?>
		</button>
	</p>

	<?php if ( $has_backup ) : ?>
		<p>
			<button type="button" id="crispy-restore-btn" class="button" style="width: 100%;">
				<?php esc_html_e( 'Restore Original', 'crispy-seo' ); ?>
			</button>
		</p>
	<?php endif; ?>

	<?php if ( $replaced_at ) : ?>
		<p class="description" style="margin-top: 15px; font-style: italic;">
			<?php
			printf(
				/* translators: %s: date and time */
				esc_html__( 'Last replaced: %s', 'crispy-seo' ),
				esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $replaced_at ) ) )
			);
			?>
		</p>
	<?php endif; ?>

	<div id="crispy-replace-progress" style="display: none; margin-top: 10px;">
		<span class="spinner is-active" style="float: none; margin: 0;"></span>
		<span class="progress-text"><?php esc_html_e( 'Processing...', 'crispy-seo' ); ?></span>
	</div>

	<div id="crispy-replace-result" style="display: none; margin-top: 10px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
	var attachmentId = <?php echo (int) $post->ID; ?>;
	var nonce = $('#crispy_media_replace_nonce').val();

	$('#crispy-replace-btn').on('click', function() {
		var file = $('#crispy-replacement-file')[0].files[0];

		if (!file) {
			alert('<?php echo esc_js( __( 'Please select a file to upload.', 'crispy-seo' ) ); ?>');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'crispy_seo_replace_media');
		formData.append('nonce', nonce);
		formData.append('attachment_id', attachmentId);
		formData.append('file', file);

		$('#crispy-replace-progress').show();
		$('#crispy-replace-result').hide();
		$('#crispy-replace-btn').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				$('#crispy-replace-progress').hide();
				$('#crispy-replace-btn').prop('disabled', false);

				if (response.success) {
					$('#crispy-replace-result')
						.html('<p style="color: green;">' + response.data.message + '</p>')
						.show();

					// Reload page after 2 seconds to show updated file.
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$('#crispy-replace-result')
						.html('<p style="color: red;">' + (response.data.message || '<?php echo esc_js( __( 'Error replacing file.', 'crispy-seo' ) ); ?>') + '</p>')
						.show();
				}
			},
			error: function() {
				$('#crispy-replace-progress').hide();
				$('#crispy-replace-btn').prop('disabled', false);
				$('#crispy-replace-result')
					.html('<p style="color: red;"><?php echo esc_js( __( 'Upload failed.', 'crispy-seo' ) ); ?></p>')
					.show();
			}
		});
	});

	$('#crispy-restore-btn').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Restore the original file? Current file will be replaced.', 'crispy-seo' ) ); ?>')) {
			return;
		}

		$('#crispy-replace-progress').show();
		$('#crispy-replace-result').hide();
		$('#crispy-restore-btn').prop('disabled', true);

		$.post(ajaxurl, {
			action: 'crispy_seo_restore_media',
			nonce: nonce,
			attachment_id: attachmentId
		}, function(response) {
			$('#crispy-replace-progress').hide();
			$('#crispy-restore-btn').prop('disabled', false);

			if (response.success) {
				$('#crispy-replace-result')
					.html('<p style="color: green;">' + response.data.message + '</p>')
					.show();

				setTimeout(function() {
					location.reload();
				}, 2000);
			} else {
				$('#crispy-replace-result')
					.html('<p style="color: red;">' + response.data.message + '</p>')
					.show();
			}
		});
	});
});
</script>
