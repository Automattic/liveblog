<?php if ( $show_archived_message ): ?>
<div class="liveblog-archived-message">
	<?php printf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.' , 'liveblog'), get_edit_post_link() . '#liveblog' ); ?>
</div>
<?php endif; ?>

<?php if ( ! current_user_can( 'edit_posts' ) ): ?>
<div class="liveblog-notification-settings-container">
	<div class="liveblog-notification-settings">
		<label class="liveblog-notification-enable-label">
			<input type="checkbox" class="liveblog-notification-enable"> <?php printf( __( 'Enable browser notifications for key events' ) ); ?>
		</label>
	</div>
</div>
<?php endif; ?>

<div id="liveblog-entries">

	<?php foreach ( (array) $entries as $entry ) : ?>

		<?php echo $entry->render(); ?>

	<?php endforeach; ?>

	<div id="liveblog-fixed-nag">
		<a href="#">
		</a>
	</div>

</div>
