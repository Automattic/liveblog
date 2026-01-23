<?php
/**
 * Entry operations service - facade for CRUD operations.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;
use WP_Error;
use WP_User;

/**
 * Entry operations service - provides CRUD operations for liveblog entries.
 *
 * This facade coordinates between the EntryService (for database operations)
 * and KeyEventService (for key event handling) to provide a unified interface
 * for legacy code that previously used Container::instance() directly.
 */
final class EntryOperations {

	/**
	 * Valid CRUD actions.
	 *
	 * @var string[]
	 */
	private const VALID_ACTIONS = array( 'insert', 'update', 'delete', 'delete_key' );

	/**
	 * Entry service for CRUD operations.
	 *
	 * @var EntryService
	 */
	private EntryService $entry_service;

	/**
	 * Key event service for key event handling.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $key_event_service;

	/**
	 * Entry repository for fetching entries.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * Content processor for preview rendering.
	 *
	 * @var ContentProcessor
	 */
	private ContentProcessor $content_processor;

	/**
	 * Constructor.
	 *
	 * @param EntryService             $entry_service     The entry service.
	 * @param KeyEventService          $key_event_service The key event service.
	 * @param EntryRepositoryInterface $repository        The entry repository.
	 * @param ContentProcessor         $content_processor The content processor.
	 */
	public function __construct(
		EntryService $entry_service,
		KeyEventService $key_event_service,
		EntryRepositoryInterface $repository,
		ContentProcessor $content_processor
	) {
		$this->entry_service     = $entry_service;
		$this->key_event_service = $key_event_service;
		$this->repository        = $repository;
		$this->content_processor = $content_processor;
	}

	/**
	 * Insert a new entry.
	 *
	 * @param int        $post_id         The post ID.
	 * @param string     $content         The entry content.
	 * @param WP_User    $user            The author user object.
	 * @param bool       $hide_author     Whether to hide the author.
	 * @param int[]|null $contributor_ids Optional contributor IDs.
	 * @return EntryId The new entry ID.
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry creation fails.
	 */
	public function insert(
		int $post_id,
		string $content,
		WP_User $user,
		bool $hide_author = false,
		?array $contributor_ids = null
	): EntryId {
		return $this->entry_service->create(
			$post_id,
			$content,
			$user,
			$hide_author,
			$contributor_ids
		);
	}

	/**
	 * Update an existing entry.
	 *
	 * @param int     $post_id  The post ID.
	 * @param EntryId $entry_id The entry ID to update.
	 * @param string  $content  The new content.
	 * @param WP_User $user     The user making the update.
	 * @return EntryId The new entry ID (update creates a new comment).
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry update fails.
	 */
	public function update( int $post_id, EntryId $entry_id, string $content, WP_User $user ): EntryId {
		return $this->entry_service->update( $post_id, $entry_id, $content, $user );
	}

	/**
	 * Delete an entry.
	 *
	 * @param int     $post_id  The post ID.
	 * @param EntryId $entry_id The entry ID to delete.
	 * @param WP_User $user     The user making the deletion.
	 * @return EntryId The delete marker entry ID.
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry deletion fails.
	 */
	public function delete( int $post_id, EntryId $entry_id, WP_User $user ): EntryId {
		return $this->entry_service->delete( $post_id, $entry_id, $user );
	}

	/**
	 * Check if an entry is a key event.
	 *
	 * @param int $entry_id The entry ID.
	 * @return bool True if the entry is a key event.
	 */
	public function is_key_event( int $entry_id ): bool {
		return $this->key_event_service->is_key_event( $entry_id );
	}

	/**
	 * Remove key action from entry content.
	 *
	 * @param string $content  The entry content.
	 * @param int    $entry_id The entry ID.
	 * @return string Modified content with /key removed.
	 */
	public function remove_key_action( string $content, int $entry_id ): string {
		return $this->key_event_service->remove_key_action( $content, $entry_id );
	}

	/**
	 * Get the entry service.
	 *
	 * @return EntryService
	 */
	public function entry_service(): EntryService {
		return $this->entry_service;
	}

	/**
	 * Get the key event service.
	 *
	 * @return KeyEventService
	 */
	public function key_event_service(): KeyEventService {
		return $this->key_event_service;
	}

	/**
	 * Check if a CRUD action is valid.
	 *
	 * @param string $action The action to check.
	 * @return bool True if valid.
	 */
	public function is_valid_action( string $action ): bool {
		return in_array( $action, self::VALID_ACTIONS, true );
	}

	/**
	 * Perform a CRUD operation and return the result for JSON.
	 *
	 * @param string  $action The CRUD action (insert, update, delete, delete_key).
	 * @param array   $args   Arguments including post_id, content, entry_id, author_id, contributor_ids.
	 * @param WP_User $user   The user performing the action.
	 * @return array|WP_Error The result array with entries, or WP_Error on failure.
	 */
	public function do_crud( string $action, array $args, WP_User $user ) {
		try {
			$entry_id = $this->execute_action( $action, $args, $user );

			// Get the entry and present it for JSON.
			$entry     = $this->repository->get_entry( $entry_id );
			$presenter = EntryPresenter::from_entry( $entry, $this->key_event_service );

			return array(
				'entries'          => array( $presenter->for_json() ),
				'latest_timestamp' => null,
			);
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'entry-invalid-args', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}
	}

