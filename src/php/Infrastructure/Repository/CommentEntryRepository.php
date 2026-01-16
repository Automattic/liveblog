<?php
/**
 * Comment-based entry repository implementation.
 *
 * @package Automattic\Liveblog\Infrastructure\Repository
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\Repository;

use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use RuntimeException;
use WP_Comment;

/**
 * Repository implementation using WordPress comments as storage.
 *
 * This is the default implementation for liveblog entries, storing them
 * as comments with type 'liveblog' and approval status 'liveblog'.
 */
final class CommentEntryRepository implements EntryRepositoryInterface {

	/**
	 * Meta key for tracking which entry this entry replaces.
	 *
	 * @var string
	 */
	public const REPLACES_META_KEY = 'liveblog_replaces';

	/**
	 * Meta key for tracking contributors.
	 *
	 * @var string
	 */
	public const CONTRIBUTORS_META_KEY = 'liveblog_contributors';

	/**
	 * Meta key for hiding authors.
	 *
	 * @var string
	 */
	public const HIDE_AUTHORS_KEY = 'liveblog_hide_authors';

	/**
	 * Comment type for liveblog entries.
	 *
	 * @var string
	 */
	public const COMMENT_TYPE = 'liveblog';

	/**
	 * Cache group for liveblog entries.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'liveblog';

	/**
	 * Find an entry by its ID.
	 *
	 * @param EntryId $id Entry ID.
	 * @return WP_Comment|null The entry data or null if not found.
	 */
	public function find_by_id( EntryId $id ): ?WP_Comment {
		$comment = get_comment( $id->to_int() );

		if ( ! $comment instanceof WP_Comment ) {
			return null;
		}

		return $comment;
	}

	/**
	 * Find entries by post ID.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Optional query arguments.
	 * @return WP_Comment[] Array of entries.
	 */
	public function find_by_post_id( int $post_id, array $args = array() ): array {
		$defaults = array(
			'post_id' => $post_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => self::COMMENT_TYPE,
		);

		$query_args = array_merge( $defaults, $args );

		return get_comments( $query_args );
	}

	/**
	 * Insert a new entry.
	 *
	 * @param array $data Entry data including post_id, content, user_id, author info.
	 * @return EntryId The new entry's ID.
	 * @throws RuntimeException If insertion fails.
	 */
	public function insert( array $data ): EntryId {
		$this->validate_insert_data( $data );

		$comment_data = array(
			'comment_post_ID'      => $data['post_id'],
			'comment_content'      => $this->sanitize_content( $data['content'] ?? '' ),
			'comment_approved'     => self::COMMENT_TYPE,
			'comment_type'         => self::COMMENT_TYPE,
			'user_id'              => $data['user_id'],
			'comment_author'       => $data['author_name'] ?? '',
			'comment_author_email' => $data['author_email'] ?? '',
			'comment_author_url'   => $data['author_url'] ?? '',
		);

		$comment_id = wp_insert_comment( $comment_data );

		if ( empty( $comment_id ) || is_wp_error( $comment_id ) ) {
			throw new RuntimeException( 'Failed to insert liveblog entry' );
		}

		$this->invalidate_cache( $data['post_id'] );

		return EntryId::from_int( $comment_id );
	}

