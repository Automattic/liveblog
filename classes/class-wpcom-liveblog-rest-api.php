<?php

/**
 * Class WPCOM_Liveblog_Rest_Api
 *
 * This class integrates with the REST API framework added in WordPress 4.4
 * It registers and required endpoints
 */

class WPCOM_Liveblog_Rest_Api {

	/**
	 * Register all of our endpoints
	 */
	public static function load() {

		add_action( 'rest_api_init', function () {
		    register_rest_route( 'liveblog/v1', '/entries_between/(?P<post_id>\d+)/(?P<start_time>\d+)/(?P<end_time>\d+)', array(
		        'methods' => 'GET',
		        'callback' => 'WPCOM_Liveblog::rest_api_entries_between',
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
		    ) );
		} );

	}

}
