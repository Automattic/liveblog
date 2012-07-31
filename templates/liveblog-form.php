<fieldset id="liveblog-actions">
	<legend><?php _e( "You're Live!", 'liveblog' ); ?></legend>
	<textarea placeholder="<?php _e( "Remember: keep it short! To insert an image, drag and drop it here.", 'liveblog' ); ?>" id="liveblog-form-entry" name="liveblog-form-entry" cols="50" rows="5"></textarea>
	<div class="liveblog-submit-wrapper">
		<span id="liveblog-submit-spinner"></span>
		<input type="button" id="liveblog-form-entry-submit" class="button" value="<?php echo esc_attr__( 'Publish Update', 'liveblog' ); ?>" />
		<?php echo wp_nonce_field( self::nonce_key, self::nonce_key, false, false ); ?>
	</div>
</fieldset>
