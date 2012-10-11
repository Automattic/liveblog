<?php

/**
 * The main Liveblog entry query class
 *
 * This class is responsible for querying the Liveblog entries. Much of the
 * work is currently done by WordPress's comments API.
 */
class WPCOM_Liveblog_Entry_Query {

	var $wp_has_comment_approved_query_support = false;

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

		$this->wp_has_comment_approved_query_support = version_compare( $wp_version, '3.5-alpha-21548' ) > 0;

		if ( !$this->wp_has_comment_approved_query_support ) {
			add_filter( 'comments_clauses', array( $this, '_comments_clauses' ) );
		}

	}

	/**
	 * Get the liveblog entries
	 *
	 * @param array $args
	 * @return array()
	 */
	private function get( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'type'    => $this->key,
			/*
			 * Even if the WordPress is 3.4 and doesn't support querying
			 * arbitrary statuses, we include it so that it can be part of
			 * the cache key
			 */
			'status'  => $this->key,
		);

		$args     = wp_parse_args( $args, $defaults );
		$comments = get_comments( $args );

		return self::entries_from_comments( $comments );
	}

	/**
	 * Get all of the liveblog entries
	 *
	 * @param array $args
	 * @return array
	 */
	public function get_all( $args = array() ) {
		return self::filter_liveblog_entries( $this->get( $args ) );
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

	/**
	 * Get the entries between two timestamps
	 *
	 * @param int $start_timestamp
	 * @param int $end_timestamp
	 * @return array()
	 */
	public function get_between_timestamps( $start_timestamp, $end_timestamp ) {
		$entries_between = array();
		$all_entries = $this->get_all_entries_asc();

		foreach ( (array) $all_entries as $entry ) {
			if ( $entry->get_timestamp() >= $start_timestamp && $entry->get_timestamp() <= $end_timestamp ) {
				$entries_between[] = $entry;
			}
		}

		return self::filter_liveblog_entries( $entries_between );
	}

	private function get_all_entries_asc() {
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
	public static function entries_from_comments( $comments = array() ) {

		// Bail if no comments
		if ( empty( $comments ) )
			return null;

		// Map each comment to a new Liveblog Entry class, so that they inherit
		// some neat helper methods.
		return array_map( array( 'WPCOM_Liveblog_Entry', 'from_comment' ), $comments );
	}

	/**
	 * Filter entries by some specific criteria
	 *
	 * @param array $entries
	 * @return array
	 */
	public static function filter_liveblog_entries( $entries = array() ) {

		// Bail if no entries
		if ( empty( $entries ) )
			return $entries;

		// Get the entry ID's
		$entries_by_id = self::key_by_get_id( $entries );

		// Loop through ID's and unset any that should be filtered out
		foreach ( (array) $entries_by_id as $id => $entry ) {
			if ( !empty( $entry->replaces ) && isset( $entries_by_id[$entry->replaces] ) ) {
				unset( $entries_by_id[$id] );
			}
		}

		return $entries_by_id;
	}

	/**
	 * Get liveblog entry key ID's
	 *
	 * @param array $entries
	 * @return array
	 */
	public static function key_by_get_id( $entries ) {
		$result = array();

		foreach ( (array) $entries as $entry )
			$result[$entry->get_id()] = $entry;

		return $result;
	}

	/**
	 * Filter the comments query to include the special comment_approved status.
	 * Required for backwards-compatibility with 3.4.x
	 *
	 * @param array $clauses
	 * @return array
	 */
	public function _comments_clauses( $clauses = array() ) {
		global $wpdb;

		// Setup the search clauses
		$needle   = $wpdb->prepare( "comment_type = %s", $this->key );
		$haystack = !empty( $clauses['where'] ) ? $clauses['where'] : '';

		// Bail if not a liveblog query
		if ( ! strstr( $haystack, $needle ) )
			return $clauses;

		$clauses['where'] = $wpdb->prepare( "comment_approved = %s AND comment_post_ID = %d AND comment_type = %s", $this->key, $this->post_id, $this->key );

		return $clauses;
	}
}
