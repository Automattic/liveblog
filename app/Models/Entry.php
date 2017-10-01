<?php 

namespace WPCOM\Liveblog\Models;

use WPCOM\Liveblog\Libraries\Entry_Cache;

class Entry
{
	public $id;
	public $post_id;
    public $author;
    public $author_email;
    public $author_url;
    public $content;
    public $type;
    public $parent;
    public $author_ip;
    public $agent;
    public $date;
    public $updated;
    public $approved;
    public $user_id;

    public function __construct() {
        $this->id       = false;
        $this->type     = 'liveblog';
        $this->approved = 'liveblog';
        $this->parent   = 0;
        $this->updated  = false;
    }

    public function save() {
        $success = false;

    	if ( $this->id ) {
    		$success = wp_update_comment( $this->convert_to_wordpress_array() );
    	}
    	else {
            $this->set_user();
    		$success = wp_insert_comment( $this->convert_to_wordpress_array() ); 
            $this->id = $success;
    	}

        $this->find( $this->id );

        $this->save_meta();

        $this->cache();

        return $success;
    }

    public function delete() {
        $this->updated = time();

        $success = wp_delete_comment( $this->id, true );

        $this->cache();
        
        return $success;
    } 



    public function save_meta() {
        if ( $this->updated === false ) {
            $this->updated = $this->timestamp();
        } else {
            $this->updated = time();
        }

        update_comment_meta( $this->id, 'wpcom_liveblog_updated', $this->updated );
    }

    public function get_meta() {
        $this->updated = get_comment_meta( $this->id, 'wpcom_liveblog_updated', true );
    }

    public function find( $id ) {
    	if ( $entry = get_comment( $id ) ) {
    		$this->fill_from_wordpress_object( $entry );
            $this->get_meta();
    		return true;
    	}
    	return false;
    }

    public function set_user() {
        $current_user = wp_get_current_user();

        $this->author       = $current_user->user_nicename;
        $this->author_email = $current_user->user_email;
        $this->author_url   = $current_user->user_url;
        $this->user_id      = $current_user->ID;
    }

    public function fill_from_wordpress_object( $entry ) {
    	$this->id 			= $entry->comment_ID;
    	$this->post_id 		= $entry->comment_post_ID;	  
		$this->author 		= $entry->comment_author;
		$this->author_email = $entry->comment_author_email;
		$this->author_url 	= $entry->comment_author_url; 
		$this->content 		= $entry->comment_content;
		$this->type 		= $entry->comment_type; 		   
		$this->parent 		= $entry->comment_parent;
		$this->author_ip 	= $entry->comment_author_IP;
		$this->agent 		= $entry->comment_agent;
		$this->date 		= $entry->comment_date;
		$this->approved 	= $entry->comment_approved;
		$this->user_id 		= $entry->user_id;
    }

    public function convert_to_wordpress_array() {
    	$args = [
    		'comment_post_ID' 	   => $this->post_id,
		    'comment_author' 	   => $this->author,
		    'comment_author_email' => $this->author_email,
		    'comment_author_url'   => $this->author_url,
		    'comment_content' 	   => $this->content,
		    'comment_type' 		   => $this->type,
		    'comment_parent' 	   => $this->parent,
		    'comment_author_IP'    => $this->author_ip,
		    'comment_agent' 	   => $this->agent,
		    'comment_date' 		   => $this->date,
		    'comment_approved' 	   => $this->approved,
		    'user_id' 			   => $this->user_id,
    	];

    	if ( $this->id ) {
    		$args[ 'comment_ID' ] = $this->id;
    	}

    	return $args;
    }

    public function timestamp() {
        return strtotime( $this->date );
    }

    public function cache() {
        $cache = new Entry_Cache( $this->post_id );
        $cache->rebuild();
    }

    public static function all( $post_id ) {
    	$entries = [];
		$comments = get_comments( [ 'post_id' => $post_id, 'type' => 'liveblog', 'status' => 'liveblog' ] );
   
		foreach ( $comments as $comment ) {
			$entry = new Entry;
			$entry->fill_from_wordpress_object( $comment );
            $entry->get_meta();
			$entries[] = $entry;
		}
		return $entries;
    }	
}