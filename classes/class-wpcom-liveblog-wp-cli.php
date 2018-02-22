<?php
WP_CLI::add_command( 'liveblog', 'WPCOM_Liveblog_WP_CLI' );

class WPCOM_Liveblog_WP_CLI extends WP_CLI_Command {


	public function readme_for_github() {
		$readme_path = dirname( __FILE__ ) . '/../readme.txt';
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
	*/
	public function fix_archive( $args, $assoc_args ) {
		global $wpdb;

		// Grab the dryrun flag from the assoc arguments if its there and define our falg as rwquired.
		$is_dryrun = ( isset( $assoc_args['dryrun'] ) ) ? true : false;

		// find all liveblogs
		WP_CLI::line( 'Finding All Live Blog Entries..' );

		$posts = new WP_Query(
			array(
				'order'    => 'ASC',
				'orderby'  => 'ID',
				'meta_key' => 'liveblog',
			)
		);

		//How many live blogs do we have?
		$total_liveblogs  = count( $posts->posts );
		$current_liveblog = 0;

		//Feedback to the user
		WP_CLI::line( 'Found ' . $total_liveblogs . ' Live Blogs.' );

		foreach ( $posts->posts as $post ) {

			//Increment the count so we get a more human readable index, inital value becomes 1 rather than 0.
			$current_liveblog ++;

			//Tell the user what we are doing, but lets colour this one se we can see its a new Liveblog in the console output.
			WP_CLI::line( WP_CLI::colorize( "%4 Processing Liveblog {$current_liveblog} of {$total_liveblogs} %n" ) );

			//Define the post ID
			$post_id = $post->ID;

			// get all entries that have been edited in the liveblog
			$entries_query = new WPCOM_Liveblog_Entry_Query( $post_id, WPCOM_Liveblog::KEY );
			$edit_entries  = $entries_query->get_all_edits( array( 'post_id' => $post_id ) );

			// find correct comment_ids to replace incorrect meta_values
			$correct_ids_array = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT comment_id FROM $wpdb->comments
				 WHERE comment_post_id = %d AND comment_id NOT IN
				 ( SELECT $wpdb->commentmeta.comment_id FROM $wpdb->commentmeta
				   INNER JOIN $wpdb->comments
				   ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
				   WHERE comment_post_id = %d )
				 ORDER BY comment_id ASC", $post_id, $post_id
				)
			);
			$correct_ids       = wp_list_pluck( $correct_ids_array, 'comment_id' );

			// replace incorrect meta_value with correct one
			if ( count( $edit_entries ) > 0 ) {

				// SHow the User how many Edited Entries we've found.
				WP_CLI::line( 'Found ' . count( $edit_entries ) . ' edited entries..' );

				foreach ( $edit_entries as $edit_entry ) {
					$entry_id = $edit_entry->get_id();

					// look for replaces property in $correct_ids
					if ( in_array( $edit_entry->replaces, $correct_ids, true ) ) {

						//The edited entry is accurate so we dont need to do anything.
						WP_CLI::line( 'No action required.. skipping Entry ' . $entry_id );
						continue;

					} else {

						$correct_id_count = count( $correct_ids );
						for ( $i = 0; $i <= $correct_id_count - 1; $i++ ) {

							// replace with correct meta_value
							if ( $correct_ids[ $i ] < $entry_id ) {

								//The edited entry needs updating to reflect the correct ID's
								WP_CLI::line( 'Correcting Entry ' . $entry_id . '...' );

								// If this isnt a dry run we can run the database Update.
								if ( false === $is_dryrun ) {
									$wpdb->update(
										$wpdb->commentmeta,
										array( 'meta_value' => $correct_ids[ $i ] ),
										array( 'comment_id' => $entry_id )
									);
								}
							}
						}
					}
				}
			}

			// find comment_ids object with correct content for replacement
			$correct_contents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT comment_id, comment_content
					 FROM $wpdb->comments
					 WHERE comment_post_id = %d
					 GROUP BY comment_content
					 HAVING count(comment_content) = 2
					 ORDER BY comment_id ASC", $post_id
				)
			);

			// find comment_ids that NEED to be replaced
			$entries_replace = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value
					FROM $wpdb->commentmeta
					INNER JOIN $wpdb->comments
					ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
					WHERE comment_post_id = %d
					ORDER BY meta_value ASC", $post_id
				)
			);

			// check to make sure entry content being replaced matches available
			if ( count( $entries_replace ) === count( $correct_contents ) ) {

				// counter
				$replaced = 0;

				//THe edited entry is accurate so we dont need to do anything.
				WP_CLI::line( 'Total of ' . count( $entries_replace ) . ' need action..' );

				foreach ( $entries_replace as $entry_replace ) {

					$content = $correct_contents[ $replaced ]->comment_content;

					if ( false === $is_dryrun ) {
						$wpdb->update(
							$wpdb->comments,
							array( 'comment_content' => $content ),
							array( 'comment_id' => $entry_replace->meta_value )
						);
					}

					//Lets update the user with what we are doing.
					WP_CLI::line( 'Replaced Content in ' . $replaced . ' Entry(ies) so far..' );

					$replaced++;
				}
			}

			//If we have a dry run flag lets just output what we would be looking to do on a live run.
			if ( true === $is_dryrun ) {
				WP_CLI::line( 'Found ' . count( $edit_entries ) . ' Edited Entries on Post ID ' . $post_id );
			}
		}

		if ( true === $is_dryrun ) {
			WP_CLI::success( 'Dry Run Completed. Please check the results and when ready re-run the command without the --dryrun flag.' );
		} else {
			WP_CLI::success( 'Fixed all entries on all liveblog posts!' );
		}
	}

	private function markdownify_headings( $readme ) {
		return preg_replace_callback(
			'/^\s*(=+)\s*(.*?)\s*=+\s*$/m',
			function( $matches ) {
				return "\n" . str_repeat( '#', 4 - strlen( $matches[1] ) ) . ' ' . $matches[2] . "\n";
			},
			$readme
		);
	}

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

	private function add_contributors_wp_org_profile_links( $readme ) {
		return preg_replace_callback(
			'/Contributors: (.*)/',
			function( $matches ) {
				$links = array_filter(
					array_map(
						function( $username ) {
							return "[$username](http://profiles.wordpress.org/$username)";
						}, preg_split( '/\s*,\s*/', $matches[1] )
					)
				);
				return 'Contributors: ' . implode( ', ', $links );
			},
			$readme
		);
	}

	private function add_screenshot_links( $readme ) {
		return preg_replace_callback(
			'/==\s*Screenshots\s*==\n(.*?)==/ms',
			function ( $matches ) {
				return "== Screenshots ==\n" . preg_replace( '/^\s*(\d+)\.\s*(.*?)$/m', '![\2](https://raw.github.com/Automattic/liveblog/master/screenshot-\1.png)', $matches[1] ) . "\n==";
			},
			$readme
		);
	}

	public static function help() {
		WP_CLI::line(
			<<<HELP
usage: wp liveblog readme_for_github
	Converts the readme.txt to real markdown to be used as a README.md
HELP
		);
	}
}
