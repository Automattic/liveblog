<?php
/**
 * Entry service for orchestrating entry operations.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use InvalidArgumentException;
use RuntimeException;
use WP_User;

/**
 * Application service for liveblog entry operations.
 *
 * This service orchestrates entry creation, updates, and deletion,
 * using the repository for persistence. It encapsulates the business
 * logic for entry operations while remaining agnostic to the storage
 * mechanism.
 */
final class EntryService {

	/**
	 * Entry repository.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * Key event service.
	 *
	 * @var KeyEventService|null
	 */
	private ?KeyEventService $key_event_service = null;

	/**
	 * Constructor.
	 *
	 * @param EntryRepositoryInterface $repository        Entry repository.
	 * @param KeyEventService|null     $key_event_service Optional key event service.
	 */
	public function __construct( EntryRepositoryInterface $repository, ?KeyEventService $key_event_service = null ) {
		$this->repository        = $repository;
		$this->key_event_service = $key_event_service;
	}

	/**
	 * Get the key event service.
	 *
	 * Uses lazy loading to avoid circular dependencies.
	 *
	 * @return KeyEventService
	 */
	private function get_key_event_service(): KeyEventService {
		if ( null === $this->key_event_service ) {
			$this->key_event_service = new KeyEventService(
				$this->repository,
				new EntryQueryService( $this->repository )
			);
		}

		return $this->key_event_service;
	}

	/**
	 * Get an entry by ID.
	 *
	 * @param EntryId $entry_id Entry ID.
	 * @return Entry|null The entry, or null if not found.
	 */
	public function get( EntryId $entry_id ): ?Entry {
		return $this->repository->get_entry( $entry_id );
	}

	/**
	 * Get entries for a liveblog post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional query arguments.
	 * @return Entry[] Array of entries.
	 */
	public function get_for_post( int $post_id, array $args = array() ): array {
		return $this->repository->get_entries( $post_id, $args );
	}

	/**
	 * Create a new liveblog entry.
	 *
	 * @param int        $post_id   Post ID for the liveblog.
	 * @param string     $content   Entry content.
	 * @param WP_User    $author    Author user object.
	 * @param bool       $hide_author Whether to hide the author.
	 * @param array|null $contributor_ids Optional contributor user IDs.
	 * @return EntryId The new entry's ID.
	 * @throws InvalidArgumentException If required parameters are invalid.
	 * @throws RuntimeException If entry creation fails.
	 */
	public function create(
		int $post_id,
		string $content,
		WP_User $author,
		bool $hide_author = false,
		?array $contributor_ids = null
	): EntryId {
		$this->validate_post_id( $post_id );

		$entry_id = $this->repository->insert(
			array(
				'post_id'      => $post_id,
				'content'      => $content,
				'user_id'      => $author->ID,
				'author_name'  => $author->display_name,
				'author_email' => $author->user_email,
				'author_url'   => $author->user_url,
			)
		);

		if ( $hide_author ) {
			$this->repository->set_authors_hidden( $entry_id, true );
		}

		if ( ! empty( $contributor_ids ) ) {
			$this->repository->set_contributors( $entry_id, $contributor_ids );
		}

		return $entry_id;
	}

	/**
	 * Update an existing liveblog entry.
	 *
	 * Creates a new entry that replaces the original. The original entry
	 * is also updated to store the new content (for audit purposes).
	 *
	 * @param int     $post_id     Post ID for the liveblog.
	 * @param EntryId $entry_id    ID of the entry to update.
	 * @param string  $content     New content.
	 * @param WP_User $author      Author of the update (typically original author).
	 * @return EntryId The new replacement entry's ID.
	 * @throws InvalidArgumentException If entry not found or post ID is invalid.
	 */
	public function update(
		int $post_id,
		EntryId $entry_id,
		string $content,
		WP_User $author
	): EntryId {
		$this->validate_post_id( $post_id );

		// Verify the original entry exists.
		$original = $this->repository->find_by_id( $entry_id );
		if ( ! $original ) {
			throw new InvalidArgumentException( 'Entry not found' );
		}

		// Create the replacement entry.
		$new_entry_id = $this->repository->insert(
			array(
				'post_id'      => $post_id,
				'content'      => $content,
				'user_id'      => $author->ID,
				'author_name'  => $author->display_name,
				'author_email' => $author->user_email,
				'author_url'   => $author->user_url,
			)
		);

		// Link the new entry to the original.
		$this->repository->set_replaces_id( $new_entry_id, $entry_id );

		// Update the original entry's content for audit trail.
		$this->repository->update( $entry_id, array( 'content' => $content ) );

		return $new_entry_id;
	}

