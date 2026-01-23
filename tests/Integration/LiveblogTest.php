<?php
/**
 * Integration tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Liveblog integration test case.
 */
final class LiveblogTest extends TestCase {

	/**
	 * Test that liveblog meta is protected.
	 */
	public function test_protected_liveblog_meta_should_return_true(): void {
		$this->assertTrue( is_protected_meta( LiveblogConfiguration::KEY ) );
	}
}
