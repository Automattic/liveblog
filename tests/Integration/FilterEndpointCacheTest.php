<?php

declare( strict_types=1 );

/**
 * Integration tests for the liveblog_cache_endpoint_base filter.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Rest_Api;

/**
 * Tests for the liveblog_cache_endpoint_base filter.
 *
 * @covers WPCOM_Liveblog_Rest_Api::build_endpoint_base
 */
final class FilterEndpointCacheTest extends TestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the endpoint base before each test.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Remove any filters added during tests.
		remove_all_filters( 'liveblog_cache_endpoint_base' );

		// Reset endpoint base.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = null;

		parent::tear_down();
	}

	/**
	 * Test that endpoint base is cached when set via load().
	 */
	public function test_endpoint_base_cached_when_set(): void {
		// Simulate the load() behavior by setting endpoint_base.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		$cached_value = WPCOM_Liveblog_Rest_Api::$endpoint_base;

		// Second call should return the cached value.
		$second_call = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		$this->assertSame( $cached_value, $second_call );
	}

	/**
	 * Test that cache can be disabled via filter.
	 */
	public function test_cache_disabled_via_filter(): void {
		add_filter( 'liveblog_cache_endpoint_base', '__return_false' );

		// Build endpoint base and modify the static value.
		$first_call = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		// Manually modify the cached value to test that it gets rebuilt.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = 'modified_value';

		// With cache disabled, this should rebuild and not return 'modified_value'.
		$second_call = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

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
		WPCOM_Liveblog_Rest_Api::$endpoint_base = 'test_value';
		WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		$this->assertTrue( $received_value );
	}

	/**
	 * Test that endpoint base contains expected liveblog namespace.
	 */
	public function test_endpoint_base_contains_liveblog_namespace(): void {
		$endpoint_base = WPCOM_Liveblog_Rest_Api::build_endpoint_base();

		$this->assertStringContainsString( 'liveblog', $endpoint_base );
	}

	/**
	 * Test that filter bypasses cache when returning false.
	 */
	public function test_filter_bypasses_cache_when_false(): void {
		// Set a cached value.
		WPCOM_Liveblog_Rest_Api::$endpoint_base = 'cached_value';

		// Without filter, should return cached value.
		$cached_result = WPCOM_Liveblog_Rest_Api::build_endpoint_base();
		$this->assertSame( 'cached_value', $cached_result );

		// Add filter to disable caching.
		add_filter( 'liveblog_cache_endpoint_base', '__return_false' );

		// Now should rebuild instead of returning cached value.
		$fresh_result = WPCOM_Liveblog_Rest_Api::build_endpoint_base();
		$this->assertNotEquals( 'cached_value', $fresh_result );
		$this->assertStringContainsString( 'liveblog', $fresh_result );
	}
}
