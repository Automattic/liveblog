<?php

/**
 * Class WPCOM_Liveblog_Rest_Api
 *
 * This class integrates with the REST API framework added in WordPress 4.4
 * It registers endpoints matching the legacy functionality in the WPCOM_Liveblog ajax methods
 *
 */

class WPCOM_Liveblog_Rest_Api {

	private static $api_version;
	private static $api_namespace;

	public static $endpoint_base;

	/**
	 * Load everything the class needs
	 */
	public static function load() {

		self::$endpoint_base = self::build_endpoint_base();

		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

	}

	public static function build_endpoint_base() {

		if ( ! empty( self::$endpoint_base ) ) {

			// @codeCoverageIgnoreStart
			return self::$endpoint_base;
			// @codeCoverageIgnoreEnd
		}

		self::$api_version   = '1';
		self::$api_namespace = 'liveblog/v' . self::$api_version;

		if ( get_option( 'permalink_structure' ) ) {
			// Pretty permalinks enabled
			$base = '/' . rest_get_url_prefix() . '/' . self::$api_namespace . '/';
		} else {
			// Pretty permalinks not enabled
			$base = '/?rest_route=/' . self::$api_namespace . '/';
		}

		return $base;

	}

	/**
	 * Register all of our endpoints
	 * Any validation, sanitization, and permission checks can be done here using callbacks
	 */
	public static function register_routes() {

		/*
		 * Get all entries for a post in between two timestamps
		 *
		 * /<post_id>/entries/<start_time>/<end_time>
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/entries/(?P<start_time>\d+)/(?P<end_time>\d+)([/]*)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_entries' ),
				'args' => array(
					'post_id' => array(
						'required' => true,
					),
					'start_time' => array(
						'required' => true,
					),
					'end_time' => array(
						'required' => true,
					),
				),
			)
		);

		/*
		 * Perform a specific CRUD action on an entry
		 * Allowed actions are 'insert', 'update', 'delete', 'delete_key'
		 *
		 * /<post_id>/crud
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/crud([/]*)',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'crud_entry' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args' => array(
					'crud_action' => array(
						'required' => true,
						'validate_callback' => array( __CLASS__, 'validate_crud_action' ),
					),
					'post_id' => array(
						'required' => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_numeric' ),
					),
					'content' => array(
						'required' => false,
					),
					'entry_id' => array(
						'required' => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_numeric' ),
					),
				)
			)
		);

		/*
		 * Get entries for a post for lazyloading on the page
		 *
		 * /<post_id>/lazyload/<max_time>/<min_time>
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/lazyload/(?P<max_time>\d+)/(?P<min_time>\d+)([/]*)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_lazyload_entries' ),
				'args' => array(
					'post_id' => array(
						'required' => true,
					),
					'max_time' => array(
						'required' => true,
					),
					'min_time' => array(
						'required' => true,
					),
				),
			)
		);

		/*
		 * Get a single entry for a post by entry ID
		 *
		 * /<post_id>/entry/<entry_id>
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/entry/(?P<entry_id>\d+)([/]*)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_single_entry' ),
				'args' => array(
					'post_id' => array(
						'required' => true,
					),
					'entry_id' => array(
						'required' => true,
					),
				),
			)
		);

		/*
		 * Take entry content and return it with pretty formatting
		 *
		 * /<post_id>/preview
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/preview([/]*)',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'format_preview_entry' ),
				'args' => array(
					'entry_content' => array(
						'required' => true,
					),
				)
			)
		);

		/*
		 * Get a list of authors matching a search term.
		 * Used to autocomplete @ mentions
		 *
		 * /authors/<term>
		 *
		 * TODO: The regex pattern will allow no slash between 'authors' and the search term.
		 *       Look into requiring the slash
		 *
		 */
		register_rest_route( self::$api_namespace, '/authors([/]*)(?P<term>.*)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_authors' ),
				'args' => array(
					'term' => array(
						'required' => false,
					),
				)
			)
		);

		/*
		 * Get a list of hashtags matching a search term.
		 * Used to autocomplete previously used #hashtags
		 *
		 * /hashtags/<term>
		 *
		 * TODO: The regex pattern will allow no slash between 'hashtags' and the search term.
		 *       Look into requiring the slash
		 *
		 */
		register_rest_route( self::$api_namespace, '/hashtags([/]*)(?P<term>.*)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( __CLASS__, 'get_hashtag_terms' ),
				'args' => array(
					'term' => array(
						'required' => false,
					),
				)
			)
		);

		/*
		 * Save and retrieve Liveblog post state and meta-data
		 *
		 * /<post_id>/post_state
		 *
		 */
		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/post_state([/]*)',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'update_post_state' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args' => array(
					'post_id' => array(
						'required' => true,
					),
					'state' => array(
						'required' => true,
					),
					'template_name' => array(
						'required' => true,
					),
					'template_format' => array(
						'required' => true,
					),
					'limit' => array(
						'required' => true,
					),
				),
			)
		);

	}

	/**
	 * Get all entries for a post in between two timestamps
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of entries
	 */
	public static function get_entries( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id         = $request->get_param( 'post_id' );
		$start_timestamp = $request->get_param( 'start_time' );
		$end_timestamp   = $request->get_param( 'end_time' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entries within the start and end boundaries
		$entries = WPCOM_Liveblog::get_entries_by_time( $start_timestamp, $end_timestamp );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Perform a specific CRUD action on an entry
	 * Allowed actions are 'insert', 'update', 'delete', 'delete_key'
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return mixed
	 */
	public static function crud_entry( WP_REST_Request $request ) {

		// Get the required parameters from the request
		$crud_action = $request->get_param( 'crud_action' );
		$args = array(
			'post_id'  => $request->get_param( 'post_id' ),
			'content'  => $request->get_param( 'content' ),
			'entry_id' => $request->get_param( 'entry_id' ),
		);

		self::set_liveblog_vars( $args['post_id'] );

		// Attempt to perform the requested action
		$entry = WPCOM_Liveblog::do_crud_entry( $crud_action, $args );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entry;
	}

	/**
	 * Get entries for a post for lazyloading on the page
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of entries
	 */
	public static function get_lazyload_entries( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id       = $request->get_param( 'post_id' );
		$max_timestamp = $request->get_param( 'max_time' );
		$min_timestamp = $request->get_param( 'min_time' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entries too be lazyloaded
		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Get a single entry for a post by entry ID
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array containing the entry if found
	 */
	public static function get_single_entry( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id  = $request->get_param( 'post_id' );
		$entry_id = $request->get_param( 'entry_id' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entry
		$entries = WPCOM_Liveblog::get_single_entry( $entry_id );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Take entry content and return it with pretty formatting
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array The entry content wrapped in HTML elements
	 */
	public static function format_preview_entry( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id       = $request->get_param( 'post_id' );
		$entry_content = $request->get_param( 'entry_content' );

		self::set_liveblog_vars( $post_id );

		// Get entry preview
		$preview = WPCOM_Liveblog::format_preview_entry( $entry_content );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $preview;
	}

	/**
	 * Get a list of authors matching a search term.
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of authors on the site
	 */
	public static function get_authors( WP_REST_Request $request ) {

		// Get required parameters from the request
		$term = $request->get_param( 'term' );

		// Get a list of authors
		$liveblog_authors = new WPCOM_Liveblog_Entry_Extend_Feature_Authors();
		$authors = $liveblog_authors->get_authors( $term );

		return $authors;
	}

	/**
	 * Get a list of hashtags matching a search term
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of matching hastags
	 */
	public static function get_hashtag_terms( WP_REST_Request $request ) {

		// Get required parameters from the request
		$term = $request->get_param( 'term' );

		// Get a list of authors
		$liveblog_hashtags = new WPCOM_Liveblog_Entry_Extend_Feature_Hashtags();
		$hashtags = $liveblog_hashtags->get_hashtag_terms( $term );

		return $hashtags;
	}

	/**
	 * Set the Liveblog state of a post
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return string THe metabox markup to be displayed
	 */
	public static function update_post_state( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id         = $request->get_param( 'post_id' );
		$state           = $request->get_param( 'state' );

		// Additional request variables used in the liveblog_admin_settings_update action
		$request_vars = array(
			'state'                        => $state,
			'liveblog-key-template-name'   => $request->get_param( 'template_name' ),
			'liveblog-key-template-format' => $request->get_param( 'template_format' ),
			'liveblog-key-limit'           => $request->get_param( 'limit' ),
		);

		self::set_liveblog_vars( $post_id );

		// Save post state
		$meta_box = WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, $state, $request_vars );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $meta_box;

	}

	/**
	 * Set a few static variables in the WPCOM_Liveblog class needed for some callbacks to work
	 *
	 * @param int $post_id The post ID for the current request
	 */
	private static function set_liveblog_vars( $post_id ) {
		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id          = $post_id;
	}

	/**
	 * Validation callback to check for allowed crud action
	 *
	 * @return bool true if $param is one of insert|update|delete|delete_key. false otherwise
	 */
	public static function validate_crud_action( $param, $request, $key ) {
		return WPCOM_Liveblog::is_valid_crud_action( $param );
	}

	/**
	 * Sanitization callback to ensure an integer value
	 *
	 * @return int $param as an integer. 0 if $param is not numeric
	 */
	public static function sanitize_numeric( $param, $request, $key ) {
		return ( ! empty( $param ) && is_numeric( $param ) ? intval( $param ) : 0 );
	}

}
