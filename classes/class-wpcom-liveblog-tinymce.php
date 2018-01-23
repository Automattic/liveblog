<?php

class WPCOM_Liveblog_TinyMCE {
	public static function load() {
		add_action( 'init', array( __CLASS__, 'setup_plugin') );
		add_action ( 'after_wp_tiny_mce', array( __CLASS__, 'extra_vars') );
	}

	public static function setup_plugin() {
		if ( ! current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) ) {
			return;
		}

		if ( get_user_option( 'rich_editing' ) !== 'true') {
			return;
		}

		add_filter( 'mce_external_plugins', array( __CLASS__, 'add_buttons' ) );
		add_filter( 'mce_buttons', array( __CLASS__, 'register_buttons' ) );
	}

	public static function add_buttons( $plugin_array ) {
		$plugin_array['liveblog_button'] = plugin_dir_url( __DIR__ ) . 'assets/dashboard/tinymce-liveblog-shortcode-button.js';

		return $plugin_array;
	}

	public static function register_buttons( $buttons ) {
		array_push( $buttons, 'liveblog_button' );
		return $buttons;
	}

	public static function extra_vars() {}
}
