<?php

/**
 * Class WPCOM_Liveblog_Entry_Embed
 *
 * Store oembeds in comment meta instead of post meta.
 */
class WPCOM_Liveblog_Entry_Embed extends WP_Embed {

	public function __construct() {
		return; //nothing happens during __construct for now
	}

	/**
	 * If a post/page was saved, then output JavaScript to make
	 * an AJAX request that will call WP_Embed::cache_oembed().
	 *
	 * Override the default method in a way it does nothing as
	 * we don't need to pre-populate the cache as comments are
	 * being created on the fly on frontend
	 */
	public function maybe_run_ajax_cache() {
	}

	/**
	 * The {@link do_shortcode()} callback function.
	 *
	 * Attempts to convert a URL into embed HTML. Starts by checking the URL against the regex of the registered embed handlers.
	 * If none of the regex matches and it's enabled, then the URL will be given to the {@link WP_oEmbed} class.
	 *
	 * @param array $attr {
	 *     Shortcode attributes. Optional.
	 *
	 *     @type int $width  Width of the embed in pixels.
	 *     @type int $height Height of the embed in pixels.
	 * }
	 * @param string $url The URL attempting to be embedded.
	 * @param int $comment_id The Comment ID of currently processed comment
	 * @return string|false The embed HTML on success, otherwise the original URL.
	 *                      `->maybe_make_link()` can return false on failure.
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

		// kses converts & into &amp; and we need to undo this
		// See https://core.trac.wordpress.org/ticket/11311
		$url = str_replace( '&amp;', '&', $url );

		// Look for known internal handlers
		$handlers = $this->handlers;
		//check for handlers registered for wp_embed class using all the helper functions
		if ( true === isset( $GLOBALS['wp_embed'] )
			&& is_a( $GLOBALS['wp_embed'], 'WP_Embed' )
			&& is_array( $GLOBALS['wp_embed']->handlers )
		) {
			//marge those in a single array
			$handlers = array_replace_recursive( $GLOBALS['wp_embed']->handlers, $this->handlers );
		}
		ksort( $handlers );
		foreach ( $handlers as $handlers ) {
			foreach ( $handlers as $handler ) {
				if ( preg_match( $handler['regex'], $url, $matches ) && is_callable( $handler['callback'] ) ) {
					$return = call_user_func( $handler['callback'], $matches, $attr, $url, $rawattr );
					if ( false !== $return ) {
						/**
						 * Filter the returned embed handler.
						 *
						 * @see WP_Embed::shortcode()
						 *
						 * @param mixed  $return The shortcode callback function to call.
						 * @param string $url    The attempted embed URL.
						 * @param array  $attr   An array of shortcode attributes.
						 */
						return apply_filters( 'embed_handler_html', $return, $url, $attr );
					}
				}
			}
		}

		$comment_id = ( ! empty( $comment->comment_ID ) ) ? $comment->comment_ID : null;
		if ( ! empty( $this->comment_ID ) ) { // Potentially set by WPCOM_Comments_Embed::autoembed()
			$comment_id = $this->comment_ID;
		}

		// Unknown URL format. Let oEmbed have a go.
		if ( $comment_id ) {

			// Check for a cached result (stored in the comment meta)
			$key_suffix    = md5( $url . wp_json_encode( $attr ) );
			$cachekey      = '_oembed_' . $key_suffix;
			$cachekey_time = '_oembed_time_' . $key_suffix;

			/**
			 * Filter the oEmbed TTL value (time to live).
			 *
			 * @param int    $time       Time to live (in seconds).
			 * @param string $url        The attempted embed URL.
			 * @param array  $attr       An array of shortcode attributes.
			 * @param int    $comment_id Comment ID.
			 */
			$ttl = apply_filters( 'oembed_ttl', DAY_IN_SECONDS, $url, $attr, $comment_id );

			$cache      = get_comment_meta( $comment_id, $cachekey, true );
			$cache_time = get_comment_meta( $comment_id, $cachekey_time, true );

			/**
			 * Check post meta in case there is no existing comment meta
			 * Odds are that related post meta exists and we should use
			 * that one in order to not make existing Liveblogs to explode
			 * before we fully transition to comment meta caching
			 */
			if ( true === empty( $cache_time ) && true === empty( $cache ) ) {
				$comment = get_comment( $comment_id );
				if ( true === is_a( $comment, 'WP_Comment' ) ) {
					$post_id    = $comment->comment_post_ID;
					$cache      = get_post_meta( $post_id, $cachekey, true );
					$cache_time = get_post_meta( $post_id, $cachekey_time, true );
				}
			}

			if ( ! $cache_time ) {
				$cache_time = 0;
			}

			$cached_recently = ( time() - $cache_time ) < $ttl;

			if ( $this->usecache || $cached_recently ) {
				// Failures are cached. Serve one if we're using the cache.
				if ( '{{unknown}}' === $cache ) {
					return $this->maybe_make_link( $url );
				}

				if ( ! empty( $cache ) ) {
					/**
					 * Filter the cached oEmbed HTML.
					 *
					 * @see WP_Embed::shortcode()
					 *
					 * @param mixed  $cache      The cached HTML result, stored in comment meta.
					 * @param string $url        The attempted embed URL.
					 * @param array  $attr       An array of shortcode attributes.
					 * @param int    $comment_id Comment ID.
					 */
					return apply_filters( 'embed_oembed_html', $cache, $url, $attr, $comment_id );
				}
			}

			/**
			 * Filter whether to inspect the given URL for discoverable link tags.
			 *
			 * @see WP_oEmbed::discover()
			 *
			 * @param bool $enable Whether to enable `<link>` tag discovery. Default true.
			 */
			$attr['discover'] = ( apply_filters( 'embed_oembed_discover', true ) );

			// Use oEmbed to get the HTML
			$html = wp_oembed_get( $url, $attr );

			// Maybe cache the result
			if ( $html ) {
				update_comment_meta( $comment_id, $cachekey, $html );
				update_comment_meta( $comment_id, $cachekey_time, time() );
			} elseif ( ! $cache ) {
				update_comment_meta( $comment_id, $cachekey, '{{unknown}}' );
			}

			// If there was a result, return it
			if ( $html ) {
				/**
				 * Filter the cached oEmbed HTML.
				 *
				 * @see WP_Embed::shortcode()
				 *
				 * @param mixed  $cache      The cached HTML result, stored in post meta.
				 * @param string $url        The attempted embed URL.
				 * @param array  $attr       An array of shortcode attributes.
				 * @param int    $comment_id Comment ID.
				 */
				return apply_filters( 'embed_oembed_html', $html, $url, $attr, $comment_id );
			}
		}

		// Still unknown
		return $this->maybe_make_link( $url );
	}

	/**
	 * Delete all Comment oEmbed caches.
	 *
	 * @param int $comment_id Comment ID to delete the caches for.
	 */
	public function delete_oembed_caches( $comment_id = 0 ) {
		if ( ! $comment_id ) {
			$comment_id = get_comment_ID();
		}
		$comment_metas = get_comment_meta( $comment_id );
		if ( empty( $comment_metas ) ) {
			return;
		}
		if ( ! is_array( $comment_metas ) ) {
			return;
		}
		$comment_meta_keys = array_keys( $comment_metas );
		if ( ! $comment_meta_keys ) {
			return;
		}

		foreach ( $comment_meta_keys as $comment_meta_key ) {
			if ( '_oembed_' === substr( $comment_meta_key, 0, 8 ) ) {
				delete_comment_meta( $comment_id, $comment_meta_key );
			}
		}
	}

	/**
	 * Triggers a caching of all comment's oEmbed results.
	 *
	 * The function is not implemented and overrides the
	 * parent class' one. We don't need to pre-populate cache
	 * for comments are they are being created on the fly.
	 *
	 * @param int $comment_id Comment ID to do the caching for.
	 */
	public function cache_oembed( $comment_id ) {
	}

	/**
	 * Callback function for WP_Embed::autoembed().
	 *
	 * @param array $match A regex match array.
	 * @return string The embed HTML on success, otherwise the original URL.
	 */
	public function autoembed_callback( $match ) {
		$oldval              = $this->linkifunknown;
		$this->linkifunknown = false;
		$return              = $this->shortcode( array(), $match[2] );
		$this->linkifunknown = $oldval;

		return $match[1] . $return . $match[3];
	}

	/**
	 * Passes any unlinked URLs that are on their own line to {@link WP_Embed::shortcode()} for potential embedding.
	 *
	 * @uses WP_Embed::autoembed_callback()
	 *
	 * @param string $content The content to be searched.
	 * @param int|WP_Comment $comment object or integer representing the Comment ID
	 * @return string Potentially modified $content.
	 */
	public function autoembed( $content, $comment = null ) {
		// Save comment's ID for later use - needed for Liveblog plugin compatibility as it calls the autoembed method directly
		if ( ! empty( $comment ) ) {
			$comment = get_comment( $comment );
			if ( is_a( $comment, 'WP_Comment' ) ) {
				$this->comment_ID = $comment->comment_ID;
			}
		}

		// Replace line breaks from all HTML elements with placeholders.
		$content = wp_replace_in_html_tags( $content, array( "\n" => '<!-- wp-line-break -->' ) );

		// Find URLs that are on their own line.
		if ( preg_match( '#(^|\s|>)https?://#i', $content ) ) {
			// Find URLs on their own line.
			$content = preg_replace_callback( '|^(\s*)(https?://[^\s<>"]+)(\s*)$|im', array( $this, 'autoembed_callback' ), $content );
			// Find URLs in their own paragraph.
			$content = preg_replace_callback( '|(<p(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/p>)|i', array( $this, 'autoembed_callback' ), $content );
		}

		// Put the line breaks back.
		return str_replace( '<!-- wp-line-break -->', "\n", $content );
	}

}
