<div class="wrap">
	<h1>Liveblog Settings</h1>
	<form method="POST" action="options.php">
		<?php $options = WPCOM_Liveblog::$auto_archive_days; ?>
		<?php settings_fields( 'liveblog_options' ); ?>
		<?php do_settings_sections( 'liveblog-settings' ); ?>
		<?php submit_button(); ?>
	</form>
</div>
