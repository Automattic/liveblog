<?php
/**
 * WPCLI Utilities for live blog migration
 */

/**
 * Holds methods for WP_CLI command related to live blog migration
 * Class Liveblog_Migration_WP_CLI
 */
class Liveblog_Migration_WP_CLI extends WPCOM_VIP_CLI_Command {

	public static $cpt_slug;

	/*
	 * Customize this to get livepress blogs for your site
	 *
	 * must return an array of post IDs
	 */
	public static function get_liveblog_ids() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_parent=0 AND post_status='publish'",
				self::$cpt_slug
			)
		);
	}

	/**
	 * Convert one live blog from comments to posts
	 *  - Per post:
	 *  - content (post)
	 *  - timestamp (post)
	 *  - headline (post)
	 *  - authors (coauthors plus)
	 *  - livepress comment ID (postmeta)
	 *  - liveblog comment ID (postmeta)
	 *
	 * ## EXAMPLES
	 *
	 *     wp liveblog convert --id=12345
	 *     wp liveblog convert --id=12345 --dry-run
	 *     wp liveblog convert --id=12345 --dry-run=false
	 *     wp liveblog convert --id=12345 --dry-run=false --delete=false
	 *     wp liveblog convert --id=12345 --dry-run=false --delete=true
	 *
	 * @synposis --id [--dry-run] [--delete]
	 *
	 * @subcommand convert
	 */
	public function convert_liveblog( $args, $assoc_args ) {

		if ( ! isset( $assoc_args['id'] ) || ! intval( $assoc_args['id'] ) ) {
			WP_CLI::error( 'You must supply a post ID to convert.' );
			exit;
		}
		$liveblog_id = intval( $assoc_args['id'] );

		if ( isset( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {
			$dry_run = false;
		} else {
			$dry_run = true;
			WP_CLI::line( '!!! Doing a dry-run, no posts will be updated.' );
		}

		if ( isset( $assoc_args['delete'] ) && 'true' === $assoc_args['delete'] ) {
			$delete = true;
		} else {
			$delete = false;
			WP_CLI::line( '!!! No old posts will be deleted.' );
		}

		global $wpdb;
		global $coauthors_plus;

		// when a comment is updated, there will be multiple version of it
		// you want the latest version of the content, but the earliest version of the timestamp

		$live_blog_comment_replacements = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT $wpdb->commentmeta.comment_ID, $wpdb->commentmeta.meta_value
				FROM $wpdb->commentmeta
				JOIN $wpdb->comments ON $wpdb->commentmeta.comment_id = $wpdb->comments.comment_id
				WHERE $wpdb->commentmeta.meta_key='liveblog_replaces' AND $wpdb->comments.comment_post_id = %d",
				$liveblog_id
			)
		);

		$comment_ids_to_skip = [];
		foreach ( $live_blog_comment_replacements as $item ) {
			// skip anything marked as being replaced
			$comment_ids_to_skip[] = $item->comment_ID;
		}
		$comment_ids_to_skip_string = join( ', ', $comment_ids_to_skip );

		// avoid generate NOT IN (), which is invalid SQL
		if ( ! $comment_ids_to_skip_string ) {
			$comment_ids_to_skip_string = '-1';
		}

		$live_blog_comments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT comment_ID, comment_content, comment_date, comment_date_gmt
				FROM $wpdb->comments
				WHERE comment_type = 'liveblog' AND comment_post_ID = %d AND comment_ID NOT IN ($comment_ids_to_skip_string)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'ORDER BY comment_date_gmt ASC',
				$liveblog_id
			)
		);
		if ( $live_blog_comments ) {
			WP_CLI::line( 'Found ' . count( $live_blog_comments ) . ' comments' );
		} else {
			WP_CLI::warning( 'No comments found for post ID ' . $liveblog_id . '. Skipping.' );
			return;
		}

		$post_count = 0;
		foreach ( $live_blog_comments as $lb_comment ) {
			WP_CLI::line( 'Found live blog comment ID ' . $lb_comment->comment_ID );
			$content = trim( $lb_comment->comment_content );
			if ( ! $content ) {
				WP_CLI::line( 'Skipping blank comment ' . $lb_comment->comment_ID );
				continue;
			}

			$headline     = get_comment_meta( $lb_comment->comment_ID, 'liveblog_headline', true );
			$authors      = get_comment_meta( $lb_comment->comment_ID, 'liveblog_contributors', true );
			$livepress_id = get_comment_meta( $lb_comment->comment_ID, 'livepress_id', true );

			if ( ! $dry_run ) {
				$new_entry_id = wp_insert_post(
					[
						'post_parent'   => $liveblog_id,
						'post_content'  => $content,
						'post_title'    => $headline,
						'post_type'     => self::$cpt_slug,
						'post_status'   => 'publish',
						'post_date'     => $lb_comment->comment_date,
						'post_date_gmt' => $lb_comment->comment_date_gmt,
					]
				);

				if ( $new_entry_id ) {
					WP_CLI::line( 'Inserted live blog comment ID ' . $lb_comment->comment_ID . ' as post ID ' . $new_entry_id );

					if ( $authors ) {
						$result = $coauthors_plus->add_coauthors( $new_entry_id, $authors, false, 'id' );
						WP_CLI::line( 'Added authors for comment ' . $lb_comment->comment_ID . ' (post ' . $new_entry_id . ') are ' . join( ',', $authors ) );
					}

					if ( $livepress_id ) {
						update_post_meta( $new_entry_id, 'livepress_id', $livepress_id );
					}
					update_post_meta( $new_entry_id, 'liveblog_id', $lb_comment->comment_ID );

					// delete post. wp_delete_post() also deletes postmeta.
					if ( $delete ) {
						if ( false === wp_delete_comment( $lb_comment->comment_ID, true ) ) {
							WP_CLI::error( 'Failed to delete comment ' . $lb_comment->comment_ID );
						} else {
							WP_CLI::line( 'Deleted comment ' . $lb_comment->comment_ID );
						}
					}
				} else {
					WP_CLI::error( 'Failed to create post for liveblog comment ID ' . $lb_comment->comment_ID );
				}
			} else {
				if ( $authors ) {
					WP_CLI::line( 'Authors for comment ' . $lb_comment->comment_ID . ' are ' . join( ',', $authors ) );
				}
			}

			$post_count++;
		}

		if ( ! $dry_run ) {
			WP_CLI::success( 'Converted live blog ID ' . $liveblog_id );
			wp_cache_flush();
		}
	}

	/**
	 * Migrate all live blog comments to posts
	 *
	 * ## EXAMPLES
	 *
	 *     wp liveblog migrate
	 *     wp liveblog migrate --dry-run
	 *     wp liveblog migrate --dry-run=false --delete=true
	 *
	 * @synposis --dry-run [--delete]
	 *
	 * @subcommand migrate
	 */
	public function migrate_liveblog( $args, $assoc_args ) {
		if ( isset( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {
			$dry_run = 'false';
		} else {
			$dry_run = 'true';
			WP_CLI::line( '!!! Doing a dry-run, no posts will be updated.' );
		}

		if ( isset( $assoc_args['delete'] ) && 'true' === $assoc_args['delete'] ) {
			$delete = 'true';
		} else {
			$delete = 'false';
			WP_CLI::line( '!!! No old posts will be deleted.' );
		}

		$live_blogs = self::get_liveblog_ids();
		WP_CLI::line( 'Found ' . count( $live_blogs ) . ' live blogs' );

		$blog_count = 0;
		foreach ( $live_blogs as $lb ) {
			WP_CLI::line( 'Found live blog ID ' . $lb->ID );
			$blog_count++;

			self::convert_liveblog(
				[],
				[
					'id'      => $lb->ID,
					'dry-run' => $dry_run,
					'delete'  => $delete,
				]
			);

			if ( 0 === $blog_count % 5 ) {
				WP_CLI::line( 'sleeping' );
				$this->stop_the_insanity();
				sleep( 5 );
			}
		}
	}
}

add_action(
	'init',
	function() {
		Liveblog_Migration_WP_CLI::$cpt_slug = apply_filters( 'wpcom_liveblog_cpt_slug', WPCOM_Liveblog_CPT::DEFAULT_CPT_SLUG );
	}
);
WP_CLI::add_command( 'liveblog', 'Liveblog_Migration_WP_CLI' );
