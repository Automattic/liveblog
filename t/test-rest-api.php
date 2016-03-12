<?php

class Test_REST_API extends WP_UnitTestCase {

	const HTTP_AUTH_USER = '';
	const HTTP_AUTH_PASS = '';

	/**
	 * Test for the expected array structure when getting entries
	 */
	function test_get_entries_by_time_not_empty_response_structure() {

		$this->setup_entry_test_state();

		$start_time = strtotime('-1 hour');
		$end_time   = strtotime('+1 hour');

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertArrayHasKey('entries', $entries);
		$this->assertArrayHasKey('latest_timestamp', $entries);

	}

	/**
	 * Test for a non-empty response when getting entries
	 */
	function test_get_entries_by_time_not_empty() {

		$this->setup_entry_test_state();

		// A time window with entries
		$start_time = strtotime('-1 hour');
		$end_time   = strtotime('+1 hour');

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertNotEmpty($entries['entries']);
		$this->assertNotNull($entries['latest_timestamp']);

	}

	/**
	 * Test for an empty response when getting entries
	 */
	function test_get_entries_by_time_is_empty() {

		$this->setup_entry_test_state();

		// A time window without entries
		$start_time = strtotime('-2 hour');
		$end_time   = strtotime('-1 hour');

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertEmpty($entries['entries']);
		$this->assertNull($entries['latest_timestamp']);

	}

	/**
	 * Test for valid return values when getting a single entry
	 */
	function test_get_single_entry_not_empty() {

		$new_entry = $this->setup_entry_test_state();

		$entry = WPCOM_Liveblog::get_single_entry( $new_entry[0]->get_id() );

		$this->assertNotEmpty( $entry['entries'] );
		$this->assertInternalType( 'int', $entry['index'] );
		$this->assertInternalType( 'int', $entry['nextTimestamp'] );
		$this->assertInternalType( 'int', $entry['previousTimestamp'] );

	}

	/**
	 * Test for valid return values when getting a single entry that doesn't exist
	 */
	function test_get_single_entry_is_empty() {

		$this->setup_entry_test_state();

		$entry = WPCOM_Liveblog::get_single_entry( 1010 );

		$this->assertEmpty( $entry['entries'] );

	}

	/**
	 * Test for a non-empty response when getting entries for lazyloading
	 */
	function test_get_lazyload_entries_by_time_not_empty() {

		// Create multiple entries
		$this->setup_entry_test_state( 3 );

		// A time window with entries
		$max_timestamp = strtotime( '+1 day' );
		$min_timestamp = 0;

		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		$this->assertNotEmpty( $entries['entries'] );
		$this->assertInternalType( 'int', $entries['index'] );

	}

	/**
	 * Test for an empty response when getting entries for lazyloading
	 */
	function test_get_lazyload_entries_by_time_is_empty() {

		$this->setup_entry_test_state();

		// A time window without entries
		$max_timestamp = strtotime( '-1 day' );
		$min_timestamp = 0;

		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		$this->assertEmpty( $entries['entries'] );
		$this->assertInternalType( 'int', $entries['index'] );

	}

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

	private function setup_entry_test_state( $number_of_entries = 1 ) {
		$entries = $this->insert_entries( $number_of_entries );

		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id          = 1;

		return $entries;
	}

	private function insert_entries( $number_of_entries = 1, $args = array() ) {
		$entries = array();

		$user = $this->factory->user->create_and_get();
		$args = array( 'post_id' => 1, 'content' => 'Test Liveblog entry', 'user' => $user, );

		for( $i = 0; $i < $number_of_entries; $i++ ) {
			$entries[] = WPCOM_Liveblog_Entry::insert( $args );
		}
		
		return $entries;
	}

}