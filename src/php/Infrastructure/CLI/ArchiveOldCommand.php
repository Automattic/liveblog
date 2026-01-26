<?php
/**
 * WP-CLI command to archive old liveblogs.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;
use WP_CLI\Utils;
use WP_Query;

/**
 * Archives liveblogs that have been inactive for a specified period.
 */
final class ArchiveOldCommand {

	/**
	 * Archive liveblogs with no recent activity.
	 *
	 * ## OPTIONS
	 *
	 * --days=<number>
	 * : Archive liveblogs with no entries in the last N days.
	 *
	 * [--dry-run]
	 * : Show what would be archived without making changes.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview liveblogs that would be archived (inactive > 30 days)
	 *     wp liveblog archive-old --days=30 --dry-run
	 *
	 *     # Archive all liveblogs inactive for 60+ days
	 *     wp liveblog archive-old --days=60 --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! isset( $assoc_args['days'] ) ) {
			WP_CLI::error( 'Please specify --days=<number> to set the inactivity threshold.' );
		}

		$days    = absint( $assoc_args['days'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $days < 1 ) {
			WP_CLI::error( 'Days must be at least 1.' );
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		WP_CLI::line( sprintf( 'Finding enabled liveblogs with no activity since %s...', $cutoff_date ) );

		$liveblogs_to_archive = $this->find_inactive_liveblogs( $cutoff_date );

		if ( empty( $liveblogs_to_archive ) ) {
			WP_CLI::success( 'No inactive liveblogs found.' );
			return;
		}

		WP_CLI::line( sprintf( 'Found %d liveblog(s) to archive:', count( $liveblogs_to_archive ) ) );
		WP_CLI::line( '' );

		// Show preview.
		$preview_data = array_map(
			function ( $post ) {
				return array(
					'ID'         => $post->ID,
					'title'      => $post->post_title,
					'last_entry' => $this->get_last_entry_date( $post->ID ) ?? 'No entries',
				);
			},
			$liveblogs_to_archive
		);

		Utils\format_items( 'table', $preview_data, array( 'ID', 'title', 'last_entry' ) );

		if ( $dry_run ) {
			WP_CLI::line( '' );
			WP_CLI::success( 'Dry run complete. No changes made.' );
			return;
		}

		// Confirm unless --yes flag is set.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( sprintf( 'Archive %d liveblog(s)?', count( $liveblogs_to_archive ) ) );
		}

		$progress = Utils\make_progress_bar( 'Archiving liveblogs', count( $liveblogs_to_archive ) );
		$archived = 0;

		foreach ( $liveblogs_to_archive as $post ) {
			$liveblog_post = LiveblogPost::from_post( $post );
			$liveblog_post->archive();
			++$archived;
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Archived %d liveblog(s).', $archived ) );
	}

	/**
	 * Find liveblogs with no activity since cutoff date.
	 *
	 * @param string $cutoff_date MySQL datetime string.
	 * @return \WP_Post[]
	 */
	private function find_inactive_liveblogs( string $cutoff_date ): array {
		global $wpdb;

		// Get all enabled liveblogs.
		$query = new WP_Query(
			array(
				'post_type'      => $this->get_supported_post_types(),
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => 'liveblog', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for finding liveblogs.
				'meta_value'     => 'enable', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only enabled liveblogs.
			)
		);

		if ( 0 === $query->found_posts ) {
			return array();
		}

		$inactive = array();

		foreach ( $query->posts as $post ) {
			$last_entry_date = $this->get_last_entry_date( $post->ID );

			// No entries = consider inactive.
			if ( null === $last_entry_date ) {
				// Check if post itself is older than cutoff.
				if ( $post->post_date < $cutoff_date ) {
					$inactive[] = $post;
				}
				continue;
			}

			// Has entries - check if last one is before cutoff.
			if ( $last_entry_date < $cutoff_date ) {
				$inactive[] = $post;
			}
		}

		return $inactive;
	}

	/**
	 * Get the date of the last entry for a liveblog.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null MySQL datetime or null if no entries.
	 */
	private function get_last_entry_date( int $post_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(comment_date) FROM $wpdb->comments
				WHERE comment_post_ID = %d AND comment_approved = 'liveblog'",
				$post_id
			)
		);

		return $date ?: null;
	}

	/**
	 * Get supported post types for liveblogs.
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
		$post_types = array( 'post' );

		/** This filter is documented in PluginBootstrapper.php */
		return apply_filters( 'liveblog_supported_post_types', $post_types );
	}
}
