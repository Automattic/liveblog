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
	<button name="state" class="button <?php echo $button['primary']? 'button-primary' : '' ?>" <?php disabled( $button['disabled'] ) ?> value="<?php echo esc_attr( $button['value'] ) ?>">
		<?php echo $button['text']; ?>
	</button>
	<?php echo $button['description'] ?>
</li>
<?php endforeach; ?>
</ul>

<?php if ( WPCOM_Liveblog::is_liveblog_commenting_supported() ): ?>
	<p>
		<button name="comment_status" class="button <?php echo esc_attr( $toggle_comment_status === 'open' && ! $is_commenting_toggle_disabled ? 'button-primary' : 'button-secondary' ) ?>" <?php disabled( $is_commenting_toggle_disabled ) ?> value="<?php echo esc_attr( $toggle_comment_status ); ?>">
			<?php echo esc_html( $toggle_comment_btn_text ); ?>
		</button>
		<?php echo esc_html( $comment_status_description ); ?>
	</p>
<?php endif; ?>
