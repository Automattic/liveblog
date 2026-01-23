<?php
/**
 * Template for a single key event in the timeline.
 *
 * @package Liveblog
 */

use Automattic\Liveblog\Application\Presenter\EntryPresenter;

?>
<li class="<?php echo esc_attr( $css_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<a class="link" href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>" data-entry-id="<?php echo esc_attr( $entry_id ); ?>">
		<span class="date liveblog-time-update"><?php echo esc_html( $entry_date ); ?> - <?php echo esc_html( $entry_time ); ?></span>
		<span class="title"><?php echo wp_kses_post( apply_filters( 'liveblog_key_event_content', $content, $post_id ) ); ?></span>
	</a>
	<?php if ( EntryPresenter::is_liveblog_editable( $post_id ) ) { ?>
		<span class="dashicons dashicons-no liveblog-key-event-delete" data-entry-id="<?php echo esc_attr( $entry_id ); ?>"></span>
	<?php } ?>
</li>
