<?php
/**
 * Interface for archive repair service.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

/**
 * Interface for repairing liveblog archive data.
 */
interface ArchiveRepairServiceInterface {

	/**
	 * Find all liveblog posts.
	 *
	 * @return \WP_Post[] Array of liveblog posts.
	 */
	public function find_liveblog_posts(): array;

	/**
	 * Repair a single liveblog post's archive data.
	 *
	 * @param int  $post_id The post ID to repair.
	 * @param bool $dry_run If true, don't make actual changes.
	 * @return array{entries_corrected: int, content_replaced: int} Repair statistics.
	 */
	public function repair_post( int $post_id, bool $dry_run = false ): array;
}
