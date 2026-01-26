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
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

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

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stub for WP_Post class.
	 *
	 * Provides minimal implementation for unit tests.
	 */
	class WP_Post {
		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $ID;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type;

		/**
		 * Post title.
		 *
		 * @var string
		 */
		public $post_title;

		/**
		 * Post status.
		 *
		 * @var string
		 */
		public $post_status;
	}
}

if ( ! class_exists( 'WP_Widget' ) ) {
	/**
	 * Stub for WP_Widget base class.
	 *
	 * Provides minimal implementation for unit tests.
	 */
	class WP_Widget {
		/**
		 * Widget ID.
		 *
		 * @var string
		 */
		public $id_base;

		/**
		 * Widget name.
		 *
		 * @var string
		 */
		public $name;

		/**
		 * Constructor stub.
		 *
		 * @param string $id_base Widget ID base.
		 * @param string $name    Widget name.
		 * @param array  $options Widget options.
		 */
		public function __construct( $id_base = '', $name = '', $options = array() ) {
			$this->id_base = $id_base;
			$this->name    = $name;
		}

		/**
		 * Get field ID stub.
		 *
		 * @param string $field_name Field name.
		 * @return string Field ID.
		 */
		public function get_field_id( $field_name ) {
			return 'widget-' . $this->id_base . '-' . $field_name;
		}

		/**
		 * Get field name stub.
		 *
		 * @param string $field_name Field name.
		 * @return string Field name for form.
		 */
		public function get_field_name( $field_name ) {
			return 'widget-' . $this->id_base . '[' . $field_name . ']';
		}
	}
}

// phpcs:enable
