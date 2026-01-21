<?php
/**
 * Trait for accessing the DI container in tests.
 *
 * @package Automattic\Liveblog\Tests\Traits
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Traits;

use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Provides helper methods for accessing the DI container in tests.
 *
 * Usage:
 *   use ContainerAwareTrait;
 *
 *   $service = $this->container()->entry_service();
 */
trait ContainerAwareTrait {

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
