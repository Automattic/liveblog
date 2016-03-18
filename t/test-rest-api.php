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
	 * Test the insert CRUD action
	 */
	function test_crud_action_insert() {

		$user  = $this->factory->user->create_and_get();
		$args  = array( 'user' => $user, );
		$entry = WPCOM_Liveblog::do_crud_entry( 'insert', $this->build_entry_args( $args ) );

		$this->assertInternalType( 'array', $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] ); // Should this always be null?

	}

	/**
	 * Test the update CRUD action
	 */
	function test_crud_action_update() {

		$new_entry = $this->setup_entry_test_state();
		$args      = array( 'entry_id' => $new_entry[0]->get_id(), 'content' => 'Updated Test Liveblog entry', );
		$entry     = WPCOM_Liveblog::do_crud_entry( 'update', $this->build_entry_args( $args ) );

		$this->assertInternalType( 'array', $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] );

	}

	/**
	 * Test the delete CRUD action
	 */
	function test_crud_action_delete() {

		// First create an entry
		$new_entry = $this->setup_entry_test_state();

		$this->assertInternalType( 'array', $new_entry );
		$this->assertInstanceOf( 'WPCOM_Liveblog_Entry', $new_entry[0] );
		
		$new_entry_id = $new_entry[0]->get_id();

		// Then delete it
		$args  = array( 'entry_id' => $new_entry_id, );
		$entry = WPCOM_Liveblog::do_crud_entry( 'delete', $this->build_entry_args( $args ) );

		// Check that it was sent to the trash
		$deleted_entry = get_comment( $new_entry_id );

		$this->assertEquals( 'trash', $deleted_entry->comment_approved);

	}

	/**
	 * Test the delete_key CRUD action
	 */
	function test_crud_action_delete_key() {

		// First create an entry with a key
		$new_entry = $this->setup_entry_test_state( 1, array( 'content' => 'Test Liveblog entry with /key' ) );
		$new_entry_id = $new_entry[0]->get_id();

		// Then delete the key
		$args      = array( 'entry_id' => $new_entry_id, );
		$entry     = WPCOM_Liveblog::do_crud_entry( 'delete_key', $this->build_entry_args( $args ) );

		// $entry will be an instance of WP_Error if the entry didn't contain a key or there was another error
		$this->assertNotInstanceOf( 'WP_Error' , $entry);

	}

	/**
	 * Test getting a preview of an entry
	 */
	function test_preview_entry() {

		// Get entry preview
		$preview = WPCOM_Liveblog::format_preview_entry( 'Test Liveblog entry with /key' );

		$this->assertInternalType( 'array', $preview );
		$this->assertNotEmpty( $preview['html'] );

	}

	/**
	 * Test getting list of authors from a string
	 */
	function test_get_authors() {

		// Get a list of authors
		$liveblog_authors = new WPCOM_Liveblog_Entry_Extend_Feature_Authors();

		$authors_not_empty = $liveblog_authors->get_authors( 'adm' ); // Should return admin
		$authors_is_empty  = $liveblog_authors->get_authors( 'fakeauthor' ); // Non-existent user

		$this->assertInternalType( 'array', $authors_not_empty );
		$this->assertInternalType( 'array', $authors_is_empty );
		$this->assertNotEmpty( $authors_not_empty );
		$this->assertEmpty( $authors_is_empty );

	}

	/**
	 * Test getting list of hashtags from a string
	 */
	function test_get_hashtags() {

		// Get a list of hashtags
		$liveblog_hashtags = new WPCOM_Liveblog_Entry_Extend_Feature_Hashtags();

		// Create a temporary hashtag
		$this->factory->term->create( array( 'name' => 'coolhashtag', 'taxonomy' => 'hashtags', 'slug' => 'coolhashtag' ) );

		$hashtags_not_empty = $liveblog_hashtags->get_hashtag_terms( 'cool' ); // Should return coolhashtag
		$hashtags_is_empty  = $liveblog_hashtags->get_hashtag_terms( 'fakehashtag' ); // Non-existent hashtag

		$this->assertInternalType( 'array', $hashtags_not_empty );
		$this->assertInternalType( 'array', $hashtags_is_empty );
		$this->assertNotEmpty( $hashtags_not_empty );
		$this->assertEmpty( $hashtags_is_empty );

	}

	/**
	 * Test updating the state of a post for Liveblog
	 */
	function test_update_post_state() {

		// Create a test post
		$post  = $this->factory->post->create_and_get();
		$state = 'enable';

		// Additional request variables used in the liveblog_admin_settings_update action
		$request_vars = array(
			'state'                        => $state,
			'liveblog-key-template-name'   => 'list',
			'liveblog-key-template-format' => 'full',
			'liveblog-key-limit'           => '5',
		);

		// Save post state and return the metabox markup
		$meta_box = WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post->ID, $state, $request_vars );

		// TODO: Possibly test for something more specific
		$this->assertInternalType( 'string', $meta_box );
		$this->assertNotEmpty( $meta_box );

	}

	/**
	 * Integration test
	 * It makes a real HTTP request to the new and old endpoints and compares the results
	 *
	 * Check the endpoints for getting entries between two timestamps
	 * Runs as an unauthenticated user
	 *
	 */
	function test_compare_new_old_endpoints_get_entries() {

		$endpoint_config = $this->get_endpoint_config();

		// Set to a range that will return some entries
		$start_time        = '1455903120';
		$end_time          = '1455910058';

		$endpoint1 = $endpoint_config['base_endpoint_url1'] . '/' . $start_time . '/' . $end_time . '/';
		$endpoint2 = $endpoint_config['base_endpoint_url2'] . '/entries/' . $start_time . '/' . $end_time . '/';

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
		
	}

	/**
	 * Integration test
	 * It makes a real HTTP request to the new and old endpoints and compares the results
	 *
	 * Check to make sure an unauthenticated user cannot insert new entries
	 */
	function test_compare_new_old_endpoints_unauthenticated_user_cannot_insert() {
		//TODO: Look into using 

		$endpoint_config = $this->get_endpoint_config();

		$endpoint1 = $endpoint_config['base_endpoint_url1'] . '/crud';
		$endpoint2 = $endpoint_config['base_endpoint_url2'] . '/crud';

		$post_data_insert = array(
			'crud_action' => 'insert',
			'post_id' => $endpoint_config['post_id'],
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

	private function setup_entry_test_state( $number_of_entries = 1, $args = array() ) {
		$entries = $this->insert_entries( $number_of_entries, $args );

		$this->set_liveblog_vars();

		return $entries;
	}

	private function set_liveblog_vars() {
		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id          = 1;
	}

	private function insert_entries( $number_of_entries = 1, $args = array() ) {
		$entries = array();

		$user = $this->factory->user->create_and_get();
		$args['user'] = $user;

		for( $i = 0; $i < $number_of_entries; $i++ ) {
			$entries[] = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		}
		
		return $entries;
	}

	private function build_entry_args( $args = array() ) {
		$defaults = array( 'post_id' => 1, 'content' => 'Test Liveblog entry', );
		return array_merge( $defaults, $args );
	}

	/**
	 * Get settings used for HTTP request integration tests
	 */
	private function get_endpoint_config() {
		$host = 'wp.local';

		$endpoint_config = array(
			'post_id'           => '5',
		);

		// Base endpoint URLs used for HTTP requests
		$endpoint_config['base_endpoint_url1'] = 'http://' . $host . '/2016/02/13/' . $endpoint_config['post_id'] . '/liveblog';
		$endpoint_config['base_endpoint_url2'] = 'http://' . $host . '/wp-json/liveblog/v1/' . $endpoint_config['post_id'];

		return $endpoint_config;
	}

}