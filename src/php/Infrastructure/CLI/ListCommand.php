<?php
/**
 * WP-CLI command to list all liveblogs.
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
 * Lists all liveblog posts with their state.
 */
final class ListCommand {

	/**
	 * List all liveblog posts.
	 *
	 * ## OPTIONS
	 *
	 * [--state=<state>]
	 * : Filter by state. Accepts: enabled, archived, all.
	 * ---
	 * default: all
	 * options:
	 *   - enabled
	 *   - archived
	 *   - all
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all liveblogs
	 *     wp liveblog list
	 *
	 *     # List only enabled liveblogs
	 *     wp liveblog list --state=enabled
	 *
	 *     # Output as JSON
	 *     wp liveblog list --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$state  = $assoc_args['state'] ?? 'all';
		$format = $assoc_args['format'] ?? 'table';

		$query_args = array(
			'post_type'      => $this->get_supported_post_types(),
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Expected to be fast with proper indexing and limited results.
				array(
					'taxonomy' => \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array(
						\Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ENABLED,
						\Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ARCHIVED,
					),
				),
			),
		);

		// Filter by state if specified.
		if ( 'all' !== $state ) {
			$query_args['tax_query'][0]['terms'] = 'enabled' === $state
				? array( \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ENABLED )
				: array( \Automattic\Liveblog\Application\Config\LiveblogConfiguration::TERM_ARCHIVED );
		}

		$query = new WP_Query( $query_args );

		if ( 0 === $query->found_posts ) {
			WP_CLI::warning( 'No liveblogs found.' );
			return;
		}

		$liveblogs = array();

		foreach ( $query->posts as $post ) {
			$liveblog_post = LiveblogPost::from_post( $post );
			$entry_count   = $this->get_entry_count( $post->ID );

			$liveblogs[] = array(
				'ID'           => $post->ID,
				'title'        => $post->post_title,
				'state'        => $liveblog_post->state(),
				'entries'      => $entry_count,
				'last_updated' => $post->post_modified,
				'url'          => get_permalink( $post->ID ),
			);
		}

		if ( 'ids' === $format ) {
			WP_CLI::log( implode( ' ', wp_list_pluck( $liveblogs, 'ID' ) ) );
			return;
		}

		Utils\format_items( $format, $liveblogs, array( 'ID', 'title', 'state', 'entries', 'last_updated' ) );
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

	/**
	 * Get the number of entries for a liveblog post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Entry count.
	 */
	private function get_entry_count( int $post_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command for counting entries.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'post' AND post_status = 'publish'",
				$post_id
			)
		);

		return (int) $count;
	}
}
