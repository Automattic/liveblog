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

// NOTE: get_comment_meta is NOT stubbed here - use Brain\Monkey Functions\expect() in tests
// that need to mock it. This allows Patchwork to intercept the function.

// phpcs:enable
