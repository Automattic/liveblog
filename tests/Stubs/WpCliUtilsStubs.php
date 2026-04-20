<?php
/**
 * WP-CLI Utils stubs for integration testing.
 *
 * Provides stub implementations of WP_CLI\Utils functions.
 *
 * @package Automattic\Liveblog\Tests\Stubs
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Stub for external namespace.

namespace WP_CLI\Utils;

/**
 * Format items for display.
 *
 * @param string $format  Output format (table, json, csv, etc.).
 * @param array  $items   Items to format.
 * @param array  $columns Columns to display.
 * @return void
 */
function format_items( $format, $items, $columns ) {
	\WP_CLI::line( sprintf( '[format_items: %s, %d items, columns: %s]', $format, count( $items ), implode( ',', $columns ) ) );

	// Store formatted output for assertions.
	$GLOBALS['wp_cli_format_items_calls'][] = array(
		'format'  => $format,
		'items'   => $items,
		'columns' => $columns,
	);
}

/**
 * Create a progress bar.
 *
 * @param string $message Progress bar message.
 * @param int    $count   Total count.
 * @return object Progress bar object with tick() and finish() methods.
 */
function make_progress_bar( $message, $count ) {
	\WP_CLI::line( sprintf( '[progress_bar: %s, %d items]', $message, $count ) );

	return new class() {
		/**
		 * Tick the progress bar.
		 *
		 * @return void
		 */
		public function tick() {}

		/**
		 * Finish the progress bar.
		 *
		 * @return void
		 */
		public function finish() {}
	};
}

/**
 * Reset the format_items call tracking.
 *
 * @return void
 */
function reset_format_items_calls() {
	$GLOBALS['wp_cli_format_items_calls'] = array();
}

/**
 * Get the format_items calls.
 *
 * @return array
 */
function get_format_items_calls() {
	return $GLOBALS['wp_cli_format_items_calls'] ?? array();
}
