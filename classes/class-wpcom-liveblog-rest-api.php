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

		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );

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

		return home_url( $base );

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
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/entries/(?P<start_time>\d+)/(?P<end_time>\d+)([/]*)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_entries' ],
				'args'     => [
					'post_id'    => [
						'required' => true,
					],
					'start_time' => [
						'required' => true,
					],
					'end_time'   => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Perform a specific CRUD action on an entry
		 * Allowed actions are 'insert', 'update', 'delete', 'delete_key'
		 *
		 * /<post_id>/crud
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/crud([/]*)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'crud_entry' ],
				'permission_callback' => [ 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ],
				'args'                => [
					'crud_action' => [
						'required'          => true,
						'validate_callback' => [ __CLASS__, 'validate_crud_action' ],
					],
					'post_id'     => [
						'required'          => false,
						'sanitize_callback' => [ __CLASS__, 'sanitize_numeric' ],
					],
					'content'     => [
						'required' => false,
					],
					'entry_id'    => [
						'required'          => false,
						'sanitize_callback' => [ __CLASS__, 'sanitize_numeric' ],
					],
					'status'      => [
						'required'          => false,
						'default'           => 'draft',
						'validate_callback' => [ __CLASS__, 'sanitize_status' ],
					],
				],
			]
		);

		/*
		 * Get entries for a post for lazyloading on the page
		 *
		 * /<post_id>/lazyload/<max_time>/<min_time>
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/lazyload/(?P<max_time>\d+)/(?P<min_time>\d+)([/]*)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_lazyload_entries' ],
				'args'     => [
					'post_id'  => [
						'required' => true,
					],
					'max_time' => [
						'required' => true,
					],
					'min_time' => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Get a single entry for a post by entry ID
		 *
		 * /<post_id>/entry/<entry_id>
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/entry/(?P<entry_id>\d+)([/]*)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_single_entry' ],
				'args'     => [
					'post_id'  => [
						'required' => true,
					],
					'entry_id' => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Take entry content and return it with pretty formatting
		 *
		 * /<post_id>/preview
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/preview([/]*)',
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ __CLASS__, 'format_preview_entry' ],
				'args'     => [
					'entry_content' => [
						'required' => true,
					],
				],
			]
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
		register_rest_route(
			self::$api_namespace,
			'/authors([/]*)(?P<term>.*)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_authors' ],
				'args'     => [
					'term' => [
						'required' => false,
					],
				],
			]
		);

		/*
		 * Save and retrieve Liveblog post state and meta-data
		 *
		 * /<post_id>/post_state
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/post_state([/]*)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'update_post_state' ],
				'permission_callback' => [ 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ],
				'args'                => [
					'post_id'         => [
						'required' => true,
					],
					'state'           => [
						'required' => true,
					],
					'template_name'   => [
						'required' => true,
					],
					'template_format' => [
						'required' => true,
					],
					'limit'           => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Get entries for a post in paged format
		 *
		 * /<post_id>/get-entries/<page>/<last_known_entry>
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/get-entries/(?P<page>\d+)/(?P<last_known_entry>[^\/]+)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_entries_paged' ],
				'args'     => [
					'post_id'          => [
						'required' => true,
					],
					'page'             => [
						'required' => true,
					],
					'last_known_entry' => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Get key events
		 *
		 * /<post_id>/get-key-events
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/get-key-events/(?P<last_known_entry>[^\/]+)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'get_key_events' ],
				'args'     => [
					'last_known_entry' => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Returns page and its entries which contains key entry
		 *
		 * /<post_id>/jump-to-key-event/<id>/<last_known_entry>
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/jump-to-key-event/(?P<id>\d+)/(?P<last_known_entry>[^\/]+)',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'jump_to_key_event' ],
				'args'     => [
					'post_id'          => [
						'required' => true,
					],
					'id'               => [
						'required' => true,
					],
					'last_known_entry' => [
						'required' => true,
					],
				],
			]
		);

		/*
		 * Returns all entries from most recent thru including key entry
		 *
		 * /<post_id>/jump-to-key-event/<id>/<last_known_entry>
		 *
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/jump-to-key-event/(?P<id>\d+)/(?P<last_known_entry>[^\/]+)/all',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ __CLASS__, 'load_all_and_jump_to_key_event' ],
				'args'     => [
					'post_id'          => [
						'required' => true,
					],
					'id'               => [
						'required' => true,
					],
					'last_known_entry' => [
						'required' => true,
					],
				],
			]
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

		add_filter( 'liveblog_all_entries_bypass_cache', [ __CLASS__, 'bypass_cache' ] );
		add_filter( 'liveblog_query_args', [ __CLASS__, 'maybe_allow_draft_post' ] );

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
		$json        = $request->get_json_params();

		$args = [
			'post_id'    => self::get_json_param( 'post_id', $json ),
			'content'    => self::get_json_param( 'content', $json ),
			'entry_id'   => self::get_json_param( 'entry_id', $json ),
			'author_ids' => self::get_json_param( 'author_ids', $json ),
			'headline'   => self::get_json_param( 'headline', $json ),
			'status'     => self::get_json_param( 'status', $json ),
		];

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

		add_filter( 'liveblog_all_entries_bypass_cache', [ __CLASS__, 'bypass_cache' ] );
		add_filter( 'liveblog_query_args', [ __CLASS__, 'maybe_allow_draft_post' ] );

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
		$json          = $request->get_json_params();
		$entry_content = self::get_json_param( 'entry_content', $json );

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
		$authors          = $liveblog_authors->get_authors( $term );

		return $authors;
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
		$post_id = $request->get_param( 'post_id' );
		$state   = $request->get_param( 'state' );

		// Additional request variables used in the liveblog_admin_settings_update action
		$request_vars = [
			'state'                        => $state,
			'liveblog-key-template-name'   => $request->get_param( 'template_name' ),
			'liveblog-key-template-format' => $request->get_param( 'template_format' ),
			'liveblog-key-limit'           => $request->get_param( 'limit' ),
		];

		self::set_liveblog_vars( $post_id );

		// Save post state
		$meta_box = WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, $state, $request_vars );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $meta_box;

	}

	/**
	 * Get entries for a post in paged format
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of entries
	 */
	public static function get_entries_paged( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id          = $request->get_param( 'post_id' );
		$page             = $request->get_param( 'page' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		self::set_liveblog_vars( $post_id );

		add_filter( 'liveblog_all_entries_bypass_cache', [ __CLASS__, 'bypass_cache' ] );
		add_filter( 'liveblog_query_args', [ __CLASS__, 'maybe_allow_draft_post' ] );

		$entries = WPCOM_Liveblog::get_entries_paged( $page, $last_known_entry );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}


	/**
	 * Get key events
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of key events
	 */
	public static function get_key_events( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id = $request->get_param( 'post_id' );

		self::set_liveblog_vars( $post_id );

		$key_events = WPCOM_Liveblog_Entry_Key_Events::all();
		$key_events = WPCOM_Liveblog::entries_for_json( $key_events );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $key_events;
	}

	/**
	 * Load all entries thru a key event.
	 */
	public static function load_all_and_jump_to_key_event( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id          = $request->get_param( 'post_id' );
		$id               = $request->get_param( 'id' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		self::set_liveblog_vars( $post_id );

		$entries = WPCOM_Liveblog::get_entries_paged( false, $last_known_entry, $id, true );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Jump to page for key event
	 *
	 * @param WP_REST_Request $request A REST request object
	 *
	 * @return array An array of entries
	 */
	public static function jump_to_key_event( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id          = $request->get_param( 'post_id' );
		$id               = $request->get_param( 'id' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		self::set_liveblog_vars( $post_id );

		$entries = WPCOM_Liveblog::get_entries_paged( false, $last_known_entry, $id );

		// Possibly do not cache the response
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
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

	/**
	 * Sanitization callback to ensure a valid post status
	 *
	 * @return int $param as an integer. 0 if $param is not a valid status
	 */
	public static function sanitize_status( $param, $request, $key ) {
		return in_array( $param, [ 'publish', 'draft' ], true );
	}

	/**
	 * Get parameter from JSON
	 * @param string $param
	 * @param array  $json
	 * @return mixed
	 */
	public static function get_json_param( $param, $json ) {
		if ( isset( $json[ $param ] ) ) {

			// contributor IDs is an array; html_entity_decode() only works on strings
			if ( is_array( $json[ $param ] ) ) {
				$values = $json[ $param ]; // copy the array; don't modify the original in-place
				array_walk( $values, 'html_entity_decode' );
				return $values;
			}

			return html_entity_decode( $json[ $param ] );
		}
		return false;
	}

	/**
	 * Checks to see if the current request includes a nonce so
	 * that we can expose draft post in the api response
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public static function maybe_allow_draft_post( $args ) {
		$nonce = null;
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( $_REQUEST['_wpnonce'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] );
		}
		$allowed_status = [ 'draft', 'publish' ];

		if ( wp_verify_nonce( $nonce, 'wp_rest' ) && WPCOM_Liveblog::current_user_can_edit_liveblog() ) {
			$status = filter_input( INPUT_GET, 'filter-status', FILTER_SANITIZE_STRING );
			if ( in_array( $status, $allowed_status, true ) ) {
				$args['post_status'] = $status;

				add_filter(
					'liveblog_updated_entry_status',
					function ( $entry_status ) use ( $status ) {
						return $status;
					}
				);
			} else {
				$args['post_status'] = $allowed_status;
			}
		}

		return $args;
	}

	/**
	 * Bypass cache when logged in so we can see the different post statuses
	 *
	 * @param $enabled
	 *
	 * @return bool
	 */
	public static function bypass_cache( $enabled ) {
		if ( WPCOM_Liveblog::current_user_can_edit_liveblog() ) {
			return true;
		}

		return $enabled;
	}
}
