<?php
/**
 * WP-CLI command to archive a liveblog.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;

/**
 * Archives a liveblog, making it read-only.
 */
final class ArchiveCommand {

	/**
	 * Archive a liveblog.
	 *
	 * Archives the liveblog, making it read-only. Entries are still displayed
	 * but new entries cannot be added.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the liveblog to archive.
	 *
	 * ## EXAMPLES
	 *
	 *     # Archive liveblog 123
	 *     wp liveblog archive 123
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$post_id = absint( $args[0] ?? 0 );

		if ( 0 === $post_id ) {
			WP_CLI::error( 'Please provide a valid post ID.' );
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( null === $liveblog_post ) {
			WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
		}

		if ( ! $liveblog_post->is_liveblog() ) {
			WP_CLI::error( sprintf( 'Post %d is not a liveblog. Enable it first with: wp liveblog enable %d', $post_id, $post_id ) );
		}

		if ( $liveblog_post->is_archived() ) {
			WP_CLI::warning( sprintf( 'Liveblog %d is already archived.', $post_id ) );
			return;
		}

		$liveblog_post->archive();

		WP_CLI::success( sprintf( 'Liveblog %d archived.', $post_id ) );
	}
}
