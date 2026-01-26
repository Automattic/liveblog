<?php
/**
 * WP-CLI command to enable liveblog on a post.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_CLI;

/**
 * Enables liveblog functionality on a post.
 */
final class EnableCommand {

	/**
	 * Enable liveblog on a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to enable liveblog on.
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable liveblog on post 123
	 *     wp liveblog enable 123
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

		if ( $liveblog_post->is_enabled() ) {
			WP_CLI::warning( sprintf( 'Post %d is already an enabled liveblog.', $post_id ) );
			return;
		}

		$liveblog_post->enable();

		WP_CLI::success( sprintf( 'Liveblog enabled on post %d.', $post_id ) );
	}
}
