<?php
/**
 * WP-CLI command to unarchive a liveblog.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;

/**
 * Unarchives a liveblog, making it editable again.
 */
final class UnarchiveCommand {

	/**
	 * Unarchive a liveblog.
	 *
	 * Unarchives the liveblog, re-enabling it for new entries.
	 * This is equivalent to enabling an archived liveblog.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the liveblog to unarchive.
	 *
	 * ## EXAMPLES
	 *
	 *     # Unarchive liveblog 123
	 *     wp liveblog unarchive 123
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

		if ( ! $liveblog_post->is_archived() ) {
			if ( $liveblog_post->is_enabled() ) {
				WP_CLI::warning( sprintf( 'Liveblog %d is already enabled.', $post_id ) );
			} else {
				WP_CLI::error( sprintf( 'Post %d is not an archived liveblog.', $post_id ) );
			}
			return;
		}

		$liveblog_post->enable();

		WP_CLI::success( sprintf( 'Liveblog %d unarchived and re-enabled.', $post_id ) );
	}
}
