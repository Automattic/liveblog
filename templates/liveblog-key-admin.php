<hr/>
<p>
	<label for="liveblog-key-template-name"><?php echo $key_name ?> </label>
	<select id="liveblog-key-template-name" name="liveblog-key-template-name">
		<?php foreach ( $templates as $template ): ?>
		<option <?php if ( $template == $current_key_template ): ?> selected="selected" <?php endif; ?> value="<?php echo $template ?>"><?php echo ucwords( str_replace( '-', ' ', $template ) ) ?></option>
		<?php endforeach; ?>
	</select>
	<label for="liveblog-key-template-format"><?php echo $key_format_name ?> </label>
	<select id="liveblog-key-template-format" name="liveblog-key-template-format">
		<?php foreach ( $formats as $format ): ?>
			<option <?php if ( $format == $current_key_format ): ?> selected="selected" <?php endif; ?> value="<?php echo $format ?>"><?php echo ucwords( str_replace( '-', ' ', $format ) ) ?></option>
		<?php endforeach; ?>
	</select>
	<button type="button" class="button button-primary liveblog-key-template-save" value="liveblog-key-template-save"><?php echo $key_button ?></button>
</p>
<p class="howto"><?php echo $key_description ?></p>
