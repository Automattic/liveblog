<?php
define( 'WP_TESTS_PATH', 'Enter the path to your unit-tests checkout' );

$GLOBALS['wp_tests_options'] = array(
    'active_plugins' => array( 'liveblog/liveblog.php' ),
);

require rtrim( WP_TESTS_PATH, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
