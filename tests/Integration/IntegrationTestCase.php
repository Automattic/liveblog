<?php
/**
 * Base test case for integration tests.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Infrastructure\DI\Container;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Base test case for integration tests.
 *
 * Provides helper methods for accessing the DI container in tests.
 *
 * Usage:
 *   class MyTest extends IntegrationTestCase {
 *       public function test_something(): void {
 *           $service = $this->container()->entry_service();
 *       }
 *   }
 */
abstract class IntegrationTestCase extends TestCase {

	/**
	 * Get the DI container instance.
	 *
	 * @return Container The container instance.
	 */
	protected function container(): Container {
		return Container::instance();
	}

	/**
	 * Reset the container state.
	 *
	 * Call this in tearDown() to ensure clean state between tests.
	 *
	 * @return void
	 */
	protected function reset_container(): void {
		Container::reset();
	}
}