	/**
	 * Execute a CRUD action.
	 *
	 * @param string  $action The CRUD action.
	 * @param array   $args   Arguments for the action.
	 * @param WP_User $user   The user performing the action.
	 * @return EntryId The resulting entry ID.
	 * @throws \InvalidArgumentException If action is invalid.
	 */
	private function execute_action( string $action, array $args, WP_User $user ): EntryId {
		$post_id = (int) ( $args['post_id'] ?? 0 );

		switch ( $action ) {
			case 'insert':
				return $this->execute_insert( $args, $user, $post_id );

			case 'update':
				return $this->execute_update( $args, $post_id );

			case 'delete':
				return $this->execute_delete( $args, $user, $post_id );

			case 'delete_key':
				return $this->execute_delete_key( $args, $post_id );

			default:
				throw new \InvalidArgumentException( 'Invalid CRUD action: ' . esc_html( $action ) );
		}
	}

	/**
	 * Execute insert action.
	 *
	 * @param array   $args    Arguments.
	 * @param WP_User $user    User performing action.
	 * @param int     $post_id Post ID.
	 * @return EntryId The new entry ID.
	 */
	private function execute_insert( array $args, WP_User $user, int $post_id ): EntryId {
		// Apply filter before insert.
		$args = apply_filters( 'liveblog_before_insert_entry', $args );

		// Set the author if provided, otherwise use current user.
		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$user = apply_filters( 'liveblog_userdata', get_userdata( $args['author_id'] ), $args['author_id'] );
		}

		$hide_author     = ! isset( $args['author_id'] ) || ! $args['author_id'];
		$contributor_ids = ! empty( $args['contributor_ids'] ) ? $args['contributor_ids'] : null;

		$entry_id = $this->entry_service->create(
			$post_id,
			$args['content'] ?? '',
			$user,
			$hide_author,
			$contributor_ids
		);

		do_action( 'liveblog_insert_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Execute update action.
	 *
	 * @param array $args    Arguments.
	 * @param int   $post_id Post ID.
	 * @return EntryId The new entry ID.
	 */
	private function execute_update( array $args, int $post_id ): EntryId {
		// Handle author selection and contributors on the original entry.
		$this->handle_update_metadata( $args );

		// Apply filter before update.
		$args = apply_filters( 'liveblog_before_update_entry', $args );

		// Get original author for the update.
		$original_comment = get_comment( $args['entry_id'] );
		$user             = get_userdata( $original_comment->user_id );

		$entry_id = $this->entry_service->update(
			$post_id,
			EntryId::from_int( (int) $args['entry_id'] ),
			$args['content'] ?? '',
			$user
		);

		do_action( 'liveblog_update_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Execute delete action.
	 *
	 * @param array   $args    Arguments.
	 * @param WP_User $user    User performing action.
	 * @param int     $post_id Post ID.
	 * @return EntryId The delete marker entry ID.
	 */
	private function execute_delete( array $args, WP_User $user, int $post_id ): EntryId {
		$entry_id = $this->entry_service->delete(
			$post_id,
			EntryId::from_int( (int) $args['entry_id'] ),
			$user
		);

		do_action( 'liveblog_delete_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Execute delete_key action.
	 *
	 * @param array $args    Arguments.
	 * @param int   $post_id Post ID.
	 * @return EntryId The entry ID.
	 */
	private function execute_delete_key( array $args, int $post_id ): EntryId {
		// Get the original author.
		$original_comment = get_comment( $args['entry_id'] );
		$user             = get_userdata( $original_comment->user_id );

		return $this->entry_service->delete_key(
			$post_id,
			EntryId::from_int( (int) $args['entry_id'] ),
			$args['content'] ?? '',
			$user
		);
	}

	/**
	 * Handle metadata updates for entry updates (author, contributors).
	 *
	 * @param array $args The update arguments.
	 * @return void
	 */
	private function handle_update_metadata( array $args ): void {
		$entry_id = (int) $args['entry_id'];

		// Update author if provided.
		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$user = apply_filters( 'liveblog_userdata', get_userdata( $args['author_id'] ), $args['author_id'] );
			if ( $user ) {
				wp_update_comment(
					array(
						'comment_ID'           => $entry_id,
						'user_id'              => $user->ID,
						'comment_author'       => $user->display_name,
						'comment_author_email' => $user->user_email,
						'comment_author_url'   => $user->user_url,
					)
				);
				update_comment_meta( $entry_id, CommentEntryRepository::HIDE_AUTHORS_KEY, false );
			}
		} else {
			update_comment_meta( $entry_id, CommentEntryRepository::HIDE_AUTHORS_KEY, true );
		}

		// Update contributors.
		if ( isset( $args['contributor_ids'] ) ) {
			$this->repository->set_contributors(
				EntryId::from_int( $entry_id ),
				! empty( $args['contributor_ids'] ) ? $args['contributor_ids'] : array()
			);
		}
	}

	/**
	 * Format entry content for preview.
	 *
	 * @param string $content The entry content to preview.
	 * @return array{html: string} The preview result.
	 */
	public function format_preview( string $content ): array {
		$content = stripslashes( wp_filter_post_kses( $content ) );
		$content = apply_filters( 'liveblog_before_preview_entry', array( 'content' => $content ) );
		$content = $content['content'];

		$html = $this->content_processor->render( $content );

		do_action( 'liveblog_preview_entry', $html );

		return array( 'html' => $html );
	}
}
