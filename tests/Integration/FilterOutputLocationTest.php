<?php
/**
 * Integration tests for the liveblog_output_at_top filter.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Tests for the liveblog_output_at_top filter.
 *
 * @covers WPCOM_Liveblog::add_liveblog_to_content
 */
final class FilterOutputLocationTest extends TestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create a liveblog post.
		$this->post_id = self::factory()->post->create();

		// Enable liveblog on the post.
		update_post_meta( $this->post_id, WPCOM_Liveblog::KEY, 'enable' );

		// Set the global post.
		$GLOBALS['post'] = get_post( $this->post_id );

		// Set the liveblog post ID.
		WPCOM_Liveblog::$post_id = $this->post_id;

		// Simulate viewing a single post.
		$this->go_to( get_permalink( $this->post_id ) );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove any filters added during tests.
		remove_all_filters( 'liveblog_output_at_top' );

		parent::tear_down();
	}

	/**
	 * Test that liveblog output is appended to content by default.
	 */
	public function test_liveblog_output_appended_by_default(): void {
		$content = '<p>Post content</p>';

		$result = WPCOM_Liveblog::add_liveblog_to_content( $content );

		// Liveblog should come after content by default.
		$this->assertStringStartsWith( '<p>Post content</p>', $result );
		$this->assertStringContainsString( 'wpcom-liveblog-container', $result );
	}

	/**
	 * Test that liveblog_output_at_top filter prepends liveblog when returning true.
	 */
	public function test_liveblog_output_at_top_filter_prepends_when_true(): void {
		add_filter( 'liveblog_output_at_top', '__return_true' );

		$content = '<p>Post content</p>';

		$result = WPCOM_Liveblog::add_liveblog_to_content( $content );

		// Liveblog should come before content when filter returns true.
		$this->assertStringStartsWith( '<div id="wpcom-liveblog-container"', $result );
		$this->assertStringEndsWith( '<p>Post content</p>', $result );
	}

	/**
	 * Test that liveblog_output_at_top filter appends liveblog when returning false.
	 */
	public function test_liveblog_output_at_top_filter_appends_when_false(): void {
		add_filter( 'liveblog_output_at_top', '__return_false' );

		$content = '<p>Post content</p>';

		$result = WPCOM_Liveblog::add_liveblog_to_content( $content );

		// Liveblog should come after content when filter returns false.
		$this->assertStringStartsWith( '<p>Post content</p>', $result );
		$this->assertStringContainsString( 'wpcom-liveblog-container', $result );
	}

	/**
	 * Test that the filter receives the correct default value.
	 */
	public function test_liveblog_output_at_top_filter_default_value(): void {
		$received_value = null;

		add_filter(
			'liveblog_output_at_top',
			function ( $at_top ) use ( &$received_value ) {
				$received_value = $at_top;
				return $at_top;
			}
		);

		$content = '<p>Post content</p>';
		WPCOM_Liveblog::add_liveblog_to_content( $content );

		$this->assertFalse( $received_value );
	}

	/**
	 * Test that only boolean true triggers prepending (strict comparison).
	 */
	public function test_liveblog_output_at_top_filter_strict_true_comparison(): void {
		// Test with truthy but non-true value.
		add_filter( 'liveblog_output_at_top', fn() => 1 );

		$content = '<p>Post content</p>';

		$result = WPCOM_Liveblog::add_liveblog_to_content( $content );

		// Should still append because 1 !== true.
		$this->assertStringStartsWith( '<p>Post content</p>', $result );
	}
}
