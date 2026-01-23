<?php
/**
 * Interface for embed handlers.
 *
 * @package Automattic\Liveblog\Application\Renderer
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Renderer;

use WP_Comment;

/**
 * Defines the contract for processing embeds in content.
 *
 * Implementations handle converting URLs to embedded content,
 * with caching appropriate to the content storage mechanism.
 */
interface EmbedHandlerInterface {

	/**
	 * Convert standalone URLs in content to embeds.
	 *
	 * @param string              $content The content to process.
	 * @param int|WP_Comment|null $comment Optional comment for cache context.
	 * @return string The processed content with URLs converted to embeds.
	 */
	public function autoembed( $content, $comment = null );
}
