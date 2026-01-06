<?php
/**
 * WordPress function stubs for unit testing.
 *
 * These stubs allow unit tests to load plugin classes without WordPress.
 * They provide minimal implementations of WordPress functions that are
 * called during class loading.
 *
 * @package Automattic\Liveblog\Tests\Unit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
	/**
	 * Stub for wp_kses_allowed_html.
	 *
	 * @param string $context Context name.
	 * @return array Empty array for unit tests.
	 */
	function wp_kses_allowed_html( $context = '' ) {
		return array();
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Stub for wp_parse_args.
	 *
	 * @param array|object|string $args     Arguments to parse.
	 * @param array               $defaults Default values.
	 * @return array Merged arguments.
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	/**
	 * Stub for get_comment_meta.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $key        Meta key.
	 * @param bool   $single     Return single value.
	 * @return mixed Empty string for unit tests.
	 */
	function get_comment_meta( $comment_id, $key = '', $single = false ) {
		return $single ? '' : array();
	}
}

// phpcs:enable

if ( ! class_exists( 'WPCOM_Liveblog' ) ) {
	/**
	 * Minimal WPCOM_Liveblog stub for unit testing.
	 *
	 * Contains only methods that can be tested without WordPress.
	 * The real class is defined in liveblog.php but has too many
	 * WordPress dependencies to load for unit tests.
	 */
	final class WPCOM_Liveblog {
 // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, PEAR.NamingConventions.ValidClassName.Invalid
		public const KEY = 'liveblog';

		/**
		 * Sanitizes an HTTP header value by removing CR, LF, and null bytes.
		 *
		 * @param string $header The header value to sanitize.
		 * @return string The sanitized header value.
		 */
		public static function sanitize_http_header( string $header ): string {
			return (string) preg_replace( '/[\r\n\0]/', '', $header );
		}
	}
}
