<?php

class Test_REST_API extends WP_UnitTestCase {

	const ENDPOINT_BASE = '/liveblog/v1';

	protected $server;

	public function setUp() {
		parent::setUp();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new WP_Test_Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tearDown() {
		parent::tearDown();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * test_does_the_non_pretty_endpoint_build_correctly
	 * @package WPCOM_Liveblog_Rest_Api
	 * @version 1.0
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::load()
	 */
	public function test_does_the_class_load_correctly() {

		WPCOM_Liveblog_Rest_Api::load();
		$base = WPCOM_Liveblog_Rest_Api::$endpoint_base;

		$this->assertNotNull( $base );
		$this->assertTrue( is_string( $base ) );

		$hook_name = 'rest_api_init';
		global $wp_filter;

		$collection = $wp_filter[ $hook_name ];

		$test_array = array();

		foreach ( $collection as $value ) {
			$test_array = array_merge( $test_array, $value );
		}

		$this->assertArrayHasKey( 'WPCOM_Liveblog_Rest_Api::register_routes', $test_array );

		//Lets test the existing endpoint base. Should return the same one as above.
		$existing_endpoint_base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();
		$this->assertSame( $base, $existing_endpoint_base );

	}


	/**
	 * test_does_the_non_pretty_endpoint_build_correctly
	 * @package WPCOM_Liveblog_Rest_Api
	 * @version 1.0
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::build_endpoint_base()
	 */
	public function test_does_the_non_pretty_endpoint_build_correctly() {

		//If the endpoint is empty
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;
		$api_namespace                          = 'liveblog/v1';

		//Non Pretty Permalink Structure
		$base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		//Assert we have a return
		$this->assertNotNull( $base );

		//Now assert the return matches the expected return.
		$expected = home_url( '/?rest_route=/' . $api_namespace . '/' );

		$this->assertSame( $expected, $base );

	}

	/**
	 * test_does_the_pretty_endpoint_build_correctly
	 * @package WPCOM_Liveblog_Rest_Api
	 * @version 1.0
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::build_endpoint_base()
	 */
	public function test_does_the_pretty_endpoint_build_correctly() {

		//Empty the base so we can generate one,
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;

		//Lets define the known API namespace.
		$api_namespace = 'liveblog/v1';

		//Lets set a pretty URL Permalink Structure
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		//Now lest fire the method again and see what we get as the method should now detect the new permalink structure and return the pretty endpoint.
		$base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		//Lets make sure something is returned
		$this->assertNotNull( $base );

		//Now assert the return matches the expected return.
		$expected = home_url( '/' . rest_get_url_prefix() . '/' . $api_namespace . '/' );

		$this->assertSame( $expected, $base );

	}

	/**
	 * Test for the expected array structure when getting entries
	 */
	public function test_get_entries_by_time_not_empty_response_structure() {

		$this->setup_entry_test_state();

		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertArrayHasKey( 'entries', $entries );
		$this->assertArrayHasKey( 'latest_timestamp', $entries );

	}

	/**
	 * Test for a non-empty response when getting entries
	 */
	public function test_get_entries_by_time_not_empty() {

		$this->setup_entry_test_state();

		// A time window with entries
		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertNotEmpty( $entries['entries'] );
		$this->assertNotNull( $entries['latest_timestamp'] );

	}

	/**
	 * Test for an empty response when getting entries
	 */
	public function test_get_entries_by_time_is_empty() {

		$this->setup_entry_test_state();

		// A time window without entries
		$start_time = strtotime( '-2 hour' );
		$end_time   = strtotime( '-1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertEmpty( $entries['entries'] );
		$this->assertNull( $entries['latest_timestamp'] );

	}

	/**
	 * Test for valid return values when getting a single entry
	 */
	public function test_get_single_entry_not_empty() {

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
	public function test_get_single_entry_is_empty() {

		$this->setup_entry_test_state();

		$entry = WPCOM_Liveblog::get_single_entry( 1010 );

		$this->assertEmpty( $entry['entries'] );

	}

	/**
	 * Test for a non-empty response when getting entries for lazyloading
	 */
	public function test_get_lazyload_entries_by_time_not_empty() {

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
	public function test_get_lazyload_entries_by_time_is_empty() {

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
	public function test_crud_action_insert() {

		$user  = $this->factory->user->create_and_get();
		$args  = array( 'user' => $user );
		$entry = WPCOM_Liveblog::do_crud_entry( 'insert', $this->build_entry_args( $args ) );

		$this->assertInternalType( 'array', $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] ); // Should this always be null?

	}

	/**
	 * Test the update CRUD action
	 */
	public function test_crud_action_update() {

		$new_entry = $this->setup_entry_test_state();
		$args      = array(
			'entry_id' => $new_entry[0]->get_id(),
			'content'  => 'Updated Test Liveblog entry',
		);
		$entry     = WPCOM_Liveblog::do_crud_entry( 'update', $this->build_entry_args( $args ) );

		$this->assertInternalType( 'array', $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] );

	}

	/**
	 * Test the delete CRUD action
	 */
	public function test_crud_action_delete() {

		// First create an entry
		$new_entry = $this->setup_entry_test_state();

		$this->assertInternalType( 'array', $new_entry );
		$this->assertInstanceOf( 'WPCOM_Liveblog_Entry', $new_entry[0] );

		$new_entry_id = $new_entry[0]->get_id();

		// Then delete it
		$args = array( 'entry_id' => $new_entry_id );
		WPCOM_Liveblog::do_crud_entry( 'delete', $this->build_entry_args( $args ) );

		// Check that it was sent to the trash
		$deleted_entry = get_comment( $new_entry_id );

		$this->assertEquals( 'trash', $deleted_entry->comment_approved );

	}

	/**
	 * Test the delete_key CRUD action
	 */
	public function test_crud_action_delete_key() {

		// First create an entry with a key
		$new_entry    = $this->setup_entry_test_state( 1, array( 'content' => 'Test Liveblog entry with /key' ) );
		$new_entry_id = $new_entry[0]->get_id();

		// Then delete the key
		$args  = array( 'entry_id' => $new_entry_id );
		$entry = WPCOM_Liveblog::do_crud_entry( 'delete_key', $this->build_entry_args( $args ) );

		// $entry will be an instance of WP_Error if the entry didn't contain a key or there was another error
		$this->assertNotInstanceOf( 'WP_Error', $entry );

	}

	/**
	 * Test getting a preview of an entry
	 */
	public function test_preview_entry() {

		// Get entry preview
		$preview = WPCOM_Liveblog::format_preview_entry( 'Test Liveblog entry with /key' );

		$this->assertInternalType( 'array', $preview );
		$this->assertNotEmpty( $preview['html'] );

	}

	/**
	 * Test getting list of authors from a string
	 */
	public function test_get_authors() {

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
	public function test_get_hashtags() {

		// Get a list of hashtags
		$liveblog_hashtags = new WPCOM_Liveblog_Entry_Extend_Feature_Hashtags();

		// Create a temporary hashtag
		$this->factory->term->create(
			array(
				'name'     => 'coolhashtag',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag',
			)
		);

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
	public function test_update_post_state() {

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
	 * Test accessing the get entries endpoint
	 */
	public function test_endpoint_get_entries() {
		// Insert 1 entry
		$this->insert_entries();

		// A time window with entries
		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		// Try to access the endpoint
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/1/entries/' . $start_time . '/' . $end_time );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test
	 * Test accessing the crud endpoint with an insert action
	 */
	public function test_endpoint_crud_action() {

		// Create an author and set as the current user
		$this->set_author_user();

		// Create a post
		$this->factory->post->create();

		// The POST data to insert
		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
			)
		);

		// Try to access the endpoint and insert an entry
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/1/crud' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $post_vars ) );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain the newly inserted entry
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test
	 * Test accessing the lazyload endpoint
	 */
	public function test_endpoint_lazyload() {
		// Insert 1 entry
		$this->insert_entries();

		// A time window with entries
		$max_timestamp = strtotime( '+1 day' );
		$min_timestamp = 0;

		// Try to access the endpoint
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/1/lazyload/' . $max_timestamp . '/' . $min_timestamp );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry
		$this->assertCount( 1, $entries['entries'] );

	}

	/**
	 * Integration test
	 * Test accessing the get single entry endpoint
	 */
	public function test_endpoint_get_single_entry() {
		// Insert 1 entry
		$new_entries = $this->insert_entries();

		// Try to access the endpoint
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/1/entry/' . $new_entries[0]->get_id() );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test
	 * Test accessing the entry preview endpoint
	 */
	public function test_endpoint_entry_preview() {

		// The POST data to preview
		$post_vars = array( 'entry_content' => 'Test Liveblog entry with /key' );

		// Try to access the endpoint
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/1/preview' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		// The result should be an array with an "html" key
		$this->assertArrayHasKey( 'html', $response->get_data() );

	}

	/**
	 * Integration test
	 * Test accessing the get authors endpoint
	 */
	public function test_endpoint_get_authors() {

		// Create 2 authors
		$this->factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Josh Smith',
			)
		);

		$this->factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'John Doe',
			)
		);

		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/authors/jo' );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		// The array should contain 2 authors
		$this->assertCount( 2, $response->get_data() );

	}

	/**
	 * Integration test
	 * Test accessing the get hashtags endpoint
	 */
	public function test_endpoint_get_hashtags() {

		// Create 2 hashtags
		$this->factory->term->create(
			array(
				'name'     => 'coolhashtag',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag',
			)
		);
		$this->factory->term->create(
			array(
				'name'     => 'coolhashtag2',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag2',
			)
		);

		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/hashtags/cool' );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		// The array should contain 2 authors
		$this->assertCount( 2, $response->get_data() );

	}

	/**
	 * Integration test
	 * Test accessing the update post state endpoint
	 */
	public function test_endpoint_update_post_state() {

		// Create an author and set as the current user
		$this->set_author_user();

		// Create a post
		$post = $this->factory->post->create_and_get();

		// The POST data
		$post_vars = array(
			'state'           => 'enable',
			'template_name'   => 'list',
			'template_format' => 'full',
			'limit'           => '5',
		);

		// Try to access the endpoint
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post->ID . '/post_state' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert successful response
		$this->assertEquals( 200, $response->get_status() );

		// The result should be an non-empty string
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * Integration test
	 * Test accessing the update post state endpoint when not logged in as an author. Should be forbidden.
	 */
	public function test_endpoint_update_post_state_forbidden() {

		// Create a post
		$post = $this->factory->post->create_and_get();

		// The POST data
		$post_vars = array(
			'state'           => 'enable',
			'template_name'   => 'list',
			'template_format' => 'full',
			'limit'           => '5',
		);

		// Try to access the endpoint to set the post as a liveblog
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post->ID . '/post_state' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert forbidden response
		$this->assertTrue( $response->get_status() === 403 || $response->get_status() === 401 );

	}

	/**
	 * Integration test
	 * Test inserting an entry when not logged in as an author. Should be forbidden.
	 */
	public function test_endpoint_crud_insert_forbidden() {

		// Create a liveblog post
		$post_id = $this->create_liveblog_post();

		// The POST data to insert
		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		// Try to access the endpoint and insert an entry
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert forbidden response
		$this->assertTrue( $response->get_status() === 403 || $response->get_status() === 401 );

	}

	/**
	 * Integration test
	 * Test for a proper 404 not found status code when requesting a bad endpoint URL
	 */
	public function test_endpoint_not_found() {

		// Try to access the endpoint
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/bad/url' );
		$response = $this->server->dispatch( $request );

		// Assert not found response
		$this->assertEquals( 404, $response->get_status() );

	}

	/**
	 * Integration test
	 * Test accessing the entry preview endpoint without the required post data
	 */
	public function test_endpoint_entry_preview_bad_request() {

		// The "entry_content" POST data is required for the preview endpoint.
		// Lets leave it out and expect a 400 bad request response

		// Create a liveblog post
		$post_id = $this->create_liveblog_post();

		// Try to access the endpoint
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/preview' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$response = $this->server->dispatch( $request );

		// Assert bad request response
		$this->assertEquals( 400, $response->get_status() );

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

		$user         = $this->factory->user->create_and_get();
		$args['user'] = $user;

		for ( $i = 0; $i < $number_of_entries; $i++ ) {
			$entries[] = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		}

		return $entries;
	}

	private function build_entry_args( $args = array() ) {
		$defaults = array(
			'post_id' => 1,
			'content' => 'Test Liveblog entry',
		);
		return array_merge( $defaults, $args );
	}

	/**
	 * Create and author and set it as the current user
	 */
	private function set_author_user() {
		$author_id = $this->factory->user->create(
			array(
				'role' => 'author',
			)
		);

		wp_set_current_user( $author_id );
	}

	/**
	 * Create a new post and make it a liveblog
	 *
	 * @return int The ID of the new liveblog post
	 */
	private function create_liveblog_post() {
		// Create a new post
		$post_id = $this->factory->post->create();

		// Make the new post a liveblog
		$state        = 'enable';
		$request_vars = array( 'state' => $state );
		WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, $state, $request_vars );

		return $post_id;
	}

}
