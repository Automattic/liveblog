<?php
/**
 * Tests for how the main Liveblog class is loaded.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Liveblog load test case.
 */
final class LiveblogLoadTest extends TestCase {

	/**
	 * Checks WPCOM_Liveblog::load is hooked to `plugins_loaded` instead of being
	 * called directly, so other plugins get a chance to register their own
	 * callbacks (e.g. on `liveblog_features`) first.
	 *
	 * @covers WPCOM_Liveblog::load()
	 */
	public function test_load_is_hooked_to_plugins_loaded(): void {
		$priority = has_action( 'plugins_loaded', array( WPCOM_Liveblog::class, 'load' ) );

		$this->assertNotFalse( $priority );
		$this->assertSame( 0, $priority );
	}
}
