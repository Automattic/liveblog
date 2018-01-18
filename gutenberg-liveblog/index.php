<?php

defined( 'ABSPATH' ) || exit;

function bb_register_liveblog_block () {
	if ( function_exists( 'register_block_type' ) ) {
		wp_enqueue_script(
			'bb-liveblog-script',
			plugins_url( 'block.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-i18n', 'wp-element' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'block.build.js' )
		);

		register_block_type( 'gutenberg/liveblog', array(
			'editor_script' => 'bb-liveblog-script',
		) );

		register_meta( 'post', 'liveblog', array(
			'show_in_rest' => true,
			'single' => true,
		) );
	}
}

add_action( 'init', 'bb_register_liveblog_block' );
