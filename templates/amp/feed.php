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
			'content' => $entry->amp_content
		) ); ?>
	<?php endforeach; ?>
	</div>

	<div pagination>
		<nav aria-label="amp live list pagination">
				<?php $links = $this->get( 'links' ); ?>
				<a href="<?php echo $links->first ?>" title="First" class="" data-link-name="">First</a>
				<?php if ( $links->prev ): ?>
					<a href="<?php echo $links->prev ?>" title="Prev" class="" data-link-name="">Prev</a>
				<?php endif; ?>
				<span><?php echo $this->get( 'page' ); ?> of <?php echo $this->get( 'pages' ) ?></span>
				<?php if ( $links->next ): ?>
					<a href="<?php echo $links->next ?>" title="Next" class="" data-link-name="">Next</a>
				<?php endif; ?>
		<a href="<?php echo $links->last ?>" title="Last" class="" data-link-name="">Last</a>
		</nav>
	</div>
</amp-live-list>
</div>
