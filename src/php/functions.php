<?php
/**
 * Liveblog helper functions.
 *
 * @package Automattic\Liveblog
 */

declare( strict_types=1 );

/**
 * Get the Liveblog DI container instance.
 *
 * This function is provided for third-party developers who need to access
 * plugin services. Internal plugin code should use Container::instance() directly.
 *
 * @return \Automattic\Liveblog\Infrastructure\DI\Container The container.
 */
function liveblog_container(): \Automattic\Liveblog\Infrastructure\DI\Container {
	return \Automattic\Liveblog\Infrastructure\DI\Container::instance();
}
