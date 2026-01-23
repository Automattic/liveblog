<?php
/**
 * Integration tests for the liveblog_cache_endpoint_base filter.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for the liveblog_cache_endpoint_base filter.
 *
 * @covers \Automattic\Liveblog\Infrastructure\WordPress\RestApiController::build_endpoint_base
 */
final class FilterEndpointCacheTest extends TestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the static endpoint base before each test.
		$this->reset_endpoint_base();
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove any filters added during tests.
		remove_all_filters( 'liveblog_cache_endpoint_base' );

		// Reset endpoint base.
		$this->reset_endpoint_base();

		parent::tear_down();
	}

	/**
	 * Reset the static endpoint base using reflection.
	 */
	private function reset_endpoint_base(): void {
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, '' );
	}

	/**
	 * Set the static endpoint base using reflection.
	 *
	 * @param string $value Value to set.
	 */
	private function set_endpoint_base( string $value ): void {
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}

	/**
	 * Get the static endpoint base using reflection.
	 *
	 * @return string
	 */
	private function get_endpoint_base(): string {
		$reflection = new \ReflectionProperty( RestApiController::class, 'static_endpoint_base' );
		$reflection->setAccessible( true );
		return $reflection->getValue( null );
	}

	/**
	 * Test that endpoint base is cached when set.
	 */
	public function test_endpoint_base_cached_when_set(): void {
		// Build endpoint base to populate the cache.
		$first_call = RestApiController::build_endpoint_base();

		$cached_value = $this->get_endpoint_base();

		// Second call should return the cached value.
		$second_call = RestApiController::build_endpoint_base();

		$this->assertSame( $cached_value, $second_call );
		$this->assertSame( $first_call, $second_call );
	}

	/**
	 * Test that cache can be disabled via filter.
	 */
	public function test_cache_disabled_via_filter(): void {
		add_filter( 'liveblog_cache_endpoint_base', '__return_false' );

		// Build endpoint base and modify the static value.
		$first_call = RestApiController::build_endpoint_base();

		// Manually modify the cached value to test that it gets rebuilt.
		$this->set_endpoint_base( 'modified_value' );

		// With cache disabled, this should rebuild and not return 'modified_value'.
		$second_call = RestApiController::build_endpoint_base();

		$this->assertNotEquals( 'modified_value', $second_call );
		$this->assertSame( $first_call, $second_call );
	}

	/**
	 * Test that the filter receives the correct default value.
	 */
	public function test_filter_receives_correct_default_value(): void {
		$received_value = null;

		add_filter(
			'liveblog_cache_endpoint_base',
			function ( $cache_enabled ) use ( &$received_value ) {
				$received_value = $cache_enabled;
				return $cache_enabled;
			}
		);

		// Need to set endpoint_base first so the filter gets called.
		$this->set_endpoint_base( 'test_value' );
		RestApiController::build_endpoint_base();

		$this->assertTrue( $received_value );
	}

	/**
	 * Test that endpoint base contains expected liveblog namespace.
	 */
	public function test_endpoint_base_contains_liveblog_namespace(): void {
		$endpoint_base = RestApiController::build_endpoint_base();

		$this->assertStringContainsString( 'liveblog', $endpoint_base );
	}

	/**
	 * Test that filter bypasses cache when returning false.
	 */
	public function test_filter_bypasses_cache_when_false(): void {
		// Set a cached value.
		$this->set_endpoint_base( 'cached_value' );

		// Without filter, should return cached value.
		$cached_result = RestApiController::build_endpoint_base();
		$this->assertSame( 'cached_value', $cached_result );

		// Add filter to disable caching.
		add_filter( 'liveblog_cache_endpoint_base', '__return_false' );

		// Now should rebuild instead of returning cached value.
		$fresh_result = RestApiController::build_endpoint_base();
		$this->assertNotEquals( 'cached_value', $fresh_result );
		$this->assertStringContainsString( 'liveblog', $fresh_result );
	}
}
