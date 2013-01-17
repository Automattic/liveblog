<h2><?php echo $active_text; ?></h2>
<p class="error"></p>
<ul>
<?php
foreach( $buttons as $button ):
	if ( $button['disabled'] ) {
		$button['primary'] = false;
		$button['description'] = '<span class="disabled">' . $button['description'] . '</span>';
	}
?>
<li>
	<button class="button <?php echo $button['primary']? 'button-primary' : '' ?>" <?php echo $button['disabled']? 'disabled="disabled"' : '' ?> value="<?php echo esc_attr( $button['value'] ) ?>">
		<?php echo $button['text']; ?>
	</button>
	<?php echo $button['description'] ?>
</li>
<?php endforeach; ?>
</ul>

