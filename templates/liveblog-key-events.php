<div class="liveblog-key-events">
	<?php if( 'false' != $title ): ?>
	<h2><?php echo $title ?></h2>
	<?php endif; ?>
	<<?php echo $wrap ?> class="<?php echo $class ?> liveblog-key-entries">
	<?php foreach ( (array) $entries as $entry ) : ?>
			<?php echo $entry->render( $template ); ?>
	<?php endforeach; ?>
	</<?php echo $wrap ?>>
</div>
