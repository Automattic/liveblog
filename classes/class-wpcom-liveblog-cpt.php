<?php
/**
 * Live Blog Custom Post Type
 */

/**
 * Register and handle the "Live Blog" Custom Post Type
 */
class WPCOM_Liveblog_CPT {

	const DEFAULT_CPT_SLUG = 'liveblog';

	public static $cpt_slug;

	/**
	 * Register the Live Blog post type
	 *
	 * @return object|WP_Error
	 */
	public static function register_post_type() {

		add_action( 'before_delete_post', [ __CLASS__, 'delete_children' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_children_from_query' ] );
		add_filter( 'parse_query', [ __CLASS__, 'hierarchical_posts_filter' ] );

		self::$cpt_slug = apply_filters( 'wpcom_liveblog_cpt_slug', self::DEFAULT_CPT_SLUG );

		return register_post_type(
			self::$cpt_slug,
			[
				'labels'    => [
					'name'          => 'Live blogs',
					'singular_name' => 'Live blog',
				],
				'menu_icon' => 'dashicons-admin-post',
			]
		);
	}

	/**
	 * Remove nested child posts when a parent is removed.
	 *
	 * @param int $parent ID of the parent post being deleted
	 */
	public static function delete_children( $parent ) {

		// Remove the query filter.
		remove_filter( 'parse_query', [ __CLASS__, 'hierarchical_posts_filter' ] );
		remove_action( 'pre_get_posts', [ __CLASS__, 'filter_children_from_query' ] );
		$parent = (int) $parent; // Force a cast as an integer.

		$post = get_post( $parent );

		// Only delete children of top-level posts.
		if ( 0 !== $post->post_parent || self::$cpt_slug !== $post->post_type ) {
			return;
		}

		// Get all children
		$children = new WP_Query(
			[
				'post_type'        => self::$cpt_slug,
				'post_parent'      => $parent,
				'suppress_filters' => false,
			]
		);

		// Remove the action so it doesn't fire again
		remove_action( 'before_delete_post', [ __CLASS__, 'delete_children' ] );

		if ( $children->have_posts() ) {
			foreach ( $children->posts as $child ) {
				// Never delete top level posts!
				if ( 0 === (int) $child->post_parent ) {
					continue;
				}
				wp_delete_post( $child->ID, true );
			}
		}

		add_action( 'before_delete_post', [ __CLASS__, 'delete_children' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_children_from_query' ] );
		add_filter( 'parse_query', [ __CLASS__, 'hierarchical_posts_filter' ] );

	}

	public static function filter_children_from_query( $query ) {

		$post_type = $query->get( 'post_type' );

		// only applies to indexes and post format
		if ( is_author() || is_search() || is_feed() || ( ( $query->is_home() || $query->is_archive() ) && ( empty( $post_type ) || in_array( $post_type, [ self::$cpt_slug ], true ) ) ) ) {
			$parent = $query->get( 'post_parent' );
			if ( empty( $parent ) ) {
				$query->set( 'post_parent', 0 );
			}
		}

	}

	/**
	 * Posts cannot typically have parent-child relationships.
	 *
	 * Our updates, however, are all "owned" by a traditional
	 * post so we know how to lump things together on the front-end
	 * and in the post editor.
	 *
	 * @param WP_Query $query Current query.
	 *
	 * @return WP_Query
	 */
	public static function hierarchical_posts_filter( $query ) {
		global $pagenow, $typenow;

		if ( is_admin() && 'edit.php' === $pagenow && in_array( $typenow, [ self::$cpt_slug ], true ) ) {
			$query->query_vars['post_parent'] = 0;
		}

		return $query;
	}
}

add_action( 'init', [ 'WPCOM_Liveblog_CPT', 'register_post_type' ] );
