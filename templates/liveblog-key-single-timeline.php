<li <?php echo $css_classes; ?> data-timestamp="<?php echo $timestamp; ?>">
	<a class="link" href="#liveblog-entry-<?php echo $entry_id; ?>">
		<span class="date liveblog-time-update"><?php echo $entry_date; ?> - <?php echo $entry_time; ?></span>
		<span class="title"><?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content($content, $post_id); ?></span>
	</a>
</li>
