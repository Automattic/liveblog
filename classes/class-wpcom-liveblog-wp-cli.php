<?php
/**
 * WP-CLI commands for liveblog.
 *
 * @package Liveblog
 */

WP_CLI::add_command( 'liveblog', 'WPCOM_Liveblog_WP_CLI' );

/**
 * Class WPCOM_Liveblog_WP_CLI
 *
 * WP-CLI command class for liveblog management.
 *
 * @phpcs:disable WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli -- Plugin must work outside VIP Go environment.
 */
class WPCOM_Liveblog_WP_CLI extends WP_CLI_Command {
	// @phpcs:enable

	/**
	 * Converts readme.txt to markdown format for GitHub.
	 *
	 * @return void
	 */
	public function readme_for_github() {
		$readme_path = __DIR__ . '/../readme.txt';
		$readme      = file_get_contents( $readme_path ); // @codingStandardsIgnoreLine
		$readme      = $this->listify_meta( $readme );
		$readme      = $this->add_contributors_wp_org_profile_links( $readme );
		$readme      = $this->add_screenshot_links( $readme );
		$readme      = $this->markdownify_headings( $readme );
		echo $readme; // @codingStandardsIgnoreLine
	}

	/**
	 * Fix wp_commentmeta table so archived liveblog posts comments display properly.
	 *
	 * @subcommand fix-archive
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function fix_archive( $args, $assoc_args ) {
		global $wpdb;

		// Grab the dryrun flag from the assoc arguments if its there and define our flag as required.
		$is_dryrun = ( isset( $assoc_args['dryrun'] ) ) ? true : false;

		// Find all liveblogs.
		WP_CLI::log( 'Finding All Live Blog Entries..' );

		$posts = new WP_Query(
			array(
				'order'    => 'ASC',
				'orderby'  => 'ID',
				'meta_key' => 'liveblog', // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_key
			)
		);

		// How many live blogs do we have?
		$total_liveblogs  = count( $posts->posts );
		$current_liveblog = 0;

		// Feedback to the user.
		WP_CLI::log( 'Found ' . $total_liveblogs . ' Live Blogs.' );

		foreach ( $posts->posts as $post ) {

			// Increment the count so we get a more human readable index, inital value becomes 1 rather than 0.
			++$current_liveblog;

			// Tell the user what we are doing, but lets colour this one se we can see its a new Liveblog in the console output.
			WP_CLI::log( WP_CLI::colorize( "%4 Processing Liveblog {$current_liveblog} of {$total_liveblogs} %n" ) );

			// Define the post ID.
			$post_id = $post->ID;

			// Get all entries that have been edited in the liveblog.
			// Query for comments with liveblog_replaces meta (these are edited entries).
			$edit_comments = get_comments(
				array(
					'post_id'  => $post_id,
					'orderby'  => 'comment_date_gmt',
					'order'    => 'ASC',
					'meta_key' => 'liveblog_replaces', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for finding edited entries.
					'status'   => 'liveblog',
				)
			);
			$edit_entries  = array_map(
				function ( $comment ) {
					return (object) array(
						'id'       => (int) $comment->comment_ID,
						'replaces' => (int) get_comment_meta( $comment->comment_ID, 'liveblog_replaces', true ),
					);
				},
				$edit_comments
			);

			// Find correct comment_ids to replace incorrect meta_values.
			$correct_ids_array = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
				$wpdb->prepare(
					"SELECT comment_id FROM $wpdb->comments
					WHERE comment_post_id = %d AND comment_id NOT IN
					( SELECT $wpdb->commentmeta.comment_id FROM $wpdb->commentmeta
					INNER JOIN $wpdb->comments
					ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
					WHERE comment_post_id = %d )
					ORDER BY comment_id ASC",
					$post_id,
					$post_id
				)
			);
			$correct_ids       = wp_list_pluck( $correct_ids_array, 'comment_id' );

			// Replace incorrect meta_value with correct one.
			if ( count( $edit_entries ) > 0 ) {

				// SHow the User how many Edited Entries we've found.
				WP_CLI::log( 'Found ' . count( $edit_entries ) . ' edited entries..' );

				foreach ( $edit_entries as $edit_entry ) {
					$entry_id = $edit_entry->id;

					// Look for replaces property in $correct_ids.
					if ( in_array( $edit_entry->replaces, $correct_ids, true ) ) {

						// The edited entry is accurate so we dont need to do anything.
						WP_CLI::log( 'No action required.. skipping Entry ' . $entry_id );
						continue;
					} else {
						$correct_id_count = count( $correct_ids );
						for ( $i = 0; $i <= $correct_id_count - 1; $i++ ) {

							// Replace with correct meta_value.
							if ( $correct_ids[ $i ] < $entry_id ) {

								// The edited entry needs updating to reflect the correct IDs.
								WP_CLI::log( 'Correcting Entry ' . $entry_id . '...' );

								// If this isnt a dry run we can run the database Update.
								if ( false === $is_dryrun ) {
									$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
										$wpdb->commentmeta,
										array(
											'meta_value' => $correct_ids[ $i ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for WP-CLI repair command.
										),
										array( 'comment_id' => $entry_id )
									);
									update_meta_cache( 'comment', array( $entry_id ) );
								}
							}
						}
					}
				}
			}

			// Find comment_ids object with correct content for replacement.
			$correct_contents = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
				$wpdb->prepare(
					"SELECT comment_id, comment_content
					FROM $wpdb->comments
					WHERE comment_post_id = %d
					GROUP BY comment_content
					HAVING count(comment_content) = 2
					ORDER BY comment_id ASC",
					$post_id
				)
			);

			// Find comment_ids that NEED to be replaced.
			$entries_replace = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
				$wpdb->prepare(
					"SELECT DISTINCT meta_value
					FROM $wpdb->commentmeta
					INNER JOIN $wpdb->comments
					ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
					WHERE comment_post_id = %d
					ORDER BY meta_value ASC",
					$post_id
				)
			);

			// Check to make sure entry content being replaced matches available.
			if ( count( $entries_replace ) === count( $correct_contents ) ) {

				// Counter.
				$replaced = 0;

				// THe edited entry is accurate so we dont need to do anything.
				WP_CLI::log( 'Total of ' . count( $entries_replace ) . ' need action..' );

				foreach ( $entries_replace as $entry_replace ) {
					$content = $correct_contents[ $replaced ]->comment_content;

					if ( false === $is_dryrun ) {
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI bulk repair operation.
							$wpdb->comments,
							array( 'comment_content' => $content ),
							array( 'comment_id' => $entry_replace->meta_value )
						);
						clean_comment_cache( $entry_replace->meta_value );
					}

					// Lets update the user with what we are doing.
					WP_CLI::log( 'Replaced Content in ' . $replaced . ' Entry(ies) so far..' );

					++$replaced;
				}
			}

			// If we have a dry run flag lets just output what we would be looking to do on a live run.
			if ( true === $is_dryrun ) {
				WP_CLI::log( 'Found ' . count( $edit_entries ) . ' Edited Entries on Post ID ' . $post_id );
			}
		}

		if ( true === $is_dryrun ) {
			WP_CLI::success( 'Dry Run Completed. Please check the results and when ready re-run the command without the --dryrun flag.' );
		} else {
			WP_CLI::success( 'Fixed all entries on all liveblog posts!' );
		}
	}

	/**
	 * Convert WordPress readme headings to markdown.
	 *
	 * @param string $readme The readme content.
	 * @return string Modified readme content.
	 */
	private function markdownify_headings( $readme ) {
		return preg_replace_callback(
			'/^\s*(=+)\s*(.*?)\s*=+\s*$/m',
			function ( $matches ) {
				return "\n" . str_repeat( '#', 4 - strlen( $matches[1] ) ) . ' ' . $matches[2] . "\n";
			},
			$readme
		);
	}

