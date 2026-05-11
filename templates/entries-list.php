<?php
/**
 * Entries list template.
 *
 * @package Automattic\Liveblog
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Application\Config\LazyloadConfiguration;

/**
 * Template variables for the entries list.
 *
 * @var LiveblogPost $liveblog_post Parent liveblog post.
 * @var Entry[]      $entries      Array of Entry entities.
 */

?>

<div id="liveblog-entries"
	class="liveblog-entries"
	data-post-id="<?php echo esc_attr( $liveblog_post->id() ); ?>"
	data-last-timestamp="<?php echo esc_attr( $entries && ! empty( $entries ) ? end( $entries )->timestamp() : 0 ); ?>"
	<?php if ( $liveblog_post->is_archived() ) : ?>
		data-is-archived="true"
	<?php endif; ?>>

	<?php if ( empty( $entries ) ) : ?>
		<p class="liveblog-empty"><?php esc_html_e( 'No entries yet.', 'liveblog' ); ?></p>
	<?php else : ?>
		<?php foreach ( $entries as $entry ) : ?>
			<?php include 'entry.php'; ?>
		<?php endforeach; ?>

		<?php 
		// Only show "Load More" if lazyload is enabled AND there are more entries to load.
		$lazyload_config = new LazyloadConfiguration();
		if ( $lazyload_config->is_enabled() && ! empty( $has_more_entries ) && $has_more_entries ) : 
			?>
			<div id="liveblog-load-more" class="liveblog-load-more" style="display: none;">
				<button class="button" type="button">
					<?php esc_html_e( 'Load more entries', 'liveblog' ); ?>
				</button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>