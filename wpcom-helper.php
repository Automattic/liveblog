<?php

/*
 * Disable Socket support
 */
define( 'LIVEBLOG_USE_SOCKETIO', false );

function wpcom_vip_liveblog_bump_stats_extras( $stat, $extra ) {
	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( $stat, $extra );
	}
}

// Use an AJAX URL, which is easier to match in server configs
// Using an endpoint can be ambiguous
add_action( 'after_liveblog_init', function() {

	// No need to use an Ajax URL if we're using the REST API.
	if ( WPCOM_Liveblog::use_rest_api() ) {
		return;
	}

	add_filter( 'liveblog_endpoint_url', function( $url, $post_id ) { return home_url( '__liveblog_' . $post_id . '/' ); }, 10, 2 );
	add_rewrite_rule( '^__liveblog_([0-9]+)/(.*)/?', 'index.php?p=$matches[1]&liveblog=$matches[2]', 'top' );

	add_filter( 'liveblog_refresh_interval', function( $refresh_interval ) {
		return 3; // more frequent updates; we can handle it.
	} );
	// If a site's permalink structure does not end with a trailing slash the url created by liveblog will redirect.
	if ( false !== strpos( $_SERVER['REQUEST_URI'],'__liveblog_' ) ){
		add_action( 'wp',
			function(){
				remove_action( 'template_redirect', 'redirect_canonical' );
			}
		);
	}
} );

// Load the Twitter scripts on every page – the sacrifice of a script is better than
// the complexity of trying to load it dynamically only when a new entry with a tweet
// comes in
add_action( 'wp_enqueue_scripts', function() {
	global $BlackbirdPie;

	// Fail gracefully if BlackbirdPie isn't available.
	if ( ! isset( $BlackbirdPie ) || ! is_a( $BlackbirdPie, 'BlackbirdPie' ) ) {
		return;
	}

	$BlackbirdPie->load_scripts();
	$BlackbirdPie->load_infinite_scroll_script();
} );

// Stats tracking for liveblog
add_action( 'liveblog_enable_post', function( $post_id ) {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'enable' );
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog-enable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

	if ( function_exists( 'send_vip_team_irc_alert' ) ) {
		send_vip_team_irc_alert( '[VIP Liveblog] Enabled on post '. get_permalink( $post_id ) . ' by ' . get_current_user_id() );
	}
} );

add_action( 'liveblog_disable_post', function( $post_id ) {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'disable' );
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog-disable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

	if ( function_exists( 'send_vip_team_irc_alert' ) ) {
		send_vip_team_irc_alert( '[VIP Liveblog] Disabled on post '. get_permalink( $post_id ) . ' by ' . get_current_user_id() );
	}
} );

add_action( 'liveblog_entry_request_empty', function() {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'miss' );
} );

add_action( 'liveblog_entry_request', function() {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'hit' );
} );

add_action( 'liveblog_preview_entry', function() {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'preview' );
} );

add_action( 'liveblog_insert_entry', function( $comment_id ) {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'insert' );
} );

add_action( 'liveblog_update_entry', function( $new_comment_id, $replaces_comment_id ) {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'update' );
}, 10, 2 );

add_action( 'liveblog_delete_entry', function( $comment_id ) {
	wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'delete' );
} );

/**
 * Clear the feed cache when a Liveblog entry is updated
 */
add_action( 'liveblog_insert_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_update_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_delete_entry', 'wpcom_invalidate_feed_cache' );

// Don't show the post box for blogs the current user isn't a member of.
// Helps protect against any accidents by superadmins.
add_filter( 'liveblog_current_user_can_edit_liveblog', function( $can_edit ) {

	// Retain super admin access for A12s.
	if ( is_automattician() || ( defined( 'A8C_PROXIED_REQUEST' ) && A8C_PROXIED_REQUEST ) ) {
		return $can_edit;
	}

	if ( $can_edit && ! is_admin() && is_user_logged_in() && ! is_user_member_of_blog() ) {
		return false;
	}

	return $can_edit;
} );

