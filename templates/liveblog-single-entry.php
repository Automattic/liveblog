<div
	id="liveblog-entry-<?php echo esc_attr( $original_entry ); ?>"
	data-entry-id="<?php echo esc_attr( $entry_id ); ?>"
	data-timestamp="<?php echo esc_attr( $timestamp ); ?>"
	class="<?php echo esc_attr( $css_classes ); ?>"
	data-sort-time="<?php echo esc_attr( $timestamp ); ?>"
	<?php if ( $updated_timestamp ) : ?> data-update-time="<?php echo esc_attr( $updated_timestamp ); ?>" <?php endif; ?>
>
	<header class="liveblog-meta">
		<span class="liveblog-author-avatar"><?php echo wp_kses_post( $avatar_img ); ?></span>
		<span class="liveblog-author-name"><?php echo wp_kses_post( $author_link ); ?></span>
		<span class="liveblog-meta-time"><a href="#liveblog-entry-<?php echo absint( $entry_id ); ?>" class="liveblog-time-update"><span class="date"><?php echo esc_html( $entry_date ); ?></span><span class="time"><?php echo esc_html( $entry_time ); ?></span></a></span>
	</header>
	<div class="liveblog-entry-text" data-original-content="<?php echo esc_attr( $original_content ); ?>">
		<?php echo $content; ?>
	</div>
	<?php if ( $is_liveblog_editable ): ?>
		<ul class="liveblog-entry-actions">
			<li><button class="liveblog-entry-edit button-secondary"><?php esc_html_e( 'Edit', 'liveblog' ); ?></button><button class="liveblog-entry-delete button-secondary"><?php esc_html_e( 'Delete', 'liveblog' ); ?></button></li>
		</ul>
	<?php endif; ?>
</div>
