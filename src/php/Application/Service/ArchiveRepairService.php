<?php
/**
 * Service for repairing liveblog archive data.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use WP_Query;

/**
 * Repairs data inconsistencies in archived liveblog posts.
 *
 * This service fixes issues that can occur when liveblog entries are edited
 * while archived. It corrects liveblog_replaces meta values and restores
 * proper comment content.
 */
final class ArchiveRepairService implements ArchiveRepairServiceInterface {

	/**
	 * Result of a repair operation.
	 *
	 * @var array{
	 *     posts_processed: int,
	 *     entries_corrected: int,
	 *     content_replaced: int
	 * }
	 */
	private array $results;

	/**
	 * Find all liveblog posts.
	 *
	 * @return \WP_Post[] Array of liveblog posts.
	 */
	public function find_liveblog_posts(): array {
		$query = new WP_Query(
			array(
				'order'          => 'ASC',
				'orderby'        => 'ID',
				'posts_per_page' => -1,
				'meta_key'       => 'liveblog', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for finding liveblogs.
			)
		);

		return $query->posts;
	}

	/**
	 * Repair a single liveblog post's archive data.
	 *
	 * @param int  $post_id The post ID to repair.
	 * @param bool $dry_run If true, don't make actual changes.
	 * @return array{entries_corrected: int, content_replaced: int} Repair statistics.
	 */
	public function repair_post( int $post_id, bool $dry_run = false ): array {
		global $wpdb;

		$stats = array(
			'entries_corrected' => 0,
			'content_replaced'  => 0,
		);

		// Get all entries that have been edited in the liveblog.
		$edit_comments = get_comments(
			array(
				'post_id'  => $post_id,
				'orderby'  => 'comment_date_gmt',
				'order'    => 'ASC',
				'meta_key' => 'liveblog_replaces', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for finding edited entries.
				'status'   => 'liveblog',
			)
		);

		$edit_entries = array_map(
			function ( $comment ) {
				return (object) array(
					'id'       => (int) $comment->comment_ID,
					'replaces' => (int) get_comment_meta( $comment->comment_ID, 'liveblog_replaces', true ),
				);
			},
			$edit_comments
		);

		// Find correct comment_ids to replace incorrect meta_values.
		$correct_ids_array = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
			$wpdb->prepare(
				"SELECT comment_id FROM $wpdb->comments
				WHERE comment_post_id = %d AND comment_id NOT IN
				( SELECT $wpdb->commentmeta.comment_id FROM $wpdb->commentmeta
				INNER JOIN $wpdb->comments
				ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
				WHERE comment_post_id = %d )
				ORDER BY comment_id ASC",
				$post_id,
				$post_id
			)
		);
		$correct_ids       = wp_list_pluck( $correct_ids_array, 'comment_id' );

		// Repair meta values.
		$stats['entries_corrected'] = $this->repair_meta_values(
			$edit_entries,
			$correct_ids,
			$dry_run
		);

		// Repair content.
		$stats['content_replaced'] = $this->repair_content( $post_id, $dry_run );

		return $stats;
	}

	/**
	 * Repair incorrect meta_value with correct ones.
	 *
	 * @param object[] $edit_entries Entries with replacement info.
	 * @param int[]    $correct_ids  Correct IDs to use.
	 * @param bool     $dry_run      If true, don't make actual changes.
	 * @return int Number of entries corrected.
	 */
	private function repair_meta_values( array $edit_entries, array $correct_ids, bool $dry_run ): int {
		global $wpdb;

		$corrected = 0;

		if ( count( $edit_entries ) === 0 ) {
			return $corrected;
		}

		foreach ( $edit_entries as $edit_entry ) {
			$entry_id = $edit_entry->id;

			if ( in_array( $edit_entry->replaces, $correct_ids, true ) ) {
				continue;
			}

			$correct_id_count = count( $correct_ids );
			for ( $i = 0; $i <= $correct_id_count - 1; $i++ ) {
				if ( $correct_ids[ $i ] < $entry_id ) {
					if ( ! $dry_run ) {
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
							$wpdb->commentmeta,
							array(
								'meta_value' => $correct_ids[ $i ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for WP-CLI repair command.
							),
							array( 'comment_id' => $entry_id )
						);
						update_meta_cache( 'comment', array( $entry_id ) );
					}
					++$corrected;
				}
			}
		}

		return $corrected;
	}

	/**
	 * Repair comment content inconsistencies.
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $dry_run If true, don't make actual changes.
	 * @return int Number of content items replaced.
	 */
	private function repair_content( int $post_id, bool $dry_run ): int {
		global $wpdb;

		// Find comment_ids with correct content for replacement.
		$correct_contents = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
			$wpdb->prepare(
				"SELECT comment_id, comment_content
				FROM $wpdb->comments
				WHERE comment_post_id = %d
				GROUP BY comment_content
				HAVING count(comment_content) = 2
				ORDER BY comment_id ASC",
				$post_id
			)
		);

		// Find comment_ids that NEED to be replaced.
		$entries_replace = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
			$wpdb->prepare(
				"SELECT DISTINCT meta_value
				FROM $wpdb->commentmeta
				INNER JOIN $wpdb->comments
				ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
				WHERE comment_post_id = %d
				ORDER BY meta_value ASC",
				$post_id
			)
		);

		// Check to make sure entry content being replaced matches available.
		if ( count( $entries_replace ) !== count( $correct_contents ) ) {
			return 0;
		}

		$replaced = 0;

		foreach ( $entries_replace as $entry_replace ) {
			$content = $correct_contents[ $replaced ]->comment_content;

			if ( ! $dry_run ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
					$wpdb->comments,
					array( 'comment_content' => $content ),
					array( 'comment_id' => $entry_replace->meta_value )
				);
				clean_comment_cache( $entry_replace->meta_value );
			}

			++$replaced;
		}

		return $replaced;
	}
}
