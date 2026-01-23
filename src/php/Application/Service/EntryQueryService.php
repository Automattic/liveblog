<?php
/**
 * Entry query service for retrieving and filtering liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;

/**
 * Service for querying and filtering liveblog entries.
 *
 * This service provides higher-level query operations that work with
 * domain Entry objects, handling common operations like filtering out
 * replaced entries and flattening the entry chain.
 */
final class EntryQueryService {

	/**
	 * The entry repository.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param EntryRepositoryInterface $repository The entry repository.
	 */
	public function __construct( EntryRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Get all entries for a post, with replaced entries filtered out.
	 *
	 * @param int   $post_id Post ID.
	 * @param int   $limit   Maximum number of entries to return. 0 for unlimited.
	 * @param array $args    Additional query arguments.
	 * @return Entry[] Array of entries.
	 */
	public function get_all( int $post_id, int $limit = 0, array $args = array() ): array {
		$defaults = array(
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		$query_args = array_merge( $defaults, $args );
		$entries    = $this->repository->get_entries( $post_id, $query_args );

		return $this->remove_replaced_entries( $entries, $limit );
	}

	/**
	 * Get the latest entry for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return Entry|null The latest entry or null if none found.
	 */
	public function get_latest( int $post_id ): ?Entry {
		$entries = $this->repository->get_entries(
			$post_id,
			array(
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'number'  => 1,
			)
		);

		return $entries[0] ?? null;
	}

	/**
	 * Get the ID of the latest entry for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|null The latest entry ID or null if none found.
	 */
	public function get_latest_id( int $post_id ): ?int {
		$latest = $this->get_latest( $post_id );

		return $latest?->id()->to_int();
	}

	/**
	 * Get the timestamp of the latest entry for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|null The latest timestamp or null if none found.
	 */
	public function get_latest_timestamp( int $post_id ): ?int {
		$latest = $this->get_latest( $post_id );

		return $latest?->timestamp();
	}

	/**
	 * Get all entries in ascending order (with caching).
	 *
	 * This method caches results for the duration of the request to avoid
	 * repeated database queries.
	 *
	 * @param int $post_id Post ID.
	 * @return Entry[] Array of entries in ascending order.
	 */
	public function get_all_entries_asc( int $post_id ): array {
		$cache_key      = 'liveblog_entries_asc_' . $post_id;
		$cached_entries = wp_cache_get( $cache_key, 'liveblog' );

		if ( false !== $cached_entries ) {
			return $cached_entries;
		}

		$entries = $this->get_all( $post_id, 0, array( 'order' => 'ASC' ) );
		wp_cache_set( $cache_key, $entries, 'liveblog' );

		return $entries;
	}

	/**
	 * Get entries between two timestamps.
	 *
	 * @param int $post_id         Post ID.
	 * @param int $start_timestamp The start timestamp (inclusive).
	 * @param int $end_timestamp   The end timestamp (inclusive).
	 * @return Entry[] Filtered entries.
	 */
	public function get_between_timestamps( int $post_id, int $start_timestamp, int $end_timestamp ): array {
		$all_entries = $this->get_all( $post_id, 0, array( 'order' => 'ASC' ) );

		return $this->find_between_timestamps( $all_entries, $start_timestamp, $end_timestamp );
	}

	/**
	 * Filter entries to those between two timestamps.
	 *
	 * @param Entry[] $entries         The entries to filter.
	 * @param int     $start_timestamp The start timestamp (inclusive).
	 * @param int     $end_timestamp   The end timestamp (inclusive).
	 * @return Entry[] Filtered entries.
	 */
	public function find_between_timestamps( array $entries, int $start_timestamp, int $end_timestamp ): array {
		$entries_between = array();

		foreach ( $entries as $entry ) {
			$timestamp = $entry->timestamp();
			if ( $timestamp >= $start_timestamp && $timestamp <= $end_timestamp ) {
				$entries_between[] = $entry;
			}
		}

		return $this->remove_replaced_entries( $entries_between );
	}

	/**
	 * Flatten entries by processing updates and deletes.
	 *
	 * This processes entries in order, applying updates and deletes to build
	 * the final list of visible entries. Different from remove_replaced_entries
	 * which just removes outdated entries.
	 *
	 * @param Entry[] $entries Array of entry objects, typically in ASC order.
	 * @return Entry[] Flattened array of entries, in DESC order.
	 */
	public function flatten_entries( array $entries ): array {
		if ( empty( $entries ) ) {
			return array();
		}

		$flatten = array();
		foreach ( $entries as $entry ) {
			$type = $entry->type()->value;
			$id   = $entry->display_id()->to_int();

			switch ( $type ) {
				case 'new':
				case 'update':
					$flatten[ $id ] = $entry;
					break;
				case 'delete':
					unset( $flatten[ $id ] );
					break;
			}
		}

		return array_reverse( $flatten, true );
	}

	/**
	 * Remove replaced entries from the list.
	 *
	 * When an entry is updated, a new entry is created with a reference to
	 * the original. This method removes the older versions, keeping only
	 * the most recent version of each entry.
	 *
	 * @param Entry[] $entries The entries to filter.
	 * @param int     $limit   Maximum number of entries to return. 0 for unlimited.
	 * @return Entry[] Filtered entries.
	 */
	public function remove_replaced_entries( array $entries, int $limit = 0 ): array {
		if ( empty( $entries ) ) {
			return $entries;
		}

		$entries_by_id = $this->assoc_array_by_id( $entries );

		foreach ( $entries_by_id as $id => $entry ) {
			$replaces = $entry->replaces();
			if ( null !== $replaces && isset( $entries_by_id[ $replaces->to_int() ] ) ) {
				unset( $entries_by_id[ $id ] );
			}
		}

		// If a limit is set and we have more than that amount of entries,
		// return just that slice.
		if ( $limit > 0 && count( $entries_by_id ) > $limit ) {
			$entries_by_id = array_slice( $entries_by_id, 0, $limit, true );
		}

		return $entries_by_id;
	}

	/**
	 * Create an associative array of entries keyed by ID.
	 *
	 * @param Entry[] $entries The entries to convert.
	 * @return array<int, Entry> Associative array of entries.
	 */
	private function assoc_array_by_id( array $entries ): array {
		$result = array();

		foreach ( $entries as $entry ) {
			$result[ $entry->id()->to_int() ] = $entry;
		}

		return $result;
	}

	/**
	 * Get entries for lazy loading with timestamp boundaries.
	 *
	 * @param int      $post_id       Post ID.
	 * @param int|null $max_timestamp Maximum timestamp (entries >= this are excluded).
	 * @param int|null $min_timestamp Minimum timestamp (entries <= this are excluded).
	 * @return Entry[] Filtered entries.
	 */
	public function get_for_lazyloading( int $post_id, ?int $max_timestamp, ?int $min_timestamp ): array {
		$entries = $this->get_all( $post_id );

		if ( empty( $entries ) ) {
			return array();
		}

		if ( null !== $max_timestamp || null !== $min_timestamp ) {
			foreach ( $entries as $key => $entry ) {
				$timestamp = $entry->timestamp();

				if (
					( null !== $max_timestamp && $timestamp >= $max_timestamp )
					|| ( null !== $min_timestamp && $timestamp <= $min_timestamp )
				) {
					unset( $entries[ $key ] );
				}
			}
		}

		return $entries;
	}

	/**
	 * Count all entries for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Number of entries.
	 */
	public function count( int $post_id ): int {
		return count( $this->get_all( $post_id ) );
	}

	/**
	 * Check if a post has any entries.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if entries exist.
	 */
	public function has_any( int $post_id ): bool {
		$entries = $this->repository->get_entries(
			$post_id,
			array( 'number' => 1 )
		);

		return ! empty( $entries );
	}

	/**
	 * Get a single entry by ID with navigation timestamps.
	 *
	 * Returns the entry along with timestamps for previous/next entries
	 * to support navigation in the UI.
	 *
	 * @param int $post_id  Post ID.
	 * @param int $entry_id Entry ID to find.
	 * @return array{entry: Entry|null, previous_timestamp: int, next_timestamp: int}
	 */
	public function get_single_entry( int $post_id, int $entry_id ): array {
		$result = array(
			'entry'              => null,
			'previous_timestamp' => 0,
			'next_timestamp'     => 0,
		);

		$all_entries = array_values( $this->get_all( $post_id ) );

		foreach ( $all_entries as $key => $entry ) {
			if ( $entry_id !== $entry->id()->to_int() ) {
				continue;
			}

			$result['entry'] = $entry;

			// In DESC order, previous entry (older) is at key+1, next (newer) is at key-1.
			if ( isset( $all_entries[ $key - 1 ] ) ) {
				$result['next_timestamp'] = $all_entries[ $key - 1 ]->timestamp();
			}

			if ( isset( $all_entries[ $key + 1 ] ) ) {
				$result['previous_timestamp'] = $all_entries[ $key + 1 ]->timestamp();
			}

			break;
		}

		return $result;
	}

	/**
	 * Get paginated entries.
	 *
	 * @param int         $post_id          Post ID.
	 * @param int         $page             Page number (1-indexed).
	 * @param int         $per_page         Entries per page.
	 * @param string|null $last_known_entry Optional ID-timestamp string of last known entry.
	 * @param int|null    $jump_to_id       Optional entry ID to jump to (calculates page).
	 * @return array{entries: Entry[], page: int, pages: int, total: int}
	 */
	public function get_entries_paged(
		int $post_id,
		int $page,
		int $per_page,
		?string $last_known_entry = null,
		?int $jump_to_id = null
	): array {
		$entries = $this->get_all_entries_asc( $post_id );
		$entries = $this->flatten_entries( $entries );

		// If there's a last known entry, offset from that point.
		if ( null !== $last_known_entry ) {
			$parts = explode( '-', $last_known_entry );
			if ( isset( $parts[0], $parts[1] ) ) {
				$last_entry_id = (int) $parts[0];
				$keys          = array_keys( $entries );
				$index         = array_search( $last_entry_id, $keys, true );
				if ( false !== $index ) {
					$entries = array_slice( $entries, $index, null, true );
				}
			}
		}

		$total = count( $entries );
		$pages = (int) ceil( $total / $per_page );

		// If no page given but jump_to_id is set, calculate the page.
		if ( 0 === $page && null !== $jump_to_id ) {
			$keys  = array_keys( $entries );
			$index = array_search( $jump_to_id, $keys, true );
			if ( false !== $index ) {
				++$index; // 1-indexed.
				$page = (int) ceil( $index / $per_page );
			}
		}

		// Ensure page is at least 1.
		$page = max( 1, $page );

		$offset  = $per_page * ( $page - 1 );
		$entries = array_slice( $entries, $offset, $per_page );

		return array(
			'entries' => $entries,
			'page'    => $page,
			'pages'   => $pages,
			'total'   => $total,
		);
	}

	/**
	 * Get an entry by ID directly from the repository.
	 *
	 * @param int $entry_id Entry ID.
	 * @return Entry|null The entry or null if not found.
	 */
	public function get_by_id( int $entry_id ): ?Entry {
		$entry_id_vo = \Automattic\Liveblog\Domain\ValueObject\EntryId::from_int( $entry_id );
		return $this->repository->get_entry( $entry_id_vo );
	}
}
