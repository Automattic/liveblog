<?php

/**
 * Responsible for querying the Liveblog entries.
 *
 * Much of the work is currently done by WordPress's comments API.
 */
class WPCOM_Liveblog_Entry_Query {

	public function __construct( $post_id, $key ) {
		global $wp_version;
		$this->post_id = $post_id;
		$this->key     = $key;
	}

	/**
	 * Query the database for specific liveblog entries
	 *
	 * @param array $args the same args for the core `get_comments()`.
	 * @return array array of `WPCOM_Liveblog_Entry` objects with the found entries
	 */
	public function get( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'type'    => $this->key,
			'status'  => $this->key,
		);

		$args     = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );

		return self::entries_from_comments( $comments );
	}

	/**
	 * Query the database for all edited liveblog entries associated with $post_id
	 *
	 * @param array $args the same args for the core `get_comments()`.
	 * @return array array of `WPCOM_Liveblog_Entry` objects with the found entries
	 */
	public function get_all_edits( $args = array() ) {
		$defaults = array(
			'orderby'  => 'comment_date_gmt',
			'order'    => 'ASC',
			'meta_key' => 'liveblog_replaces',
			'status'   => 'liveblog',
		);

		$args     = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );

		return self::entries_from_comments( $comments );
	}
	/**
	 * Get all of the liveblog entries
	 *
	 * @param array $args the same args for the core `get_comments()`
	 */
	public function get_all( $args = array() ) {
		// Due to liveblog lazy loading, duplicate entries may be displayed
		// if we actually pass the 'number' argument to get_comments
		// in this class.
		//
		// We don't want to remove the parameter entirely for backwards compatibility
		// since this is a public method, but we need instead to handle it as part
		// of remove_replaced_entries after we retrieve the entire result set.
		$number = 0;
		if ( isset( $args['number'] ) ) {
			$number = intval( $args['number'] );
			unset( $args['number'] );
		}

		return self::remove_replaced_entries( $this->get( $args ), $number );
	}

	public function count( $args = array() ) {
		return count( $this->get_all( $args ) );
	}

	public function get_by_id( $id ) {
		$comment = get_comment( $id );
		/*
		 * When running tests, WP_Comment's comment_ID and comment_post_ID return strings. However, post_id
		 * returns a string (test_update_should_update_original_entry) or
		 * an integer (test_get_by_id_should_return_the_entry). For this to pass, coerce comment_post_ID to
		 * an integer before using a strict comparison.
		 */
		if ( intval( $comment->comment_post_ID ) !== intval( $this->post_id ) || $comment->comment_type !== $this->key || $comment->comment_approved !== $this->key ) {
			return null;
		}
		$entries = self::entries_from_comments( array( $comment ) );
		return $entries[0];
	}

	public function get_latest() {

		$entries = $this->get( array( 'number' => 1 ) );

		if ( empty( $entries ) ) {
			return null;
		}

		return reset( $entries );
	}

	/**
	 * Returns latest entry id.
	 *
	 * @return int
	 */
	public function get_latest_id() {

		$latest = $this->get_latest();

		if ( is_null( $latest ) ) {
			return null;
		}

		if ( ! is_a( $latest, 'WPCOM_Liveblog_Entry' ) ) {
			return null;
		}

		return $latest->get_id();
	}

	public function get_latest_timestamp() {

		$latest = $this->get_latest();

		if ( is_null( $latest ) ) {
			return null;
		}

		if ( ! is_a( $latest, 'WPCOM_Liveblog_Entry' ) ) {
			return null;
		}

		return $latest->get_timestamp();
	}

	/**
	 * Get entries between two timestamps from a list of entries supplied.
	 *
	 * @param array $entries
	 * @param int   $start_timestamp
	 * @param int   $end_timestamp
	 * @return array
	 */
	public function find_between_timestamps( $entries, $start_timestamp, $end_timestamp ) {
		$entries_between = array();

		foreach ( (array) $entries as $entry ) {
			if ( $entry->get_timestamp() >= $start_timestamp && $entry->get_timestamp() <= $end_timestamp ) {
				$entries_between[] = $entry;
			}
		}

		return self::remove_replaced_entries( $entries_between );
	}

	/**
	 * Get entries between two timestamps from all entries.
	 *
	 * @param int $start_timestamp
	 * @param int $end_timestamp
	 * @return array
	 */
	public function get_between_timestamps( $start_timestamp, $end_timestamp ) {
		$all_entries = $this->get_all_entries_asc();
		return $this->find_between_timestamps( $all_entries, $start_timestamp, $end_timestamp );
	}

	public function has_any() {
		return (bool) $this->get();
	}

	public function get_all_entries_asc() {
		$cached_entries_asc_key = $this->key . '_entries_asc_' . $this->post_id;
		$cached_entries_asc     = wp_cache_get( $cached_entries_asc_key, 'liveblog' );
		if ( false !== $cached_entries_asc ) {
			return $cached_entries_asc;
		}
		$all_entries_asc = $this->get( array( 'order' => 'ASC' ) );
		wp_cache_set( $cached_entries_asc_key, $all_entries_asc, 'liveblog' );
		return $all_entries_asc;
	}

	public static function entries_from_comments( $comments = array() ) {

		if ( empty( $comments ) ) {
			return null;
		}

		return array_map( array( 'WPCOM_Liveblog_Entry', 'from_comment' ), $comments );
	}

	public static function remove_replaced_entries( $entries = array(), $number = 0 ) {
		if ( empty( $entries ) ) {
			return $entries;
		}

		$entries_by_id = self::assoc_array_by_id( $entries );

		foreach ( (array) $entries_by_id as $id => $entry ) {
			if ( ! empty( $entry->replaces ) && isset( $entries_by_id[ $entry->replaces ] ) ) {
				unset( $entries_by_id[ $id ] );
			}
		}

		// If a number of entries is set and we have more than that amount of entries,
		// return just that slice.
		if ( $number > 0 && count( $entries_by_id ) > $number ) {
			$entries_by_id = array_slice( $entries_by_id, 0, $number );
		}

		return $entries_by_id;
	}

	public static function assoc_array_by_id( $entries ) {
		$result = array();

		foreach ( (array) $entries as $entry ) {
			$result[ $entry->get_id() ] = $entry;
		}

		return $result;
	}

	/**
	 * Returns the Liveblog entries between the two given (optional) timestamps.
	 *
	 * @param int $max_timestamp Maximum timestamp for the Liveblog entries.
	 * @param int $min_timestamp Minimum timestamp for the Liveblog entries.
	 *
	 * @return WPCOM_Liveblog_Entry[]
	 */
	public function get_for_lazyloading( $max_timestamp, $min_timestamp ) {

		$entries = $this->get_all();
		if ( ! $entries ) {
			return array();
		}

		if ( $max_timestamp ) {
			foreach ( $entries as $key => $entry ) {
				$timestamp = $entry->get_timestamp();

				if (
					( $max_timestamp && $timestamp >= $max_timestamp )
					|| ( $min_timestamp && $timestamp <= $min_timestamp )
				) {
					unset( $entries[ $key ] );
				}
			}
		}

		return $entries;
	}
}
