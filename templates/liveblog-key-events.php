<div class="liveblog-key-events">
	<h2><?php echo $title ?></h2>
	<<?php echo $wrap ?> id="liveblog-key-entries" class="<?php echo $class ?>">
	<?php foreach ( (array) $entries as $entry ) : ?>
			<?php echo $entry->render( $template ); ?>
	<?php endforeach; ?>
	</<?php echo $wrap ?>>
</div>
