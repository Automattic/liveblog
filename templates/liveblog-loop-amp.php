<?php if ( $show_archived_message ): ?>
<div class="liveblog-archived-message">
	<?php printf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.' , 'liveblog'), esc_url( get_edit_post_link() ) . '#liveblog' ); ?>
</div>
<?php endif; ?>

<amp-live-list layout="container" data-poll-interval="<?php echo esc_attr( $amp_poll_interval ); ?>" data-max-items-per-page="<?php echo esc_attr( $amp_items_per_page ); ?>" id="amp-live-list-entries">

	<button update on="tap:amp-live-list-entries.update" class="liveblog-load-more"><?php esc_html_e( 'Load more entries&hellip;', 'liveblog' ); ?></button>

	<div items>

		<?php foreach ( (array) $entries as $entry ) : ?>

			<?php echo $entry->render(); ?>

		<?php endforeach; ?>

		<?php foreach ( (array) $deleted_entries as $entry_id ) : ?>

			<div id="liveblog-entry-<?php echo esc_attr( $entry_id ); ?>" data-sort-time="<?php echo current_time( 'timestamp' ); ?>" data-tombstone></div>

		<?php endforeach; ?>

	</div>

</amp-live-list>
