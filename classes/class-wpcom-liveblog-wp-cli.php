<?php
WP_CLI::add_command( 'liveblog', 'WPCOM_Liveblog_WP_CLI' );

class WPCOM_Liveblog_WP_CLI extends WP_CLI_Command {
	public function readme_for_github() {
		$readme_path = dirname( __FILE__ ) . '/../readme.txt';
		$readme = file_get_contents( $readme_path );
		$readme = $this->listify_meta( $readme );
		$readme = $this->add_contributors_wp_org_profile_links( $readme );
		$readme = $this->add_screenshot_links( $readme );
		$readme = $this->markdownify_headings( $readme );
		echo $readme;
	}
	
	/**
	 * Fix wp_commentmeta table so archived liveblog posts comments display properly.
	 *
	 * @subcommand fix-archive
	*/
	public function fix_archive() {
		global $wpdb;

		// find all liveblogs
		$posts = new WP_Query( array(
									'order'    => 'ASC',
									'orderby'  => 'ID',
									'meta_key' => 'liveblog' 
									) );

		foreach( $posts->posts as $post ) {
			$post_id = $post->ID;

			// get all entries that have been edited in the liveblog
			$entries_query = new WPCOM_Liveblog_Entry_Query( $post_id, WPCOM_Liveblog::key );
			$edit_entries = $entries_query->get_all_edits( array( 'post_id' => $post_id ) );			
			// find correct comment_ids to replace incorrect meta_values
			$correct_ids_array = $wpdb->get_results(
				$wpdb->prepare(
				"SELECT comment_id FROM $wpdb->comments
				 WHERE comment_post_id = %d AND comment_id NOT IN 
				 ( SELECT $wpdb->commentmeta.comment_id FROM $wpdb->commentmeta
				   INNER JOIN $wpdb->comments 
				   ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
				   WHERE comment_post_id = %d )
				 ORDER BY comment_id ASC", $post_id, $post_id )
				);
			$correct_ids = wp_list_pluck( $correct_ids_array, 'comment_id' );

			// replace incorrect meta_value with correct one
			foreach( $edit_entries as $edit_entry ) {
				$entry_id = $edit_entry->get_id();

				// look for replaces property in $correct_ids 
				if ( in_array( $edit_entry->replaces, $correct_ids ) ) {
					continue;
				}
				else {
					for ( $i = 0; $i <= count( $correct_ids ) - 1; $i++ )
					{		
						// replace with correct meta_value
						if ( $correct_ids[$i] < $entry_id ) {

							$wpdb->update(
								$wpdb->commentmeta,
								array( 'meta_value' => $correct_ids[$i] ),
								array( 'comment_id' => $entry_id )
								);
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
					 ORDER BY comment_id ASC",  $post_id )
			);

			// find comment_ids that NEED to be replaced
			$entries_replace = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT meta_value 
					FROM $wpdb->commentmeta 
					INNER JOIN $wpdb->comments 
					ON $wpdb->comments.comment_id = $wpdb->commentmeta.comment_id
					WHERE comment_post_id = %d
					ORDER BY meta_value ASC", $post_id )
				);

			// check to make sure entry content being replaced matches available
			if ( count( $entries_replace ) === count( $correct_contents) ) {
				// counter
				$replaced = 0;
				foreach( $entries_replace as $entry_replace ) {
					$content = $correct_contents[ $replaced ]->comment_content;

					$wpdb->update(
					 	$wpdb->comments,
					 	array( 'comment_content' => $content ),
					 	array( 'comment_id' => $entry_replace->meta_value )
					 	);

					$replaced++;
				}
			}
		}
		WP_CLI::line( 'Fixed all entries on all liveblog posts!' );
	}

	private function markdownify_headings( $readme ) {
		return preg_replace_callback( '/^\s*(=+)\s*(.*?)\s*=+\s*$/m',
			function( $matches ) {
				return "\n" . str_repeat( '#', 4 - strlen( $matches[1] ) ) . ' ' . $matches[2] . "\n";
			},
			$readme );
	}

	private function listify_meta( $readme ) {
		return preg_replace_callback( '/===\s*\n+(.*?)\n\n/s',
			function ( $matches ) {
				$meta = $matches[1];
				if ( !$meta ) return $matches[0];
				return "===\n" . preg_replace( '/^/m', "* ", $meta ) . "\n\n";
			},
			$readme );
	}

	private function add_contributors_wp_org_profile_links( $readme ) {
		return preg_replace_callback( '/Contributors: (.*)/',
			function( $matches ) {
				$links = array_filter( array_map(
					function( $username ) {
						return "[$username](http://profiles.wordpress.org/$username)";
					}, preg_split( '/\s*,\s*/', $matches[1] ) ) );
				return "Contributors: " . implode( ', ', $links );
			},
		   	$readme );
	}

	private function add_screenshot_links( $readme ) {
		return preg_replace_callback( '/==\s*Screenshots\s*==\n(.*?)==/ms',
			function ( $matches ) {
				return "== Screenshots ==\n" . preg_replace( '/^\s*(\d+)\.\s*(.*?)$/m', '![\2](https://raw.github.com/Automattic/liveblog/master/screenshot-\1.png)', $matches[1] ) . "\n==";
			},
			$readme );
	}

	static function help() {
		WP_CLI::line( <<<HELP
usage: wp liveblog readme_for_github
	Converts the readme.txt to real markdown to be used as a README.md
HELP
		);
	}
}
