<?php
/**
 * Template for a single key event list item.
 *
 * @package Liveblog
 */

use Automattic\Liveblog\Application\Presenter\EntryPresenter;

?>
<li class="<?php echo esc_attr( $css_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<a href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>" data-entry-id="<?php echo esc_attr( $entry_id ); ?>">
		<?php echo wp_kses_post( apply_filters( 'liveblog_key_event_content', $content, $post_id ) ); ?>
	</a>
	<?php if ( EntryPresenter::is_liveblog_editable( $post_id ) ) { ?>
		<span class="dashicons dashicons-no liveblog-key-event-delete" data-entry-id="<?php echo esc_attr( $entry_id ); ?>"></span>
	<?php } ?>
</li>
