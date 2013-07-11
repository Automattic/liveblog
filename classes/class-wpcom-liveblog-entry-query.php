<?php

/**
 * Responsible for querying the Liveblog entries.
 *
 * Much of the work is currently done by WordPress's comments API.
 */
class WPCOM_Liveblog_Entry_Query {

	/**
	 * Set the post ID and key when a new object is created
	 *
	 * @param int $post_id
	 * @param string $key
	 */
	public function __construct( $post_id, $key ) {
		global $wp_version;
		$this->post_id = $post_id;
		$this->key     = $key;
	}

	/**
	 * Get the liveblog entries
	 *
	 * @param array $args the same args for the core `get_comments()`.
	 * @return array array of `WPCOM_Liveblog_Entry` objects with the found entries
	 */
	private function get( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'type'    => $this->key,
			'status'  => $this->key,
		);

		$args     = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );

		return $this->entries_from_comments( $comments );
	}

	/**
	 * Get all of the liveblog entries
	 *
	 * @param array $args the same args for the core `get_comments()`
	 * @return array
	 */
	public function get_all( $args = array() ) {
		return self::remove_replaced_entries( $this->get( $args ) );
	}

	public function get_by_id( $id ) {
		$comment = get_comment( $id );
		if ( $comment->comment_post_ID != $this->post_id || $comment->comment_type != $this->key || $comment->comment_approved != $this->key) {
			return null;
		}
		$entries = $this->entries_from_comments( array( $comment ) );
		return $entries[0];
	}

	/**
	 * Get the latest liveblog entry
	 *
	 * @return null
	 */
	public function get_latest() {

		// Get the latest liveblog entry
		$entries = $this->get( array( 'number' => 1 ) );

		// Bail if none were found
		if ( empty( $entries ) )
			return null;

		return reset( $entries );
	}

	/**
	 * Get the latest liveblog timestamp
	 *
	 * @return mixed Null if error, timestamp if successful
	 */
	public function get_latest_timestamp() {

		// Get the latest entry
		$latest = $this->get_latest();

		// Bail if none were found
		if ( is_null( $latest ) )
			return null;

		// Bail if not a WPCOM_Liveblog_Entry class
		if ( ! is_a( $latest, 'WPCOM_Liveblog_Entry' ) )
			return null;

		// Return the timestamp of the latest entry
		return $latest->get_timestamp();
	}

	public function get_between_timestamps( $start_timestamp, $end_timestamp ) {
		$entries_between = array();
		$all_entries = $this->get_all_entries_asc();

		foreach ( (array) $all_entries as $entry ) {
			if ( $entry->get_timestamp() >= $start_timestamp && $entry->get_timestamp() <= $end_timestamp ) {
				$entries_between[] = $entry;
			}
		}

		return self::remove_replaced_entries( $entries_between );
	}

	public function has_any() {
		return (bool)$this->get();
	}

	private function get_all_entries_asc() {
		// @todo is the caching here necessary? WP_Comment_Query::query() does caching already
		$cached_entries_asc_key =  $this->key . '_entries_asc_' . $this->post_id;
		$cached_entries_asc = wp_cache_get( $cached_entries_asc_key, 'liveblog' );
		if ( false !== $cached_entries_asc ) {
			return $cached_entries_asc;
		}
		$all_entries_asc = $this->get( array( 'order' => 'ASC' ) );
		wp_cache_set( $cached_entries_asc_key, $all_entries_asc, 'liveblog' );
		return $all_entries_asc;
	}

	/**
	 * Convert each comment into a Liveblog entry
	 *
	 * @param array $comments
	 * @return array
	 */
	public function entries_from_comments( $comments = array() ) {

		// Bail if no comments
		if ( empty( $comments ) )
			return null;

		$reply_comments_by_parent = self::group_reply_comments_by_parent( $this->get_reply_comments() );

		// Map each comment to a new Liveblog Entry class, so that they inherit
		// some neat helper methods.
		$entries = array();
		foreach ( $comments as $comment ) {
			$reply_comments = array();
			if ( ! empty( $reply_comments_by_parent[$comment->comment_ID] ) ) {
				$reply_comments = $reply_comments_by_parent[$comment->comment_ID];
			}
			array_push( $entries, new WPCOM_Liveblog_Entry( $comment, $reply_comments ) );
		}

		return $entries;
	}

	/**
	 * Filter out entries, which have been replaced by other entries in
	 * the same set.
	 *
	 * @param array $entries
	 * @return array
	 */
	public static function remove_replaced_entries( $entries = array() ) {

		if ( empty( $entries ) )
			return $entries;

		$entries_by_id = self::assoc_array_by_id( $entries );

		foreach ( (array) $entries_by_id as $id => $entry ) {
			if ( !empty( $entry->replaces ) && isset( $entries_by_id[$entry->replaces] ) ) {
				unset( $entries_by_id[$id] );
			}
		}

		return $entries_by_id;
	}

	public static function assoc_array_by_id( $entries ) {
		$result = array();

		foreach ( (array) $entries as $entry )
			$result[$entry->get_id()] = $entry;

		return $result;
	}

	/**
	 * Get the liveblog reply comments for this post
	 * @param array|string $args
	 */
	public function get_reply_comments( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'type'    => WPCOM_Liveblog::reply_comment_type,
		);
		$args = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );
		$comments = self::filter_out_undisplayable_comments( $comments );
		return $comments;
	}

	/**
	 * Remove comments that are not approved or ones which are unapproved and
	 * yet not authored by the current commenter
	 * @see comments_template()
	 */
	private static function filter_out_undisplayable_comments( array $comments ) {
		global $user_ID;

		/**
		 * Comment author information fetched from the comment cookies.
		 *
		 * @uses wp_get_current_commenter()
		 */
		$commenter = wp_get_current_commenter();

		/**
		 * The name of the current comment author escaped for use in attributes.
		 */
		$comment_author = $commenter['comment_author']; // Escaped by sanitize_comment_cookies()

		/**
		 * The email address of the current comment author escaped for use in attributes.
		 */
		$comment_author_email = $commenter['comment_author_email'];  // Escaped by sanitize_comment_cookies()

		$displayable_comments = array();
		foreach ( $comments as $comment ) {
			$is_displayable = (
				$comment->comment_approved == '1'
				||
				(
					$user_ID
					&&
					$comment->comment_approved == '0'
					&&
					$comment->user_id == $user_ID
				)
				||
				(
					! empty( $comment_author )
					&&
					$comment->comment_approved == '0'
					&&
					$comment->comment_author == wp_specialchars_decode( $comment_author,ENT_QUOTES )
					&&
					$comment->comment_author_email == $comment_author_email
				)
			);
			if ( $is_displayable ) {
				array_push( $displayable_comments, $comment );
			}
		}
		return $displayable_comments;
	}

	/**
	 * Organize comments into an associative array keyed by the comment_parent
	 * @param array $comments
	 * @return array
	 */
	public static function group_reply_comments_by_parent( array $comments ) {
		$grouped_comments = array();
		foreach ( $comments as $comment ) {
			if ( ! isset( $grouped_comments[$comment->comment_parent] ) ) {
				$grouped_comments[$comment->comment_parent] = array();
			}
			array_push( $grouped_comments[$comment->comment_parent], $comment );
		}
		return $grouped_comments;
	}

}
