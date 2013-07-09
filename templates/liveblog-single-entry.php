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
</div>
