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

	/**
	 * Register all of our endpoints
	 */
	public static function load() {

		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// add_action( 'admin_notices', array( 'WPCOM_Liveblog', 'show_old_wp_notice' ) );

	}

	public static function register_routes() {

		$version = '1';
        $namespace = 'liveblog/v' . $version;
        $base = '';

		register_rest_route( $namespace, '/' . $base . '/entries_between/(?P<post_id>\d+)/(?P<start_time>\d+)/(?P<end_time>\d+)',
			array(
		        'methods' => WP_REST_Server::READABLE,
		        'callback' => array( 'WPCOM_Liveblog', 'rest_api_entries_between' ),
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
	}

}
