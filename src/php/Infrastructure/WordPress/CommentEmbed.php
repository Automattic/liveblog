<?php
/**
 * Embed handling for liveblog entries (comments).
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Renderer\EmbedHandlerInterface;
use WP_Comment;
use WP_Embed;

/**
 * Handles oEmbed caching for liveblog entries.
 *
 * Liveblog entries are stored as comments, so this class overrides WP_Embed
 * to store oEmbed caches in comment meta instead of post meta.
 */
class CommentEmbed extends WP_Embed implements EmbedHandlerInterface {

	/**
	 * Comment ID for the current embed context.
	 *
	 * @var int|null
	 */
	public ?int $comment_ID = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Override parent - no initialization needed.
	}

	/**
	 * Override parent to prevent AJAX cache population.
	 *
	 * Comments are created on the fly, so pre-populating cache isn't needed.
	 *
	 * @return void
	 */
	public function maybe_run_ajax_cache() {
		// Intentionally empty - no AJAX caching for comments.
	}

	/**
	 * Process embed shortcode.
	 *
	 * Attempts to convert a URL into embed HTML, caching results in comment meta.
	 *
	 * @param array    $attr    Shortcode attributes (width, height).
	 * @param string   $url     The URL to embed.
	 * @param int|null $comment The comment ID.
	 * @return string|false The embed HTML or original URL on failure.
	 */
	public function shortcode( $attr, $url = '', $comment = null ) {
		if ( ! empty( $comment ) ) {
			$comment = get_comment( $comment );
		} else {
			$comment = get_comment();
		}

		if ( empty( $url ) && ! empty( $attr['src'] ) ) {
			$url = $attr['src'];
		}

		$this->last_url = $url;

		if ( empty( $url ) ) {
			$this->last_attr = $attr;
			return '';
		}

		$rawattr = $attr;
		$attr    = wp_parse_args( $attr, wp_embed_defaults( $url ) );

		$this->last_attr = $attr;

		// Decode &amp; to & (kses encodes it).
		$url = str_replace( '&amp;', '&', $url );

		// Check internal handlers first.
		$handlers = $this->handlers;

		// Merge in global wp_embed handlers.
		if ( isset( $GLOBALS['wp_embed'] )
			&& $GLOBALS['wp_embed'] instanceof WP_Embed
			&& is_array( $GLOBALS['wp_embed']->handlers )
		) {
			$handlers = array_replace_recursive( $GLOBALS['wp_embed']->handlers, $this->handlers );
		}

		ksort( $handlers );

		foreach ( $handlers as $priority_handlers ) {
			foreach ( $priority_handlers as $handler ) {
				if ( preg_match( $handler['regex'], $url, $matches ) && is_callable( $handler['callback'] ) ) {
					$return = call_user_func( $handler['callback'], $matches, $attr, $url, $rawattr );
					if ( false !== $return ) {
						/** This filter is documented in wp-includes/class-wp-embed.php */
						return apply_filters( 'embed_handler_html', $return, $url, $attr ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
					}
				}
			}
		}

		// Get comment ID from various sources.
		$comment_id = null;
		if ( ! empty( $comment->comment_ID ) ) {
			$comment_id = (int) $comment->comment_ID;
		}
		if ( ! empty( $this->comment_ID ) ) {
			$comment_id = $this->comment_ID;
		}

		// Use oEmbed with comment meta caching.
		if ( $comment_id ) {
			$html = $this->get_oembed_with_comment_cache( $url, $attr, $comment_id );
			if ( $html ) {
				return $html;
			}
		}

		return $this->maybe_make_link( $url );
	}

	/**
	 * Get oEmbed HTML with comment meta caching.
	 *
	 * @param string $url        The URL to embed.
	 * @param array  $attr       Embed attributes.
	 * @param int    $comment_id Comment ID for caching.
	 * @return string|null The cached/fetched HTML or null.
	 */
	private function get_oembed_with_comment_cache( string $url, array $attr, int $comment_id ): ?string {
		$key_suffix    = md5( $url . wp_json_encode( $attr ) );
		$cachekey      = '_oembed_' . $key_suffix;
		$cachekey_time = '_oembed_time_' . $key_suffix;

		/** This filter is documented in wp-includes/class-wp-embed.php */
		$ttl = apply_filters( 'oembed_ttl', DAY_IN_SECONDS, $url, $attr, $comment_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.

		// Check comment meta cache first.
		$cache      = get_comment_meta( $comment_id, $cachekey, true );
		$cache_time = get_comment_meta( $comment_id, $cachekey_time, true );

		// Fall back to post meta for backwards compatibility.
		if ( empty( $cache_time ) && empty( $cache ) ) {
			$comment = get_comment( $comment_id );
			if ( $comment instanceof WP_Comment ) {
				$cache      = get_post_meta( $comment->comment_post_ID, $cachekey, true );
				$cache_time = get_post_meta( $comment->comment_post_ID, $cachekey_time, true );
			}
		}

		$cache_time      = $cache_time ? (int) $cache_time : 0;
		$cached_recently = ( time() - $cache_time ) < $ttl;

		if ( $this->usecache || $cached_recently ) {
			if ( '{{unknown}}' === $cache ) {
				return $this->maybe_make_link( $url );
			}

			if ( ! empty( $cache ) ) {
				/** This filter is documented in wp-includes/class-wp-embed.php */
				return apply_filters( 'embed_oembed_html', $cache, $url, $attr, $comment_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
			}
		}

		/** This filter is documented in wp-includes/class-wp-embed.php */
		$attr['discover'] = apply_filters( 'embed_oembed_discover', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.

		// Fetch the oEmbed HTML.
		$html = wp_oembed_get( $url, $attr );

		// Cache the result.
		if ( $html ) {
			update_comment_meta( $comment_id, $cachekey, $html );
			update_comment_meta( $comment_id, $cachekey_time, time() );

			/** This filter is documented in wp-includes/class-wp-embed.php */
			return apply_filters( 'embed_oembed_html', $html, $url, $attr, $comment_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.
		}

		if ( ! $cache ) {
			update_comment_meta( $comment_id, $cachekey, '{{unknown}}' );
		}

		return null;
	}

	/**
	 * Delete all oEmbed caches for a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function delete_oembed_caches( $comment_id ) {
		$comment_id = (int) $comment_id;

		if ( ! $comment_id ) {
			$comment_id = get_comment_ID();
		}

		$comment_metas = get_comment_meta( $comment_id );

		if ( empty( $comment_metas ) || ! is_array( $comment_metas ) ) {
			return;
		}

		foreach ( array_keys( $comment_metas ) as $meta_key ) {
			if ( str_starts_with( $meta_key, '_oembed_' ) ) {
				delete_comment_meta( $comment_id, $meta_key );
			}
		}
	}

	/**
	 * Override parent - no pre-population needed for comments.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function cache_oembed( $comment_id ) {
		// Intentionally empty - comments don't need pre-populated cache.
	}

	/**
	 * Callback for autoembed regex.
	 *
	 * @param string[] $match Regex match array.
	 * @return string The embed HTML or original URL.
	 */
	public function autoembed_callback( $match ) {
		$oldval              = $this->linkifunknown;
		$this->linkifunknown = false;
		$return              = $this->shortcode( array(), $match[2] );
		$this->linkifunknown = $oldval;

		return $match[1] . $return . $match[3];
	}

	/**
	 * Convert standalone URLs to embeds.
	 *
	 * @param string              $content The content to process.
	 * @param int|WP_Comment|null $comment Comment for context.
	 * @return string The processed content.
	 */
	public function autoembed( $content, $comment = null ) {
		// Store comment ID for use in shortcode().
		if ( ! empty( $comment ) ) {
			$comment_obj = get_comment( $comment );
			if ( $comment_obj instanceof WP_Comment ) {
				$this->comment_ID = (int) $comment_obj->comment_ID;
			}
		}

		// Protect line breaks in HTML tags.
		$content = wp_replace_in_html_tags( $content, array( "\n" => '<!-- wp-line-break -->' ) );

		if ( preg_match( '#(^|\s|>)https?://#i', $content ) ) {
			// URLs on their own line.
			$content = preg_replace_callback(
				'|^(\s*)(https?://[^\s<>"]+)(\s*)$|im',
				array( $this, 'autoembed_callback' ),
				$content
			);
			// URLs in their own paragraph.
			$content = preg_replace_callback(
				'|(<p(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/p>)|i',
				array( $this, 'autoembed_callback' ),
				$content
			);
		}

		// Restore line breaks.
		return str_replace( '<!-- wp-line-break -->', "\n", $content );
	}
}
