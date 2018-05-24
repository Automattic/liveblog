<div pagination>
	<nav aria-label="amp live list pagination" class="liveblog-pagination">
			<div>
			<?php $links = $this->get( 'links' ); ?>

			<?php if ( $links->prev ): ?>
				<a href="<?php echo esc_url( $links->first ); ?>" title="First" class="liveblog-btn liveblog-pagination-btn" data-link-name="">First</a>
				<a href="<?php echo esc_url( $links->prev ); ?>" title="Prev" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-prev" data-link-name="">Prev</a>
			<?php endif; ?>
			</div>

			<span class="liveblog-pagination-pages"><?php echo esc_html( $this->get( 'page' ) ); ?> of <?php echo esc_html( $this->get( 'pages' ) ); ?></span>
			<div>
			<?php if ( $links->next ) : ?>
				<a href="<?php echo esc_url( $links->next ); ?>" title="Next" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Next</a>
				<a href="<?php echo esc_url( $links->last ); ?>" title="Last" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Last</a>
			<?php endif; ?>
			</div>
	</nav>
</div>
