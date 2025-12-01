<?php

declare( strict_types=1 );

/**
 * Tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Liveblog test case.
 */
final class LiveblogTest extends TestCase {

	/**
	 * Test that headers skip newlines.
	 */
	public function test_headers_should_skip_newlines(): void {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\nba" ) );
	}

	/**
	 * Test that headers skip carriage returns.
	 */
	public function test_headers_should_skip_crs(): void {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\rba" ) );
	}

	/**
	 * Test that headers skip null bytes.
	 */
	public function test_headers_should_skip_null_bytes(): void {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( 'ba' . chr( 0 ) . 'ba' ) );
	}

	/**
	 * Test that liveblog meta is protected.
	 */
	public function test_protected_liveblog_meta_should_return_true(): void {
		$this->assertEquals( true, is_protected_meta( WPCOM_Liveblog::KEY ) );
	}
}
