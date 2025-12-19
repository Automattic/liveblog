<?php
/**
 * VIP Go Helper file.
 *
 * This file is automatically loaded by the VIP Go platform and contains
 * VIP-specific enhancements.
 *
 * @see https://docs.wpvip.com/plugins/helper-file/
 * @package Automattic\Liveblog
 */

/**
 * Purge the VIP edge cache for a liveblog when entries change.
 *
 * Ensures that a Liveblog page isn't cached with stale metadata during an
 * active liveblog. This is particularly important for SEO and social sharing
 * where the page's cached version may contain outdated entry counts or timestamps.
 *
 * @param int $comment_id ID of the comment for this entry.
 * @param int $post_id    ID for this liveblog post.
 */
function liveblog_purge_edge_cache( $comment_id, $post_id ) {
	if ( ! function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
		return;
	}

	$permalink = get_permalink( absint( $post_id ) );
	if ( ! $permalink ) {
		return;
	}

	wpcom_vip_purge_edge_cache_for_url( $permalink );
}
add_action( 'liveblog_insert_entry', 'liveblog_purge_edge_cache', 10, 2 );
add_action( 'liveblog_update_entry', 'liveblog_purge_edge_cache', 10, 2 );
add_action( 'liveblog_delete_entry', 'liveblog_purge_edge_cache', 10, 2 );
