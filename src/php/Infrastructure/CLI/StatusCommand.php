<?php
/**
 * WP-CLI command to show liveblog status.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Shows detailed status of a liveblog post.
 */
final class StatusCommand {

	/**
	 * Show detailed status of a liveblog.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the liveblog.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show status of liveblog 123
	 *     wp liveblog status 123
	 *
	 *     # Output as JSON
	 *     wp liveblog status 123 --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id = absint( $args[0] ?? 0 );
		$format  = $assoc_args['format'] ?? 'table';

		if ( 0 === $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
		}

		$liveblog_post = LiveblogPost::from_post( $post );

		if ( ! $liveblog_post->is_liveblog() ) {
			WP_CLI::error( sprintf( 'Post %d is not a liveblog.', $post_id ) );
		}

		$entry_stats       = $this->get_entry_stats( $post_id );
		$key_event_count   = $this->get_key_event_count( $post_id );
		$auto_archive      = $liveblog_post->auto_archive_expiry();
		$auto_archive_date = $auto_archive ? $auto_archive->format( 'Y-m-d H:i:s' ) : 'Not set';

		$status_data = array(
			array( 'Property', 'Value' ),
			array( 'Post ID', (string) $post_id ),
			array( 'Title', $post->post_title ),
			array( 'State', $liveblog_post->state() ),
			array( 'URL', get_permalink( $post_id ) ),
			array( 'Total Entries', (string) $entry_stats['total'] ),
			array( 'Key Events', (string) $key_event_count ),
			array( 'Unique Authors', (string) $entry_stats['authors'] ),
			array( 'First Entry', $entry_stats['first'] ?? 'None' ),
			array( 'Last Entry', $entry_stats['last'] ?? 'None' ),
			array( 'Auto-archive Expiry', $auto_archive_date ),
			array( 'Created', $post->post_date ),
			array( 'Last Modified', $post->post_modified ),
		);

		if ( 'json' === $format ) {
			$json_data = array();
			foreach ( array_slice( $status_data, 1 ) as $row ) {
				$json_data[ $row[0] ] = $row[1];
			}
			WP_CLI::log( wp_json_encode( $json_data, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			foreach ( array_slice( $status_data, 1 ) as $row ) {
				WP_CLI::log( sprintf( '%s: %s', $row[0], $row[1] ) );
			}
			return;
		}

		// Table format.
		Utils\format_items(
			'table',
			array_map(
				function ( $row ) {
					return array(
						'property' => $row[0],
						'value'    => $row[1],
					);
				},
				array_slice( $status_data, 1 )
			),
			array( 'property', 'value' )
		);
	}

	/**
	 * Get entry statistics for a liveblog.
	 *
	 * @param int $post_id Post ID.
	 * @return array{total: int, authors: int, first: string|null, last: string|null}
	 */
	private function get_entry_stats( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					COUNT(DISTINCT user_id) as authors,
					MIN(comment_date) as first_date,
					MAX(comment_date) as last_date
				FROM $wpdb->comments
				WHERE comment_post_ID = %d
				AND comment_approved = 'liveblog'",
				$post_id
			)
		);

		return array(
			'total'   => (int) ( $stats->total ?? 0 ),
			'authors' => (int) ( $stats->authors ?? 0 ),
			'first'   => $stats->first_date ?? null,
			'last'    => $stats->last_date ?? null,
		);
	}

	/**
	 * Get key event count for a liveblog.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function get_key_event_count( int $post_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->comments c
				INNER JOIN $wpdb->commentmeta cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID = %d
				AND c.comment_approved = 'liveblog'
				AND cm.meta_key = 'liveblog_key_entry'
				AND cm.meta_value = '1'",
				$post_id
			)
		);

		return (int) $count;
	}
}
