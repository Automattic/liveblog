<div class="liveblog-messages">
</div>
<div class="liveblog-tabs">
	<fieldset class="liveblog-actions">
		<legend>
			<ul>
				<li><a href="#liveblog-new-entry"><?php _e( 'New Entry', 'liveblog' ); ?></a></li>
				<li>&bull;</li>
				<li><a href="#liveblog-preview"><?php _e( 'Preview', 'liveblog' ); ?></a></li>
			</ul>
		</legend>
			<div id="liveblog-new-entry">
				<textarea placeholder="<?php esc_attr_e( "Remember: keep it short! To insert an image, drag and drop it here.", 'liveblog' ); ?>" class="liveblog-form-entry" name="liveblog-form-entry" cols="50" rows="5"></textarea>
				<div class="liveblog-submit-wrapper">
					<span class="liveblog-submit-spinner"></span>
					<input type="button" class="liveblog-form-entry-submit button" value="<?php esc_attr_e( 'Publish Update', 'liveblog' ); ?>" />
					<a href="#" class="cancel">Cancel</a>
					<?php echo wp_nonce_field( self::nonce_key, self::nonce_key, false, false ); ?>
				</div>
			</div>
			<div id="liveblog-preview">
			</div>
	</fieldset>
</div>
