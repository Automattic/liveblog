<div id="liveblog-entries">

	<?php foreach ( (array) $entries as $entry ) : ?>

		<?php echo $entry->render(); ?>

	<?php endforeach; ?>

	<div id="liveblog-fixed-nag">
		<a href="#">
			Click to see
			<div class="num">5</div>
			new updates.
		</a>
	</div>

</div>
