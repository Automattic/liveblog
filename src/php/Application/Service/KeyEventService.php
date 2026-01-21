<?php
/**
 * Key event service for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;

/**
 * Service for managing key events in liveblogs.
 *
 * Key events are entries marked with the /key command that appear
 * in the key events shortcode sidebar.
 */
final class KeyEventService {

	/**
	 * Meta key for key entries.
	 *
	 * @var string
	 */
	public const META_KEY = 'liveblog_key_entry';

	/**
	 * Meta value for key entries.
	 *
	 * @var string
	 */
	public const META_VALUE = 'true';

	/**
	 * The entry repository.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * The entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $query_service;

	/**
	 * Constructor.
	 *
	 * @param EntryRepositoryInterface $repository    The entry repository.
	 * @param EntryQueryService        $query_service The entry query service.
	 */
	public function __construct( EntryRepositoryInterface $repository, EntryQueryService $query_service ) {
		$this->repository    = $repository;
		$this->query_service = $query_service;
	}

	/**
	 * Check if an entry is a key event.
	 *
	 * @param int $entry_id The entry ID.
	 * @return bool True if the entry is a key event.
	 */
	public function is_key_event( int $entry_id ): bool {
		return self::META_VALUE === get_comment_meta( $entry_id, self::META_KEY, true );
	}

	/**
	 * Check if content contains the /key command.
	 *
	 * The /key command can appear in two forms:
	 * 1. Plain text: /key (when newly typed)
	 * 2. Transformed: <span class="liveblog-command type-key">key</span> (after processing)
	 *
	 * @param string $content The content to check.
	 * @return bool True if content contains /key command.
	 */
	public function content_has_key_command( string $content ): bool {
		// Check for plain /key command (at start or after non-word char).
		$has_plain_key = (bool) preg_match( '/(^|[^\w])\/key([^\w]|$)/', $content );

		// Check for transformed span version (flexible matching for class attribute).
		$has_span_key = (bool) preg_match( '/<span[^>]*class="[^"]*type-key[^"]*"[^>]*>/i', $content );

		return $has_plain_key || $has_span_key;
	}

	/**
	 * Mark an entry as a key event.
	 *
	 * @param int $entry_id The entry ID.
	 * @return bool True on success.
	 */
	public function mark_as_key_event( int $entry_id ): bool {
		return (bool) add_comment_meta( $entry_id, self::META_KEY, self::META_VALUE );
	}

	/**
	 * Remove key event status from an entry.
	 *
	 * @param int $entry_id The entry ID.
	 * @return bool True on success.
	 */
	public function remove_key_event( int $entry_id ): bool {
		return delete_comment_meta( $entry_id, self::META_KEY, self::META_VALUE );
	}

	/**
	 * Sync key event meta with /key command in content.
	 *
	 * When an entry is updated, check if it contains the /key command
	 * and add or remove the key event meta accordingly.
	 *
	 * @param int $entry_id The entry ID.
	 * @param int $post_id  The post ID.
	 * @return void
	 */
	public function sync_key_event_meta( int $entry_id, int $post_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by liveblog_update_entry action signature.
		$content     = get_comment_text( $entry_id );
		$has_key_cmd = $this->content_has_key_command( $content );

		// Get the original entry ID that this update replaces.
		$original_id = get_comment_meta( $entry_id, CommentEntryRepository::REPLACES_META_KEY, true );

		// Check both the new comment and the original entry.
		$ids_to_check = array( $entry_id );
		if ( $original_id ) {
			$ids_to_check[] = (int) $original_id;
		}

		foreach ( $ids_to_check as $check_id ) {
			$is_key = $this->is_key_event( $check_id );

			if ( $has_key_cmd && ! $is_key ) {
				$this->mark_as_key_event( $check_id );
			} elseif ( ! $has_key_cmd && $is_key ) {
				$this->remove_key_event( $check_id );
			}
		}
	}

	/**
	 * Handle the /key command action.
	 *
	 * Called when the /key command is used in an entry.
	 *
	 * @param string $content  The entry content.
	 * @param int    $entry_id The entry ID.
	 * @param int    $post_id  The post ID.
	 * @return void
	 */
	public function handle_key_command( string $content, int $entry_id, int $post_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Required by liveblog_command_key_after action signature.
		$this->mark_as_key_event( $entry_id );
	}

	/**
	 * Remove key event status from an entry and strip /key from content.
	 *
	 * @param string $content  The entry content.
	 * @param int    $entry_id The entry ID.
	 * @return string Modified content with /key removed.
	 */
	public function remove_key_action( string $content, int $entry_id ): string {
		delete_comment_meta( $entry_id, self::META_KEY, self::META_VALUE );
		return str_replace( '/key', '', $content );
	}

	/**
	 * Get all key events for a post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $limit   Maximum number of entries (0 for unlimited).
	 * @return Entry[] Array of key event entries.
	 */
	public function get_key_events( int $post_id, int $limit = 0 ): array {
		$args = array(
			'meta_key'   => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for key event query.
			'meta_value' => self::META_VALUE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for key event query.
		);

		return $this->query_service->get_all( $post_id, $limit, $args );
	}

	/**
	 * Render key event template data for JSON response.
	 *
	 * @param array<string, mixed> $entry        The entry data.
	 * @param Entry                $entry_object The domain Entry object.
	 * @param int                  $post_id      The post ID.
	 * @return array<string, mixed> Modified entry data with key event fields.
	 */
	public function enrich_entry_for_json( array $entry, Entry $entry_object, int $post_id ): array {
		$content = $entry_object->content()->raw();

		// Detect key event from content.
		$is_key_event       = $this->content_has_key_command( $content );
		$entry['key_event'] = $is_key_event;

		if ( $is_key_event ) {
			/**
			 * Filter the key event content format.
			 *
			 * @param string $content The entry content.
			 * @param int    $post_id The post ID.
			 */
			$entry['key_event_content'] = apply_filters( 'liveblog_key_event_content', $content, $post_id );
		}

		return $entry;
	}
}
