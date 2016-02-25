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
	// TODO: use permission_callback for any restricted endpoints

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
		// self::$endpoint_get_entries_by_date = self::$endpoint_base . 'entries_between';
		// self::$endpoint_crud = self::$endpoint_base;

	}

	/**
	 * Register all of our endpoints
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
				'callback' => array( 'WPCOM_Liveblog', 'rest_api_crud_entry' ),
				'permission_callback' => function() {
					// TODO: Restrict with current_user_can_edit_liveblog()
					return true;
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

		WPCOM_Liveblog::$is_rest_api_call = true;

		// Get required parameters from the request
		$post_id = $request->get_param('post_id');
		$start_timestamp = $request->get_param('start_time');
		$end_timestamp = $request->get_param('end_time');

		WPCOM_Liveblog::$post_id = $post_id;

		// Get liveblog entries within the start and end boundaries
		$entries = WPCOM_Liveblog::get_entries_by_time( $start_timestamp, $end_timestamp );

		return $entries;
	}

}
