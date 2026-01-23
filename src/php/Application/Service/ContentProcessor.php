<?php
/**
 * Content processor service for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Application\Renderer\EmbedHandlerInterface;
use WP_Comment;

/**
 * Processes and renders liveblog entry content.
 *
 * This service extracts the content rendering logic from the legacy
 * WPCOM_Liveblog_Entry class, handling embeds, shortcodes, image filtering,
 * and WordPress content filters.
 */
final class ContentProcessor {

	/**
	 * Embed handler for processing URLs.
	 *
	 * @var EmbedHandlerInterface
	 */
	private EmbedHandlerInterface $embed_handler;

	/**
	 * Constructor.
	 *
	 * @param EmbedHandlerInterface $embed_handler Embed handler for URL processing.
	 */
	public function __construct( EmbedHandlerInterface $embed_handler ) {
		$this->embed_handler = $embed_handler;
	}

	/**
	 * Render content to HTML.
	 *
	 * Processes the raw content through the WordPress rendering pipeline,
	 * including auto-embeds, shortcodes, and content filters.
	 *
	 * @param string          $content The raw content to render.
	 * @param WP_Comment|null $comment Optional comment for embed context.
	 * @return string The rendered HTML.
	 */
	public function render( string $content, ?WP_Comment $comment = null ): string {
		/**
		 * Filter whether to enable embeds for liveblog entries.
		 *
		 * @param bool $enable_embeds Whether embeds are enabled. Default true.
		 */
		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) ) {
				$content = $this->embed_handler->autoembed( $content, $comment );
			}
			$content = do_shortcode( $content );
		}

		// Filter image attributes based on allowed list.
		$content = $this->filter_image_attributes( $content );

		/**
		 * Filter the comment text.
		 *
		 * @param string          $content The processed content.
		 * @param WP_Comment|null $comment The comment object or null.
		 */
		return apply_filters( 'comment_text', $content, $comment ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
	}

	/**
	 * Filter image attributes based on an allowed list.
	 *
	 * By default, only 'src' and 'alt' attributes are preserved on <img> tags.
	 * Developers can extend this using the 'liveblog_image_allowed_attributes' filter.
	 *
	 * @param string $content The HTML content to filter.
	 * @return string The filtered HTML content.
	 *
	 * @example
	 * // Allow additional attributes:
	 * add_filter( 'liveblog_image_allowed_attributes', function( $attrs ) {
	 *     return array_merge( $attrs, [ 'class', 'width', 'height', 'loading', 'data-*' ] );
	 * } );
	 *
	 * @example
	 * // Allow all attributes:
	 * add_filter( 'liveblog_image_allowed_attributes', fn() => [ '*' ] );
	 */
	public function filter_image_attributes( string $content ): string {
		/**
		 * Filter the allowed attributes for images in liveblog entries.
		 *
		 * @param string[] $allowed_attributes Array of allowed attribute names or patterns.
		 *                                     Supports wildcards like 'data-*'.
		 */
		$allowed_attributes = apply_filters( 'liveblog_image_allowed_attributes', array( 'src', 'alt' ) );

		// If wildcard is present, return content unchanged.
		if ( in_array( '*', $allowed_attributes, true ) ) {
			return $content;
		}

		// Use regex to find and process img tags.
		return preg_replace_callback(
			'/<img\s+([^>]*)>/i',
			function ( array $matches ) use ( $allowed_attributes ): string {
				$attrs_string = $matches[1];

				// Parse attributes from the img tag.
				$parsed_attrs = $this->parse_attributes( $attrs_string );

				// Filter to only allowed attributes.
				$filtered_attrs = array();
				foreach ( $parsed_attrs as $name => $value ) {
					if ( $this->is_attribute_allowed( $name, $allowed_attributes ) ) {
						$filtered_attrs[ $name ] = $value;
					}
				}

				// Rebuild the img tag.
				$new_attrs = array();
				foreach ( $filtered_attrs as $name => $value ) {
					$new_attrs[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
				}

				return '<img ' . implode( ' ', $new_attrs ) . '>';
			},
			$content
		) ?? $content;
	}

	/**
	 * Parse HTML attributes from a string.
	 *
	 * @param string $attrs_string The attributes string.
	 * @return array<string, string> Associative array of attribute name => value.
	 */
	private function parse_attributes( string $attrs_string ): array {
		$parsed_attrs = array();

		if ( preg_match_all( '/(\w[\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $attrs_string, $attr_matches, PREG_SET_ORDER ) ) {
			foreach ( $attr_matches as $attr_match ) {
				$name                  = strtolower( $attr_match[1] );
				$value                 = $attr_match[2] ?? $attr_match[3] ?? $attr_match[4] ?? '';
				$parsed_attrs[ $name ] = $value;
			}
		}

		return $parsed_attrs;
	}

	/**
	 * Check if an attribute name is allowed based on the allowed list.
	 *
	 * Supports exact matches and wildcard patterns like 'data-*'.
	 *
	 * @param string   $name    The attribute name to check.
	 * @param string[] $allowed The list of allowed attribute patterns.
	 * @return bool Whether the attribute is allowed.
	 */
	private function is_attribute_allowed( string $name, array $allowed ): bool {
		foreach ( $allowed as $pattern ) {
			// Exact match.
			if ( $pattern === $name ) {
				return true;
			}
			// Wildcard pattern (e.g., 'data-*').
			if ( str_ends_with( $pattern, '*' ) ) {
				$prefix = substr( $pattern, 0, -1 );
				if ( str_starts_with( $name, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
