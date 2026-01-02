<?php
/**
 * Image Optimization admin page.
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

$image_optimizer = crispy_seo()->getComponent( 'image_optimizer' );
$stats           = $image_optimizer ? $image_optimizer->getStats() : [];
$libraries       = $image_optimizer ? $image_optimizer->getAvailableLibraries() : [];

$jpeg_quality    = get_option( 'crispy_seo_jpeg_quality', 82 );
$png_compression = get_option( 'crispy_seo_png_compression', 6 );
$webp_quality    = get_option( 'crispy_seo_webp_quality', 80 );
$auto_optimize   = get_option( 'crispy_seo_auto_optimize', true );
$create_webp     = get_option( 'crispy_seo_create_webp', true );

// Handle settings update.
if ( isset( $_POST['crispy_seo_save_optimization_settings'] ) ) {
	check_admin_referer( 'crispy_seo_optimization_settings' );

	update_option( 'crispy_seo_jpeg_quality', (int) $_POST['jpeg_quality'] );
	update_option( 'crispy_seo_png_compression', (int) $_POST['png_compression'] );
	update_option( 'crispy_seo_webp_quality', (int) $_POST['webp_quality'] );
	update_option( 'crispy_seo_auto_optimize', isset( $_POST['auto_optimize'] ) );
	update_option( 'crispy_seo_create_webp', isset( $_POST['create_webp'] ) );

	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'crispy-seo' ) . '</p></div>';

	// Refresh values.
	$jpeg_quality    = get_option( 'crispy_seo_jpeg_quality', 82 );
	$png_compression = get_option( 'crispy_seo_png_compression', 6 );
	$webp_quality    = get_option( 'crispy_seo_webp_quality', 80 );
	$auto_optimize   = get_option( 'crispy_seo_auto_optimize', true );
	$create_webp     = get_option( 'crispy_seo_create_webp', true );
}
?>

<div class="wrap crispy-seo-image-optimization">
	<h1><?php esc_html_e( 'Image Optimization', 'crispy-seo' ); ?></h1>

	<?php if ( ! $libraries['imagick'] && ! $libraries['gd'] ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Warning:', 'crispy-seo' ); ?></strong>
				<?php esc_html_e( 'No image processing library available. Please install GD or Imagick PHP extension.', 'crispy-seo' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="crispy-stats-boxes" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
		<div class="card" style="flex: 1; min-width: 200px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total Images', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #0073aa;">
				<?php echo esc_html( number_format_i18n( $stats['total_images'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 200px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Optimized', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #46b450;">
				<?php echo esc_html( number_format_i18n( $stats['optimized_count'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 200px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Pending', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #f0b849;">
				<?php echo esc_html( number_format_i18n( $stats['queue_pending'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="card" style="flex: 1; min-width: 200px; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Total Saved', 'crispy-seo' ); ?></h3>
			<p style="font-size: 24px; font-weight: bold; margin: 0; color: #826eb4;">
				<?php echo esc_html( size_format( $stats['total_saved'] ?? 0 ) ); ?>
			</p>
		</div>
	</div>

	<div class="card" style="margin-bottom: 20px;">
		<h2><?php esc_html_e( 'Bulk Optimization', 'crispy-seo' ); ?></h2>

		<p>
			<?php
			printf(
				/* translators: %d: number of unoptimized images */
				esc_html__( 'You have %d unoptimized images.', 'crispy-seo' ),
				(int) ( $stats['unoptimized'] ?? 0 )
			);
			?>
		</p>

		<div id="bulk-optimization-controls">
			<?php wp_nonce_field( 'crispy_seo_image_optimization', 'optimization_nonce' ); ?>

			<button type="button" id="queue-all-btn" class="button button-primary">
				<?php esc_html_e( 'Queue All Unoptimized Images', 'crispy-seo' ); ?>
			</button>
			<button type="button" id="process-queue-btn" class="button" disabled>
				<?php esc_html_e( 'Process Queue', 'crispy-seo' ); ?>
			</button>
			<span class="spinner" style="float: none;"></span>
		</div>

		<div id="optimization-progress" style="display: none; margin-top: 20px;">
			<div class="progress-bar-container" style="background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden;">
				<div class="progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
			</div>
			<p class="progress-text" style="margin-top: 10px;"></p>
		</div>
	</div>

	<div class="card" style="margin-bottom: 20px;">
		<h2><?php esc_html_e( 'Settings', 'crispy-seo' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'crispy_seo_optimization_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="jpeg-quality"><?php esc_html_e( 'JPEG Quality', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="range" id="jpeg-quality" name="jpeg_quality"
							   value="<?php echo esc_attr( $jpeg_quality ); ?>"
							   min="60" max="100" step="1">
						<span id="jpeg-quality-value"><?php echo esc_html( $jpeg_quality ); ?>%</span>
						<p class="description">
							<?php esc_html_e( 'Higher values = better quality, larger files. Recommended: 80-85.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="png-compression"><?php esc_html_e( 'PNG Compression', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="range" id="png-compression" name="png_compression"
							   value="<?php echo esc_attr( $png_compression ); ?>"
							   min="0" max="9" step="1">
						<span id="png-compression-value"><?php echo esc_html( $png_compression ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Higher values = more compression. Recommended: 6-7.', 'crispy-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webp-quality"><?php esc_html_e( 'WebP Quality', 'crispy-seo' ); ?></label>
					</th>
					<td>
						<input type="range" id="webp-quality" name="webp_quality"
							   value="<?php echo esc_attr( $webp_quality ); ?>"
							   min="50" max="100" step="1"
							   <?php echo ! $libraries['webp'] ? 'disabled' : ''; ?>>
						<span id="webp-quality-value"><?php echo esc_html( $webp_quality ); ?>%</span>
						<?php if ( ! $libraries['webp'] ) : ?>
							<p class="description" style="color: #dc3232;">
								<?php esc_html_e( 'WebP support not available. Install GD with WebP or Imagick.', 'crispy-seo' ); ?>
							</p>
						<?php else : ?>
							<p class="description">
								<?php esc_html_e( 'Quality for generated WebP images. Recommended: 75-85.', 'crispy-seo' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Optimize', 'crispy-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_optimize" value="1"
								<?php checked( $auto_optimize ); ?>>
							<?php esc_html_e( 'Automatically optimize images on upload', 'crispy-seo' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Create WebP', 'crispy-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="create_webp" value="1"
								<?php checked( $create_webp ); ?>
								<?php echo ! $libraries['webp'] ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Create WebP versions of optimized images', 'crispy-seo' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="crispy_seo_save_optimization_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'crispy-seo' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'System Information', 'crispy-seo' ); ?></h2>

		<table class="widefat" style="max-width: 500px;">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Imagick', 'crispy-seo' ); ?></strong></td>
					<td>
						<?php if ( $libraries['imagick'] ) : ?>
							<span style="color: #46b450;">&#10004; <?php esc_html_e( 'Available', 'crispy-seo' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;">&#10006; <?php esc_html_e( 'Not Available', 'crispy-seo' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'GD Library', 'crispy-seo' ); ?></strong></td>
					<td>
						<?php if ( $libraries['gd'] ) : ?>
							<span style="color: #46b450;">&#10004; <?php esc_html_e( 'Available', 'crispy-seo' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;">&#10006; <?php esc_html_e( 'Not Available', 'crispy-seo' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WebP Support', 'crispy-seo' ); ?></strong></td>
					<td>
						<?php if ( $libraries['webp'] ) : ?>
							<span style="color: #46b450;">&#10004; <?php esc_html_e( 'Available', 'crispy-seo' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;">&#10006; <?php esc_html_e( 'Not Available', 'crispy-seo' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var nonce = $('#optimization_nonce').val();
	var processing = false;

	// Range slider value display.
	$('#jpeg-quality').on('input', function() {
		$('#jpeg-quality-value').text($(this).val() + '%');
	});
	$('#png-compression').on('input', function() {
		$('#png-compression-value').text($(this).val());
	});
	$('#webp-quality').on('input', function() {
		$('#webp-quality-value').text($(this).val() + '%');
	});

	// Queue all images.
	$('#queue-all-btn').on('click', function() {
		var $btn = $(this);
		var $spinner = $btn.siblings('.spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(ajaxurl, {
			action: 'crispy_seo_queue_all_images',
			nonce: nonce
		}, function(response) {
			$spinner.removeClass('is-active');
			$btn.prop('disabled', false);

			if (response.success) {
				alert(response.data.message);
				if (response.data.queued > 0) {
					$('#process-queue-btn').prop('disabled', false);
				}
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Error queuing images.', 'crispy-seo' ) ); ?>');
			}
		});
	});

	// Process queue.
	$('#process-queue-btn').on('click', function() {
		if (processing) return;
		processing = true;

		var $btn = $(this);
		var $spinner = $btn.siblings('.spinner');
		var $progress = $('#optimization-progress');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$progress.show();

		processNextBatch();
	});

	function processNextBatch() {
		$.post(ajaxurl, {
			action: 'crispy_seo_process_queue',
			nonce: nonce
		}, function(response) {
			if (response.success) {
				var stats = response.data.stats;
				var total = stats.queue_completed + stats.queue_failed + stats.queue_pending + stats.queue_processing;
				var done = stats.queue_completed + stats.queue_failed;
				var percent = total > 0 ? Math.round((done / total) * 100) : 100;

				$('.progress-bar').css('width', percent + '%');
				$('.progress-text').text(
					'<?php echo esc_js( __( 'Processed:', 'crispy-seo' ) ); ?> ' + done + ' / ' + total +
					' (<?php echo esc_js( __( 'Failed:', 'crispy-seo' ) ); ?> ' + stats.queue_failed + ')'
				);

				if (stats.queue_pending > 0 || stats.queue_processing > 0) {
					setTimeout(processNextBatch, 500);
				} else {
					processing = false;
					$('.spinner').removeClass('is-active');
					$('#process-queue-btn').prop('disabled', false);
					alert('<?php echo esc_js( __( 'Optimization complete!', 'crispy-seo' ) ); ?>');
					location.reload();
				}
			} else {
				processing = false;
				$('.spinner').removeClass('is-active');
				$('#process-queue-btn').prop('disabled', false);
				alert(response.data.message || '<?php echo esc_js( __( 'Error processing queue.', 'crispy-seo' ) ); ?>');
			}
		}).fail(function() {
			processing = false;
			$('.spinner').removeClass('is-active');
			$('#process-queue-btn').prop('disabled', false);
		});
	}
});
</script>
