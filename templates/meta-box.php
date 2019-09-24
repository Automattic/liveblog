<?php
	$active_text = $template_variables['active_text'] ?? '';
	$buttons     = $template_variables['buttons'] ?? [];
?>
<hr>
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
	<p><?php echo wp_kses_post( $button['description'] ); ?></p>
</li>
<?php endforeach; ?>
</ul>
