<?php

$GLOBALS['wp_tests_options'] = array(
    'active_plugins' => array( basename( dirname( dirname( __FILE__ ) ) ) . '/liveblog.php' ),
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

