<?php
/**
 * Minimal WP_CLI stubs so wp-cli command classes can be loaded and exercised
 * from PHPUnit, where the real WP-CLI runtime isn't present.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stubs for external WP-CLI classes.
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch -- Stub file containing multiple classes.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Stub file containing multiple classes.
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Mirrors the real WP_CLI signature.

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Stub for the WP-CLI base command class.
	 */
	class WP_CLI_Command {}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Stub for the WP_CLI static API.
	 *
	 * Only implements what `fix_archive()` actually calls. Tracks whether
	 * success() was reached so tests can assert the command completed
	 * without a fatal, without needing a full call log.
	 */
	class WP_CLI {

		/**
		 * Whether success() has been called since the last reset().
		 *
		 * @var bool
		 */
		public static $success_called = false;

		/**
		 * Reset tracked state between tests.
		 *
		 * @return void
		 */
		public static function reset() {
			self::$success_called = false;
		}

		/**
		 * Stub for WP_CLI::add_command(). No-op.
		 *
		 * @param string $name     Command name.
		 * @param mixed  $callable Command handler.
		 * @return void
		 */
		public static function add_command( $name, $callable ) {}

		/**
		 * Stub for WP_CLI::log(). No-op.
		 *
		 * @param string $message Message to log.
		 * @return void
		 */
		public static function log( $message ) {}

		/**
		 * Stub for WP_CLI::success(). Records that it was reached.
		 *
		 * @param string $message Message to log.
		 * @return void
		 */
		public static function success( $message ) {
			self::$success_called = true;
		}

		/**
		 * Stub for WP_CLI::colorize(). Returns the string unchanged.
		 *
		 * @param string $string String to colorize.
		 * @return string
		 */
		public static function colorize( $string ) {
			return $string;
		}
	}
}

// phpcs:enable
