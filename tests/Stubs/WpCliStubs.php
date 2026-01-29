<?php
/**
 * WP-CLI stubs for integration testing.
 *
 * Provides stub implementations of WP_CLI and WP_CLI\Utils that track method calls
 * for assertions in tests.
 *
 * @package Automattic\Liveblog\Tests\Stubs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stub for external class.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Stub for external namespace.
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch -- Stub file containing multiple classes.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses -- Stub file containing multiple classes.

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * WP_CLI stub class for testing.
	 *
	 * Tracks all method calls for assertions in tests.
	 */
	class WP_CLI {
		/**
		 * Recorded method calls.
		 *
		 * @var array
		 */
		private static $calls = [];

		/**
		 * Flag to control confirm() behaviour.
		 *
		 * @var bool
		 */
		private static $confirm_returns = true;

		/**
		 * Reset call history and state.
		 *
		 * @return void
		 */
		public static function reset() {
			self::$calls           = [];
			self::$confirm_returns = true;
		}

		/**
		 * Record a method call.
		 *
		 * @param string $method Method name.
		 * @param array  $args   Method arguments.
		 * @return void
		 */
		private static function record_call( $method, $args ) {
			self::$calls[] = [
				'method' => $method,
				'args'   => $args,
			];
		}

		/**
		 * Get all recorded calls.
		 *
		 * @return array
		 */
		public static function get_calls() {
			return self::$calls;
		}

		/**
		 * Get calls for a specific method.
		 *
		 * @param string $method Method name.
		 * @return array
		 */
		public static function get_calls_for( $method ) {
			return array_values(
				array_filter(
					self::$calls,
					function ( $call ) use ( $method ) {
						return $call['method'] === $method;
					}
				)
			);
		}

		/**
		 * Check if a method was called.
		 *
		 * @param string $method Method name.
		 * @return bool
		 */
		public static function was_called( $method ) {
			return count( self::get_calls_for( $method ) ) > 0;
		}

		/**
		 * Get the last call for a specific method.
		 *
		 * @param string $method Method name.
		 * @return array|null
		 */
		public static function get_last_call_for( $method ) {
			$calls = self::get_calls_for( $method );
			return $calls ? end( $calls ) : null;
		}

		/**
		 * Set what confirm() should return.
		 *
		 * @param bool $value Return value for confirm().
		 * @return void
		 */
		public static function set_confirm_returns( $value ) {
			self::$confirm_returns = $value;
		}

		/**
		 * Output a success message.
		 *
		 * @param string $message Message to output.
		 * @return void
		 */
		public static function success( $message ) {
			self::record_call( 'success', [ $message ] );
		}

		/**
		 * Output an error message and exit.
		 *
		 * @param string $message Message to output.
		 * @throws WP_CLI_ExitException Always throws to simulate exit.
		 */
		public static function error( $message ) {
			self::record_call( 'error', [ $message ] );
			throw new WP_CLI_ExitException( $message, 1 );
		}

		/**
		 * Output a warning message.
		 *
		 * @param string $message Message to output.
		 * @return void
		 */
		public static function warning( $message ) {
			self::record_call( 'warning', [ $message ] );
		}

		/**
		 * Output a line of text.
		 *
		 * @param string $message Message to output.
		 * @return void
		 */
		public static function line( $message = '' ) {
			self::record_call( 'line', [ $message ] );
		}

		/**
		 * Output a log message.
		 *
		 * @param string $message Message to output.
		 * @return void
		 */
		public static function log( $message ) {
			self::record_call( 'log', [ $message ] );
		}

		/**
		 * Output debug information.
		 *
		 * @param string $message Message to output.
		 * @return void
		 */
		public static function debug( $message ) {
			self::record_call( 'debug', [ $message ] );
		}

		/**
		 * Ask for confirmation.
		 *
		 * @param string $question Question to ask.
		 * @return void
		 * @throws WP_CLI_ExitException When confirm returns false.
		 */
		public static function confirm( $question ) {
			self::record_call( 'confirm', [ $question ] );

			if ( ! self::$confirm_returns ) {
				throw new WP_CLI_ExitException( 'Cancelled by user.', 0 );
			}
		}

		/**
		 * Display a colorized string.
		 *
		 * @param string $string String to colorize.
		 * @param bool   $label  Whether this is a label.
		 * @return string The input string (no colorization in stub).
		 */
		public static function colorize( $string, $label = false ) {
			return $string;
		}

		/**
		 * Get a command instance.
		 *
		 * @param string $command Command name.
		 * @return object|null
		 */
		public static function get_command( $command ) {
			return null;
		}

		/**
		 * Register a command.
		 *
		 * @param string $name    Command name.
		 * @param mixed  $handler Command handler.
		 * @param array  $args    Command arguments.
		 * @return void
		 */
		public static function add_command( $name, $handler, $args = [] ) {
			self::record_call( 'add_command', [ $name, $handler, $args ] );
		}
	}

	/**
	 * Exception thrown when WP_CLI::error() is called.
	 *
	 * Simulates the exit behaviour of the real WP_CLI.
	 */
	class WP_CLI_ExitException extends Exception {
		/**
		 * Exit code.
		 *
		 * @var int
		 */
		private $exit_code;

		/**
		 * Constructor.
		 *
		 * @param string $message   Error message.
		 * @param int    $exit_code Exit code.
		 */
		public function __construct( $message, $exit_code = 1 ) {
			parent::__construct( $message );
			$this->exit_code = $exit_code;
		}

		/**
		 * Get the exit code.
		 *
		 * @return int
		 */
		public function get_exit_code() {
			return $this->exit_code;
		}
	}
}

// Initialize the global for format_items tracking.
if ( ! isset( $GLOBALS['wp_cli_format_items_calls'] ) ) {
	$GLOBALS['wp_cli_format_items_calls'] = [];
}
