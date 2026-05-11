<?php
/**
 * Post-based entry repository implementation.
 *
 * @package Automattic\Liveblog\Infrastructure\Repository
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\Repository;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\Author;
use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Automattic\Liveblog\Domain\ValueObject\EntryContent;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use DateTimeImmutable;
use DateTimeZone;
use WP_Post;

/**
 * Post-based implementation of entry repository.
 *
 * Stores entries as hierarchical WordPress posts instead of comments.
 */
final class PostEntryRepository implements EntryRepositoryInterface {

	/**
	 * Get a single entry by ID.
	 *
	 * @param EntryId $id Entry ID.
	 * @return Entry|null Entry entity or null if not found.
	 */
	public function get_entry( EntryId $id ): ?Entry {
		$post = get_post( $id->to_int() );

		if ( ! $post || 'post' !== $post->post_type ) {
			return null;
		}

		return $this->hydrate_entry( $post );
	}

	/**
	 * Get all entries for a post.
	 *
	 * @param int   $post_id Parent post ID.
	 * @param array $args    Query arguments.
	 * @return Entry[] Array of entry entities.
	 */
	public function get_entries( int $post_id, array $args = array() ): array {
		$defaults = array(
			'post_parent' => $post_id,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'numberposts' => $args['limit'] ?? -1,
		);

		$query_args = array_merge( $defaults, $args );
		$posts      = get_posts( $query_args );

		return array_map(
			function ( WP_Post $post ): Entry {
				return $this->hydrate_entry( $post );
			},
			$posts
		);
	}

	/**
	 * Find a post by ID (raw WordPress post object).
	 *
	 * @param EntryId $id Entry ID.
	 * @return WP_Post|null Post object or null.
	 */
	public function find_by_id( EntryId $id ): ?WP_Post {
		$post = get_post( $id->to_int() );
		return $post && 'post' === $post->post_type ? $post : null;
	}

