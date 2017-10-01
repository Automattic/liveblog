<?php

namespace WPCOM\Liveblog\Libraries;

use WPCOM\Liveblog\Models\Entry;

class Entry_Cache {

	private $post_id;
	private $entries_key;
	private $polling_key;
	private $expiration;

	public function __construct( $post_id ) {
		$this->post_id 		= $post_id;
		$this->entries_key  = 'wpcom_liveblog_entries_' . $post_id;
		$this->polling_key  = 'wpcom_liveblog_polling_' . $post_id;
		$this->expiration	= 86400;
	}

	public function rebuild() {
		$entries = Entry::all( $this->post_id );
		return $this->set( $entries );
	}

	public function set( $entries ) {
		set_transient( $this->entries_key, $entries, $this->expiration );

		$polling = [];
		foreach ( $entries as $entry ) {
			$polling[] = [
				'id' 	  => $entry->id,
				'updated' => $entry->updated
			];
		}
		usort( $polling, function( $a, $b ) {
		    return $b['updated'] - $a['updated'];
		} );

		set_transient( $this->polling_key, $polling, $this->expiration );

		return [ $entries, $polling ];
	}

	public function get_entries() {
		$entries = get_transient( $this->entries_key );

		if ( $entries === false ) {
			$entries = $this->rebuild()[0];
		}

		return $entries;
	}


	public function get_polling() {
		$polling = get_transient( $this->polling_key );

		if ( $polling === false ) {
			$polling = $this->rebuild()[1];
		}

		return $polling;
	}

	public function delete() {
		delete_transient( $this->entries_key );
		delete_transient( $this->polling_key );
	}

}