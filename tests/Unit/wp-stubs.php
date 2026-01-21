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

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Generic.Classes.DuplicateClassName.Found
// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

if ( ! class_exists( 'WPCOM_Liveblog' ) ) {
	/**
	 * Minimal WPCOM_Liveblog stub for unit testing.
	 *
	 * Contains only methods that can be tested without WordPress.
	 * The real class is defined in liveblog.php but has too many
	 * WordPress dependencies to load for unit tests.
	 */
	final class WPCOM_Liveblog {

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

		/**
		 * Check if liveblog is editable.
		 *
		 * @return bool Always true in unit tests.
		 */
		public static function is_liveblog_editable(): bool {
			return true;
		}

		/**
		 * Get avatar HTML.
		 *
		 * @param mixed $id_or_email User ID or email.
		 * @param int   $size        Avatar size.
		 * @return string Avatar HTML.
		 */
		public static function get_avatar( $id_or_email, int $size = 30 ): string {
			return '<img src="avatar.jpg" width="' . $size . '" height="' . $size . '" />';
		}

		/**
		 * Get liveblog state.
		 *
		 * @return string|false Liveblog state.
		 */
		public static function get_liveblog_state() {
			// Can be overridden in tests via Mockery alias or patchwork.
			return 'enable';
		}
	}
}

// phpcs:enable
