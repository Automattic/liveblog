<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../liveblog.php';
}

function _set_global_wp_query() {
	if ( ! isset( $GLOBALS['wp_query'] ) ) {
		$GLOBALS['wp_the_query'] = new WP_Query(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
tests_add_filter( 'muplugins_loaded', '_set_global_wp_query' );

require $_tests_dir . '/includes/bootstrap.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

require_once dirname( __FILE__ ) . '/class-wp-test-spy-rest-server.php';
