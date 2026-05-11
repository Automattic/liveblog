<?php
/**
 * WP-CLI command to migrate liveblog state from post meta to taxonomy.
 *
 * @package Automattic\Liveblog\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\CLI;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use WP_CLI;
use WP_CLI\Utils;
use WP_Query;

/**
 * Migrates liveblog state from the legacy 'liveblog' post meta
 * to the 'liveblog_state' taxonomy.
 */
final class MigrateToTaxonomyCommand {

	/**
	 * Ensure the liveblog_state taxonomy and its terms are registered.
	 *
	 * The taxonomy may not be registered yet if this command runs
	 * before the init hook fires. Called idempotently — safe to
	 * invoke even if registration has already occurred.
	 *
	 * @return void
	 */
	private function ensure_taxonomy_registered(): void {
		if ( ! taxonomy_exists( LiveblogConfiguration::TAXONOMY ) ) {
			\register_taxonomy(
				LiveblogConfiguration::TAXONOMY,
				array( 'post' ),
				array(
					'public'             => false,
					'show_ui'            => false,
					'show_in_nav_menus'  => false,
					'show_in_quick_edit' => false,
					'show_admin_column'  => false,
					'hierarchical'       => false,
					'rewrite'            => false,
					'query_var'          => false,
				)
			);
		}

		\wp_insert_term( 'Enabled', LiveblogConfiguration::TAXONOMY, array( 'slug' => LiveblogConfiguration::TERM_ENABLED ) );
		\wp_insert_term( 'Archived', LiveblogConfiguration::TAXONOMY, array( 'slug' => LiveblogConfiguration::TERM_ARCHIVED ) );
	}

	/**
	 * Migrate liveblog state from post meta to taxonomy.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview what would be migrated without making changes.
	 *
	 * [--batch-size=<number>]
	 * : Number of posts to process per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview the migration
	 *     wp liveblog migrate-to-taxonomy --dry-run
	 *
	 *     # Run the migration
	 *     wp liveblog migrate-to-taxonomy
	 *
	 *     # Migrate with custom batch size
	 *     wp liveblog migrate-to-taxonomy --batch-size=50
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = absint( $assoc_args['batch-size'] ?? 100 );

		if ( $batch_size < 1 ) {
			WP_CLI::error( 'Batch size must be at least 1.' );
		}

		$this->ensure_taxonomy_registered();

		WP_CLI::line( 'Querying posts with legacy liveblog meta...' );

		$offset   = 0;
		$migrated = 0;
		$total    = 0;
		$progress = null;

		do {
			$query = new WP_Query(
				array(
					'post_type'      => 'post',
					'posts_per_page' => $batch_size,
					'offset'         => $offset,
					'meta_key'       => 'liveblog',
					'meta_compare'   => 'EXISTS',
					'post_status'    => 'any',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			if ( null === $progress && $query->found_posts > 0 ) {
				$total    = $query->found_posts;
				$progress = Utils\make_progress_bar( 'Migrating posts', $total );
			}

			foreach ( $query->posts as $post ) {
				$meta_value = \get_post_meta( $post->ID, 'liveblog', true );

				$term = 'archive' === $meta_value
					? LiveblogConfiguration::TERM_ARCHIVED
					: LiveblogConfiguration::TERM_ENABLED;

				if ( $dry_run ) {
					WP_CLI::line(
						sprintf(
							'Would migrate post %d: meta="%s" → term="%s"',
							$post->ID,
							$meta_value,
							$term
						)
					);
				} else {
					\wp_set_object_terms( $post->ID, $term, LiveblogConfiguration::TAXONOMY, false );
					\delete_post_meta( $post->ID, 'liveblog' );
					\wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );
				}

				++$migrated;

				if ( $progress ) {
					$progress->tick();
				}
			}

			$offset += $batch_size;

			// Stop garbage collection during migration.
			\wp_suspend_cache_invalidation( false );
		} while ( $query->post_count > 0 );

		if ( $progress ) {
			$progress->finish();
		}

		if ( 0 === $migrated ) {
			WP_CLI::success( 'No posts found with legacy liveblog meta. Nothing to migrate.' );
			return;
		}

		$action = $dry_run ? 'Would have migrated' : 'Migrated';
		WP_CLI::success( sprintf( '%s %d post(s).', $action, $migrated ) );
	}
}
