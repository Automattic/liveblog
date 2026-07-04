<?php
/**
 * Plugin Name: Liveblog
 * Plugin URI: http://wordpress.org/extend/plugins/liveblog/
 * Description: Empowers website owners to provide rich and engaging live event coverage to a large, distributed audience.
 * Version:     1.12.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      WordPress.com VIP, Big Bite Creative and contributors
 * Author URI: https://github.com/Automattic/liveblog/graphs/contributors
 * Text Domain: liveblog
 *
 * @package Liveblog
 */

if ( ! class_exists( 'WPCOM_Liveblog' ) ) :
	define( 'LIVEBLOG_PLUGIN_FILE', __FILE__ );

	require __DIR__ . '/classes/class-wpcom-liveblog.php';

	WPCOM_Liveblog::load();

	/** Plupload Helpers */
	if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
		require_once ABSPATH . 'wp-includes/load.php';
	}

	if ( ! function_exists( 'size_format' ) ) {
		require_once ABSPATH . 'wp-includes/functions.php';
	}

	if ( ! function_exists( 'wp_max_upload_size' ) ) {
		require_once ABSPATH . 'wp-includes/media.php';
	}
endif;
