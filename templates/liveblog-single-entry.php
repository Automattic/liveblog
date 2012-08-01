<div id="liveblog-entry-<?php echo $entry_id; ?>" <?php echo $css_classes; ?>>
	<header class="liveblog-meta">
		<span class="liveblog-author-avatar"><?php echo $avatar_img; ?></span>
		<span class="liveblog-author-name"><?php echo $author_link; ?></span>
		<span class="liveblog-meta-time"><a href="#liveblog-entry-<?php echo $entry_id; ?>"><?php echo $entry_time; ?></a></span>
	</header>
	<div class="liveblog-entry-text">
		<?php echo $content; ?>
	</div>
	<ul class="liveblog-entry-actions">
		<li><button class="liveblog-entry-delete"><?php _e( 'Delete', 'liveblog' ); ?></button></li>
	</ul>
</div>
