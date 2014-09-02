<?php if ( $show_archived_message ): ?>
<div class="liveblog-archived-message">
	<?php printf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.' , 'liveblog'), get_edit_post_link() . '#liveblog' ); ?>
</div>
<?php endif; ?>
<div id="liveblog-entries">

	<?php foreach ( (array) $processed_entries as $processed_entry ) : ?>

		<?php echo $processed_entry; ?>

	<?php endforeach; ?>

	<div id="liveblog-fixed-nag">
		<a href="#">
		</a>
	</div>

</div>
