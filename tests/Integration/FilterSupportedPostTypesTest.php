<?php
/**
 * Integration tests for the liveblog_supported_post_types filter.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Tests for the liveblog_supported_post_types filter.
 *
 * @covers WPCOM_Liveblog::init
 * @covers WPCOM_Liveblog::is_viewing_liveblog_post
 * @covers WPCOM_Liveblog::get_liveblog_state
 */
final class FilterSupportedPostTypesTest extends TestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset static properties that might be left over from other tests.
		WPCOM_Liveblog::$is_rest_api_call = false;
		WPCOM_Liveblog::$post_id          = null;
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove any filters added during tests.
		remove_all_filters( 'liveblog_supported_post_types' );

		// Reset supported post types to default.
		WPCOM_Liveblog::$supported_post_types = array( 'post' );

		// Reset static properties.
		WPCOM_Liveblog::$is_rest_api_call = false;
		WPCOM_Liveblog::$post_id          = null;

		parent::tear_down();
	}

	/**
	 * Test that post type is supported by default.
	 */
	public function test_post_type_supported_by_default(): void {
		$this->assertTrue( post_type_supports( 'post', WPCOM_Liveblog::KEY ) );
	}

	/**
	 * Test that page type is not supported by default.
	 */
	public function test_page_type_not_supported_by_default(): void {
		$this->assertFalse( post_type_supports( 'page', WPCOM_Liveblog::KEY ) );
	}

	/**
	 * Test that filter receives the correct default value.
	 */
	public function test_filter_receives_correct_default_value(): void {
		$received_value = null;

		add_filter(
			'liveblog_supported_post_types',
			function ( $post_types ) use ( &$received_value ) {
				$received_value = $post_types;
				return $post_types;
			}
		);

		// Re-run init to trigger the filter.
		WPCOM_Liveblog::init();

		$this->assertIsArray( $received_value );
		$this->assertContains( 'post', $received_value );
	}

	/**
	 * Test that filter can add additional post types.
	 */
	public function test_filter_can_add_additional_post_types(): void {
		add_filter(
			'liveblog_supported_post_types',
			function ( $post_types ) {
				$post_types[] = 'page';
				return $post_types;
			}
		);

		// Re-run init to trigger the filter.
		WPCOM_Liveblog::init();

		$this->assertContains( 'page', WPCOM_Liveblog::$supported_post_types );
		$this->assertTrue( post_type_supports( 'page', WPCOM_Liveblog::KEY ) );
	}

	/**
	 * Test that is_viewing_liveblog_post uses supported post types.
	 */
	public function test_is_viewing_liveblog_post_uses_supported_types(): void {
		// Create a page with liveblog enabled.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page_id, WPCOM_Liveblog::KEY, 'enable' );

		// Set the liveblog post ID.
		WPCOM_Liveblog::$post_id = $page_id;

		// Set the global post.
		$GLOBALS['post'] = get_post( $page_id );

		// Navigate to the page.
		$this->go_to( get_permalink( $page_id ) );

		// By default, pages are not supported, so should return false.
		WPCOM_Liveblog::$supported_post_types = array( 'post' );
		$this->assertFalse( WPCOM_Liveblog::is_viewing_liveblog_post() );

		// Add page support.
		WPCOM_Liveblog::$supported_post_types = array( 'post', 'page' );
		$this->assertTrue( WPCOM_Liveblog::is_viewing_liveblog_post() );
	}

	/**
	 * Test that get_liveblog_state uses supported post types.
	 */
	public function test_get_liveblog_state_uses_supported_types(): void {
		// Create a page with liveblog enabled.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page_id, WPCOM_Liveblog::KEY, 'enable' );

		// Set the liveblog post ID.
		WPCOM_Liveblog::$post_id = $page_id;

		// Set the global post.
		$GLOBALS['post'] = get_post( $page_id );

		// Navigate to the page.
		$this->go_to( get_permalink( $page_id ) );

		// By default, pages are not supported, so should return false.
		WPCOM_Liveblog::$supported_post_types = array( 'post' );
		$this->assertFalse( WPCOM_Liveblog::get_liveblog_state( $page_id ) );

		// Add page support.
		WPCOM_Liveblog::$supported_post_types = array( 'post', 'page' );
		$this->assertEquals( 'enable', WPCOM_Liveblog::get_liveblog_state( $page_id ) );
	}

	/**
	 * Test that custom post types can be added via filter.
	 */
	public function test_custom_post_type_support_via_filter(): void {
		// Register a custom post type.
		register_post_type(
			'liveblog_event',
			array(
				'public' => true,
				'label'  => 'Events',
			)
		);

		add_filter(
			'liveblog_supported_post_types',
			function ( $post_types ) {
				$post_types[] = 'liveblog_event';
				return $post_types;
			}
		);

		// Re-run init to trigger the filter.
		WPCOM_Liveblog::init();

		$this->assertContains( 'liveblog_event', WPCOM_Liveblog::$supported_post_types );
		$this->assertTrue( post_type_supports( 'liveblog_event', WPCOM_Liveblog::KEY ) );

		// Clean up.
		unregister_post_type( 'liveblog_event' );
	}
}
