<div id="liveblog-entry-<?php echo $entry_id ?>" <?php echo $css_classes ?>>
	<div class="liveblog-entry-text">
		<?php echo $comment_text ?>
	</div>
	<header class="liveblog-meta">
		<span class="liveblog-author-avatar"><?php echo $avatar_img ?></span>
		<span class="liveblog-author-name"><?php $author_link ?></span>
		<span class="liveblog-meta-time"><a href="#liveblog-entry-<?php echo $entry_id ?>"><?php echo $entry_time ?></a></span>
	</header>
</div>
