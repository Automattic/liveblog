<?php
/**
 * Integration tests for the liveblog_supported_post_types filter.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for the liveblog_supported_post_types filter.
 *
 * @covers \Automattic\Liveblog\Application\Config\LiveblogConfiguration
 * @covers \Automattic\Liveblog\Domain\Entity\LiveblogPost::is_viewing_liveblog_post
 * @covers \Automattic\Liveblog\Domain\Entity\LiveblogPost::state
 */
final class FilterSupportedPostTypesTest extends TestCase {

	/**
	 * Original supported post types to restore after tests.
	 *
	 * @var string[]
	 */
	private array $original_supported_types;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Store original supported post types.
		$this->original_supported_types = LiveblogConfiguration::get_supported_post_types();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove any filters added during tests.
		remove_all_filters( 'liveblog_supported_post_types' );

		// Restore supported post types to original.
		LiveblogConfiguration::set_supported_post_types( $this->original_supported_types );

		parent::tear_down();
	}

	/**
	 * Test that post type is supported by default.
	 */
	public function test_post_type_supported_by_default(): void {
		$this->assertTrue( post_type_supports( 'post', LiveblogConfiguration::KEY ) );
	}

	/**
	 * Test that page type is not supported by default.
	 */
	public function test_page_type_not_supported_by_default(): void {
		$this->assertFalse( post_type_supports( 'page', LiveblogConfiguration::KEY ) );
	}

	/**
	 * Test that is_viewing_liveblog_post uses supported post types.
	 */
	public function test_is_viewing_liveblog_post_uses_supported_types(): void {
		// Create a page with liveblog enabled.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page_id, LiveblogConfiguration::KEY, 'enable' );

		// Set the global post.
		$GLOBALS['post'] = get_post( $page_id );

		// Navigate to the page.
		$this->go_to( get_permalink( $page_id ) );

		// By default, pages are not supported, so should return false.
		LiveblogConfiguration::set_supported_post_types( array( 'post' ) );
		$this->assertFalse( LiveblogPost::is_viewing_liveblog_post() );

		// Add page support.
		LiveblogConfiguration::set_supported_post_types( array( 'post', 'page' ) );
		$this->assertTrue( LiveblogPost::is_viewing_liveblog_post() );
	}

	/**
	 * Test that LiveblogPost::state() uses supported post types.
	 */
	public function test_liveblog_state_uses_supported_types(): void {
		// Create a page with liveblog enabled.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page_id, LiveblogConfiguration::KEY, 'enable' );

		// Set the global post.
		$GLOBALS['post'] = get_post( $page_id );

		// Navigate to the page.
		$this->go_to( get_permalink( $page_id ) );

		// By default, pages are not supported, so LiveblogPost should return null.
		LiveblogConfiguration::set_supported_post_types( array( 'post' ) );
		$liveblog_post = LiveblogPost::from_id( $page_id );
		$this->assertNull( $liveblog_post );

		// Add page support.
		LiveblogConfiguration::set_supported_post_types( array( 'post', 'page' ) );
		$liveblog_post = LiveblogPost::from_id( $page_id );
		$this->assertNotNull( $liveblog_post );
		$this->assertEquals( 'enable', $liveblog_post->state() );
	}

	/**
	 * Test that supported post types can be configured.
	 */
	public function test_supported_post_types_can_be_configured(): void {
		// Register a custom post type.
		register_post_type(
			'liveblog_event',
			array(
				'public' => true,
				'label'  => 'Events',
			)
		);

		// Add liveblog support to custom post type.
		add_post_type_support( 'liveblog_event', LiveblogConfiguration::KEY );

		// Update configuration.
		LiveblogConfiguration::set_supported_post_types( array( 'post', 'liveblog_event' ) );

		$this->assertContains( 'liveblog_event', LiveblogConfiguration::get_supported_post_types() );
		$this->assertTrue( post_type_supports( 'liveblog_event', LiveblogConfiguration::KEY ) );

		// Clean up.
		unregister_post_type( 'liveblog_event' );
	}
}
