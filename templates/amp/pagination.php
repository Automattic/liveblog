<div pagination>
	<nav aria-label="amp live list pagination" class="liveblog-pagination">
			<div>
			<?php $links = $this->get( 'links' ); ?>

			<?php if ( $links->prev ): ?>
				<a href="<?php echo esc_url( $links->first ); ?>" title="<?php _e( 'First', 'liveblog' ); ?>" class="liveblog-btn liveblog-pagination-btn" data-link-name="">First</a>
				<a href="<?php echo esc_url( $links->prev ); ?>" title="<?php _e( 'Prev', 'liveblog' ); ?>" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-prev" data-link-name="">Prev</a>
			<?php endif; ?>
			</div>

			<span class="liveblog-pagination-pages"><?php echo esc_html( $this->get( 'page' ) ); ?> of <?php echo esc_html( $this->get( 'pages' ) ); ?></span>
			<div>
			<?php if ( $links->next ) : ?>
				<a href="<?php echo esc_url( $links->next ); ?>" title="<?php _e( 'Next', 'liveblog' ); ?>" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Next</a>
				<a href="<?php echo esc_url( $links->last ); ?>" title="<?php _e( 'Last', 'liveblog' ); ?>" class="liveblog-btn liveblog-pagination-btn liveblog-pagination-next" data-link-name="">Last</a>
			<?php endif; ?>
			</div>
	</nav>
</div>
