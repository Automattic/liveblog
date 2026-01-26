<?php
/**
 * WP-CLI command to list liveblog entries.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Lists entries for a liveblog post.
 */
final class EntriesCommand {

	/**
	 * Entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $entry_query_service;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService $entry_query_service Entry query service.
	 */
	public function __construct( EntryQueryService $entry_query_service ) {
		$this->entry_query_service = $entry_query_service;
	}

	/**
	 * List entries for a liveblog.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the liveblog.
	 *
	 * [--key-events]
	 * : Only show key events.
	 *
	 * [--limit=<number>]
	 * : Limit the number of entries returned.
	 * ---
	 * default: 20
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
	 *     # List recent entries for liveblog 123
	 *     wp liveblog entries 123
	 *
	 *     # List only key events
	 *     wp liveblog entries 123 --key-events
	 *
	 *     # Export all entries as JSON
	 *     wp liveblog entries 123 --limit=0 --format=json
	 *
	 *     # Get entry IDs only
	 *     wp liveblog entries 123 --format=ids
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id    = absint( $args[0] ?? 0 );
		$key_events = isset( $assoc_args['key-events'] );
		$limit      = (int) ( $assoc_args['limit'] ?? 20 );
		$format     = $assoc_args['format'] ?? 'table';

		if ( 0 === $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( null === $liveblog_post || ! $liveblog_post->is_liveblog() ) {
			WP_CLI::error( sprintf( 'Post %d is not a liveblog.', $post_id ) );
		}

		$entries = $this->get_entries( $post_id, $key_events, $limit );

		if ( empty( $entries ) ) {
			$message = $key_events ? 'No key events found.' : 'No entries found.';
			WP_CLI::warning( $message );
			return;
		}

		if ( 'ids' === $format ) {
			WP_CLI::log( implode( ' ', wp_list_pluck( $entries, 'ID' ) ) );
			return;
		}

		$formatted_entries = array_map(
			function ( $entry ) use ( $key_events ) {
				$data = array(
					'ID'      => $entry->comment_ID,
					'author'  => $entry->comment_author,
					'date'    => $entry->comment_date,
					'content' => wp_trim_words( wp_strip_all_tags( $entry->comment_content ), 20 ),
				);

				if ( ! $key_events ) {
					$data['key_event'] = get_comment_meta( $entry->comment_ID, 'liveblog_key_entry', true ) ? 'Yes' : 'No';
				}

				return $data;
			},
			$entries
		);

		$columns = $key_events
			? array( 'ID', 'author', 'date', 'content' )
			: array( 'ID', 'author', 'date', 'key_event', 'content' );

		Utils\format_items( $format, $formatted_entries, $columns );
	}

	/**
	 * Get entries for a liveblog.
	 *
	 * @param int  $post_id    Post ID.
	 * @param bool $key_events Only return key events.
	 * @param int  $limit      Maximum entries (0 for all).
	 * @return \WP_Comment[]
	 */
	private function get_entries( int $post_id, bool $key_events, int $limit ): array {
		$args = array(
			'post_id' => $post_id,
			'status'  => 'liveblog',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		if ( $limit > 0 ) {
			$args['number'] = $limit;
		}

		if ( $key_events ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI command for listing entries.
				array(
					'key'   => 'liveblog_key_entry',
					'value' => '1',
				),
			);
		}

		return get_comments( $args );
	}
}
