<div class="amp-wp-article-liveblog">
<amp-live-list
	layout="container"
	data-poll-interval="15000"
	data-max-items-per-page="1"
	id="amp-live-list-insert-blog">

	<button id="live-list-update-button"
		update
		on="tap:amp-live-list-insert-blog.update"
		class="ampstart-btn caps">You have updates</button>
	<div items>
	<?php foreach ( $this->get( 'entries' ) as $entry ): ?>

		<?php $this->load_part( 'entry', array(
			'content' => $entry->amp_content,
			'authors' => $entry->authors,
			'time'	  => $entry->entry_time
		) ); ?>
	<?php endforeach; ?>
	</div>

	<div pagination>
		<nav aria-label="amp live list pagination" class="liveblog-pagination">
				<div>
				<?php $links = $this->get( 'links' ); ?>
				<?php if ( $links->prev ): ?>
					<a href="<?php echo $links->first ?>" title="First" class="liveblog-btn liveblog-pagination-btn" data-link-name="">First</a>
					<a href="<?php echo $links->prev ?>" title="Prev" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-prev" data-link-name="">Prev</a>
				<?php endif; ?>
				</div>
				<span class="liveblog-pagination-pages"><?php echo $this->get( 'page' ); ?> of <?php echo $this->get( 'pages' ) ?></span>
				<div>
				<?php if ( $links->next ): ?>
					<a href="<?php echo $links->next ?>" title="Next" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Next</a>
				<?php endif; ?>
					<a href="<?php echo $links->last ?>" title="Last" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Last</a>
				</div>
		</nav>
	</div>
</amp-live-list>
</div>
