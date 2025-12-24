<?php
/**
 * Unit tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit;

use Yoast\WPTestUtils\BrainMonkey\TestCase;
use WPCOM_Liveblog;

/**
 * Liveblog unit test case.
 */
final class LiveblogTest extends TestCase {

	/**
	 * Test that headers skip newlines.
	 *
	 * @covers WPCOM_Liveblog::sanitize_http_header
	 */
	public function test_headers_should_skip_newlines(): void {
		$this->assertSame( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\nba" ) );
	}

	/**
	 * Test that headers skip carriage returns.
	 *
	 * @covers WPCOM_Liveblog::sanitize_http_header
	 */
	public function test_headers_should_skip_crs(): void {
		$this->assertSame( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\rba" ) );
	}

	/**
	 * Test that headers skip null bytes.
	 *
	 * @covers WPCOM_Liveblog::sanitize_http_header
	 */
	public function test_headers_should_skip_null_bytes(): void {
		$this->assertSame( 'baba', WPCOM_Liveblog::sanitize_http_header( 'ba' . chr( 0 ) . 'ba' ) );
	}
}
