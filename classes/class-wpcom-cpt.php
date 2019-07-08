<?php
/**
 * Live Blog Custom Post Type
 */

/**
 * Register and handle the "Live Blog" Custom Post Type
 */
class WPCOM_Liveblog_CPT {

	const DEFAULT_CPT_SLUG = 'wpcom_liveblog';

	public static $cpt_slug;

	/**
	 * Register the Live Blog post type
	 *
	 * @return object|WP_Error
	 */
	public static function register_post_type() {
		self::$cpt_slug = apply_filter( 'wpcom_liveblog_cpt_slug', self::DEFAULT_CPT_SLUG );

		return register_post_type(
			self::$cpt_slug,
			[
				'labels'        => [
					'name'               => 'Live blogs',
					'singular_name'      => 'Live blog',
				],
				'menu_icon'     => 'dashicons-admin-post'
			]
		);
	}
}

add_action( 'init', [ 'WPCOM_Liveblog_CPT', 'register_post_type'] );