/**
 * Disable default WordPress embeds in liveblog entries in order to be able to override the autoembed feature by custom solution
 *
 * The liveblog's native behaviour results in oembed cache being stored in post meta which may result in huge number of such
 * post metas stored and eventually exceed the 1M limit for memcache (all post metas are stored in a single key).
 *
 * Discussion: http://wp.me/poqVs-caP
 *
 * We'll use a custom class for storing the oembed meta in comment meta
 * @see wpcom_liveblog_autoembed
 *
 * As far as the function name and code is concerned. We could simply use `__return_false` directly as the filter's callback,
 * but using custom function wrapping the `__return_false()` call is adding more readability to the code and should prevent
 * confusions and conflicts with custom functionality in themes and other plugins
 */
function wpcom_liveblog_disable_embeds() {
	return __return_false();
}
add_filter( 'liveblog_entry_enable_embeds', 'wpcom_liveblog_disable_embeds', 1000, 0 );

/**
 * WordPress.com specific comment_text filter for handling the automebeds in liveblog entries
 *
 * Filters the comment_text for liveblog entries only with custom implementation of autoembed
 * which is taking advantage of comment meta (vs. post meta) for storing oembed cache
 *
 * Discussion: http://wp.me/poqVs-caP
 *
 * @param string $comment_text Text of the current comment.
 * @param WP_Comment $comment Optional. WP_Comment object.
 */
function wpcom_liveblog_autoembed( $comment_text, $comment = null ) {
	//This filter is meant only for liveblog entries
	if ( true === is_a( $comment, 'WP_Comment' ) && 'liveblog' === $comment->comment_type ) {

		/**
		 * remove the filter preventing autoembed in WPCOM_Liveblog_Entry::render_content
		 * it needs to be removed as we're using the very same filter later in this function
		 * and we still want plugins and themes to be able to take advantage of the filters
		 */
		remove_filter( 'liveblog_entry_enable_embeds', 'wpcom_liveblog_disable_embeds', 1000 );

		//honor the standard filter from WPCOM_Liveblog_Entry::render_content as well as the option
		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) ) {

				/**
				 * Check for existence of WPCOM_Comments_Embed class which extends the WP_Embed class
				 * The WPCOM_Comments_Embed class does not store oembeds meta cache in post meta, but
				 * in comment meta and thus preventing the memcache entry storing all post's meta
				 * exceeding 1M limit.
				 * See http://wp.me/poqVs-caP
				 */
				if ( true === class_exists( 'WPCOM_Comments_Embed' )
					 && true === isset( $GLOBALS['wpcom_comments_embed'] )
					 && is_a( $GLOBALS['wpcom_comments_embed'], 'WPCOM_Comments_Embed' )
				) {
					global $wpcom_comments_embed;
					$comment_text = $wpcom_comments_embed->autoembed( $comment_text, $comment );
				} else {
					//defaults to standard WP_Embed class and post meta cache for oembed
					global $wp_embed;
					$comment_text = $wp_embed->autoembed( $comment_text );
				}

			}

			$comment_text = do_shortcode( $comment_text );
		}

		//re-enable the filter preventing autoembed in WPCOM_Liveblog_Entry::render_content
		add_filter( 'liveblog_entry_enable_embeds', 'wpcom_liveblog_disable_embeds', 1000, 0 );

	}
	return $comment_text;
}
//need to hook soon in order to filter the comment's link before they are turned to HTML
add_filter( 'comment_text', 'wpcom_liveblog_autoembed', 2, 2 );

