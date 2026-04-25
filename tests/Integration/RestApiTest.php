<?php
/**
 * Tests for the Liveblog REST API.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Filter\AuthorFilter;
use Automattic\Liveblog\Application\Filter\HashtagFilter;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Infrastructure\DI\Container;
use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;
use WP_REST_Request;

/**
 * REST API test case.
 */
final class RestApiTest extends IntegrationTestCase {

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
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test does the class load correctly.
	 *
	 * @covers \Automattic\Liveblog\Infrastructure\WordPress\RestApiController::init()
	 */
	public function test_does_the_class_load_correctly(): void {
		// Reset the static endpoint base.
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, '' );

		$base = RestApiController::build_endpoint_base();

		$this->assertNotNull( $base );
		$this->assertTrue( is_string( $base ) );

		// Verify register_routes hook is registered (should be registered via PluginBootstrapper).
		$hook_name = 'rest_api_init';
		global $wp_filter;

		$collection = $wp_filter[ $hook_name ] ?? array();

		$hook_registered = false;
		foreach ( $collection as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && 'register_routes' === $callback['function'][1] ) {
					$hook_registered = true;
					break 2;
				}
			}
		}

		$this->assertTrue( $hook_registered, 'register_routes should be hooked to rest_api_init' );

		// Test the existing endpoint base. Should return the same one as above.
		$existing_endpoint_base = RestApiController::build_endpoint_base();
		$this->assertSame( $base, $existing_endpoint_base );
	}

	/**
	 * Test does the non pretty endpoint build correctly.
	 *
	 * @covers \Automattic\Liveblog\Infrastructure\WordPress\RestApiController::build_endpoint_base()
	 */
	public function test_does_the_non_pretty_endpoint_build_correctly(): void {
		// Reset the static endpoint base.
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, '' );

		$api_namespace = 'liveblog/v1';

		// Non Pretty Permalink Structure (default).
		$base = RestApiController::build_endpoint_base();

		// Assert we have a return.
		$this->assertNotNull( $base );

		// Now assert the return matches the expected return.
		$expected = home_url( '/?rest_route=/' . $api_namespace . '/' );

		$this->assertSame( $expected, $base );
	}

	/**
	 * Test does the pretty endpoint build correctly.
	 *
	 * @covers \Automattic\Liveblog\Infrastructure\WordPress\RestApiController::build_endpoint_base()
	 */
	public function test_does_the_pretty_endpoint_build_correctly(): void {
		// Reset the static endpoint base.
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, '' );

		// Define the known API namespace.
		$api_namespace = 'liveblog/v1';

		// Set a pretty URL Permalink Structure.
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		// Build endpoint - should detect the new permalink structure and return the pretty endpoint.
		$base = RestApiController::build_endpoint_base();

		// Make sure something is returned.
		$this->assertNotNull( $base );

		// Assert the return matches the expected return.
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

		$request_router = Container::instance()->request_router();
		$entries        = $request_router->get_entries_between( 1, $start_time, $end_time );

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

		$request_router = Container::instance()->request_router();
		$entries        = $request_router->get_entries_between( 1, $start_time, $end_time );

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

		$request_router = Container::instance()->request_router();
		$entries        = $request_router->get_entries_between( 1, $start_time, $end_time );

		$this->assertEmpty( $entries['entries'] );
		$this->assertNull( $entries['latest_timestamp'] );
	}

	/**
	 * Test for valid return values when getting a single entry.
	 */
	public function test_get_single_entry_not_empty(): void {
		$new_entry = $this->setup_entry_test_state();

		$request_router = Container::instance()->request_router();
		$entry          = $request_router->get_single_entry( 1, $new_entry[0]->id()->to_int() );

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

		$request_router = Container::instance()->request_router();
		$entry          = $request_router->get_single_entry( 1, 1010 );

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

		$request_router = Container::instance()->request_router();
		$entries        = $request_router->get_lazyload_entries( 1, $max_timestamp, $min_timestamp );

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

		$request_router = Container::instance()->request_router();
		$entries        = $request_router->get_lazyload_entries( 1, $max_timestamp, $min_timestamp );

		$this->assertEmpty( $entries['entries'] );
		$this->assertIsInt( $entries['index'] );
	}

	/**
	 * Test the insert CRUD action.
	 */
	public function test_crud_action_insert(): void {
		$user = self::factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		$entry_operations = Container::instance()->entry_operations();
		$entry            = $entry_operations->do_crud( 'insert', $this->build_entry_args(), $user );

		$this->assertIsArray( $entry );
		$this->assertNotEmpty( $entry['entries'] );
		$this->assertNull( $entry['latest_timestamp'] ); // Should this always be null?
	}

	/**
	 * Test the update CRUD action.
	 */
	public function test_crud_action_update(): void {
		$new_entry = $this->setup_entry_test_state();
		$user      = wp_get_current_user();
		$args      = array(
			'entry_id' => $new_entry[0]->id()->to_int(),
			'content'  => 'Updated Test Liveblog entry',
		);

		$entry_operations = Container::instance()->entry_operations();
		$entry            = $entry_operations->do_crud( 'update', $this->build_entry_args( $args ), $user );

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
		$this->assertInstanceOf( Entry::class, $new_entry[0] );

		$new_entry_id = $new_entry[0]->id()->to_int();
		$user         = wp_get_current_user();

		// Then delete it.
		$args             = array( 'entry_id' => $new_entry_id );
		$entry_operations = Container::instance()->entry_operations();
		$entry_operations->do_crud( 'delete', $this->build_entry_args( $args ), $user );

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
		$new_entry_id = $new_entry[0]->id()->to_int();
		$user         = wp_get_current_user();

		// Then delete the key.
		$args             = array( 'entry_id' => $new_entry_id );
		$entry_operations = Container::instance()->entry_operations();
		$entry            = $entry_operations->do_crud( 'delete_key', $this->build_entry_args( $args ), $user );

		// $entry will be an instance of WP_Error if the entry didn't contain a key or there was another error.
		$this->assertNotInstanceOf( 'WP_Error', $entry );
	}

	/**
	 * Test getting a preview of an entry.
	 */
	public function test_preview_entry(): void {
		// Get entry preview.
		$entry_operations = Container::instance()->entry_operations();
		$preview          = $entry_operations->format_preview( 'Test Liveblog entry with /key' );

		$this->assertIsArray( $preview );
		$this->assertNotEmpty( $preview['html'] );
	}

	/**
	 * Test getting list of authors from a string.
	 */
	public function test_get_authors(): void {
		// Get a list of authors.
		$author_filter = new AuthorFilter();

		$authors_not_empty = $author_filter->get_authors( 'adm' ); // Should return admin.
		$authors_is_empty  = $author_filter->get_authors( 'fakeauthor' ); // Non-existent user.

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
		$hashtag_filter = new HashtagFilter();

		// Create a temporary hashtag.
		self::factory()->term->create(
			array(
				'name'     => 'coolhashtag',
				'taxonomy' => 'hashtags',
				'slug'     => 'coolhashtag',
			)
		);

		$hashtags_not_empty = $hashtag_filter->get_hashtag_terms( 'cool' ); // Should return coolhashtag.
		$hashtags_is_empty  = $hashtag_filter->get_hashtag_terms( 'fakehashtag' ); // Non-existent hashtag.

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
		$admin_controller = Container::instance()->admin_controller();
		$meta_box         = $admin_controller->set_liveblog_state( $post->ID, $state, $request_vars );

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
		// Create an author who also owns the target post — the permission
		// callback now requires `edit_post` on the URL post_id.
		$author_id = $this->set_author_user();
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		// The POST data to insert.
		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		// Try to access the endpoint and insert an entry.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
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
		$request  = new WP_REST_Request( 'GET', self::ENDPOINT_BASE . '/' . $post_id . '/entry/' . $new_entries[0]->id()->to_int() );
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
		// Create an author who also owns the target post — the preview route
		// is now scoped to `edit_post` on the URL post_id.
		$author_id = $this->set_author_user();
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		// The POST data to preview.
		$post_vars = array( 'entry_content' => 'Test Liveblog entry with /key' );

		// Try to access the endpoint.
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/preview' );
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
		// Create an author who also owns the target post — the post_state
		// route is now scoped to `edit_post` on the URL post_id.
		$author_id = $this->set_author_user();
		$post      = self::factory()->post->create_and_get( array( 'post_author' => $author_id ) );

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
	 * An author who does not own the target post must not be able to insert
	 * an entry into another user's liveblog (CWE-285).
	 */
	public function test_endpoint_crud_insert_denies_non_owner_author(): void {
		// Post belongs to a different author.
		$owner_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id  = self::factory()->post->create( array( 'post_author' => $owner_id ) );

		// Current user is a different author with `publish_posts` but no
		// `edit_post` on the target.
		$attacker_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $attacker_id );

		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $post_vars ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * An editor (default cap `edit_others_posts`) can edit any post's liveblog.
	 */
	public function test_endpoint_crud_insert_allows_editor_on_others_post(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $post_vars ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * The CRUD endpoint must use the URL post_id, not the JSON body. Otherwise
	 * a caller who passes the permission check for one post could redirect
	 * the action at another post they have no right to edit.
	 */
	public function test_endpoint_crud_json_body_post_id_cannot_override_url(): void {
		// Author owns post A.
		$author_id    = $this->set_author_user();
		$owned_post   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$victim_owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$victim_post  = self::factory()->post->create( array( 'post_author' => $victim_owner ) );

		// Capture the post_id the entry was actually inserted against, so the
		// test does not depend on for_json() exposing post_id (it does not).
		$inserted_post_ids = array();
		$capture           = static function ( $entry_id, $post_id ) use ( &$inserted_post_ids ) {
			$inserted_post_ids[] = (int) $post_id;
		};
		add_action( 'liveblog_insert_entry', $capture, 10, 2 );

		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $victim_post, // Body claims a post the caller cannot edit.
				'content'     => 'attacker entry',
			)
		);

		// URL targets the owned post (so permission_callback passes).
		$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $owned_post . '/crud' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $post_vars ) );
		$response = $this->server->dispatch( $request );

		remove_action( 'liveblog_insert_entry', $capture, 10 );

		$this->assertEquals( 200, $response->get_status() );

		// Entry must have been attached to the URL post, not the body post.
		$this->assertSame( array( (int) $owned_post ), $inserted_post_ids );
	}

	/**
	 * The post-scoped permission helper follows whatever WordPress maps
	 * `edit_post` to in the current environment, rather than naming roles. If
	 * a site customises role caps (for instance, removes `edit_others_posts`
	 * from Editors), the check correctly denies write access to other users'
	 * liveblogs.
	 */
	public function test_endpoint_crud_follows_capability_not_role(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$post_vars = $this->build_entry_args(
			array(
				'crud_action' => 'insert',
				'post_id'     => $post_id,
			)
		);

		// Strip `edit_others_posts` from this user via the user_has_cap filter.
		// Per-request only; does not mutate the shared role definition.
		$strip_cap = static function ( $allcaps ) {
			unset( $allcaps['edit_others_posts'] );
			return $allcaps;
		};
		add_filter( 'user_has_cap', $strip_cap );

		try {
			$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
			$request->add_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $post_vars ) );
			$response = $this->server->dispatch( $request );

			$this->assertEquals( 403, $response->get_status() );
		} finally {
			remove_filter( 'user_has_cap', $strip_cap );
		}
	}

	/**
	 * The `liveblog_current_user_can_edit_liveblog` filter still applies to
	 * the post-scoped permission callback, preserving the existing extension
	 * point for downstream code that further restricts editing.
	 */
	public function test_endpoint_crud_filter_can_deny(): void {
		$author_id = $this->set_author_user();
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		add_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );

		try {
			$post_vars = $this->build_entry_args(
				array(
					'crud_action' => 'insert',
					'post_id'     => $post_id,
				)
			);

			$request = new WP_REST_Request( 'POST', self::ENDPOINT_BASE . '/' . $post_id . '/crud' );
			$request->add_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $post_vars ) );
			$response = $this->server->dispatch( $request );

			$this->assertEquals( 403, $response->get_status() );
		} finally {
			remove_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );
		}
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
		return $this->insert_entries( $number_of_entries, $args );
	}

	/**
	 * Insert entries using domain services.
	 *
	 * @param int   $number_of_entries Number of entries to create.
	 * @param array $args              Arguments.
	 * @return Entry[] Array of Entry objects.
	 */
	private function insert_entries( int $number_of_entries = 1, array $args = array() ): array {
		$entries = array();

		$user = self::factory()->user->create_and_get();
		wp_set_current_user( $user->ID );
		$args['user'] = $user;

		$container     = $this->container();
		$entry_service = $container->entry_service();
		$repository    = $container->entry_repository();

		$merged_args = $this->build_entry_args( $args );

		for ( $i = 0; $i < $number_of_entries; $i++ ) {
			$entry_id  = $entry_service->create(
				(int) $merged_args['post_id'],
				$merged_args['content'] ?? 'Test Liveblog entry',
				$args['user']
			);
			$entries[] = $repository->get_entry( $entry_id );
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
	 * Create an author and set it as the current user.
	 *
	 * @return int The new author's user ID.
	 */
	private function set_author_user(): int {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		wp_set_current_user( $author_id );

		return $author_id;
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
		$state            = 'enable';
		$request_vars     = array( 'state' => $state );
		$admin_controller = Container::instance()->admin_controller();
		$admin_controller->set_liveblog_state( $post_id, $state, $request_vars );

		return $post_id;
	}

	/**
	 * Data provider for non-public liveblog posts.
	 *
	 * Each entry is a post_status that should not expose liveblog entries to
	 * anonymous callers of the public read endpoints.
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
		// Create as a published liveblog post with entries, then transition to
		// the target status. This avoids WordPress rewriting the status during
		// insert (e.g. future posts with no future date, or liveblog enablement
		// refusing non-publish states).
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

		foreach ( $this->public_read_urls_for_post( $post_id, $entry->id()->to_int() ) as $url ) {
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
	 * This prevents the endpoints from being used as an oracle to distinguish
	 * liveblog posts from regular posts.
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
		$this->insert_entries( 1, array( 'post_id' => $post_id ) );
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
	 * Useful for headless setups that want to expose entries for drafts without
	 * granting `read_post` to the anonymous caller.
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
