<div class="liveblog-key-events">
	<h2><?php echo $title ?></h2>
	<div id="liveblog-key-entries">
		<?php foreach ( (array) $entries as $entry ) : ?>

			<?php echo $entry->render( $template ); ?>

	<?php endforeach; ?>
	</div>
</div>
