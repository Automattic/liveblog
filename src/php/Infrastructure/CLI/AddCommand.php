<?php
/**
 * WP-CLI command to add a liveblog entry.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;
use WP_User;

/**
 * Adds a new entry to a liveblog.
 */
final class AddCommand {

	/**
	 * Entry service.
	 *
	 * @var EntryService
	 */
	private EntryService $entry_service;

	/**
	 * Constructor.
	 *
	 * @param EntryService $entry_service Entry service.
	 */
	public function __construct( EntryService $entry_service ) {
		$this->entry_service = $entry_service;
	}

	/**
	 * Add a new entry to a liveblog.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the liveblog.
	 *
	 * <content>
	 * : The entry content. Use quotes for multi-word content.
	 *
	 * [--author=<user_id>]
	 * : User ID for the entry author. Defaults to current user or admin.
	 *
	 * [--contributors=<user_ids>]
	 * : Comma-separated list of contributor user IDs.
	 *
	 * [--hide-authors]
	 * : Hide the author name on this entry.
	 *
	 * [--key-event]
	 * : Mark this entry as a key event.
	 *
	 * [--porcelain]
	 * : Output only the new entry ID.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a simple entry
	 *     wp liveblog add 123 "Breaking news: something happened!"
	 *
	 *     # Add entry with specific author
	 *     wp liveblog add 123 "Update from the field" --author=5
	 *
	 *     # Add entry with multiple contributors
	 *     wp liveblog add 123 "Team report" --author=5 --contributors=6,7,8
	 *
	 *     # Add anonymous key event
	 *     wp liveblog add 123 "Major development!" --hide-authors --key-event
	 *
	 *     # Get just the entry ID for scripting
	 *     wp liveblog add 123 "New entry" --porcelain
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id  = absint( $args[0] ?? 0 );
		$content  = $args[1] ?? '';
		$porcelain = isset( $assoc_args['porcelain'] );

		if ( 0 === $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		if ( empty( $content ) ) {
			WP_CLI::error( 'Please provide entry content.' );
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( null === $liveblog_post ) {
			WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
		}

		if ( ! $liveblog_post->is_enabled() ) {
			if ( $liveblog_post->is_archived() ) {
				WP_CLI::error( sprintf( 'Liveblog %d is archived. Unarchive it first with: wp liveblog unarchive %d', $post_id, $post_id ) );
			} else {
				WP_CLI::error( sprintf( 'Post %d is not an enabled liveblog.', $post_id ) );
			}
		}

		// Get author.
		$author = $this->get_author( $assoc_args );
		if ( null === $author ) {
			WP_CLI::error( 'Could not determine author. Specify --author=<user_id> or ensure a user is logged in.' );
		}

		// Parse options.
		$hide_authors = isset( $assoc_args['hide-authors'] );
		$key_event    = isset( $assoc_args['key-event'] );
		$contributors = $this->parse_contributors( $assoc_args['contributors'] ?? '' );

		// Apply content filters (commands, emojis, etc.).
		$content = apply_filters( 'liveblog_before_insert_entry', $content );

		// Create the entry.
		$entry_id = $this->entry_service->create(
			$post_id,
			$content,
			$author,
			$hide_authors,
			$contributors
		);

		if ( null === $entry_id ) {
			WP_CLI::error( 'Failed to create entry.' );
		}

		// Mark as key event if requested.
		if ( $key_event ) {
			update_comment_meta( $entry_id->to_int(), 'liveblog_key_entry', '1' );
		}

		if ( $porcelain ) {
			WP_CLI::log( (string) $entry_id->to_int() );
			return;
		}

		WP_CLI::success(
			sprintf(
				'Entry %d added to liveblog %d.%s',
				$entry_id->to_int(),
				$post_id,
				$key_event ? ' (Key event)' : ''
			)
		);
	}

	/**
	 * Get the author for the entry.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return WP_User|null
	 */
	private function get_author( array $assoc_args ): ?WP_User {
		if ( isset( $assoc_args['author'] ) ) {
			$user = get_user_by( 'id', absint( $assoc_args['author'] ) );
			if ( $user instanceof WP_User ) {
				return $user;
			}
			WP_CLI::warning( sprintf( 'User %d not found, using default.', $assoc_args['author'] ) );
		}

		// Try current user.
		$current_user = wp_get_current_user();
		if ( $current_user->exists() ) {
			return $current_user;
		}

		// Fall back to first admin.
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		if ( ! empty( $admins ) ) {
			return $admins[0];
		}

		return null;
	}

	/**
	 * Parse contributor IDs from comma-separated string.
	 *
	 * @param string $contributors Comma-separated user IDs.
	 * @return int[]
	 */
	private function parse_contributors( string $contributors ): array {
		if ( empty( $contributors ) ) {
			return array();
		}

		$ids = array_map( 'absint', explode( ',', $contributors ) );
		return array_filter( $ids );
	}
}
