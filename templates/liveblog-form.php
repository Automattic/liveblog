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
				<?php if ( apply_filters( 'liveblog_rich_text_editing_allowed', true ) ): ?>
					<label class="liveblog-html-edit-toggle">
						<input type="checkbox">
						<?php esc_html_e( 'Edit HTML', 'liveblog' ) ?>
					</label>
					<div class="liveblog-rich-form-entry">
						<div class="liveblog-edit-commands">
							<button type="button" class="liveblog-formatting-command" data-command="bold"><?php esc_attr_e( 'Bold', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="italic"><?php esc_attr_e( 'Italics', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="underline"><?php esc_attr_e( 'Underline', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="strikeThrough"><?php esc_attr_e( 'Srike-through', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="insertImage"><?php esc_attr_e( 'Image', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="createLink"><?php esc_attr_e( 'Link', 'liveblog' ); ?></button>
							<button type="button" class="liveblog-formatting-command" data-command="removeFormat"><?php esc_attr_e( 'Reset', 'liveblog' ); ?></button>
						</div>
						<div class="liveblog-form-rich-entry" contenteditable="true" title="<?php esc_attr_e( "Remember: keep it short! To insert an image, drag and drop it here.", 'liveblog' ); ?>">{{content}}</div>
					</div>
				<?php endif; ?>
				<div class="liveblog-submit-wrapper">
					<span class="liveblog-submit-spinner"></span>
					<input type="button" class="liveblog-form-entry-submit button" value="{{submit_label}}" />
					<a href="#" class="cancel"><?php _e( 'Cancel', 'liveblog' ); ?></a>
					<a href="#" class="liveblog-entry-delete"><?php _e( 'Delete', 'liveblog' ); ?></a>
				</div>
			</div>
			<div class="liveblog-preview">
			</div>
	</fieldset>
</script>
