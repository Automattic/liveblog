<?php
/**
 * Entry repository interface.
 *
 * @package Automattic\Liveblog\Domain\Repository
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\Repository;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use WP_Post;

/**
 * Defines the contract for liveblog entry persistence.
 *
 * This interface abstracts the storage mechanism for liveblog entries,
 * allowing different implementations (comments, custom post types, etc.)
 * while maintaining a consistent API.
 *
 * @todo Replace WP_Post references with domain DTOs to remove the
 *       WordPress dependency from the Domain layer. find_by_id() 
 *       and find_by_post_id() return WordPress infrastructure types — 
 *       extract these into a separate infrastructure-facing interface 
 *       or replace with array DTOs.
 */
interface EntryRepositoryInterface {

	/**
	 * Get an Entry entity by its ID.
	 *
	 * This is the preferred method for retrieving entries as it returns
	 * a fully hydrated domain entity.
	 *
	 * @param EntryId $id Entry ID.
	 * @return Entry|null The entry or null if not found.
	 */
	public function get_entry( EntryId $id ): ?Entry;

	/**
	 * Get Entry entities by post ID.
	 *
	 * This is the preferred method for retrieving multiple entries as it
	 * returns fully hydrated domain entities.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional query arguments.
	 * @return Entry[] Array of entries.
	 */
	public function get_entries( int $post_id, array $args = array() ): array;

	/**
	 * Find raw entry data by entry ID.
	 *
	 * Lower-level method that returns the underlying WordPress object.
	 * Prefer get_entry() for most use cases.
	 *
	 * @param EntryId $id Entry ID.
	 * @return WP_Post|null The entry data or null if not found.
	 */
	public function find_by_id( EntryId $id ): ?WP_Post;

	/**
	 * Find raw entry data by post ID.
	 *
	 * Lower-level method that returns underlying WordPress objects.
	 * Prefer get_entries() for most use cases.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional query arguments.
	 * @return array Array of WP_Post objects.
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
