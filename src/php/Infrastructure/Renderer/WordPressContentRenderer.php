<?php
/**
 * WordPress content renderer implementation.
 *
 * @package Automattic\Liveblog\Infrastructure\Renderer
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\Renderer;

use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use WP_Comment;
use WPCOM_Liveblog_Entry;

/**
 * Renders entry content using WordPress and the legacy liveblog rendering pipeline.
 *
 * This implementation wraps the existing WPCOM_Liveblog_Entry::render_content()
 * method, providing a clean interface whilst maintaining backwards compatibility.
 */
final class WordPressContentRenderer implements ContentRendererInterface {

	/**
	 * Render content to HTML.
	 *
	 * Uses the existing WordPress-based rendering pipeline which handles
	 * embeds, shortcodes, and content filters.
	 *
	 * @param string          $content The raw content to render.
	 * @param WP_Comment|null $comment Optional comment for embed context.
	 * @return string The rendered HTML.
	 */
	public function render( string $content, ?WP_Comment $comment = null ): string {
		return WPCOM_Liveblog_Entry::render_content( $content, $comment ?: false );
	}
}
