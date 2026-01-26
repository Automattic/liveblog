<?php
/**
 * WP-CLI command to fix archived liveblog data.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\ArchiveRepairServiceInterface;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Fixes wp_commentmeta table so archived liveblog posts comments display properly.
 *
 * @phpcs:disable WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli -- Plugin must work outside VIP Go environment.
 */
final class FixArchiveCommand {

	/**
	 * The archive repair service.
	 *
	 * @var ArchiveRepairServiceInterface
	 */
	private ArchiveRepairServiceInterface $service;

	/**
	 * Constructor.
	 *
	 * @param ArchiveRepairServiceInterface $service The archive repair service.
	 */
	public function __construct( ArchiveRepairServiceInterface $service ) {
		$this->service = $service;
	}

	/**
	 * Fix wp_commentmeta table so archived liveblog posts comments display properly.
	 *
	 * This command repairs data inconsistencies that can occur when liveblog
	 * entries are edited while archived. It corrects the liveblog_replaces
	 * meta values and restores proper comment content.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Run without making changes to see what would be modified.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview what would be fixed
	 *     wp liveblog fix-archive --dry-run
	 *
	 *     # Actually fix the archives
	 *     wp liveblog fix-archive
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$is_dry_run = isset( $assoc_args['dry-run'] );

		if ( $is_dry_run ) {
			WP_CLI::line( 'Running in dry-run mode. No changes will be made.' );
			WP_CLI::line( '' );
		}

		WP_CLI::line( 'Finding all liveblog entries...' );

		$posts = $this->service->find_liveblog_posts();

		$total_posts = count( $posts );

		if ( 0 === $total_posts ) {
			WP_CLI::warning( 'No liveblog posts found.' );
			return;
		}

		WP_CLI::line( sprintf( 'Found %d liveblog post(s).', $total_posts ) );
		WP_CLI::line( '' );

		$progress = Utils\make_progress_bar( 'Processing liveblogs', $total_posts );

		$totals = array(
			'entries_corrected' => 0,
			'content_replaced'  => 0,
		);

		foreach ( $posts as $post ) {
			$stats = $this->service->repair_post( $post->ID, $is_dry_run );

			$totals['entries_corrected'] += $stats['entries_corrected'];
			$totals['content_replaced']  += $stats['content_replaced'];

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::line( '' );

		// Display summary.
		WP_CLI::line( sprintf( 'Entries corrected: %d', $totals['entries_corrected'] ) );
		WP_CLI::line( sprintf( 'Content items replaced: %d', $totals['content_replaced'] ) );

		if ( $is_dry_run ) {
			WP_CLI::success( 'Dry run completed. Re-run without --dry-run to apply changes.' );
		} else {
			WP_CLI::success( 'Fixed all entries on all liveblog posts.' );
		}
	}
}
