<?php
/**
 * Template for liveblog entries loop.
 *
 * @package Liveblog
 */

if ( $show_archived_message ) : ?>
	<div class="liveblog-archived-message">
		<?php
		// translators: 1: edit post url.
		echo wp_kses_post( sprintf( __( '<strong>This liveblog is archived.</strong> If you need to publish new updates or edit or delete the old ones, you need to <a href="%s">enable it first</a>.', 'liveblog' ), get_edit_post_link() . '#liveblog' ) );
		?>
	</div>
<?php endif; ?>

<div id="liveblog-entries"
	class="liveblog-entries"
	data-post-id="<?php echo esc_attr( (string) ( $post_id ?? 0 ) ); ?>"
	data-last-timestamp="<?php echo esc_attr( (string) ( $last_timestamp ?? 0 ) ); ?>"
	<?php if ( ! empty( $is_archived ) && $is_archived ) : ?>
		data-is-archived="true"
	<?php endif; ?>>

	<?php if ( empty( $entries_html ) ) : ?>
		<p class="liveblog-empty"><?php esc_html_e( 'No entries yet.', 'liveblog' ); ?></p>
	<?php else : ?>
		<?php echo wp_kses_post( $entries_html ); ?>
	<?php endif; ?>

	<?php 
	if ( ! empty( $has_more_entries ) && $has_more_entries ) : 
		?>
		<button class="liveblog-load-more" data-set-index="0"><?php esc_html_e( 'Load more entries&hellip;', 'liveblog' ); ?></button>
	<?php endif; ?>

	<div id="liveblog-fixed-nag" class="liveblog-fixed-bar">
		<a href="#"></a>
	</div>

</div>
