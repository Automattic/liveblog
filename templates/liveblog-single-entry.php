<?php
/**
 * Template for a single liveblog entry.
 *
 * Available variables:
 * - $entry_id            - Entry ID
 * - $post_id             - Post ID
 * - $css_classes         - CSS classes for the entry
 * - $content             - Rendered entry content
 * - $original_content    - Original unrendered content
 * - $authors             - Array of author objects with id, key, name, avatar
 * - $entry_time          - Formatted time string
 * - $entry_timestamp     - ISO 8601 timestamp for datetime attribute
 * - $timestamp           - Unix timestamp
 * - $share_link          - Permalink to this entry
 * - $key_event           - Whether this is a key event
 * - $is_liveblog_editable - Whether the liveblog can be edited
 *
 * @package Liveblog
 */

$entry_classes = 'liveblog-entry ' . esc_attr( $css_classes );
if ( $key_event ) {
	$entry_classes .= ' is-key-event';
}
?>
<article id="liveblog-entry-<?php echo esc_attr( $entry_id ); ?>" class="<?php echo esc_attr( $entry_classes ); ?>" data-timestamp="<?php echo esc_attr( $timestamp ); ?>">
	<header class="liveblog-entry-header">
		<a class="liveblog-meta-time" href="<?php echo esc_url( $share_link ); ?>">
			<time datetime="<?php echo esc_attr( $entry_timestamp ); ?>">
				<?php echo esc_html( $entry_time ); ?>
			</time>
		</a>
		<?php if ( $is_liveblog_editable ) : ?>
			<div class="liveblog-entry-actions">
				<button class="liveblog-btn-edit"><?php esc_html_e( 'Edit', 'liveblog' ); ?></button>
				<button class="liveblog-btn-delete"><?php esc_html_e( 'Delete', 'liveblog' ); ?></button>
			</div>
		<?php endif; ?>
	</header>
	<div class="liveblog-entry-main">
		<?php if ( ! empty( $authors ) && is_array( $authors ) ) : ?>
			<header class="liveblog-meta-authors">
				<?php foreach ( $authors as $author ) : ?>
					<div class="liveblog-meta-author">
						<?php if ( ! empty( $author['avatar'] ) ) : ?>
							<div class="liveblog-meta-author-avatar">
								<?php echo wp_kses_post( $author['avatar'] ); ?>
							</div>
						<?php endif; ?>
						<span class="liveblog-meta-author-name">
							<?php echo wp_kses_post( $author['name'] ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</header>
		<?php endif; ?>
		<div class="liveblog-entry-content" data-original-content="<?php echo esc_attr( $original_content ); ?>">
			<?php echo wp_kses_post( $content ); ?>
		</div>
	</div>
</article>
