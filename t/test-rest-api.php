<?php

class Test_REST_API extends WP_UnitTestCase {

	const HTTP_AUTH_USER = '';
	const HTTP_AUTH_PASS = '';

	/**
	 * These are integration tests.
	 * They make real HTTP requests to the new and old endpoints and compare the results
	 */
	function test_compare_new_old_endpoint_output() {

		// Setup some config variables. These will need to be changed for different environments
		$host              = 'wp.local';
		$post_id           = '5';
		$old_partial_query = '/2016/02/13/';

		// Base endpoints used for all calls
		$base_endpoint1 = 'http://' . $host . $old_partial_query . $post_id . '/liveblog';
		$base_endpoint2 = 'http://' . $host . '/wp-json/liveblog/v1/' . $post_id;


		/***********************************************************************
		 * Check the endpoints for getting entries between two timestamps
		 * Runs as an unauthenticated user
		 ***********************************************************************/

		// Set to a range that will return some entries
		$start_time        = '1455903120';
		$end_time          = '1455910058';

		$endpoint1 = $base_endpoint1 . '/' . $start_time . '/' . $end_time . '/';
		$endpoint2 = $base_endpoint2 . '/' . $start_time . '/' . $end_time . '/';

		// Call the first endpoint
		$ch1 = curl_init();
		$ch1 = self::set_common_curl_options( $ch1 );
		curl_setopt( $ch1, CURLOPT_URL, $endpoint1 );

		$response1 = curl_exec( $ch1 );
		curl_close( $ch1 );

		// Call the second endpoint
		$ch2 = curl_init();
		$ch2 = self::set_common_curl_options( $ch2 );
		curl_setopt( $ch2, CURLOPT_URL, $endpoint2 );

		$response2 = curl_exec( $ch2 );
		curl_close( $ch2 );

		// Cross your fingers and toes
		$this->assertJsonStringEqualsJsonString( $response1, $response2 );


		/***********************************************************************
		 * Check to make sure an unauthenticated user cannot insert new entries
		 ***********************************************************************/

		$endpoint1 = $base_endpoint1 . '/crud';
		$endpoint2 = $base_endpoint2 . '/crud';

		$post_data_insert = array(
			'crud_action' => 'insert',
			'post_id' => $post_id,
			'entry_id' => '',
			'content' => 'Crazy test entry3!',
		);

		$ch1 = curl_init();
		$ch1 = self::set_common_curl_options( $ch1 );
		curl_setopt( $ch1, CURLOPT_URL, $endpoint1 );
		curl_setopt( $ch1, CURLOPT_POST, 1 );
		curl_setopt( $ch1, CURLOPT_POSTFIELDS, $post_data_insert );

		curl_exec( $ch1 );
		$response1_http_code = curl_getinfo( $ch1, CURLINFO_HTTP_CODE );
		curl_close( $ch1 );

		$ch2 = curl_init();
		$ch2 = self::set_common_curl_options( $ch2 );
		curl_setopt( $ch2, CURLOPT_URL, $endpoint2 );
		curl_setopt( $ch2, CURLOPT_POST, 1 );
		curl_setopt( $ch2, CURLOPT_POSTFIELDS, $post_data_insert );

		curl_exec( $ch2 );
		$response2_http_code = curl_getinfo( $ch2, CURLINFO_HTTP_CODE );
		curl_close( $ch2 );

		// HTTP response codes should be 403 Forbidden
		$this->assertEquals( 403, $response1_http_code );
		$this->assertEquals( 403, $response2_http_code );
		
	}

	private static function set_common_curl_options( $_ch ) {
		curl_setopt( $_ch, CURLOPT_HEADER, 0 );
		curl_setopt( $_ch, CURLOPT_HTTPHEADER, array('Accept:application/json' ) );
		curl_setopt( $_ch, CURLOPT_RETURNTRANSFER, 1 );

		// Use HTTP auth
		if ( ! empty( self::HTTP_AUTH_USER ) && ! empty( self::HTTP_AUTH_PASS ) ) {
			curl_setopt( $_ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
			curl_setopt( $_ch, CURLOPT_USERPWD, self::HTTP_AUTH_USER . ':' . self::HTTP_AUTH_PASS );
		}

		return $_ch;
	}

}