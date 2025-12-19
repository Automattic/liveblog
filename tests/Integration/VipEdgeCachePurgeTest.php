<?php

declare( strict_types=1 );

/**
 * Integration tests for VIP edge cache purging.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for the VIP edge cache purge functionality.
 *
 * @covers ::liveblog_purge_edge_cache
 */
final class VipEdgeCachePurgeTest extends TestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Tracks URLs that were purged.
	 *
	 * @var array
	 */
	private array $purged_urls = array();

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create a test post.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Liveblog',
				'post_status' => 'publish',
			)
		);

		// Reset purged URLs tracker.
		$this->purged_urls = array();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove the mock VIP function if we created it.
		$this->purged_urls = array();

		parent::tear_down();
	}

	/**
	 * Test that the purge function is hooked to liveblog_insert_entry.
	 */
	public function test_purge_function_hooked_to_insert_entry(): void {
		$this->assertIsInt(
			has_action( 'liveblog_insert_entry', 'liveblog_purge_edge_cache' )
		);
	}

	/**
	 * Test that the purge function is hooked to liveblog_update_entry.
	 */
	public function test_purge_function_hooked_to_update_entry(): void {
		$this->assertIsInt(
			has_action( 'liveblog_update_entry', 'liveblog_purge_edge_cache' )
		);
	}

	/**
	 * Test that the purge function is hooked to liveblog_delete_entry.
	 */
	public function test_purge_function_hooked_to_delete_entry(): void {
		$this->assertIsInt(
			has_action( 'liveblog_delete_entry', 'liveblog_purge_edge_cache' )
		);
	}

	/**
	 * Test that the function returns early when VIP function doesn't exist.
	 */
	public function test_returns_early_when_vip_function_not_available(): void {
		// The VIP function shouldn't exist in test environment.
		$this->assertFalse( function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) );

		// This should not throw an error.
		liveblog_purge_edge_cache( 1, $this->post_id );

		// If we got here without error, the early return worked.
		$this->assertTrue( true );
	}

	/**
	 * Test that the function calls the VIP purge function with correct URL.
	 */
	public function test_calls_vip_purge_with_correct_url(): void {
		// Create a mock for the VIP function.
		$this->mock_vip_purge_function();

		$expected_url = get_permalink( $this->post_id );

		liveblog_purge_edge_cache( 1, $this->post_id );

		$this->assertContains( $expected_url, $this->purged_urls );
	}

	/**
	 * Test that the function handles invalid post ID gracefully.
	 */
	public function test_handles_invalid_post_id(): void {
		// Create a mock for the VIP function.
		$this->mock_vip_purge_function();

		// Use a non-existent post ID.
		liveblog_purge_edge_cache( 1, 999999 );

		// Should not have purged anything.
		$this->assertEmpty( $this->purged_urls );
	}

	/**
	 * Test that the function sanitizes the post ID.
	 */
	public function test_sanitizes_post_id(): void {
		// Create a mock for the VIP function.
		$this->mock_vip_purge_function();

		$expected_url = get_permalink( $this->post_id );

		// Pass post ID as string (simulating untrusted input).
		liveblog_purge_edge_cache( 1, (string) $this->post_id );

		$this->assertContains( $expected_url, $this->purged_urls );
	}

	/**
	 * Create a mock for the VIP purge function.
	 *
	 * This creates the function in the global namespace if it doesn't exist.
	 */
	private function mock_vip_purge_function(): void {
		if ( ! function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
			$purged_urls = &$this->purged_urls;

			// Define the function in global namespace.
			eval( 'function wpcom_vip_purge_edge_cache_for_url( $url ) {
				global $wpcom_vip_liveblog_test_purged_urls;
				$wpcom_vip_liveblog_test_purged_urls[] = $url;
			}' );
		}

		// Use a global to track purged URLs since we can't use $this in eval.
		$GLOBALS['wpcom_vip_liveblog_test_purged_urls'] = &$this->purged_urls;
	}
}
