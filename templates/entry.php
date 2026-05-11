<?php
/**
 * Single liveblog entry card template.
 *
 * Renders a liveblog entry as a card with author header,
 * timestamp, content, and optional breakout link.
 *
 * @package Automattic\Liveblog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;

$display_id     = $entry->display_id()->to_int();
$timestamp      = $entry->timestamp();
$entry_post     = get_post( $entry->id()->to_int() );
$breakout_id    = get_post_meta( $entry->id()->to_int(), 'liveblog_breakout_post_id', true );
$breakout_post  = $breakout_id ? get_post( (int) $breakout_id ) : null;
$is_breakout    = $breakout_post && 'publish' === $breakout_post->post_status;
$read_more_text = $is_breakout ? get_post_meta( $entry->id()->to_int(), 'liveblog_breakout_read_more_text', true ) : '';
$read_more_text = $read_more_text ? $read_more_text : __( 'Read more', 'liveblog' );
$card_class     = $is_breakout ? 'liveblog-entry-breakout' : 'liveblog-entry-normal';
?>
<article id="liveblog-entry-<?php echo esc_attr( (string) $display_id ); ?>"
	class="liveblog-entry liveblog-entry-card <?php echo esc_attr( $card_class ); ?>"
	data-entry-id="<?php echo esc_attr( (string) $display_id ); ?>"
	data-timestamp="<?php echo esc_attr( (string) $timestamp ); ?>">

	<header class="liveblog-card-header">
		<span class="liveblog-card-author">
			<?php
			$authors = $entry->authors();
			if ( ! $authors->is_empty() && ! $authors->primary()->is_anonymous() ) {
				echo esc_html( $authors->primary()->name() );
			}
			?>
		</span>
		<time datetime="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>">
			<?php echo esc_html( human_time_diff( $timestamp, time() ) . ' ago' ); ?>
		</time>
		<?php if ( $is_breakout ) : ?>
			<span class="liveblog-breakout-badge"><?php esc_html_e( 'Breakout', 'liveblog' ); ?></span>
		<?php endif; ?>
	</header>

	<?php if ( $entry_post && has_post_thumbnail( $entry_post ) ) : ?>
	<div class="liveblog-card-featured">
		<?php echo wp_kses_post( get_the_post_thumbnail( $entry_post, 'large', array( 'loading' => 'lazy' ) ) ); ?>
	</div>
	<?php endif; ?>

	<div class="liveblog-entry-content liveblog-card-content">
		<?php
		if ( $entry_post ) {
			echo wp_kses_post( apply_filters( 'liveblog_the_content', $entry_post->post_content ) );
		}
		?>
	</div>

	<?php if ( $is_breakout && $breakout_post ) : ?>
	<footer class="liveblog-card-footer">
		<a href="<?php echo esc_url( get_permalink( $breakout_post ) ); ?>" class="liveblog-read-more">
			<?php echo esc_html( $read_more_text ); ?> →
		</a>
	</footer>
	<?php endif; ?>
</article>
