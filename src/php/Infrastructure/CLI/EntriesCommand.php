<?php
/**
 * WP-CLI command to list liveblog entries.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\Entry;
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
		$post_id = absint( $args[0] ?? 0 );
		$limit   = (int) ( $assoc_args['limit'] ?? 20 );
		$format  = $assoc_args['format'] ?? 'table';

		if ( 0 === $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( null === $liveblog_post || ! $liveblog_post->is_liveblog() ) {
			WP_CLI::error( sprintf( 'Post %d is not a liveblog.', $post_id ) );
		}

		$entries = $this->entry_query_service->get_all( $post_id, $limit );

		if ( empty( $entries ) ) {
			WP_CLI::warning( 'No entries found.' );
			return;
		}

		if ( 'ids' === $format ) {
			WP_CLI::log( implode( ' ', wp_list_pluck( $entries, 'ID' ) ) );
			return;
		}

		$formatted_entries = array_map(
			function ( Entry $entry ) {
				return array(
					'ID'      => $entry->id()->to_int(),
					'author'  => $entry->authors()->is_empty() ? 'Anonymous' : $entry->authors()->primary()->name(),
					'date'    => $entry->created_at()->format( 'Y-m-d H:i:s' ),
					'content' => wp_trim_words( wp_strip_all_tags( $entry->content()->raw() ), 20 ),
				);
			},
			$entries
		);

		Utils\format_items(
			$format,
			$formatted_entries,
			array( 'ID', 'author', 'date', 'content' )
		);
	}
}
