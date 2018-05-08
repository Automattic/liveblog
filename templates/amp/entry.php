<div class="liveblog-entry" id="post1"
  data-sort-time="20180424142720">

	<aside class="liveblog-entry-aside">
		<a class="liveblog-meta-time" href="#" target="_blank">
			<span>1 day ago</span>
			<span><?php echo esc_html ( $this->get('time') ); ?> </span>
		</a>
	</aside>

	<div class="liveblog-entry-main">
		<header class="liveblog-meta-authors">

			<?php foreach ($this->get('authors') as $author) : ?>


			<?php $avatar_url = get_avatar_url( $author['id'] ); ?>


			<div class="liveblog-meta-author">
				<div class="liveblog-meta-author-avatar">
					<amp-img alt="A view of the sea"
					src="<?php echo esc_html ( $avatar_url ); ?>"
					width="20"
					height="20"
					layout="responsive">
					</amp-img>
				</div>
				<span class="liveblog-meta-author-name"><?php echo esc_html( $author['name'] ); ?></span>
			</div>

			<?php endforeach ?>
		</header>

		<div class="liveblog-entry-content">
			<?php echo $this->get('content') ?>
		</div>
	</div>


</div>
