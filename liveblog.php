<?php
/**
 * Plugin Name: Liveblog
 * Plugin URI: http://wordpress.org/extend/plugins/liveblog/
 * Description: Empowers website owners to provide rich and engaging live event coverage to a large, distributed audience.
 * Version:     1.10.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      WordPress.com VIP, Big Bite Creative and contributors
 * Author URI: https://github.com/Automattic/liveblog/graphs/contributors
 * Text Domain: liveblog
 *
 * @package Liveblog
 */

declare( strict_types=1 );

namespace Automattic\Liveblog;

use Automattic\Liveblog\Infrastructure\DI\Container;
use Automattic\Liveblog\Infrastructure\WordPress\PluginBootstrapper;
use Automattic\Liveblog\Infrastructure\WordPress\PluploadCompat;

// Define plugin constants.
const PLUGIN_FILE = __FILE__;
const VERSION     = '1.10.0';

// Legacy constants for backwards compatibility.
\define( 'LIVEBLOG_FILE', __FILE__ );
\define( 'LIVEBLOG_VERSION', VERSION );

// Load Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Load helper functions for third-party developers.
require_once __DIR__ . '/src/php/functions.php';

// Ensure Plupload helper functions are available.
PluploadCompat::ensure_functions();

// Initialise the plugin.
$container = Container::instance();
( new PluginBootstrapper( $container ) )->init();

/**
 * Get the DI container.
 *
 * @return Container
 */
function container(): Container {
	return Container::instance();
}