class WPCOM_Comments_Embed extends WP_Embed {

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
		return;
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
		}

		if ( empty( $comment ) ) {
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
		$attr = wp_parse_args( $attr, wp_embed_defaults( $url ) );

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
		foreach ( $handlers as $priority => $handlers ) {
			foreach ( $handlers as $id => $handler ) {
				if ( preg_match( $handler['regex'], $url, $matches ) && is_callable( $handler['callback'] ) ) {
					if ( false !== $return = call_user_func( $handler['callback'], $matches, $attr, $url, $rawattr ) )
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

		$comment_ID = ( ! empty( $comment->comment_ID ) ) ? $comment->comment_ID : null;
		if ( ! empty( $this->comment_ID ) ) { // Potentially set by WPCOM_Comments_Embed::autoembed()
			$comment_ID = $this->comment_ID;
		}

		// Unknown URL format. Let oEmbed have a go.
		if ( $comment_ID ) {

			// Check for a cached result (stored in the comment meta)
			$key_suffix = md5( $url . serialize( $attr ) );
			$cachekey = '_oembed_' . $key_suffix;
			$cachekey_time = '_oembed_time_' . $key_suffix;

			/**
			 * Filter the oEmbed TTL value (time to live).
			 *
			 * @param int    $time       Time to live (in seconds).
			 * @param string $url        The attempted embed URL.
			 * @param array  $attr       An array of shortcode attributes.
			 * @param int    $comment_ID Comment ID.
			 */
			$ttl = apply_filters( 'oembed_ttl', DAY_IN_SECONDS, $url, $attr, $comment_ID );

			$cache = get_comment_meta( $comment_ID, $cachekey, true );
			$cache_time = get_comment_meta( $comment_ID, $cachekey_time, true );

			/**
			 * Check post meta in case there is no existing comment meta
			 * Odds are that related post meta exists and we should use
			 * that one in order to not make existing Liveblogs to explode
			 * before we fully transition to comment meta caching
			 */
			if ( true === empty( $cache_time ) && true === empty( $cache ) ) {
				$comment = get_comment( $comment_ID );
				if ( true === is_a( $comment, 'WP_Comment' ) ) {
					$post_id = $comment->comment_post_ID;
					$cache = get_post_meta( $post_id, $cachekey, true );
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
					 * @param int    $comment_ID Comment ID.
					 */
					return apply_filters( 'embed_oembed_html', $cache, $url, $attr, $comment_ID );
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
				update_comment_meta( $comment_ID, $cachekey, $html );
				update_comment_meta( $comment_ID, $cachekey_time, time() );
			} elseif ( ! $cache ) {
				update_comment_meta( $comment_ID, $cachekey, '{{unknown}}' );
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
				 * @param int    $comment_ID Comment ID.
				 */
				return apply_filters( 'embed_oembed_html', $html, $url, $attr, $comment_ID );
			}
		}

		// Still unknown
		return $this->maybe_make_link( $url );
	}

	/**
	 * Delete all Comment oEmbed caches.
	 *
	 * @param int $comment_ID Comment ID to delete the caches for.
	 */
	public function delete_oembed_caches( $comment_ID = 0 ) {
		if ( ! $comment_id ) {
			$comment_id = get_comment_ID;
		}
		$comment_metas = get_comment_meta( $comment_ID );
		if ( empty( $comment_metas ) ) {
			return;
		}
		if ( ! is_array( $comment_metas ) ) {
			return;
		}
		if ( ! $comment_meta_keys = array_keys( $comment_metas ) ) {
			return;
		}

		foreach ( $comment_meta_keys as $comment_meta_key ) {
			if ( '_oembed_' == substr( $comment_meta_key, 0, 8 ) )
				delete_comment_meta( $comment_ID, $comment_meta_key );
		}
	}

	/**
	 * Triggers a caching of all comment's oEmbed results.
	 *
	 * The function is not implemented and overrides the
	 * parent class' one. We don't need to pre-populate cache
	 * for comments are they are being created on the fly.
	 *
	 * @param int $comment_ID Comment ID to do the caching for.
	 */
	public function cache_oembed( $comment_ID ) {
		return;
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
		$content = preg_replace_callback( '|^(\s*)(https?://[^\s"]+)(\s*)$|im', array( $this, 'autoembed_callback' ), $content );

		// Put the line breaks back.
		return str_replace( '<!-- wp-line-break -->', "\n", $content );
	}

}

$GLOBALS['wpcom_comments_embed'] = new WPCOM_Comments_Embed();
