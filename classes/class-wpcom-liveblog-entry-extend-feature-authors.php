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
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 */
	public function load() {
		$this->class_prefix = apply_filters( 'liveblog_author_class', $this->class_prefix );

		add_filter( 'comment_class',            array( $this, 'add_author_class_to_entry' ), 10, 3 );
		add_action( 'wp_ajax_liveblog_authors', array( $this, 'ajax_authors') );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {
		$config[] = array(
			'at'   => $this->get_prefixes()[0],
			'data' => admin_url( 'admin-ajax.php' ) .'?action=liveblog_authors',
		);

		return $config;
	}

	/**
	 * Filters the input.
	 *
	 * @param mixed $entry
	 * @return mixed
	 */
	public function filter( $entry ) {
		$args = array(
			'who'    => 'authors',
			'fields' => ['user_nicename'],
		);

		$authors = array_map( function ($author) {
			return strtolower($author->user_nicename);
		}, get_users( $args ) );

		$entry['content'] = preg_replace_callback( $this->get_regex(), function ($match) use ($authors) {
			$author = apply_filters( 'liveblog_author', $match[1] );

			if ( ! in_array($author, $authors) ) {
				return $match[0];
			}

			return '<a href="/'.get_author_posts_url(-1, $author).'" class="liveblog-author '.$this->class_prefix.$author.'">'.$author.'</a>';
		}, $entry['content']);

		return $entry;
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
		$authors   = array();
		$comment = get_comment( $comment_id );

		if ( 'liveblog' == $comment->comment_type ) {
			preg_match_all( '/(?<!\w)'.preg_quote( $this->class_prefix ).'\w+/', $comment->comment_content, $authors );
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
		$args = array(
			'who'    => 'authors',
			'fields' => ['user_nicename'],
		);

		$users = array_map( function ($user) {
			return strtolower($user->user_nicename);
		},  get_users( $args ) );

		header( "Content-Type: application/json" );
		echo json_encode( $users );

		exit;
	}

}