	/**
	 * Convert plugin meta section to bulleted list.
	 *
	 * @param string $readme The readme content.
	 * @return string Modified readme content.
	 */
	private function listify_meta( $readme ) {
		return preg_replace_callback(
			'/===\s*\n+(.*?)\n\n/s',
			function ( $matches ) {
				$meta = $matches[1];
				if ( ! $meta ) {
					return $matches[0];
				}
				return "===\n" . preg_replace( '/^/m', '* ', $meta ) . "\n\n";
			},
			$readme
		);
	}

	/**
	 * Add WordPress.org profile links for contributors.
	 *
	 * @param string $readme The readme content.
	 * @return string Modified readme content.
	 */
	private function add_contributors_wp_org_profile_links( $readme ) {
		return preg_replace_callback(
			'/Contributors: (.*)/',
			function ( $matches ) {
				$links = array_filter(
					array_map(
						function ( $username ) {
							return "[$username](http://profiles.wordpress.org/$username)";
						},
						preg_split( '/\s*,\s*/', $matches[1] )
					)
				);
				return 'Contributors: ' . implode( ', ', $links );
			},
			$readme
		);
	}

	/**
	 * Add screenshot image links.
	 *
	 * @param string $readme The readme content.
	 * @return string Modified readme content.
	 */
	private function add_screenshot_links( $readme ) {
		return preg_replace_callback(
			'/==\s*Screenshots\s*==\n(.*?)==/ms',
			function ( $matches ) {
				return "== Screenshots ==\n" . preg_replace( '/^\s*(\d+)\.\s*(.*?)$/m', '![\2](https://raw.github.com/Automattic/liveblog/master/screenshot-\1.png)', $matches[1] ) . "\n==";
			},
			$readme
		);
	}

	/**
	 * Display help information.
	 *
	 * @return void
	 */
	public static function help() {
		WP_CLI::log(
			<<<'HELP'
usage: wp liveblog readme_for_github
	Converts the readme.txt to real markdown to be used as a README.md
HELP
		);
	}
}
