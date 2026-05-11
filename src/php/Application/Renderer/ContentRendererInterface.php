<?php
/**
 * Content renderer interface.
 *
 * @package Automattic\Liveblog\Application\Renderer
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Renderer;

use WP_Post;

/**
 * Interface for rendering entry content.
 *
 * Abstracts the content rendering process to allow for testability
 * and different rendering strategies.
 */
interface ContentRendererInterface {

	/**
	 * Render content to HTML.
	 *
	 * @param string       $content The raw content to render.
	 * @param WP_Post|null $post  Optional post for context.
	 * @return string The rendered HTML.
	 */
	public function render( string $content, ?WP_Post $post = null ): string;
}
