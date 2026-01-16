<?php
/**
 * Entry repository interface.
 *
 * @package Automattic\Liveblog\Domain\Repository
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\Repository;

use Automattic\Liveblog\Domain\ValueObject\EntryId;
use WP_Comment;

/**
 * Defines the contract for liveblog entry persistence.
 *
 * This interface abstracts the storage mechanism for liveblog entries,
 * allowing different implementations (comments, custom post types, etc.)
 * while maintaining a consistent API.
 */
interface EntryRepositoryInterface {

	/**
	 * Find an entry by its ID.
	 *
	 * @param EntryId $id Entry ID.
	 * @return WP_Comment|null The entry data or null if not found.
	 */
	public function find_by_id( EntryId $id ): ?WP_Comment;

	/**
	 * Find entries by post ID.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional query arguments.
	 * @return WP_Comment[] Array of entries.
	 */
	public function find_by_post_id( int $post_id, array $args = array() ): array;

	/**
	 * Insert a new entry.
	 *
	 * @param array $data Entry data including post_id, content, author info.
	 * @return EntryId The new entry's ID.
	 * @throws \RuntimeException If insertion fails.
	 */
	public function insert( array $data ): EntryId;

	/**
	 * Update an existing entry.
	 *
	 * @param EntryId $id   Entry ID.
	 * @param array   $data Data to update.
	 * @return bool True on success.
	 * @throws \RuntimeException If update fails.
	 */
	public function update( EntryId $id, array $data ): bool;

	/**
	 * Delete an entry.
	 *
	 * @param EntryId $id    Entry ID.
	 * @param bool    $force Whether to force permanent deletion.
	 * @return bool True on success.
	 */
	public function delete( EntryId $id, bool $force = false ): bool;

	/**
	 * Get the ID of the entry that this entry replaces.
	 *
	 * @param EntryId $id Entry ID.
	 * @return EntryId|null The replaced entry ID or null.
	 */
	public function get_replaces_id( EntryId $id ): ?EntryId;

	/**
	 * Set the entry that this entry replaces.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param EntryId $replaces ID of the entry being replaced.
	 * @return bool True on success.
	 */
	public function set_replaces_id( EntryId $id, EntryId $replaces ): bool;

	/**
	 * Find entries that reference a given entry as their replacement target.
	 *
	 * Used to find orphaned update/delete entries when cleaning up.
	 *
	 * @param int     $post_id  Post ID.
	 * @param EntryId $entry_id Entry ID being replaced.
	 * @param EntryId $exclude  Entry ID to exclude from results.
	 * @return WP_Comment[] Array of referencing entries.
	 */
	public function find_referencing_entries( int $post_id, EntryId $entry_id, EntryId $exclude ): array;

	/**
	 * Get contributor user IDs for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return int[] Array of user IDs.
	 */
	public function get_contributors( EntryId $id ): array;

	/**
	 * Set contributors for an entry.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param int[]   $user_ids Array of contributor user IDs.
	 * @return bool True on success.
	 */
	public function set_contributors( EntryId $id, array $user_ids ): bool;

	/**
	 * Check if authors are hidden for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return bool True if authors are hidden.
	 */
	public function is_authors_hidden( EntryId $id ): bool;

	/**
	 * Set whether authors are hidden for an entry.
	 *
	 * @param EntryId $id     Entry ID.
	 * @param bool    $hidden Whether to hide authors.
	 * @return bool True on success.
	 */
	public function set_authors_hidden( EntryId $id, bool $hidden ): bool;

	/**
	 * Invalidate any caches for entries belonging to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_cache( int $post_id ): void;
}
