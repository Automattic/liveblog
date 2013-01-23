<div id="liveblog-entry-<?php echo $entry_id; ?>" <?php echo $css_classes; ?> data-timestamp="<?php echo $timestamp; ?>">
	<header class="liveblog-meta">
		<span class="liveblog-author-avatar"><?php echo $avatar_img; ?></span>
		<span class="liveblog-author-name"><?php echo $author_link; ?></span>
		<span class="liveblog-meta-time"><a href="#liveblog-entry-<?php echo $entry_id; ?>"><span class="date"><?php echo $entry_date; ?></span><span class="time"><?php echo $entry_time; ?></span></a></span>
	</header>
	<div class="liveblog-entry-text" data-original-content="<?php echo esc_attr( $original_content ); ?>">
		<?php echo $content; ?>
	</div>
<?php if ( $is_liveblog_editable ): ?>
	<ul class="liveblog-entry-actions">
		<li><button class="liveblog-entry-edit button-secondary"><?php _e( 'Edit', 'liveblog' ); ?></button><button class="liveblog-entry-delete button-secondary"><?php _e( 'Delete', 'liveblog' ); ?></button></li>
	</ul>
<?php endif; ?>
</div>
