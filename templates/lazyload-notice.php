<div class="error">
<p>
<?php
echo wp_kses_post(
	sprintf(
		/* translators: 1: plugin name, 2: plugins page URL */
		__( 'Please <a href="%2$s">deactivate the %1$s plugin</a> as Liveblog itself comes now with built-in lazyloading.', 'liveblog' ),
		$plugin,
		admin_url( 'plugins.php?plugin_status=active' )
	)
);
?>
</p>
</div>
