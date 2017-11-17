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
	if ( is_automattician() || ( defined( A8C_PROXIED_REQUEST ) && A8C_PROXIED_REQUEST ) ) {
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


$GLOBALS['wpcom_comments_embed'] = new WPCOM_Comments_Embed();
