<?php

/**
 * Class WPCOM_Liveblog_AMP
 *
 * Adds AMP support for Liveblog
 */
class WPCOM_Liveblog_AMP {

	public static $scripts = [];

	public static $styles = [];

	/**
	 * Called by WPCOM_Liveblog::load(),
	 */
	public static function load() {

		// Make sure AMP plugin is installed, if not exit.
		if ( ! function_exists( 'amp_activate' ) ) {
			return;
		}

		self::$scripts = [];

		self::$styles = [
			'amp-custom' =>  dirname( __DIR__ ) . '/assets/amp.css',
		];

		//var_dump(is_amp_endpoint());
		//current_theme_supports( 'amp' )

		add_filter( 'template_redirect', function() {
			if ( is_amp_endpoint() ) {
				remove_filter( 'the_content', array( 'WPCOM_Liveblog', 'add_liveblog_to_content' ), 20 );
				add_filter( 'the_content', array( __CLASS__, 'append_liveblog_to_content' ), 7 );
				remove_filter( 'the_content', 'wpautop' );
				remove_action( 'wp_enqueue_scripts', array( 'WPCOM_Liveblog', 'enqueue_scripts' ) );
			}
		}, 10 );

		//add_filter( 'amp_post_template_metadata', array( __CLASS__, 'append_liveblog_to_metadata' ), 10, 2 );

		// add_action( 'amp_post_template_css', function() {
		// 	foreach ( self::$styles as $style ) {
		// 		include $style;
		// 	}
		// } );
		//
		//

		function my_enqueue_styles() {
			wp_enqueue_style( 'liveblog', plugin_dir_url( __DIR__ ) . 'assets/amp.css' );
		}
		add_action( 'wp_enqueue_scripts', 'my_enqueue_styles' );
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

		foreach ( self::$styles as $style ) {
			//$content .= '<style>' . file_get_contents($style) . '</style>';
		}

		//$content .= file_get_contents($style);

		// echo '<pre>';
		// var_dump($content);
		// echo '</pre>';

		return $content;
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
}
