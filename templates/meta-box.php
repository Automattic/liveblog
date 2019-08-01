<p class="error"></p>
<p class="success"><?php echo esc_html( $update_text ); ?></p>
<h2><?php echo wp_kses_post( $active_text ); ?></h2>
<ul>
<?php
foreach ( $buttons as $button ) :
	if ( $button['disabled'] ) {
		$button['primary']     = false;
		$button['description'] = '<span class="disabled">' . esc_html( $button['description'] ) . '</span>';
	}
	?>
<li>
	<button class="button <?php echo $button['primary'] ? 'button-primary' : ''; ?>" <?php echo $button['disabled'] ? 'disabled="disabled"' : ''; ?> value="<?php echo esc_attr( $button['value'] ); ?>">
		<?php echo esc_html( $button['text'] ); ?>
	</button>
	<?php echo wp_kses_post( $button['description'] ); ?>
</li>
<?php endforeach; ?>
</ul>
<?php
foreach ( $extra_fields as $fields ) :
	echo wp_kses( $fields, WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
endforeach;
