<h2><?php echo $active_text; ?></h2>
<p class="error"></p>
<ul>
<?php
foreach( $buttons as $button ):
	if ( $button['current'] ) {
		$button['description'] = '<span class="disabled">' . $button['description'] . '</span>';
	}
?>
<li>
	<button class="button <?php echo $button['primary']? 'button-primary' : '' ?>" <?php echo $button['current']? 'disabled="disabled"' : '' ?> value="<?php echo esc_attr( $button['value'] ) ?>">
		<?php echo $button['text']; ?>
	</button>
	<?php echo $button['description'] ?>
</li>
<?php endforeach; ?>
</ul>

