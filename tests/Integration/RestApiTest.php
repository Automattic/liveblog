<?php
/**
 * Tests for the Liveblog REST API.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WP_REST_Request;
use WPCOM_Liveblog;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_Entry_Extend_Feature_Authors;
use WPCOM_Liveblog_Entry_Extend_Feature_Hashtags;
use WPCOM_Liveblog_Rest_Api;

/**
 * REST API test case.
 */
final class RestApiTest extends TestCase {

	private const ENDPOINT_BASE = '/liveblog/v1';

	/**
	 * REST server instance.
	 *
	 * @var SpyRestServer
	 */
	protected SpyRestServer $server;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new SpyRestServer();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		parent::tear_down();

		global $wp_rest_server;
		$wp_rest_server = null;
	}

	/**
	 * Test does the class load correctly.
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::load()
	 */
	public function test_does_the_class_load_correctly(): void {
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

		// Lets test the existing endpoint base. Should return the same one as above.
		$existing_endpoint_base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();
		$this->assertSame( $base, $existing_endpoint_base );
	}

	/**
	 * Test does the non pretty endpoint build correctly.
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::build_endpoint_base()
	 */
	public function test_does_the_non_pretty_endpoint_build_correctly(): void {
		// If the endpoint is empty.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;
		$api_namespace                          = 'liveblog/v1';

		// Non Pretty Permalink Structure.
		$base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		// Assert we have a return.
		$this->assertNotNull( $base );

		// Now assert the return matches the expected return.
		$expected = home_url( '/?rest_route=/' . $api_namespace . '/' );

		$this->assertSame( $expected, $base );
	}

	/**
	 * Test does the pretty endpoint build correctly.
	 *
	 * @covers \WPCOM_Liveblog_Rest_Api::build_endpoint_base()
	 */
	public function test_does_the_pretty_endpoint_build_correctly(): void {
		// Empty the base so we can generate one.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;

		// Lets define the known API namespace.
		$api_namespace = 'liveblog/v1';

		// Lets set a pretty URL Permalink Structure.
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		// Now lest fire the method again and see what we get as the method should now detect the new permalink structure and return the pretty endpoint.
		$base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		// Lets make sure something is returned.
		$this->assertNotNull( $base );

		// Now assert the return matches the expected return.
		$expected = home_url( '/' . rest_get_url_prefix() . '/' . $api_namespace . '/' );

		$this->assertSame( $expected, $base );
	}

	/**
	 * Test for the expected array structure when getting entries.
	 */
	public function test_get_entries_by_time_not_empty_response_structure(): void {
		$this->setup_entry_test_state();

		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertArrayHasKey( 'entries', $entries );
		$this->assertArrayHasKey( 'latest_timestamp', $entries );
	}

	/**
	 * Test for a non-empty response when getting entries.
	 */
	public function test_get_entries_by_time_not_empty(): void {
		$this->setup_entry_test_state();

		// A time window with entries.
		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertNotEmpty( $entries['entries'] );
		$this->assertNotNull( $entries['latest_timestamp'] );
	}

	/**
	 * Test for an empty response when getting entries.
	 */
	public function test_get_entries_by_time_is_empty(): void {
		$this->setup_entry_test_state();

		// A time window without entries.
		$start_time = strtotime( '-2 hour' );
		$end_time   = strtotime( '-1 hour' );

		$entries = WPCOM_Liveblog::get_entries_by_time( $start_time, $end_time );

		$this->assertEmpty( $entries['entries'] );
		$this->assertNull( $entries['latest_timestamp'] );
	}

	/**
	 * Test for valid return values when getting a single entry.
	 */
	public function test_get_single_entry_not_empty(): void {
		$new_entry = $this->setup_entry_test_state();

		$entry = WPCOM_Liveblog::get_single_entry( $new_entry[0]->get_id() );

		$this->assertNotEmpty( $entry['entries'] );
		$this->assertIsInt( $entry['index'] );
		$this->assertIsInt( $entry['nextTimestamp'] );
		$this->assertIsInt( $entry['previousTimestamp'] );
	}

	/**
	 * Test for valid return values when getting a single entry that doesn't exist.
	 */
	public function test_get_single_entry_is_empty(): void {
		$this->setup_entry_test_state();

		$entry = WPCOM_Liveblog::get_single_entry( 1010 );

		$this->assertEmpty( $entry['entries'] );
	}

	/**
	 * Test for a non-empty response when getting entries for lazyloading.
	 */
	public function test_get_lazyload_entries_by_time_not_empty(): void {
		// Create multiple entries.
		$this->setup_entry_test_state( 3 );

		// A time window with entries.
		$max_timestamp = strtotime( '+1 day' );
		$min_timestamp = 0;

		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		$this->assertNotEmpty( $entries['entries'] );
		$this->assertIsInt( $entries['index'] );
	}

	/**
	 * Test for an empty response when getting entries for lazyloading.
	 */
	public function test_get_lazyload_entries_by_time_is_empty(): void {
		$this->setup_entry_test_state();

		// A time window without entries.
		$max_timestamp = strtotime( '-1 day' );
		$min_timestamp = 0;

		$entries = WPCOM_Liveblog::get_lazyload_entries( $max_timestamp, $min_timestamp );

		$this->assertEmpty( $entries['entries'] );
		$this->assertIsInt( $entries['index'] );
	}

	/**
	 * Test the insert CRUD action.
	 */
	public function test_crud_action_insert(): void {
		$user  = self::factory()->user->create_and_get();
		$args  = array( 'user' => $user );
		$entry = WPCOM_Liveblog::do_crud_entry( 'insert', $this->build_entry_args( $args ) );

		$this->assertIsArray( $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] ); // Should this always be null?
	}

	/**
	 * Test the update CRUD action.
	 */
	public function test_crud_action_update(): void {
		$new_entry = $this->setup_entry_test_state();
		$args      = array(
			'entry_id' => $new_entry[0]->get_id(),
			'content'  => 'Updated Test Liveblog entry',
		);
		$entry     = WPCOM_Liveblog::do_crud_entry( 'update', $this->build_entry_args( $args ) );

		$this->assertIsArray( $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] );
	}

	/**
	 * Test the delete CRUD action.
	 */
	public function test_crud_action_delete(): void {
		// First create an entry.
		$new_entry = $this->setup_entry_test_state();

		$this->assertIsArray( $new_entry );
		$this->assertInstanceOf( WPCOM_Liveblog_Entry::class, $new_entry[0] );

		$new_entry_id = $new_entry[0]->get_id();

		// Then delete it.
		$args = array( 'entry_id' => $new_entry_id );
		WPCOM_Liveblog::do_crud_entry( 'delete', $this->build_entry_args( $args ) );

		// Check that it was sent to the trash.
		$deleted_entry = get_comment( $new_entry_id );

		$this->assertEquals( 'trash', $deleted_entry->comment_approved );
	}

	/**
	 * Test the delete_key CRUD action.
	 */
	public function test_crud_action_delete_key(): void {
		// First create an entry with a key.
		$new_entry    = $this->setup_entry_test_state( 1, array( 'content' => 'Test Liveblog entry with /key' ) );
		$new_entry_id = $new_entry[0]->get_id();

		// Then delete the key.
		$args  = array( 'entry_id' => $new_entry_id );
		$entry = WPCOM_Liveblog::do_crud_entry( 'delete_key', $this->build_entry_args( $args ) );

		// $entry will be an instance of WP_Error if the entry didn't contain a key or there was another error.
		$this->assertNotInstanceOf( 'WP_Error', $entry );
	}

	/**
	 * Test getting a preview of an entry.
	 */
	public function test_preview_entry(): void {
		// Get entry preview.
		$preview = WPCOM_Liveblog::format_preview_entry( 'Test Liveblog entry with /key' );

		$this->assertIsArray( $preview );
		$this->assertNotEmpty( $preview['html'] );
	}

	/**
	 * Test getting list of authors from a string.
	 */
	public function test_get_authors(): void {
		// Get a list of authors.
		$liveblog_authors = new WPCOM_Liveblog_Entry_Extend_Feature_Authors();

		$authors_not_empty = $liveblog_authors->get_authors( 'adm' ); // Should return admin.
		$authors_is_empty  = $liveblog_authors->get_authors( 'fakeauthor' ); // Non-existent user.

		$this->assertIsArray( $authors_not_empty );
		$this->assertIsArray( $authors_is_empty );
		$this->assertNotEmpty( $authors_not_empty );
		$this->assertEmpty( $authors_is_empty );
	}

	/**
	 * Test getting list of hashtags from a string.
	 */
	public function test_get_hashtags(): void {
		// Get a list of hashtags.
		$liveblog_hashtags = new WPCOM_Liveblog_Entry_Extend_Feature_Hashtags();

		// Create a temporary hashtag.
		self::factory()->term->create(
			array(
				'name'     => 'coolhashtag',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag',
			)
		);

		$hashtags_not_empty = $liveblog_hashtags->get_hashtag_terms( 'cool' ); // Should return coolhashtag.
		$hashtags_is_empty  = $liveblog_hashtags->get_hashtag_terms( 'fakehashtag' ); // Non-existent hashtag.

		$this->assertIsArray( $hashtags_not_empty );
		$this->assertIsArray( $hashtags_is_empty );
		$this->assertNotEmpty( $hashtags_not_empty );
		$this->assertEmpty( $hashtags_is_empty );
	}

	/**
	 * Test updating the state of a post for Liveblog.
	 */
	public function test_update_post_state(): void {
		// Create a test post.
		$post  = self::factory()->post->create_and_get();
		$state = 'enable';

		// Additional request variables used in the liveblog_admin_settings_update action.
		$request_vars = array(
			'state'                        => $state,
			'liveblog-key-template-name'   => 'list',
			'liveblog-key-template-format' => 'full',
			'liveblog-key-limit'           => '5',
		);

		// Save post state and return the metabox markup.
		$meta_box = WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post->ID, $state, $request_vars );

		// TODO: Possibly test for something more specific.
		$this->assertIsString( $meta_box );
		$this->assertNotEmpty( $meta_box );
	}

	/**
	 * Integration test - Test accessing the get entries endpoint.
	 */
	public function test_endpoint_get_entries(): void {
		$post_id = $this->create_liveblog_post();
		$this->insert_entries( 1, array( 'post_id' => $post_id ) );

		// A time window with entries.
		$start_time = strtotime( '-1 hour' );
		$end_time   = strtotime( '+1 hour' );

		// Try to access the endpoint.
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/' . $post_id . '/entries/' . $start_time . '/' . $end_time );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry.
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test - Test accessing the crud endpoint with an insert action.
	 */
	public function test_endpoint_crud_action(): void {
		// Create an author and set as the current user.
		$this->set_author_user();

		// Create a post.
		self::factory()->post->create();

		// The POST data to insert.
		$post_vars = $this->build_entry_args( array( 'crud_action' => 'insert' ) );

		// Try to access the endpoint and insert an entry.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/1/crud' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $post_vars ) );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain the newly inserted entry.
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test - Test accessing the lazyload endpoint.
	 */
	public function test_endpoint_lazyload(): void {
		$post_id = $this->create_liveblog_post();
		$this->insert_entries( 1, array( 'post_id' => $post_id ) );

		// A time window with entries.
		$max_timestamp = strtotime( '+1 day' );
		$min_timestamp = 0;

		// Try to access the endpoint.
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/' . $post_id . '/lazyload/' . $max_timestamp . '/' . $min_timestamp );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry.
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test - Test accessing the get single entry endpoint.
	 */
	public function test_endpoint_get_single_entry(): void {
		$post_id     = $this->create_liveblog_post();
		$new_entries = $this->insert_entries( 1, array( 'post_id' => $post_id ) );

		// Try to access the endpoint.
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/' . $post_id . '/entry/' . $new_entries[0]->get_id() );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		$entries = $response->get_data();

		// The array should contain 1 entry.
		$this->assertCount( 1, $entries['entries'] );
	}

	/**
	 * Integration test - Test accessing the entry preview endpoint.
	 */
	public function test_endpoint_entry_preview(): void {
		// Create an author and set as the current user.
		$this->set_author_user();

		// The POST data to preview.
		$post_vars = array( 'entry_content' => 'Test Liveblog entry with /key' );

		// Try to access the endpoint.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/1/preview' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		// The result should be an array with an "html" key.
		$this->assertArrayHasKey( 'html', $response->get_data() );
	}

	/**
	 * Integration test - Test accessing the get authors endpoint.
	 */
	public function test_endpoint_get_authors(): void {
		// Create an author and set as the current user.
		$this->set_author_user();

		// Create 2 authors.
		self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Josh Smith',
			)
		);

		self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'John Doe',
			)
		);

		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/authors/jo' );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		// The array should contain 2 authors.
		$this->assertCount( 2, $response->get_data() );
	}

	/**
	 * Integration test - Test accessing the get hashtags endpoint.
	 */
	public function test_endpoint_get_hashtags(): void {
		// Create an author and set as the current user.
		$this->set_author_user();

		// Create 2 hashtags.
		self::factory()->term->create(
			array(
				'name'     => 'coolhashtag',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag',
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'coolhashtag2',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag2',
			)
		);

		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/hashtags/cool' );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		// The array should contain 2 authors.
		$this->assertCount( 2, $response->get_data() );
	}

	/**
	 * Integration test - Test accessing the update post state endpoint.
	 */
	public function test_endpoint_update_post_state(): void {
		// Create an author and set as the current user.
		$this->set_author_user();

		// Create a post.
		$post = self::factory()->post->create_and_get();

		// The POST data.
		$post_vars = array(
			'state'           => 'enable',
			'template_name'   => 'list',
			'template_format' => 'full',
			'limit'           => '5',
		);

		// Try to access the endpoint.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post->ID . '/post_state' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert successful response.
		$this->assertEquals( 200, $response->get_status() );

		// The result should be an non-empty string.
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * Integration test - Test accessing the update post state endpoint when not logged in as an author. Should be forbidden.
	 */
	public function test_endpoint_update_post_state_forbidden(): void {
		// Create a post.
		$post = self::factory()->post->create_and_get();

		// The POST data.
		$post_vars = array(
			'state'           => 'enable',
			'template_name'   => 'list',
			'template_format' => 'full',
			'limit'           => '5',
		);

		// Try to access the endpoint to set the post as a liveblog.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post->ID . '/post_state' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert forbidden response.
		$this->assertTrue( $response->get_status() === 403 || $response->get_status() === 401 );
	}

	/**
	 * Integration test - Test inserting an entry when not logged in as an author. Should be forbidden.
	 */
	public function test_endpoint_crud_insert_forbidden(): void {
		// Create a liveblog post.
		$post_id = $this->create_liveblog_post();

		// The POST data to insert.
		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		// Try to access the endpoint and insert an entry.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $post_vars );
		$response = $this->server->dispatch( $request );

		// Assert forbidden response.
		$this->assertTrue( $response->get_status() === 403 || $response->get_status() === 401 );
	}

	/**
	 * Integration test - Test for a proper 404 not found status code when requesting a bad endpoint URL.
	 */
	public function test_endpoint_not_found(): void {
		// Try to access the endpoint.
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/bad/url' );
		$response = $this->server->dispatch( $request );

		// Assert not found response.
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Integration test - Test accessing the entry preview endpoint without the required post data.
	 */
	public function test_endpoint_entry_preview_bad_request(): void {
		// The "entry_content" POST data is required for the preview endpoint.
		// Lets leave it out and expect a 400 bad request response.

		// Create a liveblog post.
		$post_id = $this->create_liveblog_post();

		// Try to access the endpoint.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/preview' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$response = $this->server->dispatch( $request );

		// Assert bad request response.
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Setup entry test state.
	 *
	 * @param int   $number_of_entries Number of entries to create.
	 * @param array $args              Arguments.
	 * @return array Array of entries.
	 */
	private function setup_entry_test_state( int $number_of_entries = 1, array $args = array() ): array {
		$entries = $this->insert_entries( $number_of_entries, $args );

		$this->set_liveblog_vars();

		return $entries;
	}

	/**
	 * Set liveblog vars.
	 */
	private function set_liveblog_vars(): void {
		WPCOM_Liveblog::$is_rest_api_call = true;
		WPCOM_Liveblog::$post_id          = 1;
	}

	/**
	 * Insert entries.
	 *
	 * @param int   $number_of_entries Number of entries to create.
	 * @param array $args              Arguments.
	 * @return array Array of entries.
	 */
	private function insert_entries( int $number_of_entries = 1, array $args = array() ): array {
		$entries = array();

		$user         = self::factory()->user->create_and_get();
		$args['user'] = $user;

		for ( $i = 0; $i < $number_of_entries; $i++ ) {
			$entries[] = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		}

		return $entries;
	}

	/**
	 * Build entry args.
	 *
	 * @param array $args Arguments.
	 * @return array Merged arguments.
	 */
	private function build_entry_args( array $args = array() ): array {
		$defaults = array(
			'post_id' => 1,
			'content' => 'Test Liveblog entry',
		);
		return array_merge( $defaults, $args );
	}

	/**
	 * Create and author and set it as the current user.
	 */
	private function set_author_user(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		wp_set_current_user( $author_id );
	}

	/**
	 * Create a new post and make it a liveblog.
	 *
	 * @param array $post_args Optional post arguments to override the defaults (e.g. post_status).
	 * @return int The ID of the new liveblog post.
	 */
	private function create_liveblog_post( array $post_args = array() ): int {
		// Create a new post.
		$post_id = self::factory()->post->create( $post_args );

		// Make the new post a liveblog.
		$state        = 'enable';
		$request_vars = array( 'state' => $state );
		WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, $state, $request_vars );

		return $post_id;
	}

	/**
	 * Data provider for non-public liveblog posts.
	 *
	 * Each entry is a post_status that should not expose liveblog entries to anonymous
	 * callers of the public read endpoints.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function non_public_post_statuses(): array {
		return array(
			'draft'   => array( 'draft' ),
			'private' => array( 'private' ),
			'future'  => array( 'future' ),
			'trash'   => array( 'trash' ),
		);
	}

	/**
	 * Anonymous requests must not retrieve liveblog entries for non-public posts.
	 *
	 * Covers HackerOne reports #3683538 and #3615321 (CWE-639, IDOR).
	 *
	 * @dataProvider non_public_post_statuses
	 *
	 * @param string $post_status Post status to test.
	 */
	public function test_public_read_endpoints_deny_anonymous_for_non_public_posts( string $post_status ): void {
		// Create as a published liveblog post with entries, then transition to the target
		// status. This avoids WordPress rewriting the status during insert (e.g. future
		// posts with no future date, or liveblog enablement refusing non-publish states).
		$post_id = $this->create_liveblog_post();
		$entry   = $this->insert_entries( 1, array( 'post_id' => $post_id ) )[0];

		$update = array(
			'ID'          => $post_id,
			'post_status' => $post_status,
		);
		if ( 'future' === $post_status ) {
			$update['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
			$update['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		}
		wp_update_post( $update );

		foreach ( $this->public_read_urls_for_post( $post_id, $entry->get_id() ) as $url ) {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', $url ) );

			$this->assertEquals(
				404,
				$response->get_status(),
				sprintf( 'Expected 404 on %s for post_status=%s', $url, $post_status )
			);
		}
	}

	/**
	 * Anonymous requests for a post that does not exist must receive a 404.
	 */
	public function test_public_read_endpoints_deny_anonymous_for_missing_post(): void {
		$missing_post_id = 999999;

		foreach ( $this->public_read_urls_for_post( $missing_post_id, 1 ) as $url ) {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', $url ) );
			$this->assertEquals( 404, $response->get_status(), sprintf( 'Expected 404 on %s', $url ) );
		}
	}

	/**
	 * Anonymous requests for a published post that is not a liveblog must receive a 404.
	 *
	 * This prevents the endpoints from being used as an oracle to distinguish liveblog
	 * posts from regular posts.
	 */
	public function test_public_read_endpoints_deny_anonymous_for_non_liveblog_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		foreach ( $this->public_read_urls_for_post( $post_id, 1 ) as $url ) {
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', $url ) );
			$this->assertEquals( 404, $response->get_status(), sprintf( 'Expected 404 on %s', $url ) );
		}
	}

	/**
	 * An editor user can read liveblog entries on a draft post they have permission to read.
	 */
	public function test_public_read_endpoints_allow_editor_on_draft_post(): void {
		$post_id = $this->create_liveblog_post();
		$entry   = $this->insert_entries( 1, array( 'post_id' => $post_id ) )[0];
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$request  = new WP_REST_Request(
			'GET',
			self::ENDPOINT_BASE . '/' . $post_id . '/entries/' . strtotime( '-1 hour' ) . '/' . strtotime( '+1 hour' )
		);
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['entries'] );
	}

	/**
	 * The liveblog_rest_read_permission filter can override the default denial.
	 *
	 * Useful for headless setups that want to expose entries for drafts without granting
	 * `read_post` to the anonymous caller.
	 */
	public function test_liveblog_rest_read_permission_filter_can_allow(): void {
		$post_id = $this->create_liveblog_post();
		$this->insert_entries( 1, array( 'post_id' => $post_id ) );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		add_filter( 'liveblog_rest_read_permission', '__return_true' );

		$url      = self::ENDPOINT_BASE . '/' . $post_id . '/entries/' . strtotime( '-1 hour' ) . '/' . strtotime( '+1 hour' );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', $url ) );

		remove_filter( 'liveblog_rest_read_permission', '__return_true' );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Build the list of public read endpoint URLs that accept a post_id for testing.
	 *
	 * @param int $post_id  Post ID to embed.
	 * @param int $entry_id Entry ID to embed for routes that accept one.
	 * @return string[]
	 */
	private function public_read_urls_for_post( int $post_id, int $entry_id ): array {
		$base    = self::ENDPOINT_BASE . '/' . $post_id;
		$start   = strtotime( '-1 hour' );
		$end     = strtotime( '+1 hour' );
		$max_ts  = strtotime( '+1 day' );
		$min_ts  = 0;
		$last_id = 0;

		return array(
			$base . '/entries/' . $start . '/' . $end,
			$base . '/lazyload/' . $max_ts . '/' . $min_ts,
			$base . '/entry/' . $entry_id,
			$base . '/get-entries/1/' . $last_id,
			$base . '/get-key-events/' . $last_id,
			$base . '/jump-to-key-event/' . $entry_id . '/' . $last_id,
		);
	}
}
