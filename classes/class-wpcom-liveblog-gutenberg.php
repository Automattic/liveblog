<?php

class WPCOM_Liveblog_Gutenberg {
	public static function load() {
		add_action( 'init', array( __CLASS__, 'register_block') );
		add_action( 'init', array( __CLASS__, 'register_key_events_block') );
	}

	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_enqueue_script(
			'liveblog-block',
			plugins_url( 'assets/dashboard/liveblog-block.build.js', __DIR__ ),
			array( 'wp-blocks', 'wp-i18n', 'wp-element' )
		);

		register_block_type( 'gutenberg/liveblog', array(
			'editor_script' => 'liveblog-block',
		) );

		register_meta( 'post', 'liveblog', array(
			'show_in_rest' => true,
			'single' => true,
		) );
	}

	public static function register_key_events_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_enqueue_script(
			'liveblog-key-events',
			plugins_url( 'assets/dashboard/key-events-block.build.js', __DIR__ ),
			array( 'wp-blocks', 'wp-i18n', 'wp-element' )
		);

		register_block_type( 'gutenberg/key-events-block', array(
			'editor_script' => 'liveblog-key-events-block',
		) );
	}
}
