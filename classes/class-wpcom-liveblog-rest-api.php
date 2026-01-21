<?php
/**
 * REST API endpoints for Liveblog.
 *
 * @package Liveblog
 */

/**
 * Class WPCOM_Liveblog_Rest_Api
 *
 * This class integrates with the REST API framework added in WordPress 4.4
 * It registers endpoints matching the legacy functionality in the WPCOM_Liveblog ajax methods.
 */
class WPCOM_Liveblog_Rest_Api {

	/**
	 * API version.
	 *
	 * @var string
	 */
	private static $api_version;

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private static $api_namespace;

	/**
	 * Endpoint base URL.
	 *
	 * @var string
	 */
	public static $endpoint_base;

	/**
	 * Load everything the class needs.
	 *
	 * @return void
	 */
	public static function load() {

		self::$endpoint_base = self::build_endpoint_base();

		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Build the REST API endpoint base URL.
	 *
	 * @return string The endpoint base URL.
	 */
	public static function build_endpoint_base() {

		/**
		 * Filters whether to use the static cache for the REST API endpoint base URL.
		 *
		 * By default, the endpoint base URL is cached in a static variable for performance.
		 * This can cause issues on multi-domain sites where the endpoint URL needs to vary
		 * based on the current request context.
		 *
		 * Return false to disable the static cache and rebuild the endpoint base on each call.
		 *
		 * @since 1.10.0
		 *
		 * @param bool $cache_enabled Whether to enable static caching. Default true.
		 */
		if ( ! empty( self::$endpoint_base ) && apply_filters( 'liveblog_cache_endpoint_base', true ) ) {

			// @codeCoverageIgnoreStart
			return self::$endpoint_base;
			// @codeCoverageIgnoreEnd
		}

		self::$api_version   = '1';
		self::$api_namespace = 'liveblog/v' . self::$api_version;

		if ( get_option( 'permalink_structure' ) ) {
			// Pretty permalinks enabled.
			$base = '/' . rest_get_url_prefix() . '/' . self::$api_namespace . '/';
		} else {
			// Pretty permalinks not enabled.
			$base = '/?rest_route=/' . self::$api_namespace . '/';
		}

		return home_url( $base );
	}

	/**
	 * Register all of our endpoints.
	 * Any validation, sanitization, and permission checks can be done here using callbacks.
	 *
	 * @return void
	 */
	public static function register_routes() {
		/*
		 * Get all entries for a post in between two timestamps.
		 *
		 * /<post_id>/entries/<start_time>/<end_time>
		 */
		register_rest_route(
			self::$api_namespace,
			'/(?P<post_id>\d+)/entries/(?P<start_time>\d+)/(?P<end_time>\d+)([/]*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_entries' ),
				'args'                => array(
					'post_id'    => array(
						'required' => true,
					),
					'start_time' => array(
						'required' => true,
					),
					'end_time'   => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
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
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'crud_entry' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'crud_action' => array(
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'validate_crud_action' ),
					),
					'post_id'     => array(
						'required'          => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_numeric' ),
					),
					'content'     => array(
						'required' => false,
					),
					'entry_id'    => array(
						'required'          => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_numeric' ),
					),
				),
			)
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_lazyload_entries' ),
				'args'                => array(
					'post_id'  => array(
						'required' => true,
					),
					'max_time' => array(
						'required' => true,
					),
					'min_time' => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_single_entry' ),
				'args'                => array(
					'post_id'  => array(
						'required' => true,
					),
					'entry_id' => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
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
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'format_preview_entry' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'entry_content' => array(
						'required' => true,
					),
				),
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
		register_rest_route(
			self::$api_namespace,
			'/authors([/]*)(?P<term>.*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_authors' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'term' => array(
						'required' => false,
					),
				),
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
		register_rest_route(
			self::$api_namespace,
			'/hashtags([/]*)(?P<term>.*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_hashtag_terms' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'term' => array(
						'required' => false,
					),
				),
			)
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
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_post_state' ),
				'permission_callback' => array( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'post_id'         => array(
						'required' => true,
					),
					'state'           => array(
						'required' => true,
					),
					'template_name'   => array(
						'required' => true,
					),
					'template_format' => array(
						'required' => true,
					),
					'limit'           => array(
						'required' => true,
					),
				),
			)
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_entries_paged' ),
				'args'                => array(
					'post_id'          => array(
						'required' => true,
					),
					'page'             => array(
						'required' => true,
					),
					'last_known_entry' => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_key_events' ),
				'args'                => array(
					'last_known_entry' => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'jump_to_key_event' ),
				'args'                => array(
					'post_id'          => array(
						'required' => true,
					),
					'id'               => array(
						'required' => true,
					),
					'last_known_entry' => array(
						'required' => true,
					),
				),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get all entries for a post in between two timestamps.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of entries.
	 */
	public static function get_entries( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id         = $request->get_param( 'post_id' );
		$start_timestamp = $request->get_param( 'start_time' );
		$end_timestamp   = $request->get_param( 'end_time' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entries within the start and end boundaries.
		$entries = WPCOM_Liveblog::get_entries_by_time( $start_timestamp, $end_timestamp );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Perform a specific CRUD action on an entry.
	 * Allowed actions are 'insert', 'update', 'delete', 'delete_key'.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return mixed The result of the CRUD operation.
	 */
	public static function crud_entry( WP_REST_Request $request ) {

		// Get the required parameters from the request.
		$crud_action = $request->get_param( 'crud_action' );
		$json        = $request->get_json_params();

		$args = array(
			'post_id'         => self::get_json_param( 'post_id', $json ),
			'content'         => self::get_json_param( 'content', $json ),
			'entry_id'        => self::get_json_param( 'entry_id', $json ),
			'author_id'       => self::get_json_param( 'author_id', $json ),
			'contributor_ids' => self::get_json_param( 'contributor_ids', $json ),
		);

		self::set_liveblog_vars( $args['post_id'] );

		// Attempt to perform the requested action.
		$entry = WPCOM_Liveblog::do_crud_entry( $crud_action, $args );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entry;
	}

	/**
	 * Get entries for a post for lazyloading on the page.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of entries.
	 */
	public static function get_lazyload_entries( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id       = $request->get_param( 'post_id' );
		$max_timestamp = $request->get_param( 'max_time' );
		$min_timestamp = $request->get_param( 'min_time' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entries to be lazyloaded.
		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Get a single entry for a post by entry ID.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array containing the entry if found.
	 */
	public static function get_single_entry( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id  = $request->get_param( 'post_id' );
		$entry_id = $request->get_param( 'entry_id' );

		self::set_liveblog_vars( $post_id );

		// Get liveblog entry.
		$entries = WPCOM_Liveblog::get_single_entry( $entry_id );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Take entry content and return it with pretty formatting.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array The entry content wrapped in HTML elements.
	 */
	public static function format_preview_entry( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id       = $request->get_param( 'post_id' );
		$json          = $request->get_json_params();
		$entry_content = self::get_json_param( 'entry_content', $json );

		self::set_liveblog_vars( $post_id );

		// Get entry preview.
		$preview = WPCOM_Liveblog::format_preview_entry( $entry_content );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $preview;
	}

	/**
	 * Get a list of authors matching a search term.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of authors on the site.
	 */
	public static function get_authors( WP_REST_Request $request ) {
		$term = $request->get_param( 'term' );

		// Use DDD author filter for autocomplete.
		$author_filter = \Automattic\Liveblog\Infrastructure\ServiceContainer::instance()->author_filter();
		return $author_filter->get_authors( $term );
	}

	/**
	 * Get a list of hashtags matching a search term.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of matching hashtags.
	 */
	public static function get_hashtag_terms( WP_REST_Request $request ) {
		$term = $request->get_param( 'term' );

		// Use DDD hashtag filter for autocomplete.
		$hashtag_filter = \Automattic\Liveblog\Infrastructure\ServiceContainer::instance()->hashtag_filter();
		return $hashtag_filter->get_hashtag_terms( $term );
	}

	/**
	 * Set the Liveblog state of a post.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return string The metabox markup to be displayed.
	 */
	public static function update_post_state( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id = $request->get_param( 'post_id' );
		$state   = $request->get_param( 'state' );

		// Additional request variables used in the liveblog_admin_settings_update action.
		$request_vars = array(
			'state'                        => $state,
			'liveblog-key-template-name'   => $request->get_param( 'template_name' ),
			'liveblog-key-template-format' => $request->get_param( 'template_format' ),
			'liveblog-key-limit'           => $request->get_param( 'limit' ),
		);

		self::set_liveblog_vars( $post_id );

		// Save post state.
		$meta_box = WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, $state, $request_vars );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $meta_box;
	}

	/**
	 * Get entries for a post in paged format.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of entries.
	 */
	public static function get_entries_paged( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id          = $request->get_param( 'post_id' );
		$page             = $request->get_param( 'page' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		self::set_liveblog_vars( $post_id );

		$entries = WPCOM_Liveblog::get_entries_paged( $page, $last_known_entry );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}


	/**
	 * Get key events.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of key events.
	 */
	public static function get_key_events( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id = $request->get_param( 'post_id' );

		self::set_liveblog_vars( $post_id );

		$key_event_service = \Automattic\Liveblog\Infrastructure\ServiceContainer::instance()->key_event_service();
		$key_events        = $key_event_service->get_key_events( (int) $post_id );
		$key_events        = WPCOM_Liveblog::entries_for_json( $key_events );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $key_events;
	}

	/**
	 * Jump to page for key event.
	 *
	 * @param WP_REST_Request $request A REST request object.
	 *
	 * @return array An array of entries.
	 */
	public static function jump_to_key_event( WP_REST_Request $request ) {

		// Get required parameters from the request.
		$post_id          = $request->get_param( 'post_id' );
		$id               = $request->get_param( 'id' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		self::set_liveblog_vars( $post_id );

		$entries = WPCOM_Liveblog::get_entries_paged( false, $last_known_entry, $id );

		// Possibly do not cache the response.
		WPCOM_Liveblog::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Set a few static variables in the WPCOM_Liveblog class needed for some callbacks to work.
	 *
	 * @param int $post_id The post ID for the current request.
	 * @return void
	 */
	private static function set_liveblog_vars( $post_id ) {
		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id          = $post_id;
	}

	/**
	 * Validation callback to check for allowed crud action.
	 *
	 * @param string          $param   The parameter value.
	 * @param WP_REST_Request $request The REST request.
	 * @param string          $key     The parameter key.
	 * @return bool True if $param is one of insert|update|delete|delete_key.
	 */
	public static function validate_crud_action( $param, $request, $key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by REST API validate_callback signature.
		return WPCOM_Liveblog::is_valid_crud_action( $param );
	}

	/**
	 * Sanitization callback to ensure an integer value.
	 *
	 * @param mixed           $param   The parameter value.
	 * @param WP_REST_Request $request The REST request.
	 * @param string          $key     The parameter key.
	 * @return int The param as an integer. 0 if $param is not numeric.
	 */
	public static function sanitize_numeric( $param, $request, $key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by REST API sanitize_callback signature.
		return ( ! empty( $param ) && is_numeric( $param ) ? intval( $param ) : 0 );
	}

	/**
	 * Get parameter from JSON.
	 *
	 * @param string $param The parameter name.
	 * @param array  $json  The JSON data.
	 * @return mixed The parameter value or false if not found.
	 */
	public static function get_json_param( $param, $json ) {
		if ( isset( $json[ $param ] ) ) {
			// Handle arrays (e.g., contributor_ids from multi-select).
			if ( is_array( $json[ $param ] ) ) {
				return array_map( 'html_entity_decode', $json[ $param ] );
			}
			return html_entity_decode( $json[ $param ] );
		}
		return false;
	}
}
