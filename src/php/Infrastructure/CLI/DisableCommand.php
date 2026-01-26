<?php
/**
 * WP-CLI command to disable liveblog on a post.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;

/**
 * Disables liveblog functionality on a post.
 */
final class DisableCommand {

	/**
	 * Disable liveblog on a post.
	 *
	 * Completely disables liveblog functionality. Unlike archiving,
	 * this removes the liveblog display from the post entirely.
	 * Existing entries are preserved and can be restored by re-enabling.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to disable liveblog on.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Disable liveblog on post 123
	 *     wp liveblog disable 123
	 *
	 *     # Disable without confirmation
	 *     wp liveblog disable 123 --yes
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
			WP_CLI::warning( sprintf( 'Post %d is not a liveblog.', $post_id ) );
			return;
		}

		// Require confirmation unless --yes flag is set.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm(
				sprintf(
					'Are you sure you want to disable liveblog on post %d? The liveblog will no longer display on the post.',
					$post_id
				)
			);
		}

		$liveblog_post->disable();

		WP_CLI::success( sprintf( 'Liveblog disabled on post %d. Entries are preserved and can be restored by re-enabling.', $post_id ) );
	}
}
