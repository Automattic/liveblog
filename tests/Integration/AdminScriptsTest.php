<?php
/**
 * Integration tests for admin script enqueueing.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Admin scripts integration test case.
 *
 * These tests ensure the Liveblog metabox JavaScript is properly
 * included and enqueued. See GitHub issue #804.
 *
 * Note: Build artifact tests were removed as the integration test
 * workflow runs without the build step. The webpack config test
 * is sufficient to prevent regressions.
 */
final class AdminScriptsTest extends TestCase {

	/**
	 * Test that the admin source file exists.
	 *
	 * This ensures the source entry point hasn't been accidentally removed.
	 */
	public function test_admin_source_file_exists(): void {
		$admin_src = dirname( __DIR__, 2 ) . '/src/admin/index.js';
		$this->assertFileExists( $admin_src, 'Admin source file should exist' );
	}

	/**
	 * Test that webpack config includes admin entry point.
	 *
	 * This is the key test that prevents regressions like issue #804
	 * where the metabox JavaScript was inadvertently removed from the build.
	 */
	public function test_webpack_config_includes_admin_entry(): void {
		$webpack_config = file_get_contents( dirname( __DIR__, 2 ) . '/webpack.config.js' );
		$this->assertStringContainsString(
			'admin:',
			$webpack_config,
			'Webpack config should include admin entry point'
		);
	}
}
