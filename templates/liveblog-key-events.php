<?php 
	$entries  = $template_variables['entries'] ?? [];
	$template = $template_variables['template'] ?? '';
	$wrap     = $template_variables['wrap'] ?? '';
	$class    = $template_variables['class'] ?? '';
?>
<?php if ( ! empty( $template_variables['title'] ) ) : ?>
	<div id="liveblog-key-events" data-title="<?php echo esc_attr( $template_variables['title'] ); ?>"></div>
<?php else : ?>
	<div id="liveblog-key-events"></div>
	<?php
endif;
