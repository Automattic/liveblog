<?php
class WPCOM_Liveblog_Entry_Query {

	function __construct( $post_id, $key ) {
		$this->post_id = $post_id;
		$this->key = $key;
	}

	private function get( $args = array() ) {
		$defaults = array(
			'post_id' => $this->post_id,
			'orderby' => 'comment_date_gmt',
			'order' => 'DESC',
			'type' => $this->key,
			'comment_approved' => $this->key,
		);
		$args = array_merge( $defaults, $args );
		$comments = get_comments( $args );
		return self::entries_from_comments( $comments );
	}

	function get_all( $args = array() ) {
		return self::filter_event_entries( $this->get( $args ) );
	}

	function get_latest() {
		$entries = $this->get( array( 'number' => 1 ) );
		if ( empty( $entries ) )
			return null;
		reset( $entries );
		$first = current( $entries );
		return $first;
	}

	function get_latest_timestamp() {
		$latest = $this->get_latest();
		if ( is_null( $latest ) ) {
			return null;
		}
		return $latest->get_timestamp();
	}

	function get_between_timestamps( $start_timestamp, $end_timestamp ) {
		$all_entries = $this->get();
		$entries_between = array();

		foreach( $all_entries as $entry ) {
			if ( $entry->get_timestamp() >= $start_timestamp && $entry->get_timestamp() <= $end_timestamp ) {
				$entries_between[] = $entry;
			}
		}

		return self::filter_event_entries( $entries_between );
	}

	static function entries_from_comments( $comments ) {
		if ( !$comments ) {
			return null;
		}
		$entries = array_map( array( 'WPCOM_Liveblog_Entry', 'from_comment' ), $comments );
		return $entries;
	}

	static function filter_event_entries( $entries ) {
		if ( !$entries ) {
			return $entries;
		}
		$entries_by_id = self::key_by_get_id( $entries );
		foreach( $entries_by_id as $id => $entry ) {
			if ( $entry->replaces && isset( $entries_by_id[$entry->replaces] ) ) {
				unset( $entries_by_id[$id] );
			}
		}
		return $entries_by_id;
	}

	static function key_by_get_id( $entries ) {
		$result = array();
		foreach( $entries as $entry ) {
			$result[$entry->get_id()] = $entry;
		}
		return $result;
	}
}
