<?php $div_id = WPCOM_Liveblog::comment_element_id_base . $entry_id ?>
<div id="<?php echo esc_attr( $div_id ); ?>" <?php echo $css_classes; ?> data-timestamp="<?php echo $timestamp; ?>">
	<header class="liveblog-meta">
		<span class="liveblog-author-avatar"><?php echo $avatar_img; ?></span>
		<span class="liveblog-author-name"><?php echo $author_link; ?></span>
		<span class="liveblog-meta-time"><a href="<?php echo esc_url( '#' . $div_id ); ?>"><span class="date"><?php echo $entry_date; ?></span><span class="time"><?php echo $entry_time; ?></span></a></span>
	</header>
	<div class="liveblog-entry-text" data-original-content="<?php echo esc_attr( $original_content ); ?>">
		<?php echo $content; ?>
	</div>
<?php if ( $is_liveblog_editable || $is_liveblog_commenting_open ): ?>
	<ul class="liveblog-entry-actions">
		<li>
			<?php if ( $is_liveblog_editable ): ?>
				<button class="liveblog-entry-edit button-secondary"><?php _e( 'Edit', 'liveblog' ); ?></button>
			<?php endif; ?>
			<?php if ( $is_liveblog_commenting_open ): ?>
				<a
					class="comment-reply-link"
					href="<?php echo esc_url( add_query_arg( 'replytocom', $entry_id ) . '#respond' ) ?>"
					data-comment-id="<?php echo esc_attr( $entry_id ) ?>"
					data-comment-element-id="<?php echo esc_attr( $div_id ) ?>"
					data-respond-element-id="<?php echo esc_attr( 'respond' ) ?>"
					data-post-id="<?php echo esc_attr( get_the_ID() ) ?>"
				><?php _e( 'Reply', 'liveblog' ); ?></a>
			<?php endif; ?>
			<?php if ( $is_liveblog_editable ): ?>
				<button class="liveblog-entry-delete button-secondary"><?php _e( 'Delete', 'liveblog' ); ?></button>
			<?php endif; ?>
		</li>
	</ul>
<?php endif; ?>

<?php if ( $is_liveblog_commenting_open ): ?>
	<details class="liveblog-reply-comments"  <?php if (empty( $reply_comments )): ?> hidden <?php endif; ?>>
		<summary><?php esc_html_e( 'Replies', 'liveblog' ) ?></summary>
		<ol>
			<?php global $comment, $post; ?>
			<?php foreach( $reply_comments as $comment ): ?>
				<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
					<article id="comment-<?php comment_ID(); ?>" class="comment">
						<header class="comment-meta comment-author vcard">
							<?php
								echo get_avatar( $comment, 44 );
								printf( '<cite class="fn">%1$s %2$s</cite>',
									get_comment_author_link(),
									// If current post author is also comment author, make it known visually.
									( $comment->user_id === $post->post_author ) ? '<span> ' . __( 'Post author', 'liveblog' ) . '</span>' : ''
								);
								printf( '<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
									esc_url( get_comment_link( $comment->comment_ID ) ),
									get_comment_time( 'c' ),
									/* translators: 1: date, 2: time */
									sprintf( __( '%1$s at %2$s', 'liveblog' ), get_comment_date(), get_comment_time() )
								);
							?>
						</header><!-- .comment-meta -->

						<?php if ( '0' == $comment->comment_approved ) : ?>
							<p class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'liveblog' ); ?></p>
						<?php endif; ?>

						<section class="comment-content comment">
							<?php comment_text(); ?>
							<?php edit_comment_link( __( 'Edit', 'liveblog' ), '<p class="edit-link">', '</p>' ); ?>
						</section><!-- .comment-content -->

					</article><!-- #comment-## -->
			<?php endforeach; ?>
		</ol>
	</details>
<?php endif; ?>
</div>
