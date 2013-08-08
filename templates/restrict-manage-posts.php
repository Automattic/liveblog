<select name="liveblog_state">
	<?php foreach( $options as $value => $text ): ?>
		<option value="<?php echo esc_attr( $value ) ?>" <?php selected( $value, get_query_var( 'liveblog_state' ) ) ?> >
			<?php echo esc_html( $text ) ?>
		</option>
	<?php endforeach; ?>
</select>
