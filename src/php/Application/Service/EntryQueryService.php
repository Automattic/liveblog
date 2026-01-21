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
}
