<?php 

add_filter( 'the_content', function( $content ) {

	global $post;
	
	if ( 'enable' === get_post_meta( $post->ID, 'liveblog', true ) ) {
		return '<div id="wpcom-liveblog-container"></div>';
	}

} );

add_action( 'wp_enqueue_scripts', function() {
	global $post;
	$post_id = false;
	if ( isset( $post->ID) ) {
		$post_id = $post->ID;
	}


	$cache = new WPCOM\Liveblog\Libraries\Entry_Cache( $post_id );
	$last_entry = $cache->get_polling()[0];

	$permissions = new WPCOM\Liveblog\Libraries\Permissions();
	if ( $permissions->edit_others_posts() ) {
		$permissions = 'true';
	} else {
		$permissions = 'false';
	}

	$root = str_replace( '/app' , '', plugin_dir_url( __FILE__ ));
	wp_enqueue_script( 'wpcom-liveblog-app', $root . 'assets/app.js', [], false, true );
	wp_localize_script( 'wpcom-liveblog-app', 'wpcomLiveblog', [
		'api' => esc_url_raw( rest_url() ) . 'liveblog/v2' ,
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'post_id' => $post_id,
		'last_entry' => $last_entry,
		'can_edit' => $permissions,
		'timestamp' => time(),
	] );

	wp_enqueue_style( 'wpcom-liveblog-styles', $root . 'assets/app.css' );
} );



