<li class="<?php echo esc_attr( $css_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<a href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>" data-entry-id="<?php echo esc_attr( $entry_id ); ?>">
		<?php echo wp_kses_post( WPCOM_Liveblog_Entry_Key_Events::get_formatted_content( $content, $post_id ) ); ?>
	</a>
	<?php if ( WPCOM_Liveblog::current_user_can_edit_liveblog() ) { ?>
		<span class="dashicons dashicons-no liveblog-key-event-delete" data-entry-id="<?php echo esc_attr( $entry_id ); ?>"></span>
	<?php } ?>
</li>
