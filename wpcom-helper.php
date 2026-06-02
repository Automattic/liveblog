<?php
/**
 * WordPress.com helper functions for liveblog.
 *
 * @package Liveblog
 */

/*
 * Disable Socket support.
 */
define( 'LIVEBLOG_USE_SOCKETIO', false );

/**
 * Bump stats extras for liveblog actions.
 *
 * @param string $stat  The stat name.
 * @param string $extra The extra value.
 * @return void
 */
function wpcom_vip_liveblog_bump_stats_extras( $stat, $extra ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress.com VIP helper function.
	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( $stat, $extra );
	}
}

// Load the Twitter scripts on every page.
// The sacrifice of a script is better than
// the complexity of trying to load it dynamically only when a new entry with a tweet
// comes in.
add_action(
	'wp_enqueue_scripts',
	function () {
		// Fail gracefully if BlackbirdPie isn't available.
		if ( ! isset( $GLOBALS['BlackbirdPie'] ) || ! is_a( $GLOBALS['BlackbirdPie'], 'BlackbirdPie' ) ) {
			return;
		}

		$GLOBALS['BlackbirdPie']->load_scripts();
		$GLOBALS['BlackbirdPie']->load_infinite_scroll_script();
	}
);

// Stats tracking for liveblog.
add_action(
	'liveblog_enable_post',
	function ( $post_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'enable' );
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog-enable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

		if ( function_exists( 'send_vip_team_irc_alert' ) ) {
			send_vip_team_irc_alert( '[VIP Liveblog] Enabled on post ' . get_permalink( $post_id ) . ' by ' . get_current_user_id() );
		}
	}
);

add_action(
	'liveblog_disable_post',
	function ( $post_id ) {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog', 'disable' );
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog-disable-by-theme', str_replace( '/', '-', get_stylesheet() ) );

		if ( function_exists( 'send_vip_team_irc_alert' ) ) {
			send_vip_team_irc_alert( '[VIP Liveblog] Disabled on post ' . get_permalink( $post_id ) . ' by ' . get_current_user_id() );
		}
	}
);

add_action(
	'liveblog_entry_request_empty',
	function () {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'miss' );
	}
);

add_action(
	'liveblog_entry_request',
	function () {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_request', 'hit' );
	}
);

add_action(
	'liveblog_preview_entry',
	function () {
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'preview' );
	}
);

add_action(
	'liveblog_insert_entry',
	function ( $comment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by action signature.
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'insert' );
	}
);

add_action(
	'liveblog_update_entry',
	function ( $new_comment_id, $replaces_comment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by action signature.
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'update' );
	},
	10,
	2
);

add_action(
	'liveblog_delete_entry',
	function ( $comment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by action signature.
		wpcom_vip_liveblog_bump_stats_extras( 'liveblog_entry_action', 'delete' );
	}
);

/**
 * Clear the feed cache when a Liveblog entry is updated
 */
add_action( 'liveblog_insert_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_update_entry', 'wpcom_invalidate_feed_cache' );
add_action( 'liveblog_delete_entry', 'wpcom_invalidate_feed_cache' );

/*
 * Don't show the post box for blogs the current user isn't a member of.
 * Helps protect against any accidents by superadmins.
 */
add_filter(
	'liveblog_current_user_can_edit_liveblog',
	function ( $can_edit ) {

		// Retain super admin access for A12s.
		if ( is_automattician() || ( defined( 'A8C_PROXIED_REQUEST' ) && A8C_PROXIED_REQUEST ) ) { // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.UsingRestrictedConstant -- Legitimate A8C proxy detection.
			return $can_edit;
		}

		if ( $can_edit && ! is_admin() && is_user_logged_in() && ! is_user_member_of_blog() ) {
			return false;
		}

		return $can_edit;
	}
);
