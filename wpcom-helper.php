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
add_action(
	'after_liveblog_init',
	function() {

		// No need to use an Ajax URL if we're using the REST API.
		if ( WPCOM_Liveblog::use_rest_api() ) {
			return;
		}

		add_filter(
			'liveblog_endpoint_url',
			function( $url, $post_id ) {
				return home_url( '__liveblog_' . $post_id . '/' );
			},
			10,
			2
		);
		add_rewrite_rule( '^__liveblog_([0-9]+)/(.*)/?', 'index.php?p=$matches[1]&liveblog=$matches[2]', 'top' );

		add_filter(
			'liveblog_refresh_interval',
			function( $refresh_interval ) {
				return 3; // more frequent updates; we can handle it.
			}
		);
		// If a site's permalink structure does not end with a trailing slash the url created by liveblog will redirect.
		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '__liveblog_' ) ) { // input var ok
			add_action(
				'wp',
				function() {
					remove_action( 'template_redirect', 'redirect_canonical' );
				}
			);
		}
	}
);

// Load the Twitter scripts on every page – the sacrifice of a script is better than
// the complexity of trying to load it dynamically only when a new entry with a tweet
// comes in
add_action(
	'wp_enqueue_scripts',
	function() {
		// Fail gracefully if BlackbirdPie isn't available.
		if ( ! isset( $GLOBALS['BlackbirdPie'] ) || ! is_a( $GLOBALS['BlackbirdPie'], 'BlackbirdPie' ) ) {
			return;
		}

		$GLOBALS['BlackbirdPie']->load_scripts();
		$GLOBALS['BlackbirdPie']->load_infinite_scroll_script();
	}
);

// Stats tracking for liveblog
add_action(
	'liveblog_enable_post',
	function( $post_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'enable' );
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog-enable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

		if ( function_exists( 'send_vip_team_irc_alert' ) ) {
			send_vip_team_irc_alert( '[VIP Liveblog] Enabled on post ' . get_permalink( $post_id ) . ' by ' . get_current_user_id() );
		}
	}
);

add_action(
	'liveblog_disable_post',
	function( $post_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'disable' );
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog-disable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

		if ( function_exists( 'send_vip_team_irc_alert' ) ) {
			send_vip_team_irc_alert( '[VIP Liveblog] Disabled on post ' . get_permalink( $post_id ) . ' by ' . get_current_user_id() );
		}
	}
);

add_action(
	'liveblog_entry_request_empty',
	function() {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'miss' );
	}
);

add_action(
	'liveblog_entry_request',
	function() {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'hit' );
	}
);

add_action(
	'liveblog_preview_entry',
	function() {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'preview' );
	}
);

add_action(
	'liveblog_insert_entry',
	function( $comment_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'insert' );
	}
);

add_action(
	'liveblog_update_entry',
	function( $new_comment_id, $replaces_comment_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'update' );
	},
	10,
	2
);

add_action(
	'liveblog_delete_entry',
	function( $comment_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'delete' );
	}
);

/**
 * Clear the feed cache when a Liveblog entry is updated
 */
add_action( 'liveblog_insert_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_update_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_delete_entry', 'wpcom_invalidate_feed_cache' );

// Don't show the post box for blogs the current user isn't a member of.
// Helps protect against any accidents by superadmins.
add_filter(
	'liveblog_current_user_can_edit_liveblog',
	function( $can_edit ) {

		// Retain super admin access for A12s.
		if ( is_automattician() || ( defined( 'A8C_PROXIED_REQUEST' ) && A8C_PROXIED_REQUEST ) ) { // phpcs:ignore WordPressVIPMinimum.Constants.ConstantRestrictions.ConstantRestrictions
			return $can_edit;
		}

		if ( $can_edit && ! is_admin() && is_user_logged_in() && ! is_user_member_of_blog() ) {
			return false;
		}

		return $can_edit;
	}
);
