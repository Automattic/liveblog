<div id="liveblog-entries">

	<?php foreach ( (array) $entries as $entry ) : ?>

		<?php echo $entry->render(); ?>

	<?php endforeach; ?>

	<div id="liveblog-fixed-nag">
		<a href="#">
		</a>
	</div>

</div>
