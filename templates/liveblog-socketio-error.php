<?php 
	$message = $template_variables['message'] ?? '';
?>
<div class="error">
	<p>
		<?php echo esc_html( $message ); ?>
	</p>
</div>
