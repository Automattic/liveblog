<li <?php echo $css_classes; ?> data-timestamp="<?php echo $timestamp; ?>">
	<a href="#liveblog-entry-<?php echo $entry_id; ?>">
		<span><?php echo $entry_date; ?><?php echo $entry_time; ?></span>
		<?php echo WPCOM_Liveblog_Entry_Key_Events::get_formatted_content($content, $post_id); ?>
	</a>
</li>
