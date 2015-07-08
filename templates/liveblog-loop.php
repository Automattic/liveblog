<?php if ( $show_archived_message ): ?>
<div class="liveblog-archived-message">
	<?php printf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.' , 'liveblog'), get_edit_post_link() . '#liveblog' ); ?>
</div>
<?php endif; ?>

<?php if ( ! current_user_can( 'edit_posts' ) ): ?>
<div class="liveblog-notification-settings-container">
	<a class="liveblog-notification-settings-toggle" href="#"><?php printf( __( 'Notification settings' ) ); ?></a>

	<div class="liveblog-notification-settings">
		<label for="liveblog-notification-enable">
			<input class="liveblog-notification-enable" type="checkbox"> <?php printf( __( 'Enable browser notifications' ) ); ?>
		</label>

		<form class="liveblog-notification-options">
			<label>
				<input type="checkbox" class="liveblog-notification-key"> <?php printf( __( 'Subscribe to key events' ) ); ?>
			</label>
			<label>
				<input type="checkbox" class="liveblog-notification-alerts"> <?php printf( __( 'Subscribe to author alerts ') ); ?>
			</label>
			<label> <?php printf( __( 'Tags to subscribe to (space separated):' ) ); ?>
				<div class="liveblog-notification-tags-wrap">
					<input type="text" class="liveblog-notification-tags">
					<div class="liveblog-notification-saved"></div>
				</div>
			</label>
		</form>
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
