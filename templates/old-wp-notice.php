<div class="error">
<p>
<?php
	// translators: 1: version, 2: update url, 3: deactivate url
	$format_string = __( 'Your current WordPress is version %1$s, which is too old to run the liveblog plugin. The minimum required version is %2$s. Please, either <a href="%3$s">update WordPress</a>, or <a href="%4$s">deactivate the liveblog plugin</a>.', 'liveblog' );
	echo wp_kses(
		sprintf( $format_string, $wp_version, $min_version, esc_url( admin_url( 'update-core.php' ) ), esc_url( admin_url( 'plugins.php#liveblog' ) ) ),
		array( 'a' => array( 'href' => array() ) )
	); ?>
</p>
</div>
