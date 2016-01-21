<div class="error">
<p>
<?php printf(
	esc_html__( 'Your current PHP is version %1$s, which is too old to run the Liveblog plugin with WebSocket support enabled. The minimum required version is %2$s. Please, either update PHP or disable WebSocket support by removing or setting to false the constant LIVEBLOG_USE_SOCKETIO in wp-config.php.', 'liveblog' ),
	$php_version,
	$php_min_version
); ?>
</p>
</div>
