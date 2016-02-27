<?php

/**
 * Class WPCOM_Liveblog_Rest_Api
 *
 * This class integrates with the REST API framework added in WordPress 4.4
 * It registers and required endpoints
 */

class WPCOM_Liveblog_Rest_Api {

	// TODO: Following REST conventions with method names such as register_routes, get_items, get_item, create_item, update_item and delete_item
	// -- This might be helpful if further integration is needed when the full REST API is available in core
 	// -- See: http://v2.wp-api.org/extending/adding/
 	// TODO: Make sure caching is handled the same way as in WPCOM_Liveblog

	private static $api_version;
	private static $api_namespace;

	public static $endpoint_base;
	public static $endpoint_get_entries_by_date;
	public static $endpoint_crud;

	/**
	 * Load everything the class needs
	 */
	public static function load() {

		self::$api_version = '1';
		self::$api_namespace = 'liveblog/v' . self::$api_version;

		// Populate the endpoint variables
		self::init_endpoints();

		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

	}

	private static function init_endpoints() {

		self::$endpoint_base = '/wp-json/' . self::$api_namespace . '/';

	}

	/**
	 * Register all of our endpoints
	 * Any validation, sanitization, and permission checks can be done here using callbacks
	 */
	public static function register_routes() {

		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/(?P<start_time>\d+)/(?P<end_time>\d+)',
			array(
		        'methods' => WP_REST_Server::READABLE,
		        'callback' => array( __CLASS__, 'get_entries' ),
		        'args' => array(
		        	'post_id' => array(
		            	'required' => true,
		            	'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		            'start_time' => array(
		            	'required' => true,
		                'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		            'end_time' => array(
		            	'required' => true,
		            	'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		        ),
	    	)
	    );

		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/crud',
	    	array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'crud_entry' ),
				'permission_callback' => function() {
					// Check if the current user can edit the liveblog
					return WPCOM_Liveblog::current_user_can_edit_liveblog();
				},
		        'args' => array(
		        	'crud_action' => array(
		            	'required' => true,
		            	'validate_callback' => function( $param, $request, $key ) {
		            		// Must be one of these values
		            		return in_array( $param, array( 'insert', 'update', 'delete', 'delete_key' ) );
		                },
		            ),
		            'post_id' => array(
		            	'required' => false,
		            	'sanitize_callback' => function( $param, $request, $key ) {
		            		return ( ! empty( $param ) && is_numeric( $param ) ? intval( $param ) : 0 );
		                },
		            ),
		            'content' => array(
		            	'required' => false,
		            	'sanitize_callback' => function( $param, $request, $key ) {
		            		return ( ! empty( $param ) ? $param : '' );
		                },
		            ),
		            'entry_id' => array(
		            	'required' => false,
		            	'sanitize_callback' => function( $param, $request, $key ) {
		            		return ( ! empty( $param ) && is_numeric( $param ) ? intval( $param ) : 0 );
		                },
		            ),
		        )
			)
	    );

		register_rest_route( self::$api_namespace, '/(?P<post_id>\d+)/lazyload/(?P<max_time>\d+)/(?P<min_time>\d+)',
			array(
		        'methods' => WP_REST_Server::READABLE,
		        'callback' => array( __CLASS__, 'get_lazyload_entries' ),
		        'args' => array(
		        	'post_id' => array(
		            	'required' => true,
		            	'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		            'max_time' => array(
		            	'required' => true,
		                'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		            'min_time' => array(
		            	'required' => true,
		            	'validate_callback' => function( $param, $request, $key ) {
		                    return is_numeric( $param );
		                },
		            ),
		        ),
	    	)
	    );

	}

	/**
	 * Look for any new Liveblog entries, and return them via JSON
	 * Uses the new REST API framework added in 4.4
	 *
	 * @param WP_REST_Request $request  A REST request object
	 *
	 * @return An array of live blog entries
	 */
	public static function get_entries( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id = $request->get_param('post_id');
		$start_timestamp = $request->get_param('start_time');
		$end_timestamp = $request->get_param('end_time');

		self::set_liveblog_vars($post_id);

		// Get liveblog entries within the start and end boundaries
		$entries = WPCOM_Liveblog::get_entries_by_time( $start_timestamp, $end_timestamp );

		return $entries;
	}

	/**
	 * Perform a specific CRUD task on an entry
	 *
	 * @param WP_REST_Request $request  A REST request object
	 *
	 * @return 
	 */
	public static function crud_entry( WP_REST_Request $request ) {

		// Get the required parameters from the request
		$crud_action = $request->get_param( 'crud_action' );
		$args = array(
			'post_id'  => $request->get_param( 'post_id' ),
			'content'  => $request->get_param( 'content' ),
			'entry_id' => $request->get_param( 'entry_id' ),
		);

		self::set_liveblog_vars($args['post_id']);

		// Attempt to perform the requested action
		$entry = WPCOM_Liveblog::do_crud_entry( $crud_action, $args );

		return $entry;
	}

	public static function get_lazyload_entries( WP_REST_Request $request ) {

		// Get required parameters from the request
		$post_id = $request->get_param('post_id');
		$max_timestamp = $request->get_param('max_time');
		$min_timestamp = $request->get_param('min_time');

		self::set_liveblog_vars($post_id);

		// Get liveblog entries too be lazyloaded
		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		return $entries;
	}

	/**
	 * Set a few static variables in the WPCOM_Liveblog class needed for callbacks to work
	 */
	private static function set_liveblog_vars($post_id) {
		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id = $post_id;
	}

}
