<div id="liveblog-messages">
</div>
<script type="text/template" id="liveblog-form-template">
	<fieldset class="liveblog-actions">
		<legend>
			<ul>
				<li class="active entry"><a href="#">{{entry_tab_label}}</a></li>
				<li>&bull;</li>
				<li class="preview"><a href="#"><?php _e( 'Preview', 'liveblog' ); ?></a></li>
			</ul>
		</legend>
			<div class="liveblog-edit-entry">
				<textarea placeholder="<?php esc_attr_e( "Remember: keep it short! To insert an image, drag and drop it here.", 'liveblog' ); ?>" class="liveblog-form-entry" name="liveblog-form-entry" cols="50" rows="5">{{content}}</textarea>
				<div class="liveblog-submit-wrapper">
					<span class="liveblog-submit-spinner"></span>
					<input type="button" class="liveblog-form-entry-submit button" value="{{submit_label}}" />
					<a href="#" class="cancel">Cancel</a>
				</div>
			</div>
			<div class="liveblog-preview">
			</div>
	</fieldset>
</script>
