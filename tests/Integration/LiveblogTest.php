<?php

declare( strict_types=1 );

/**
 * Integration tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Liveblog integration test case.
 */
final class LiveblogTest extends TestCase {

	/**
	 * Test that liveblog meta is protected.
	 */
	public function test_protected_liveblog_meta_should_return_true(): void {
		$this->assertTrue( is_protected_meta( WPCOM_Liveblog::KEY ) );
	}
}