	/**
	 * Find posts by parent ID (raw WordPress post objects).
	 *
	 * @param int   $post_id Parent post ID.
	 * @param array $args    Query arguments.
	 * @return WP_Post[] Array of post objects.
	 */
	public function find_by_post_id( int $post_id, array $args = array() ): array {
		$defaults = array(
			'post_parent' => $post_id,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		$query_args = array_merge( $defaults, $args );
		return get_posts( $query_args );
	}

	/**
	 * Insert a new entry.
	 *
	 * @param array $data Entry data.
	 * @return EntryId Created entry ID.
	 * @throws \RuntimeException If insertion fails.
	 */
	public function insert( array $data ): EntryId {
		$post_data = array(
			'post_type'    => 'post',
			'post_parent'  => $data['post_id'],
			'post_content' => $data['content'],
			'post_author'  => $data['author_id'] ?? $data['user_id'] ?? 0,
			'post_status'  => $data['status'] ?? 'publish',
		);

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = $data['title'];
		}

		$entry_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $entry_id ) ) {
			throw new \RuntimeException( esc_html( $entry_id->get_error_message() ) );
		}

		// Set metadata.
		if ( isset( $data['hide_authors'] ) ) {
			update_post_meta( $entry_id, 'liveblog_hide_authors', (bool) $data['hide_authors'] );
		} else {
			update_post_meta( $entry_id, 'liveblog_hide_authors', false );
		}

		if ( isset( $data['contributors'] ) && is_array( $data['contributors'] ) ) {
			update_post_meta( $entry_id, 'liveblog_contributors', array_map( 'absint', $data['contributors'] ) );
		}

		if ( isset( $data['replaces'] ) ) {
			update_post_meta( $entry_id, 'liveblog_replaces', (int) $data['replaces'] );
		}

		wp_cache_delete( "liveblog_entries_asc_{$data['post_id']}", 'liveblog' );
		wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );

		return EntryId::from_int( $entry_id );
	}

	/**
	 * Update an existing entry.
	 *
	 * @param EntryId $id   Entry ID.
	 * @param array   $data Entry data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( EntryId $id, array $data ): bool {
		$post_data = array(
			'ID' => $id->to_int(),
		);

		if ( isset( $data['content'] ) ) {
			$post_data['post_content'] = $data['content'];
		}

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = $data['title'];
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Update metadata.
		if ( isset( $data['hide_authors'] ) ) {
			update_post_meta( $id->to_int(), 'liveblog_hide_authors', (bool) $data['hide_authors'] );
		}

		if ( isset( $data['contributors'] ) ) {
			update_post_meta( $id->to_int(), 'liveblog_contributors', array_map( 'absint', $data['contributors'] ) );
		}

		$post = get_post( $id->to_int() );
		if ( $post ) {
			wp_cache_delete( "liveblog_entries_asc_{$post->post_parent}", 'liveblog' );
		}

		return true;
	}

	/**
	 * Delete an entry.
	 *
	 * @param EntryId $id    Entry ID.
	 * @param bool    $force Whether to force delete (skip trash).
	 * @return bool True on success, false on failure.
	 */
	public function delete( EntryId $id, bool $force = false ): bool {
		$post = get_post( $id->to_int() );

		if ( ! $post ) {
			return false;
		}

		if ( $force ) {
			$result = wp_delete_post( $id->to_int(), true );
		} else {
			$result = wp_trash_post( $id->to_int() );
		}

		if ( $result ) {
			wp_cache_delete( "liveblog_entries_asc_{$post->post_parent}", 'liveblog' );
			wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );
		}

		return (bool) $result;
	}

	/**
	 * Get the ID of the entry being replaced.
	 *
	 * @param EntryId $id Entry ID.
	 * @return EntryId|null Replaced entry ID or null.
	 */
	public function get_replaces_id( EntryId $id ): ?EntryId {
		$replaces = (int) get_post_meta( $id->to_int(), 'liveblog_replaces', true );

		return $replaces > 0 ? EntryId::from_int( $replaces ) : null;
	}

	/**
	 * Set the ID of the entry being replaced.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param EntryId $replaces Replaced entry ID.
	 * @return bool True on success.
	 */
	public function set_replaces_id( EntryId $id, EntryId $replaces ): bool {
		return (bool) update_post_meta( $id->to_int(), 'liveblog_replaces', $replaces->to_int() );
	}

	/**
	 * Get contributor user IDs for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return int[] Array of user IDs.
	 */
	public function get_contributors( EntryId $id ): array {
		$contributors = get_post_meta( $id->to_int(), 'liveblog_contributors', true );

		return is_array( $contributors ) ? $contributors : array();
	}

	/**
	 * Set contributor user IDs for an entry.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param array   $user_ids Array of user IDs.
	 * @return bool True on success.
	 */
	public function set_contributors( EntryId $id, array $user_ids ): bool {
		return (bool) update_post_meta( $id->to_int(), 'liveblog_contributors', array_map( 'absint', $user_ids ) );
	}

	/**
	 * Check if authors are hidden for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return bool True if hidden.
	 */
	public function is_authors_hidden( EntryId $id ): bool {
		return (bool) get_post_meta( $id->to_int(), 'liveblog_hide_authors', true );
	}

	/**
	 * Set whether authors are hidden for an entry.
	 *
	 * @param EntryId $id     Entry ID.
	 * @param bool    $hidden Whether to hide authors.
	 * @return bool True on success.
	 */
	public function set_authors_hidden( EntryId $id, bool $hidden ): bool {
		return (bool) update_post_meta( $id->to_int(), 'liveblog_hide_authors', $hidden );
	}

	/**
	 * Invalidate cache for a post's entries.
	 *
	 * @param int $post_id Parent post ID.
	 */
	public function invalidate_cache( int $post_id ): void {
		wp_cache_delete( "liveblog_entries_asc_{$post_id}", 'liveblog' );
	}

	/**
	 * Hydrate a WP_Post object into an Entry entity.
	 *
	 * @param WP_Post $post Post object.
	 * @return Entry Entry entity.
	 */
	private function hydrate_entry( WP_Post $post ): Entry {
		$entry_id    = EntryId::from_int( $post->ID );
		$replaces_id = $this->get_replaces_id( $entry_id );

		// Build author from post author.
		$author  = Author::from_user_id( (int) $post->post_author );
		$authors = AuthorCollection::from_authors( $author );

		// Add contributors.
		$contributors = $this->get_contributors( $entry_id );
		foreach ( $contributors as $user_id ) {
			$contributors_author = Author::from_user_id( (int) $user_id );
			$authors             = $authors->with( $contributors_author );
		}

		// Hide authors if meta set.
		if ( $this->is_authors_hidden( $entry_id ) ) {
			$authors = AuthorCollection::empty();
		}

		$content = EntryContent::from_raw( $post->post_content );

		$created_at = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$post->post_date_gmt,
			new DateTimeZone( 'UTC' )
		);

		if ( false === $created_at ) {
			$created_at = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		}

		return Entry::create(
			$entry_id,
			$post->post_parent,
			$content,
			$authors,
			$replaces_id,
			$created_at
		);
	}
}
