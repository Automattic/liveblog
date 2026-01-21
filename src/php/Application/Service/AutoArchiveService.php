<?php
/**
 * Auto-archive service for liveblogs.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

/**
 * Handles automatic archiving of liveblogs based on expiry dates.
 */
class AutoArchiveService {

	/**
	 * Meta key for storing the auto-archive expiry date.
	 *
	 * @var string
	 */
	public const EXPIRY_META_KEY = 'liveblog_autoarchive_expiry_date';

	/**
	 * Liveblog post meta key.
	 *
	 * @var string
	 */
	private const LIVEBLOG_META_KEY = 'liveblog';

	/**
	 * Number of days after which to auto-archive.
	 *
	 * @var int|null
	 */
	private ?int $auto_archive_days;

	/**
	 * Constructor.
	 *
	 * @param int|null $auto_archive_days Number of days after which to auto-archive, or null to disable.
	 */
	public function __construct( ?int $auto_archive_days = null ) {
		$this->auto_archive_days = $auto_archive_days;
	}

	/**
	 * Check if auto-archiving is enabled.
	 *
	 * @return bool True if auto-archiving is enabled.
	 */
	public function is_enabled(): bool {
		return null !== $this->auto_archive_days;
	}

	/**
	 * Get the number of days after which to auto-archive.
	 *
	 * @return int|null Number of days or null if disabled.
	 */
	public function get_auto_archive_days(): ?int {
		return $this->auto_archive_days;
	}

	/**
	 * Execute the auto-archive housekeeping task.
	 *
	 * Checks all liveblogs for expiry and archives any that have passed their expiry date.
	 *
	 * @return int Number of liveblogs archived.
	 */
	public function execute_housekeeping(): int {
		if ( ! $this->is_enabled() ) {
			return 0;
		}

		$posts = $this->get_liveblog_posts();
		$today = $this->get_current_timestamp();

		$archived_count = 0;

		foreach ( $posts as $post ) {
			if ( $this->should_archive_post( $post->ID, $today ) ) {
				$this->archive_liveblog( $post->ID );
				++$archived_count;
			}
		}

		return $archived_count;
	}

	/**
	 * Check if a liveblog post should be archived.
	 *
	 * @param int $post_id         The post ID.
	 * @param int $current_timestamp Current timestamp.
	 * @return bool True if the post should be archived.
	 */
	public function should_archive_post( int $post_id, int $current_timestamp ): bool {
		$expiry = get_post_meta( $post_id, self::EXPIRY_META_KEY, true );

		if ( ! $expiry ) {
			return false;
		}

		return (int) $expiry < $current_timestamp;
	}

	/**
	 * Get all liveblog posts.
	 *
	 * @return \WP_Post[] Array of liveblog posts.
	 */
	private function get_liveblog_posts(): array {
		$query = new \WP_Query(
			array(
				'order'    => 'ASC',
				'orderby'  => 'ID',
				'meta_key' => self::LIVEBLOG_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		return $query->posts;
	}

	/**
	 * Get the current timestamp.
	 *
	 * @return int Current timestamp.
	 */
	private function get_current_timestamp(): int {
		return strtotime( gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Archive a liveblog.
	 *
	 * @param int $post_id The post ID to archive.
	 * @return void
	 */
	private function archive_liveblog( int $post_id ): void {
		\WPCOM_Liveblog::set_liveblog_state( $post_id, 'archive' );
	}
}