	/**
	 * Delete a liveblog entry.
	 *
	 * Creates a delete marker entry that replaces the original.
	 * Also cleans up any orphaned update entries.
	 *
	 * @param int     $post_id  Post ID for the liveblog.
	 * @param EntryId $entry_id ID of the entry to delete.
	 * @param WP_User $author   Author performing the deletion.
	 * @return EntryId The delete marker entry's ID.
	 * @throws InvalidArgumentException If entry not found or post ID is invalid.
	 */
	public function delete(
		int $post_id,
		EntryId $entry_id,
		WP_User $author
	): EntryId {
		$this->validate_post_id( $post_id );

		// Verify the original entry exists.
		$original = $this->repository->find_by_id( $entry_id );
		if ( ! $original ) {
			throw new InvalidArgumentException( 'Entry not found' );
		}

		// Create the delete marker (empty content).
		$delete_marker_id = $this->repository->insert(
			array(
				'post_id'      => $post_id,
				'content'      => '',
				'user_id'      => $author->ID,
				'author_name'  => $author->display_name,
				'author_email' => $author->user_email,
				'author_url'   => $author->user_url,
			)
		);

		// Link the delete marker to the original.
		$this->repository->set_replaces_id( $delete_marker_id, $entry_id );

		// Clean up orphaned update entries.
		$this->cleanup_orphaned_entries( $post_id, $entry_id, $delete_marker_id );

		// Delete the original entry.
		$this->repository->delete( $entry_id, false );

		return $delete_marker_id;
	}

	/**
	 * Update the author of an entry.
	 *
	 * @param EntryId      $entry_id  Entry ID.
	 * @param WP_User|null $author    New author, or null to hide authors.
	 * @return bool True on success.
	 * @throws InvalidArgumentException If entry not found.
	 */
	public function update_author( EntryId $entry_id, ?WP_User $author ): bool {
		$entry = $this->repository->find_by_id( $entry_id );
		if ( ! $entry ) {
			throw new InvalidArgumentException( 'Entry not found' );
		}

		if ( null === $author ) {
			// Hide authors.
			return $this->repository->set_authors_hidden( $entry_id, true );
		}

		// Update author info.
		$this->repository->update(
			$entry_id,
			array(
				'user_id'      => $author->ID,
				'author_name'  => $author->display_name,
				'author_email' => $author->user_email,
				'author_url'   => $author->user_url,
			)
		);

		// Show authors.
		return $this->repository->set_authors_hidden( $entry_id, false );
	}

	/**
	 * Add contributors to an entry.
	 *
	 * @param EntryId $entry_id       Entry ID.
	 * @param int[]   $contributor_ids User IDs of contributors.
	 * @return bool True on success.
	 * @throws InvalidArgumentException If entry not found.
	 */
	public function set_contributors( EntryId $entry_id, array $contributor_ids ): bool {
		$entry = $this->repository->find_by_id( $entry_id );
		if ( ! $entry ) {
			throw new InvalidArgumentException( 'Entry not found' );
		}

		return $this->repository->set_contributors( $entry_id, $contributor_ids );
	}

	/**
	 * Get contributors for an entry.
	 *
	 * @param EntryId $entry_id Entry ID.
	 * @return int[] Array of contributor user IDs.
	 */
	public function get_contributors( EntryId $entry_id ): array {
		return $this->repository->get_contributors( $entry_id );
	}

	/**
	 * Check if authors are hidden for an entry.
	 *
	 * @param EntryId $entry_id Entry ID.
	 * @return bool True if authors are hidden.
	 */
	public function is_authors_hidden( EntryId $entry_id ): bool {
		return $this->repository->is_authors_hidden( $entry_id );
	}

	/**
	 * Delete a key event from an entry.
	 *
	 * Removes the key event meta and strips the /key command from content,
	 * then updates the entry.
	 *
	 * @param int     $post_id  Post ID for the liveblog.
	 * @param EntryId $entry_id ID of the entry.
	 * @param string  $content  Current content (with /key to be stripped).
	 * @param WP_User $author   Author of the update.
	 * @return EntryId The updated entry's ID.
	 * @throws InvalidArgumentException If entry not found or post ID is invalid.
	 */
	public function delete_key(
		int $post_id,
		EntryId $entry_id,
		string $content,
		WP_User $author
	): EntryId {
		// Remove the key event meta and strip /key from content.
		$updated_content = $this->get_key_event_service()->remove_key_action( $content, $entry_id->to_int() );

		// Update the entry with the new content.
		return $this->update( $post_id, $entry_id, $updated_content, $author );
	}

	/**
	 * Clean up orphaned update entries.
	 *
	 * When an entry is deleted, any previous update entries that reference
	 * it should also be removed.
	 *
	 * @param int     $post_id          Post ID.
	 * @param EntryId $entry_id         Original entry ID.
	 * @param EntryId $exclude_entry_id Entry ID to exclude (the delete marker).
	 * @return void
	 */
	private function cleanup_orphaned_entries( int $post_id, EntryId $entry_id, EntryId $exclude_entry_id ): void {
		$orphans = $this->repository->find_referencing_entries( $post_id, $entry_id, $exclude_entry_id );

		foreach ( $orphans as $orphan ) {
			$this->repository->delete( EntryId::from_int( (int) $orphan->comment_ID ), true );
		}
	}

	/**
	 * Validate post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 * @throws InvalidArgumentException If post ID is invalid.
	 */
	private function validate_post_id( int $post_id ): void {
		if ( $post_id <= 0 ) {
			throw new InvalidArgumentException( 'Invalid post ID' );
		}
	}
}
