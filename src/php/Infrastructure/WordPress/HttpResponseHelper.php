<?php
/**
 * HTTP response helper for liveblog AJAX/REST responses.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;

/**
 * Helper class for HTTP responses in liveblog.
 *
 * Provides methods for sending JSON responses and error messages
 * with appropriate HTTP headers and status codes.
 */
final class HttpResponseHelper {

	/**
	 * Flag to prevent caching response.
	 *
	 * @var bool
	 */
	private static bool $do_not_cache_response = false;

	/**
	 * Cache control max age value.
	 *
	 * @var int|null
	 */
	private static ?int $cache_control_max_age = null;

	/**
	 * Send a JSON response and exit.
	 *
	 * @param mixed $data The data to encode as JSON.
	 * @param array $args Optional arguments. Supports 'cache' key:
	 *                    - false: Send no-cache headers
	 *                    - int: Send Cache-Control max-age header.
	 * @return never
	 */
	public static function json_return( $data, array $args = array() ): void {
		$defaults = array(
			'cache' => LiveblogConfiguration::RESPONSE_CACHE_MAX_AGE,
		);

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter the JSON return arguments.
		 *
		 * @param array $args The arguments array.
		 * @param mixed $data The data being returned.
		 */
		$args = apply_filters( 'liveblog_json_return_args', $args, $data );

		// Send cache headers, where appropriate.
		if ( false === $args['cache'] ) {
			nocache_headers();
		} elseif ( is_numeric( $args['cache'] ) ) {
			header( sprintf( 'Cache-Control: max-age=%d', $args['cache'] ) );
		}

		header( 'Content-Type: application/json' );
		self::prevent_caching_if_needed();

		echo wp_json_encode( $data );
		exit();
	}

	/**
	 * Send a server error (HTTP 500) and exit.
	 *
	 * @param string $message The error message.
	 * @return never
	 */
	public static function send_server_error( string $message ): void {
		self::status_header_with_message( 500, $message );
		exit();
	}

	/**
	 * Send a user error (HTTP 406 - Not Acceptable) and exit.
	 *
	 * @param string $message The error message.
	 * @return never
	 */
	public static function send_user_error( string $message ): void {
		self::status_header_with_message( 406, $message );
		exit();
	}

	/**
	 * Send a forbidden error (HTTP 403) and exit.
	 *
	 * @param string $message The error message.
	 * @return never
	 */
	public static function send_forbidden_error( string $message ): void {
		self::status_header_with_message( 403, $message );
		exit();
	}

	/**
	 * Send a status header with a custom message.
	 *
	 * Temporarily modifies the WordPress header description to include
	 * the custom message in the HTTP status line.
	 *
	 * @param int    $status  HTTP status code.
	 * @param string $message Custom status message.
	 * @return void
	 */
	public static function status_header_with_message( int $status, string $message ): void {
		global $wp_header_to_desc;

		$status           = absint( $status );
		$official_message = isset( $wp_header_to_desc[ $status ] ) ? $wp_header_to_desc[ $status ] : '';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily modifying for custom status message.
		$wp_header_to_desc[ $status ] = self::sanitize_http_header( $message );

		status_header( $status );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original value.
		$wp_header_to_desc[ $status ] = $official_message;
	}

	/**
	 * Sanitize text for use in HTTP headers.
	 *
	 * Removes newlines and null bytes which are forbidden in headers
	 * and could be used for header injection attacks.
	 *
	 * @param string $text The text to sanitize.
	 * @return string Sanitized text safe for headers.
	 */
	public static function sanitize_http_header( string $text ): string {
		return str_replace( array( "\n", "\r", chr( 0 ) ), '', $text );
	}

	/**
	 * Set the do-not-cache flag for the current response.
	 *
	 * @param bool $do_not_cache Whether to prevent caching.
	 * @return void
	 */
	public static function set_do_not_cache( bool $do_not_cache ): void {
		self::$do_not_cache_response = $do_not_cache;
	}

	/**
	 * Set the cache control max age for the current response.
	 *
	 * @param int|null $max_age Max age in seconds, or null to disable.
	 * @return void
	 */
	public static function set_cache_control_max_age( ?int $max_age ): void {
		self::$cache_control_max_age = $max_age;
	}

	/**
	 * Prevent caching if needed based on current state.
	 *
	 * Sends no-cache headers if $do_not_cache_response is true,
	 * or Cache-Control headers if $cache_control_max_age is set.
	 *
	 * @return void
	 */
	public static function prevent_caching_if_needed(): void {
		// Avoid errors in test environments or when output has started.
		if ( headers_sent() ) {
			return;
		}

		if ( self::$do_not_cache_response ) {
			nocache_headers();
		} elseif ( null !== self::$cache_control_max_age ) {
			header( 'Cache-control: max-age=' . self::$cache_control_max_age );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + self::$cache_control_max_age ) );
		}
	}

	/**
	 * Reset the response state (for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$do_not_cache_response = false;
		self::$cache_control_max_age = null;
	}
}
