<?php
/**
 * WP-CLI command to show overall liveblog statistics.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use WP_CLI;
use WP_CLI\Utils;
use WP_Query;

/**
 * Shows overall statistics for all liveblogs.
 */
final class StatsCommand {

	/**
	 * Show overall liveblog statistics.
	 *
	 * ## OPTIONS
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
	 *     # Show overall stats
	 *     wp liveblog stats
	 *
	 *     # Output as JSON
	 *     wp liveblog stats --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		global $wpdb;

		$format = $assoc_args['format'] ?? 'table';

		// Count liveblogs by state.
		$enabled_count  = $this->count_liveblogs_by_state( 'enable' );
		$archived_count = $this->count_liveblogs_by_state( 'archive' );
		$total_count    = $enabled_count + $archived_count;

		// Count total entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$total_entries = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' AND post_parent > 0"
		);

		// Count unique authors.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$unique_authors = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_author) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' AND post_parent > 0 AND post_author > 0"
		);

		// Get most active liveblog.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$most_active = $wpdb->get_row(
			"SELECT post_parent, COUNT(*) as entry_count
			FROM $wpdb->posts
			WHERE post_type = 'post'
			AND post_status = 'publish'
			AND post_parent > 0
			GROUP BY post_parent
			ORDER BY entry_count DESC
			LIMIT 1"
		);

		$most_active_title = '';
		$most_active_count = 0;
		if ( $most_active ) {
			$post              = get_post( $most_active->post_parent );
			$most_active_title = $post ? $post->post_title : 'Unknown';
			$most_active_count = (int) $most_active->entry_count;
		}

		// Get date of most recent entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command.
		$most_recent_entry = $wpdb->get_var(
			"SELECT MAX(post_date) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' AND post_parent > 0"
		);

		$stats = array(
			array( 'Metric', 'Value' ),
			array( 'Total Liveblogs', (string) $total_count ),
			array( 'Enabled', (string) $enabled_count ),
			array( 'Archived', (string) $archived_count ),
			array( 'Total Entries', number_format( $total_entries ) ),
			array( 'Unique Authors', (string) $unique_authors ),
			array( 'Avg Entries/Liveblog', $total_count > 0 ? number_format( $total_entries / $total_count, 1 ) : '0' ),
			array( 'Most Active Liveblog', $most_active_title ? sprintf( '%s (%d entries)', $most_active_title, $most_active_count ) : 'None' ),
			array( 'Most Recent Entry', $most_recent_entry ?? 'None' ),
		);

		if ( 'json' === $format ) {
			$json_data = array();
			foreach ( array_slice( $stats, 1 ) as $row ) {
				$json_data[ $row[0] ] = $row[1];
			}
			WP_CLI::log( wp_json_encode( $json_data, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			foreach ( array_slice( $stats, 1 ) as $row ) {
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
						'metric' => $row[0],
						'value'  => $row[1],
					);
				},
				array_slice( $stats, 1 )
			),
			array( 'metric', 'value' )
		);
	}

	/**
	 * Count liveblogs by state.
	 *
	 * @param string $state The state value (enable or archive).
	 * @return int
	 */
	private function count_liveblogs_by_state( string $state ): int {
		$slug = 'enable' === $state
			? \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ENABLED
			: \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ARCHIVED;

		$query = new WP_Query(
			array(
				'post_type'      => $this->get_supported_post_types(),
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Expected to be fast with proper indexing and limited results.
					array(
						'taxonomy' => \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $slug,
					),
				),
			)
		);

		return $query->found_posts;
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
