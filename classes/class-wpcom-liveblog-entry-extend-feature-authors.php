<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature_Authors
 *
 * The base class for autocomplete features.
 */
class WPCOM_Liveblog_Entry_Extend_Feature_Authors extends WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The class prefix.
	 *
	 * @var string
	 */
	protected $class_prefix = 'author-';

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array( '@', '\x{0040}' );

	/**
	 * An author cache for the filter.
	 *
	 * @var array
	 */
	protected $authors = array();

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	public function load() {

		// Allow plugins, themes, etc. to change
		// the generated author class.
		$this->class_prefix = apply_filters( 'liveblog_author_class', $this->class_prefix );

		// This is the regex used to revert the
		// generated author html back to the
		// raw input format (e.g @author).
		$this->revert_regex = implode( '', array(
			preg_quote( '<a href="', '~' ),
			'[^"]+',
			preg_quote( '" class="liveblog-author ', '~' ),
			preg_quote( $this->class_prefix, '~' ),
			'([^"]+)',
			preg_quote( '"', '~' ),
			'[^>]*>\\1',
			preg_quote( '</a>', '~' ),
		) );

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_author_revert_regex', $this->revert_regex );

		// We hook into the comment_class filter to
		// be able to alter the comment content.
		add_filter( 'comment_class',            array( $this, 'add_author_class_to_entry' ), 10, 3 );

		// Add an ajax endpoint to find the authors
		// which is to be used on the front end.
		add_action( 'wp_ajax_liveblog_authors', array( $this, 'ajax_authors') );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {

		$endpoint_url = admin_url( 'admin-ajax.php' ) .'?action=liveblog_authors';

		if ( WPCOM_Liveblog::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( WPCOM_Liveblog_Rest_Api::build_endpoint_base() ) . 'authors');
		}

		// Add our config to the front end autocomplete
		// config, after first allowing other plugins,
		// themes, etc. to modify it as required
		$config[] = apply_filters( 'liveblog_author_config', array(
			'type'        => 'ajax',
			'cache'       => 1000 * 60 * 30,
			'url'         => $endpoint_url,
			'search'      => 'key',
			'regex'       => '@([\w\-]*)$',
			'replacement' => '@${key}',
			'template'    => '${avatar} ${name}',
		) );

		return $config;
	}

	/**
	 * Filters the input.
	 *
	 * @param mixed $entry
	 * @return mixed
	 */
	public function filter( $entry ) {

		// The args used in the get_users query.
		$args = array(
			'who'    => 'authors',
			'fields' => array( 'user_nicename' ),
		);

		// Map the authors and store them on the object
		// for use in another function, we need
		// them to be lowercased.
		$this->authors = array_map( array( $this, 'map_authors' ), get_users( $args ) );

		// Map over every match and apply it via the
		// preg_replace_callback method.
		$entry['content'] = preg_replace_callback(
			$this->get_regex(),
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		);

		return $entry;
	}

	/**
	 * Maps the authors.
	 *
	 * @param string $author
	 * @return string
	 */
	public function map_authors( $author ) {
		return strtolower( $author->user_nicename );
	}

	/**
	 * The preg replace callback for the filter.
	 *
	 * @param array $match
	 * @return string
	 */
	public function preg_replace_callback( $match ) {

		// Allow any plugins, themes, etc. to modify the match.
		$author = apply_filters( 'liveblog_author', $match[2] );

		// If the match isn't actually an author then we can
		// safely say that this doesn't need to be matched.
		if ( ! in_array( $author, $this->authors ) ) {
			return $match[0];
		}

		// Replace the @author content with a link to
		// the author's post listing page.
		return str_replace(
			$match[1],
			'<a href="'.get_author_posts_url( -1, $author ).'" class="liveblog-author '.$this->class_prefix.$author.'">'.$author.'</a>',
			$match[0]
		);
	}

	/**
	 * Reverts the input.
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function revert( $content ) {
		return preg_replace( '~'.$this->revert_regex.'~', '@$1', $content );
	}

	/**
	 * Adds author-{author} class to entry
	 *
	 * @param array  $classes
	 * @param string $class
	 * @param int    $comment_id
	 * @return array
	 */
	public function add_author_class_to_entry( $classes, $class, $comment_id ) {
		$authors = array();
		$comment = get_comment( $comment_id );

		// Check if the comment is a live blog comment.
		if ( WPCOM_Liveblog::key == $comment->comment_type ) {

			// Grab all the prefixed classes applied.
			preg_match_all( '/(?<!\w)'.preg_quote( $this->class_prefix ).'\w+/', $comment->comment_content, $authors );

			// Append the first class to the classes array.
			$classes = array_merge( $classes, $authors[0] );
		}

		return $classes;
	}

	/**
	 * Returns an array of authors
	 *
	 * @return array
	 */
	public function ajax_authors() {

		//Sanitize the input safely.
		if( isset( $_GET['autocomplete'] ) ) {
			$term = sanitize_text_field( $_GET['autocomplete'] );
		} else {
			$term = '';
		}

		$users = $this->get_authors( $term );

		header( "Content-Type: application/json" );
		echo wp_json_encode( $users );
		exit;
	}

	public function get_authors( $term ) {

		// The args used in the get_users query.
		$args = array(
			'who'    => 'authors',
			'fields' => array( 'ID', 'user_nicename', 'display_name' ),
			'number' => 10,
		);

		// If there is no search term then search
		// for nothing to get everything.
		// If there is a search term, then append
		// '*' to match chars after the term.
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term.'*';
		}

		// Map the authors into the expected format.
		$users = array_map( array( $this, 'map_ajax_authors' ),  get_users( $args ) );

		return $users;
	}

	/**
	 * Maps the authors for ajax.
	 *
	 * @param string $author
	 * @return string
	 */
	public function map_ajax_authors( $author ) {
		return array(
			'id' => $author->ID,
			'key' => strtolower($author->user_nicename),
			'name' => $author->display_name,
			'avatar' => get_avatar( $author->ID, 20 ),
		);
	}

}
