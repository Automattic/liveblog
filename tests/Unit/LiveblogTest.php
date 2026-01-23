<?php
/**
 * Unit tests for the HttpResponseHelper class.
 *
 * @package Automattic\Liveblog\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit;

use Automattic\Liveblog\Infrastructure\WordPress\HttpResponseHelper;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * HttpResponseHelper unit test case.
 *
 * @coversDefaultClass \Automattic\Liveblog\Infrastructure\WordPress\HttpResponseHelper
 */
final class LiveblogTest extends TestCase {

	/**
	 * Test that headers skip newlines.
	 *
	 * @covers ::sanitize_http_header
	 */
	public function test_headers_should_skip_newlines(): void {
		$this->assertSame( 'baba', HttpResponseHelper::sanitize_http_header( "ba\nba" ) );
	}

	/**
	 * Test that headers skip carriage returns.
	 *
	 * @covers ::sanitize_http_header
	 */
	public function test_headers_should_skip_crs(): void {
		$this->assertSame( 'baba', HttpResponseHelper::sanitize_http_header( "ba\rba" ) );
	}

	/**
	 * Test that headers skip null bytes.
	 *
	 * @covers ::sanitize_http_header
	 */
	public function test_headers_should_skip_null_bytes(): void {
		$this->assertSame( 'baba', HttpResponseHelper::sanitize_http_header( 'ba' . chr( 0 ) . 'ba' ) );
	}
}
