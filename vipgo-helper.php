<?php
class WPCOM_Liveblog_VIPGo_Helper {

	/**
	 * Hook actions and filters
	 */
	public static function hooks() {
		add_action( 'liveblog_insert_entry', [ __CLASS__, 'purge_liveblog_edge_cache' ], 10, 2 );
	}

	/**
	 * Purge LiveBlog edge cache (Varnish) on liveblog updates.
	 *
	 * @param int $liveblog_update_id Update entry id.
	 * @param int $post_id            Parent LiveBlog post id.
	 * @return void
	 */
	public static function purge_liveblog_edge_cache( $liveblog_update_id, $post_id ) {
		if ( ! defined( 'VIP_GO_ENV' ) || false === VIP_GO_ENV ) {
			return;
		}

		if ( ! function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
			return;
		}

		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return;
		}

		wpcom_vip_purge_edge_cache_for_url( $permalink );

		if ( ! function_exists( 'amp_get_permalink' ) ) {
			return;
		}

		$amp_permalink = amp_get_permalink( $post_id );
		if ( ! $amp_permalink ) {
			return;
		}

		wpcom_vip_purge_edge_cache_for_url( $amp_permalink );
	}
}

add_action( 'after_setup_theme', [ 'WPCOM_Liveblog_VIPGo_Helper', 'hooks' ] );
