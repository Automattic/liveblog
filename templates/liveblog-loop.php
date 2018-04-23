<?php if ( $show_archived_message ) : ?>
<div class="liveblog-archived-message">
	<?php
	// translators: 1: edit post url
	echo wp_kses_post( sprintf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.', 'liveblog' ), get_edit_post_link() . '#liveblog' ) );
	?>
</div>
<?php endif; ?>

<div id="liveblog-entries">

	<?php foreach ( (array) $entries as $entry ) : ?>

		<?php echo wp_kses_post( $entry->render() ); ?>

	<?php endforeach; ?>

	<?php if ( WPCOM_Liveblog_Lazyloader::is_enabled() ) : ?>

		<button class="liveblog-load-more" data-set-index="0"><?php esc_html_e( 'Load more entries&hellip;', 'liveblog' ); ?></button>

	<?php endif; ?>

	<div id="liveblog-fixed-nag" class="liveblog-fixed-bar">
		<a href="#">
		</a>
	</div>

	<?php if ( WPCOM_Liveblog_Socketio_Loader::is_enabled() ) : ?>
		<div id="liveblog-socketio-error-container" class="liveblog-fixed-bar">
			<p id="liveblog-socketio-error"></p>
		</div>
	<?php endif; ?>

</div>
