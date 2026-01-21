<?php
/**
 * WordPress content renderer implementation.
 *
 * @package Automattic\Liveblog\Infrastructure\Renderer
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\Renderer;

use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Application\Service\ContentProcessor;
use WP_Comment;

/**
 * Renders entry content using WordPress and the liveblog content pipeline.
 *
 * This implementation delegates to the ContentProcessor service, which handles
 * embeds, shortcodes, image filtering, and WordPress content filters.
 */
final class WordPressContentRenderer implements ContentRendererInterface {

	/**
	 * The content processor service.
	 *
	 * @var ContentProcessor
	 */
	private ContentProcessor $content_processor;

	/**
	 * Constructor.
	 *
	 * @param ContentProcessor|null $content_processor Optional content processor instance.
	 */
	public function __construct( ?ContentProcessor $content_processor = null ) {
		$this->content_processor = $content_processor ?? new ContentProcessor();
	}

	/**
	 * Render content to HTML.
	 *
	 * Delegates to the ContentProcessor service which handles embeds,
	 * shortcodes, and content filters.
	 *
	 * @param string          $content The raw content to render.
	 * @param WP_Comment|null $comment Optional comment for embed context.
	 * @return string The rendered HTML.
	 */
	public function render( string $content, ?WP_Comment $comment = null ): string {
		return $this->content_processor->render( $content, $comment );
	}
}
