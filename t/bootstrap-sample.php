<?php
define( 'WP_TESTS_PATH', 'Enter the path to your wordpress-tests' );

$GLOBALS['wp_tests_options'] = array(
	/*
    'active_plugins' => array( 'plugin-dir/plugin-file.php' ),
	*/

);

require rtrim( WP_TESTS_PATH, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'init.php';
