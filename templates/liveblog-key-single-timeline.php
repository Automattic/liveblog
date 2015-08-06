<li class="<?php echo esc_attr( $css_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<a class="link" href="#liveblog-entry-<?php echo esc_attr( $entry_id ); ?>">
		<span class="date liveblog-time-update"><?php echo esc_html( $entry_date ); ?> - <?php echo esc_html( $entry_time ); ?></span>
		<span class="title"><?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content($content, $post_id); ?></span>
	</a>
</li>
