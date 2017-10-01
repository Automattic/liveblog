<?php 

namespace WPCOM\Liveblog\Controllers;

use WPCOM\Liveblog\Models\Entry;
use WPCOM\Liveblog\Libraries\Entry_Cache;

class Entry_Controller
{
	public function get( \WP_REST_Request $request ) {
		$entry_id = $request->get_param( 'entry' );
		$entry 	  = new Entry();

		$entry->find( $entry_id );

		return $entry;
	}

	public function all( \WP_REST_Request $request ) {	
		$post_id = $request->get_param( 'post' );
		$cache 	 = new Entry_Cache( $post_id );

		return $cache->get_entries();
	}

	public function create( \WP_REST_Request $request ) {	
		$post_id = $request->get_param( 'post' 	 );
		$content = $request->get_param( 'content' );

		$entry 	 		= new Entry();
		$entry->post_id = $post_id;
		$entry->content = $content;
		$entry->save();

		return [ 
			'id' => $entry->id, 
			'updated' => $entry->timestamp(), 
			'nonce' => wp_create_nonce( 'wp_rest' ) 
		];
	}

	public function update( \WP_REST_Request $request ) {	
		$entry_id = $request->get_param( 'entry'   );
		$content  = $request->get_param( 'content' );
		$entry 	  = new Entry();

		$entry->find( $entry_id );
		$entry->content = $content;
		$entry->save();

		return [ 
			'id' => $entry->id, 
			'updated' => $entry->updated,
			'nonce' => wp_create_nonce( 'wp_rest' ) 
		];
	}

	public function delete( \WP_REST_Request $request ) {	
		$entry_id = $request->get_param( 'entry' );
		$entry 	  = new Entry();

		$entry->find( $entry_id );
		$entry->delete();

		return [ 
			'id' => $entry->id, 
			'updated' => $entry->updated,
			'nonce' => wp_create_nonce( 'wp_rest' ) 
		];
	}

	public function polling( \WP_REST_Request $request ) {	
		$post_id = $request->get_param( 'post' );
		$cache 	 = new Entry_Cache( $post_id );

		return $cache->get_polling();
	}
}