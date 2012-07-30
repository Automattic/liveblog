<div id="liveblog-entries">

	<?php foreach ( (array) array_reverse( $entries ) as $entry ) : ?>
	
		<?php echo $entry->render(); ?>

	<?php endforeach; ?>

</div>
