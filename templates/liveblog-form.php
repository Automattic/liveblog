<div id="liveblog-messages">
</div>
<div id="liveblog-tabs">
	<fieldset id="liveblog-actions">
		<legend>
			<ul>
				<li><a href="#liveblog-new-entry">New Entry</a></li>
				<li>&bull;</li>
				<li><a href="#liveblog-preview">Preview</a></li>
			</ul>
		</legend>
			<div id="liveblog-new-entry">
				<textarea placeholder="<?php esc_attr_e( "Remember: keep it short! To insert an image, drag and drop it here.", 'liveblog' ); ?>" id="liveblog-form-entry" name="liveblog-form-entry" cols="50" rows="5"></textarea>
				<div class="liveblog-submit-wrapper">
					<span id="liveblog-submit-spinner"></span>
					<input type="button" id="liveblog-form-entry-submit" class="button" value="<?php esc_attr_e( 'Publish Update', 'liveblog' ); ?>" />
					<?php echo wp_nonce_field( self::nonce_key, self::nonce_key, false, false ); ?>
				</div>
			</div>
			<div id="liveblog-preview">
			</div>
	</fieldset>
</div>
