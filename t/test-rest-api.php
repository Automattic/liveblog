<?php

class Test_REST_API extends WP_UnitTestCase {

	function test_compare_new_old_endpoint_output() {

		// Setup some config variables. These will need to be changed for different environments
		$host              = 'wp.local';
		$post_id           = '5';
		$auth_user         = '';
		$auth_pass         = '';
		$old_partial_query = '/2016/02/13/';
		
		// If host requires http auth
		$auth_string = ( ( ! empty( $auth_user ) && ! empty( $auth_pass ) ) ? $auth_user . ':' . $auth_pass . '@' : '' );

		// Base endpoints used for all calls
		$base_endpoint1 = 'http://' . $auth_string . $host . $old_partial_query . $post_id . '/liveblog';
		$base_endpoint2 = 'http://' . $auth_string . $host . '/wp-json/liveblog/v1/' . $post_id;

		/*
		 * Check the endpoints for getting entries between two timestamps
		 * Runs as an unauthenticated user
		 */

		// Set to a range that will return some entries
		$start_time        = '1455903120';
		$end_time          = '1455910058';

		$endpoint_get_entries1 = $base_endpoint1 . '/' . $start_time . '/' . $end_time . '/';
		$endpoint_get_entries2 = $base_endpoint2 . '/' . $start_time . '/' . $end_time . '/';

		// Call the first endpoint
		$ch1 = curl_init();
		$ch1 = self::set_common_curl_options( $ch1 );
		curl_setopt( $ch1, CURLOPT_URL, $endpoint_get_entries1 );

		$response1 = curl_exec( $ch1 );
		curl_close( $ch1 );

		// Call the second endpoint
		$ch2 = curl_init();
		$ch2 = self::set_common_curl_options( $ch2 );
		curl_setopt( $ch2, CURLOPT_URL, $endpoint_get_entries2 );

		$response2 = curl_exec( $ch2 );
		curl_close( $ch2 );

		// Cross your fingers and toes
		$this->assertJsonStringEqualsJsonString( $response1, $response2 );

		/*
		 * TODO: Check all other endpoints
		 */
		
	}

	private static function set_common_curl_options( $_ch ) {
		curl_setopt( $_ch, CURLOPT_HEADER, 0 );
		curl_setopt( $_ch, CURLOPT_HTTPHEADER, array('Accept:application/json' ) );
		curl_setopt( $_ch, CURLOPT_RETURNTRANSFER, 1 );

		return $_ch;
	}

}