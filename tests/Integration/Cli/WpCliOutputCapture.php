<?php
/**
 * Helper for capturing and asserting WP-CLI output.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use WP_CLI;
use function WP_CLI\Utils\get_format_items_calls;
use function WP_CLI\Utils\reset_format_items_calls;

/**
 * Helper class for capturing WP-CLI output in tests.
 */
final class WpCliOutputCapture {

	/**
	 * Reset all captured output.
	 *
	 * @return void
	 */
	public function reset(): void {
		WP_CLI::reset();
		reset_format_items_calls();
	}

	/**
	 * Get all success messages.
	 *
	 * @return string[] Success messages.
	 */
	public function get_success_messages(): array {
		return array_map(
			fn( array $call ) => $call['args'][0],
			WP_CLI::get_calls_for( 'success' )
		);
	}

	/**
	 * Get all error messages.
	 *
	 * @return string[] Error messages.
	 */
	public function get_error_messages(): array {
		return array_map(
			fn( array $call ) => $call['args'][0],
			WP_CLI::get_calls_for( 'error' )
		);
	}

	/**
	 * Get all warning messages.
	 *
	 * @return string[] Warning messages.
	 */
	public function get_warning_messages(): array {
		return array_map(
			fn( array $call ) => $call['args'][0],
			WP_CLI::get_calls_for( 'warning' )
		);
	}

	/**
	 * Get all line output.
	 *
	 * @return string[] Line output.
	 */
	public function get_lines(): array {
		return array_map(
			fn( array $call ) => $call['args'][0],
			WP_CLI::get_calls_for( 'line' )
		);
	}

	/**
	 * Get all log output.
	 *
	 * @return string[] Log output.
	 */
	public function get_logs(): array {
		return array_map(
			fn( array $call ) => $call['args'][0],
			WP_CLI::get_calls_for( 'log' )
		);
	}

	/**
	 * Get all output as a single string.
	 *
	 * @return string All output joined by newlines.
	 */
	public function get_all_output(): string {
		$output = [];

		foreach ( WP_CLI::get_calls() as $call ) {
			if ( in_array( $call['method'], [ 'success', 'error', 'warning', 'line', 'log' ], true ) ) {
				$output[] = sprintf( '[%s] %s', $call['method'], $call['args'][0] ?? '' );
			}
		}

		return implode( "\n", $output );
	}

	/**
	 * Check if success was called.
	 *
	 * @return bool
	 */
	public function has_success(): bool {
		return WP_CLI::was_called( 'success' );
	}

	/**
	 * Check if error was called.
	 *
	 * @return bool
	 */
	public function has_error(): bool {
		return WP_CLI::was_called( 'error' );
	}

	/**
	 * Check if warning was called.
	 *
	 * @return bool
	 */
	public function has_warning(): bool {
		return WP_CLI::was_called( 'warning' );
	}

	/**
	 * Check if confirm was called.
	 *
	 * @return bool
	 */
	public function was_confirm_called(): bool {
		return WP_CLI::was_called( 'confirm' );
	}

	/**
	 * Get format_items calls.
	 *
	 * @return array<int, array{format: string, items: array, columns: array}>
	 */
	public function get_format_items_calls(): array {
		return get_format_items_calls();
	}

	/**
	 * Check if output contains a string.
	 *
	 * @param string $needle String to search for.
	 * @return bool
	 */
	public function output_contains( string $needle ): bool {
		return str_contains( $this->get_all_output(), $needle );
	}

	/**
	 * Get the last success message.
	 *
	 * @return string|null
	 */
	public function get_last_success(): ?string {
		$messages = $this->get_success_messages();
		return $messages ? end( $messages ) : null;
	}

	/**
	 * Get the last error message.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		$messages = $this->get_error_messages();
		return $messages ? end( $messages ) : null;
	}

	/**
	 * Get the last warning message.
	 *
	 * @return string|null
	 */
	public function get_last_warning(): ?string {
		$messages = $this->get_warning_messages();
		return $messages ? end( $messages ) : null;
	}
}
