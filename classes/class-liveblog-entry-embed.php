<?php

/**
 * Class Liveblog_Entry_Embed
 *
 */
class Liveblog_Entry_Embed extends WP_Embed {

	public function __construct() {
		return; //nothing happens during __construct for now
	}

	/**
	 * If a post/page was saved, then output JavaScript to make
	 * an AJAX request that will call WP_Embed::cache_oembed().
	 *
	 * Override the default method in a way it does nothing as
	 * we don't need to pre-populate the cache as posts are
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
	 * @param int $entry_id The post ID of currently processed post
	 * @return string|false The embed HTML on success, otherwise the original URL.
	 *                      `->maybe_make_link()` can return false on failure.
	 */
	public function shortcode( $attr, $url = '', $entry = null ) {
		if ( ! empty( $entry ) ) {
			$entry = get_post( $entry );
		} else {
			$entry = get_post();
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

		$entry_id = ( ! empty( $entry->ID ) ) ? $entry->ID : null;
		if ( ! empty( $this->ID ) ) { // Potentially set by WPCOM_Comments_Embed::autoembed()
			$entry_id = $this->ID;
		}

		// Unknown URL format. Let oEmbed have a go.
		if ( $entry_id ) {

			// Check for a cached result (stored in the post meta)
			$key_suffix    = md5( $url . wp_json_encode( $attr ) );
			$cachekey      = '_oembed_' . $key_suffix;
			$cachekey_time = '_oembed_time_' . $key_suffix;

			/**
			 * Filter the oEmbed TTL value (time to live).
			 *
			 * @param int    $time       Time to live (in seconds).
			 * @param string $url        The attempted embed URL.
			 * @param array  $attr       An array of shortcode attributes.
			 * @param int    $entry_id   Post ID.
			 */
			$ttl = apply_filters( 'oembed_ttl', DAY_IN_SECONDS, $url, $attr, $entry_id );

			$cache      = get_post_meta( $entry_id, $cachekey, true );
			$cache_time = get_post_meta( $entry_id, $cachekey_time, true );

			/**
			 * Check post meta in case there is no existing post meta
			 * Odds are that related post meta exists and we should use
			 * that one in order to not make existing Liveblogs to explode
			 * before we fully transition to post meta caching
			 */
			if ( true === empty( $cache_time ) && true === empty( $cache ) ) {
				$entry = get_post( $entry_id );
				if ( true === is_a( $entry, 'WP_Post' ) ) {
					$post_id    = $entry->post_parent;
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
					 * @param mixed  $cache      The cached HTML result, stored in post meta.
					 * @param string $url        The attempted embed URL.
					 * @param array  $attr       An array of shortcode attributes.
					 * @param int    $entry_id   Post ID.
					 */
					return apply_filters( 'embed_oembed_html', $cache, $url, $attr, $entry_id );
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
				update_post_meta( $entry_id, $cachekey, $html );
				update_post_meta( $entry_id, $cachekey_time, time() );
			} elseif ( ! $cache ) {
				update_post_meta( $entry_id, $cachekey, '{{unknown}}' );
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
				 * @param int    $entry_id   Comment ID.
				 */
				return apply_filters( 'embed_oembed_html', $html, $url, $attr, $entry_id );
			}
		}

		// Still unknown
		return $this->maybe_make_link( $url );
	}

	/**
	 * Delete all Comment oEmbed caches.
	 *
	 * @param int $entry_id post ID to delete the caches for.
	 */
	public function delete_oembed_caches( $entry_id = 0 ) {
		if ( ! $entry_id ) {
			$entry_id = get_the_ID();
		}
		$post_metas = get_post_meta( $entry_id );
		if ( empty( $post_metas ) ) {
			return;
		}
		if ( ! is_array( $post_metas ) ) {
			return;
		}
		$post_meta_keys = array_keys( $post_metas );
		if ( ! $post_meta_keys ) {
			return;
		}

		foreach ( $post_meta_keys as $post_meta_key ) {
			if ( '_oembed_' === substr( $post_meta_key, 0, 8 ) ) {
				delete_post_meta( $entry_id, $post_meta_key );
			}
		}
	}

	/**
	 * Triggers a caching of all post's oEmbed results.
	 *
	 * The function is not implemented and overrides the
	 * parent class' one. We don't need to pre-populate cache
	 * for posts are they are being created on the fly.
	 *
	 * @param int $entry_id Post ID to do the caching for.
	 */
	public function cache_oembed( $entry_id ) {
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
		$return              = $this->shortcode( [], $match[2] );
		$this->linkifunknown = $oldval;

		return $match[1] . $return . $match[3];
	}

	/**
	 * Passes any unlinked URLs that are on their own line to {@link WP_Embed::shortcode()} for potential embedding.
	 *
	 * @uses WP_Embed::autoembed_callback()
	 *
	 * @param string $content The content to be searched.
	 * @param int|WP_Post $emtry object or integer representing the Post ID
	 * @return string Potentially modified $content.
	 */
	public function autoembed( $content, $entry = null ) {
		// Save post's ID for later use - needed for Liveblog plugin compatibility as it calls the autoembed method directly
		if ( ! empty( $entry ) ) {
			$entry = get_post( $entry );
			if ( is_a( $entry, 'WP_Post' ) ) {
				$this->ID = $entry->ID;
			}
		}

		// Replace line breaks from all HTML elements with placeholders.
		$content = wp_replace_in_html_tags( $content, [ "\n" => '<!-- wp-line-break -->' ] );

		// Find URLs that are on their own line.
		if ( preg_match( '#(^|\s|>)https?://#i', $content ) ) {
			// Find URLs on their own line.
			$content = preg_replace_callback( '|^(\s*)(https?://[^\s<>"]+)(\s*)$|im', [ $this, 'autoembed_callback' ], $content );
			// Find URLs in their own paragraph.
			$content = preg_replace_callback( '|(<p(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/p>)|i', [ $this, 'autoembed_callback' ], $content );
		}

		// Put the line breaks back.
		return str_replace( '<!-- wp-line-break -->', "\n", $content );
	}

}
