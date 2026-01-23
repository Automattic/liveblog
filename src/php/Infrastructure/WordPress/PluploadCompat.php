<?php
/**
 * Plupload compatibility helper.
 *
 * Ensures required WordPress functions are available for Plupload functionality
 * which may be needed early in the plugin loading process.
 *
 * @package Liveblog
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

/**
 * Handles loading of WordPress functions required by Plupload.
 */
final class PluploadCompat {

	/**
	 * Ensure required functions for Plupload are available.
	 *
	 * These functions are typically available, but may need to be loaded
	 * explicitly when the plugin initialises early in the WordPress load.
	 */
	public static function ensure_functions(): void {
		if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
			require_once ABSPATH . 'wp-includes/load.php';
		}

		if ( ! function_exists( 'size_format' ) ) {
			require_once ABSPATH . 'wp-includes/functions.php';
		}

		if ( ! function_exists( 'wp_max_upload_size' ) ) {
			require_once ABSPATH . 'wp-includes/media.php';
		}
	}
}
