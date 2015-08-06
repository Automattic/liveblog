<li class="<?php echo esc_attr( $css_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<a href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>">
		<?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content( $content, $post_id ); ?>
	</a>
</li>
