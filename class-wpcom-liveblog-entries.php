<?php

class WPCOM_Liveblog_Entries {

	function __construct( $post_id, $key ) {
		$this->post_id = $post_id;
		$this->key = $key;
	}

	function get( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order' => 'DESC',
			'type' => $this->key,
			'comment_approved' => $this->key,
		);
		$args = array_merge( $defaults, $args );
		$entries = get_comments( $args );
		return $entries;
	}

	function get_latest() {
		$entries = $this->get( array( 'number' => 1 ) );
		if ( empty( $entries ) )
			return null;
		return $entries[0];
	}

	function get_latest_timestamp() {
		$latest = $this->get_latest();
		if ( is_null( $latest ) ) {
			return null;
		}
		return mysql2date( 'G', $latest->comment_date_gmt );
	}

	function get_between_timestamps( $start_timestamp, $end_timestamp ) {
		$start_date = $this->mysql_from_timestamp( $start_timestamp );
		$end_date = $this->mysql_from_timestamp( $end_timestamp );

		$all_entries = $this->get();
		$entries_between = array();

		foreach( $all_entries as $entry ) {
			if ( $entry->comment_date_gmt >= $start_date && $entry->comment_date_gmt <= $end_date ) {
				$entries_between[] = $entry;
			}
		}

		return $entries_between;
	}

	private function mysql_from_timestamp( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
