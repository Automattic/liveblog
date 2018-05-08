<?php

/**
 * Class WPCOM_Liveblog_AMP
 *
 * Adds AMP support for Liveblog
 */
class WPCOM_Liveblog_AMP {

	/**
	 * Called by WPCOM_Liveblog::load(),
	 */
	public static function load() {

		// Make sure AMP plugin is installed, if not exit.
		if ( ! function_exists( 'amp_activate' ) ) {
			return;
		}

		// Are we viewing AMP?
		// bail

		// Remove current content filter.
		add_filter( 'template_redirect', function() {
			remove_filter( 'the_content', array( 'WPCOM_Liveblog', 'add_liveblog_to_content' ), 20 );
		}, 10 );

		add_filter( 'the_content', array( __CLASS__, 'append_liveblog_to_content' ), 20 );
	}

	/**
	 * Get template.
	 *
	 * @param  string $name      Name of Template.
	 * @param  array  $variables Variables to be passed to Template.
	 * @return string            Rendered Template
	 */
	public static function get_template( $name, $variables = array() ) {
		$template = new WPCOM_Liveblog_AMP_Template();
		return $template->render( $name, $variables );
	}

	/**
	 * Append Liveblog to Content
	 */
	public static function append_liveblog_to_content( $content ) {
		global $post;

		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		$entries = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last_known_entry );

		// Set the last known entry for users who don't have one yet.
		if ( $request->last_known_entry === false ) {
			$request->last_known_entry = $entries['entries'][0]->id . '-' . $entries['entries'][0]->timestamp;
		}

		$content .= self::get_template( 'feed', array(
			'entries' 	=> $entries['entries'],
			'page'		=> $entries['page'],
			'pages'		=> $entries['pages'],
			'links'		=> self::get_pagination_links( $request, $entries['pages'], $post->post_id ),
		) );

		return $content;
	}

	/**
	 * Get Page and Last known entry from the request.
	 *
	 * @return object Request Data.
	 */
	public static function get_request_data() {
		$amp  				= get_query_var( 'amp' );
		$page 				= preg_match( '/page\/(\d*)/', $amp, $matches ) ? (int) $matches[1] : 1;
		$last_known_entry 	= preg_match( '/last-known-entry\/([\d-]*)/', $amp, $matches ) ? $matches[1] : false;

		return (object) array(
			'page' 				=> $page,
			'last_known_entry' 	=> $last_known_entry,
		);
	}


	public static function get_pagination_links( $request, $pages, $post_id ) {
		$links = array();

		$permalink = amp_get_permalink( $post_id );

		$links['first'] = self::build_paged_permalink( $permalink, 1, $request->last_known_entry );
		$links['last'] = self::build_paged_permalink( $permalink, $pages, $request->last_known_entry );

		$links['prev'] = false;
		if ( $request->page > 1 ) {
			$links['prev'] = self::build_paged_permalink( $permalink, $request->page - 1, $request->last_known_entry );
		}

		$links['next'] = false;
		if ( $request->page < $pages ) {
			$links['next'] = self::build_paged_permalink( $permalink, $request->page + 1, $request->last_known_entry );
		}

		return (object) $links;
	}

	public static function build_paged_permalink( $permalink, $page, $last_known_entry ) {
		return $permalink . '/page/'. $page .'/last-known-entry/' . $last_known_entry;
	}

}