	/**
	 * Update an existing entry.
	 *
	 * @param EntryId $id   Entry ID.
	 * @param array   $data Data to update.
	 * @return bool True on success.
	 * @throws RuntimeException If update fails.
	 */
	public function update( EntryId $id, array $data ): bool {
		$comment = $this->find_by_id( $id );

		if ( ! $comment ) {
			throw new RuntimeException( 'Entry not found' );
		}

		$update_data = array( 'comment_ID' => $id->to_int() );

		if ( isset( $data['content'] ) ) {
			$update_data['comment_content'] = $this->sanitize_content( $data['content'] );
		}

		if ( isset( $data['user_id'] ) ) {
			$update_data['user_id'] = $data['user_id'];
		}

		if ( isset( $data['author_name'] ) ) {
			$update_data['comment_author'] = $data['author_name'];
		}

		if ( isset( $data['author_email'] ) ) {
			$update_data['comment_author_email'] = $data['author_email'];
		}

		if ( isset( $data['author_url'] ) ) {
			$update_data['comment_author_url'] = $data['author_url'];
		}

		$result = wp_update_comment( $update_data );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'Failed to update liveblog entry' );
		}

		$this->invalidate_cache( (int) $comment->comment_post_ID );

		return true;
	}

	/**
	 * Delete an entry.
	 *
	 * @param EntryId $id    Entry ID.
	 * @param bool    $force Whether to force permanent deletion.
	 * @return bool True on success.
	 */
	public function delete( EntryId $id, bool $force = false ): bool {
		$comment = $this->find_by_id( $id );

		if ( ! $comment ) {
			return false;
		}

		$post_id = (int) $comment->comment_post_ID;
		$result  = wp_delete_comment( $id->to_int(), $force );

		if ( $result ) {
			$this->invalidate_cache( $post_id );
		}

		return (bool) $result;
	}

	/**
	 * Get the ID of the entry that this entry replaces.
	 *
	 * @param EntryId $id Entry ID.
	 * @return EntryId|null The replaced entry ID or null.
	 */
	public function get_replaces_id( EntryId $id ): ?EntryId {
		$replaces = get_comment_meta( $id->to_int(), self::REPLACES_META_KEY, true );

		if ( empty( $replaces ) ) {
			return null;
		}

		return EntryId::from_int( (int) $replaces );
	}

	/**
	 * Set the entry that this entry replaces.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param EntryId $replaces ID of the entry being replaced.
	 * @return bool True on success.
	 */
	public function set_replaces_id( EntryId $id, EntryId $replaces ): bool {
		$result = add_comment_meta( $id->to_int(), self::REPLACES_META_KEY, $replaces->to_int() );

		return false !== $result;
	}

	/**
	 * Find entries that reference a given entry as their replacement target.
	 *
	 * @param int     $post_id  Post ID.
	 * @param EntryId $entry_id Entry ID being replaced.
	 * @param EntryId $exclude  Entry ID to exclude from results.
	 * @return WP_Comment[] Array of referencing entries.
	 */
	public function find_referencing_entries( int $post_id, EntryId $entry_id, EntryId $exclude ): array {
		return get_comments(
			array(
				'post_id'         => $post_id,
				'type'            => self::COMMENT_TYPE,
				'status'          => self::COMMENT_TYPE,
				'meta_key'        => self::REPLACES_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'      => $entry_id->to_int(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'comment__not_in' => array( $exclude->to_int() ),
			)
		);
	}

	/**
	 * Get contributor user IDs for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return int[] Array of user IDs.
	 */
	public function get_contributors( EntryId $id ): array {
		$contributors = get_comment_meta( $id->to_int(), self::CONTRIBUTORS_META_KEY, true );

		if ( ! is_array( $contributors ) ) {
			return array();
		}

		return array_map( 'intval', $contributors );
	}

	/**
	 * Set contributors for an entry.
	 *
	 * @param EntryId $id       Entry ID.
	 * @param int[]   $user_ids Array of contributor user IDs.
	 * @return bool True on success.
	 */
	public function set_contributors( EntryId $id, array $user_ids ): bool {
		$comment_id = $id->to_int();

		// Remove empty values.
		$user_ids = array_filter( array_map( 'intval', $user_ids ) );

		if ( empty( $user_ids ) ) {
			return delete_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY );
		}

		if ( metadata_exists( 'comment', $comment_id, self::CONTRIBUTORS_META_KEY ) ) {
			$result = update_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, $user_ids );
		} else {
			$result = add_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, $user_ids );
		}

		return false !== $result;
	}

	/**
	 * Check if authors are hidden for an entry.
	 *
	 * @param EntryId $id Entry ID.
	 * @return bool True if authors are hidden.
	 */
	public function is_authors_hidden( EntryId $id ): bool {
		return (bool) get_comment_meta( $id->to_int(), self::HIDE_AUTHORS_KEY, true );
	}

	/**
	 * Set whether authors are hidden for an entry.
	 *
	 * @param EntryId $id     Entry ID.
	 * @param bool    $hidden Whether to hide authors.
	 * @return bool True on success.
	 */
	public function set_authors_hidden( EntryId $id, bool $hidden ): bool {
		$comment_id = $id->to_int();

		if ( $hidden ) {
			$result = update_comment_meta( $comment_id, self::HIDE_AUTHORS_KEY, true );
		} else {
			$result = delete_comment_meta( $comment_id, self::HIDE_AUTHORS_KEY );
		}

		return false !== $result;
	}

	/**
	 * Invalidate any caches for entries belonging to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_cache( int $post_id ): void {
		wp_cache_delete( 'liveblog_entries_asc_' . $post_id, self::CACHE_GROUP );
	}

	/**
	 * Validate data for insert operation.
	 *
	 * @param array $data Data to validate.
	 * @return void
	 * @throws RuntimeException If validation fails.
	 */
	private function validate_insert_data( array $data ): void {
		if ( empty( $data['post_id'] ) ) {
			throw new RuntimeException( 'Missing required field: post_id' );
		}

		if ( empty( $data['user_id'] ) ) {
			throw new RuntimeException( 'Missing required field: user_id' );
		}
	}

	/**
	 * Sanitize content for storage.
	 *
	 * @param string $content Raw content.
	 * @return string Sanitized content.
	 */
	private function sanitize_content( string $content ): string {
		return wp_filter_post_kses( $content );
	}
}
