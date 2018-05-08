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

		self::$scripts = [
			'amp-live-list' 	=> 'https://cdn.ampproject.org/v0/amp-live-list-0.1.js',
			'amp-social-share'  => 'https://cdn.ampproject.org/v0/amp-social-share-0.1.js'
		];

		self::$styles = [
			'amp-custom' =>  dirname( __DIR__ ) . '/assets/app.css',
		];

		add_filter( 'amp_post_template_data', array( __CLASS__, 'append_liveblog_to_content' ), 10, 2 );

		add_action( 'amp_post_template_css', function() {
			foreach ( self::$styles as $style ) {
				include $style;
			}
		} );
	}

	/**
	 * Append Liveblog to Content
	 *
	 * @param  array  $data AMP Data.
	 * @param  object $post WP Post.
	 * @return array       Updated AMP Data
	 */
	public static function append_liveblog_to_content( $data, $post ) {
		// If we are not viewing a liveblog post then exist the filter.
		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		$entries = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last_known_entry );

		// Set the last known entry for users who don't have one yet.
		if ( $request->last_known_entry === false ) {
			$request->last_known_entry = $entries['entries'][0]->id . '-' . $entries['entries'][0]->timestamp;
		}

		foreach ( $entries['entries'] as $key => $entry ) {
			$amp_content 							= self::prepare_entry_content( $entry->content, $entry );
			$entries['entries'][$key]->amp_content 	= $amp_content->get_amp_content();
			$data['amp_component_scripts'] 			= array_merge( $data['amp_component_scripts'], $amp_content->get_amp_scripts() );
			$data['post_amp_styles'] 				= array_merge( $data['post_amp_styles'], $amp_content->get_amp_styles() );
		}

		$data['amp_component_scripts'] = array_merge( $data['amp_component_scripts'], self::$scripts );

		$data['post_amp_content'] .= self::get_template( 'feed', array(
			'entries' 	=> $entries['entries'],
			'page'		=> $entries['page'],
			'pages'		=> $entries['pages'],
			'links'		=> self::get_pagination_links( $request, $entries['pages'], $post->post_id ),
		) );

		return $data;
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

	public static function prepare_entry_content( $content, $entry ) {
		$amp_content = new AMP_Content(
			$content,
			apply_filters(
				'amp_content_embed_handlers', array(
					'AMP_Twitter_Embed_Handler'     => array(),
					'AMP_YouTube_Embed_Handler'     => array(),
					'AMP_DailyMotion_Embed_Handler' => array(),
					'AMP_Vimeo_Embed_Handler'       => array(),
					'AMP_SoundCloud_Embed_Handler'  => array(),
					'AMP_Instagram_Embed_Handler'   => array(),
					'AMP_Vine_Embed_Handler'        => array(),
					'AMP_Facebook_Embed_Handler'    => array(),
					'AMP_Pinterest_Embed_Handler'   => array(),
					'AMP_Gallery_Embed_Handler'     => array(),
					'WPCOM_AMP_Polldaddy_Embed'     => array(),
				), $entry
			),
			apply_filters(
				'amp_content_sanitizers', array(
					'AMP_Style_Sanitizer'             => array(),
					'AMP_Img_Sanitizer'               => array(),
					'AMP_Video_Sanitizer'             => array(),
					'AMP_Audio_Sanitizer'             => array(),
					'AMP_Playbuzz_Sanitizer'          => array(),
					'AMP_Iframe_Sanitizer'            => array(
						'add_placeholder' => true,
					),
					'AMP_Tag_And_Attribute_Sanitizer' => array(), // Note: This whitelist sanitizer must come at the end to clean up any remaining issues the other sanitizers didn't catch.
				), $entry
			),
			array(
				'content_max_width' => 600,
			)
		);
		return $amp_content;
	}
}
