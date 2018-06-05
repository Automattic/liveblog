<div id="liveblog-messages">
</div>
<script type="text/template" id="liveblog-form-template">
	<fieldset class="liveblog-actions">
		<legend>
			<ul>
				<li class="active entry"><a href="#">{{entry_tab_label}}</a></li>
				<li>&bull;</li>
				<li class="preview"><a href="#"><?php esc_html_e( 'Preview', 'liveblog' ); ?></a></li>
			</ul>
		</legend>
			<div class="liveblog-edit-entry">
				<textarea placeholder="<?php esc_attr_e( 'Remember: keep it short! To insert an image, drag and drop it here.', 'liveblog' ); ?>" class="liveblog-form-entry" name="liveblog-form-entry" cols="50" rows="5">{{content}}</textarea>
				<?php if ( apply_filters( 'liveblog_rich_text_editing_allowed', true ) ) : ?>
					<label class="liveblog-html-edit-toggle">
						<input type="checkbox">
						<?php esc_html_e( 'Edit HTML', 'liveblog' ); ?>
					</label>
					<div class="liveblog-rich-form-entry">
						<div class="liveblog-edit-commands">
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="bold" title="<?php esc_attr_e( 'Bold (Ctrl/Cmd + B)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Bold', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="italic" title="<?php esc_attr_e( 'Italics (Ctrl/Cmd + I)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Italics', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="underline" title="<?php esc_attr_e( 'Underline (Ctrl/Cmd + U)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Underline', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="strikeThrough" title="<?php esc_attr_e( 'Srike-through (Ctrl/Cmd + S)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Srike-through', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="createLink" title="<?php esc_attr_e( 'Link (Ctrl/Cmd + K)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Link', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="unlink" title="<?php esc_attr_e( 'Unlink (Ctrl/Cmd + L)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Unlink', 'liveblog' ); ?></span>
							<span tabindex="0" role="button" class="liveblog-formatting-command" data-command="removeFormat" title="<?php esc_attr_e( 'Remove formatting (Ctrl/Cmd + \\)', 'liveblog' ); ?>"><span class="icon"></span><?php esc_html_e( 'Remove formatting', 'liveblog' ); ?></span>
						</div>
						<div class="liveblog-rich-text-wrapper">
							<div class="liveblog-form-rich-entry" contenteditable="true" title="<?php esc_attr_e( 'Remember: keep it short! To insert an image, drag and drop it here.', 'liveblog' ); ?>"></div>
						</div>
					</div>
				<?php endif; ?>
				<div class="liveblog-submit-wrapper">
					<span class="liveblog-submit-spinner"></span>
					<input type="button" class="liveblog-form-entry-submit button" value="{{submit_label}}" />
					<a href="#" class="cancel"><?php esc_html_e( 'Cancel', 'liveblog' ); ?></a>
					<a href="#" class="liveblog-entry-delete"><?php esc_html_e( 'Delete', 'liveblog' ); ?></a>
				</div>
			</div>
			<div class="liveblog-preview">
			</div>
	</fieldset>
</script>
