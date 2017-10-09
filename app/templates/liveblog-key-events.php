<div class="liveblog-key-events">

    <a name="key-events-widget"></a>

	<?php if( 'false' != $title ): ?>
	<h2><?php echo esc_html( $title ) ?></h2>
	<?php endif; ?>
	<<?php echo $wrap ?> class="<?php echo esc_attr( $class ) ?> liveblog-key-entries">
	<?php foreach ( (array) $entries as $entry ) : ?>
		<?php echo $entry->render( $template ); ?>
	<?php endforeach; ?>
	</<?php echo $wrap ?>>
</div>
