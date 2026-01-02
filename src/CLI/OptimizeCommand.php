<?php
/**
 * Image Optimization WP-CLI Command
 *
 * Provides CLI commands for image optimization.
 *
 * @package CrispySEO\CLI
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\CLI;

use CrispySEO\Media\ImageOptimizer;
use WP_CLI;

/**
 * Manage image optimization in Crispy SEO.
 */
class OptimizeCommand {

	/**
	 * Image optimizer instance.
	 *
	 * @var ImageOptimizer
	 */
	private ImageOptimizer $optimizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->optimizer = new ImageOptimizer();
	}

	/**
	 * Optimize a single image.
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : Attachment ID to optimize.
	 *
	 * [--jpeg-quality=<quality>]
	 * : JPEG quality (60-100).
	 * ---
	 * default: 82
	 * ---
	 *
	 * [--create-webp]
	 * : Also create WebP version.
	 *
	 * [--no-backup]
	 * : Skip backup of original.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize image 123
	 *     wp crispy-seo optimize image 123 --jpeg-quality=75 --create-webp
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function image( array $args, array $assocArgs ): void {
		$attachmentId = (int) $args[0];

		$options = [
			'jpeg_quality' => (int) ( $assocArgs['jpeg-quality'] ?? 82 ),
			'create_webp'  => isset( $assocArgs['create-webp'] ),
			'backup'       => ! isset( $assocArgs['no-backup'] ),
		];

		$attachment = \get_post( $attachmentId );

		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			WP_CLI::error( 'Invalid attachment ID.' );
		}

		$mimeType = get_post_mime_type( $attachmentId );

		if ( strpos( $mimeType, 'image/' ) !== 0 ) {
			WP_CLI::error( 'Attachment is not an image.' );
		}

		WP_CLI::log( sprintf( 'Optimizing: %s', \get_attached_file( $attachmentId ) ) );

		$result = $this->optimizer->optimizeImage( $attachmentId, $options );

		if ( $result['success'] ) {
			WP_CLI::success(
				sprintf(
					'%s Saved %s (%.2f%%)',
					$result['message'],
					size_format( $result['savings_bytes'] ),
					$result['savings_percent']
				)
			);
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Queue all unoptimized images.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum images to queue.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize queue
	 *     wp crispy-seo optimize queue --limit=500
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function queue( array $args, array $assocArgs ): void {
		global $wpdb;

		$limit = (int) ( $assocArgs['limit'] ?? 1000 );

		// Get unoptimized images.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachmentIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_crispy_seo_optimized_at'
				 WHERE p.post_type = 'attachment'
				 AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
				 AND pm.meta_id IS NULL
				 LIMIT %d",
				$limit
			)
		);

		if ( empty( $attachmentIds ) ) {
			WP_CLI::log( 'No unoptimized images found.' );
			return;
		}

		$queued = 0;
		$progress = WP_CLI\Utils\make_progress_bar( 'Queuing images', count( $attachmentIds ) );

		foreach ( $attachmentIds as $id ) {
			if ( $this->optimizer->queueOptimization( (int) $id ) ) {
				++$queued;
			}
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( '%d images queued for optimization.', $queued ) );
	}

	/**
	 * Process the optimization queue.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum images to process per batch.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--all]
	 * : Process entire queue (may take a long time).
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize process
	 *     wp crispy-seo optimize process --limit=100
	 *     wp crispy-seo optimize process --all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function process( array $args, array $assocArgs ): void {
		$limit     = (int) ( $assocArgs['limit'] ?? 50 );
		$all       = isset( $assocArgs['all'] );
		$processed = 0;
		$failed    = 0;

		if ( $all ) {
			$stats = $this->optimizer->getStats();
			$total = $stats['queue_pending'] + $stats['queue_processing'];

			if ( $total === 0 ) {
				WP_CLI::log( 'Queue is empty.' );
				return;
			}

			$progress = WP_CLI\Utils\make_progress_bar( 'Processing queue', $total );

			while ( true ) {
				$result = $this->optimizer->processQueueBatch( 10 );

				$processed += $result['processed'];
				$failed    += $result['failed'];

				for ( $i = 0; $i < $result['processed'] + $result['failed']; $i++ ) {
					$progress->tick();
				}

				if ( $result['processed'] === 0 && $result['failed'] === 0 ) {
					break;
				}
			}

			$progress->finish();
		} else {
			WP_CLI::log( sprintf( 'Processing up to %d images...', $limit ) );

			$result    = $this->optimizer->processQueueBatch( $limit );
			$processed = $result['processed'];
			$failed    = $result['failed'];
		}

		WP_CLI::success(
			sprintf(
				'Processed %d images. Failed: %d.',
				$processed,
				$failed
			)
		);
	}

	/**
	 * Show optimization statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function stats( array $args, array $assocArgs ): void {
		$stats     = $this->optimizer->getStats();
		$libraries = $this->optimizer->getAvailableLibraries();

		WP_CLI::log( 'Image Optimization Statistics:' );
		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '  Total images: %d', $stats['total_images'] ) );
		WP_CLI::log( sprintf( '  Optimized: %d', $stats['optimized_count'] ) );
		WP_CLI::log( sprintf( '  Unoptimized: %d', $stats['unoptimized'] ) );
		WP_CLI::log( '' );
		WP_CLI::log( 'Queue Status:' );
		WP_CLI::log( sprintf( '  Pending: %d', $stats['queue_pending'] ) );
		WP_CLI::log( sprintf( '  Processing: %d', $stats['queue_processing'] ) );
		WP_CLI::log( sprintf( '  Completed: %d', $stats['queue_completed'] ) );
		WP_CLI::log( sprintf( '  Failed: %d', $stats['queue_failed'] ) );
		WP_CLI::log( '' );
		WP_CLI::log( 'Savings:' );
		WP_CLI::log( sprintf( '  Total saved: %s', size_format( $stats['total_saved'] ) ) );
		WP_CLI::log( sprintf( '  Average savings: %.2f%%', $stats['avg_savings'] ) );
		WP_CLI::log( '' );
		WP_CLI::log( 'Available Libraries:' );
		WP_CLI::log( sprintf( '  Imagick: %s', $libraries['imagick'] ? 'Yes' : 'No' ) );
		WP_CLI::log( sprintf( '  GD: %s', $libraries['gd'] ? 'Yes' : 'No' ) );
		WP_CLI::log( sprintf( '  WebP: %s', $libraries['webp'] ? 'Yes' : 'No' ) );
	}

	/**
	 * Optimize all unoptimized images.
	 *
	 * This command queues and processes all unoptimized images.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum images to optimize.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize all
	 *     wp crispy-seo optimize all --limit=500 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function all( array $args, array $assocArgs ): void {
		$limit = (int) ( $assocArgs['limit'] ?? 100 );

		$stats = $this->optimizer->getStats();

		WP_CLI::log( sprintf( 'Found %d unoptimized images.', $stats['unoptimized'] ) );

		if ( $stats['unoptimized'] === 0 ) {
			WP_CLI::log( 'All images are already optimized.' );
			return;
		}

		$toProcess = min( $limit, $stats['unoptimized'] );

		if ( ! isset( $assocArgs['yes'] ) ) {
			WP_CLI::confirm( sprintf( 'Optimize %d images?', $toProcess ) );
		}

		// Queue images.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachmentIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_crispy_seo_optimized_at'
				 WHERE p.post_type = 'attachment'
				 AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
				 AND pm.meta_id IS NULL
				 LIMIT %d",
				$toProcess
			)
		);

		$progress  = WP_CLI\Utils\make_progress_bar( 'Optimizing', count( $attachmentIds ) );
		$processed = 0;
		$failed    = 0;
		$saved     = 0;

		foreach ( $attachmentIds as $id ) {
			$result = $this->optimizer->optimizeImage( (int) $id );

			if ( $result['success'] ) {
				++$processed;
				$saved += $result['savings_bytes'];
			} else {
				++$failed;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success(
			sprintf(
				'Optimized %d images. Failed: %d. Total saved: %s.',
				$processed,
				$failed,
				size_format( $saved )
			)
		);
	}

	/**
	 * Create WebP versions of all optimized images.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum images to process.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--quality=<quality>]
	 * : WebP quality (50-100).
	 * ---
	 * default: 80
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo optimize webp
	 *     wp crispy-seo optimize webp --limit=500 --quality=75
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function webp( array $args, array $assocArgs ): void {
		$limit   = (int) ( $assocArgs['limit'] ?? 100 );
		$quality = (int) ( $assocArgs['quality'] ?? 80 );

		$libraries = $this->optimizer->getAvailableLibraries();

		if ( ! $libraries['webp'] ) {
			WP_CLI::error( 'WebP support is not available. Install GD with WebP or Imagick.' );
		}

		global $wpdb;

		// Get images without WebP versions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachmentIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'attachment'
				 AND post_mime_type IN ('image/jpeg', 'image/png')
				 LIMIT %d",
				$limit
			)
		);

		if ( empty( $attachmentIds ) ) {
			WP_CLI::log( 'No images to process.' );
			return;
		}

		$progress = WP_CLI\Utils\make_progress_bar( 'Creating WebP', count( $attachmentIds ) );
		$created  = 0;
		$skipped  = 0;

		foreach ( $attachmentIds as $id ) {
			$filePath = \get_attached_file( (int) $id );

			if ( ! $filePath ) {
				++$skipped;
				$progress->tick();
				continue;
			}

			$webpPath = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $filePath );

			// Skip if WebP already exists.
			if ( file_exists( $webpPath ) ) {
				++$skipped;
				$progress->tick();
				continue;
			}

			$result = $this->optimizer->createWebP( $filePath, $quality );

			if ( $result ) {
				++$created;
			} else {
				++$skipped;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Created %d WebP images. Skipped: %d.', $created, $skipped ) );
	}
}
