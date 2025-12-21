<?php
/**
 * Enable Lexical editor for liveblog testing.
 *
 * This mu-plugin enables the experimental Lexical editor feature flag
 * for testing purposes only. Do not use in production.
 *
 * @package Liveblog
 */

add_filter( 'liveblog_use_lexical_editor', '__return_true' );
