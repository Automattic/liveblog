<?php

/**
 * Class Liveblog_Entry_Extend_Feature_Authors
 *
 * The base class for autocomplete features.
 */
class Liveblog_Entry_Extend_Feature_Authors extends Liveblog_Entry_Extend_Feature {

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
	protected $prefixes = [ '@', '\x{0040}' ];

	/**
	 * An author cache for the filter.
	 *
	 * @var array
	 */
	protected $authors = [];

	/**
	 * Called by Liveblog_Entry_Extend::load()
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
		$this->revert_regex = implode(
			'',
			[
				preg_quote( '<a href="', '~' ),
				'[^"]+',
				preg_quote( '" class="liveblog-author ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '"', '~' ),
				'[^>]*>\\1',
				preg_quote( '</a>', '~' ),
			]
		);

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_author_revert_regex', $this->revert_regex );

		// We hook into the post_class filter to
		// be able to alter the entry content.
		add_filter( 'post_class', [ $this, 'add_author_class_to_entry' ], 10, 3 );

		// Add an ajax endpoint to find the authors
		// which is to be used on the front end.
		add_action( 'wp_ajax_liveblog_authors', [ $this, 'ajax_authors' ] );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {

		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_authors';

		if ( Liveblog::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( apply_filters( 'liveblog_endpoint_url', Liveblog_Rest_Api::build_endpoint_base() ) . 'authors' ) );
		}

		// Add our config to the front end autocomplete
		// config, after first allowing other plugins,
		// themes, etc. to modify it as required
		$config[] = apply_filters(
			'liveblog_author_config',
			[
				'type'        => 'ajax',
				'cache'       => 1000 * 60 * 30,
				'url'         => esc_url( $endpoint_url ),
				'displayKey'  => 'key',
				'search'      => 'key',
				'regex'       => '@([\w\-]*)$',
				'replacement' => '@${key}',
				'template'    => '${avatar} ${name}',
				'trigger'     => '@',
				'name'        => 'Author',
				'replaceText' => '@$',
			]
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

		// The args used in the get_users query.
		$args = [
			'who'    => 'authors',
			'fields' => [ 'user_nicename' ],
		];

		// Map the authors and store them on the object
		// for use in another function, we need
		// them to be lowercased.
		$authors       = apply_filters( 'liveblog_author_list', get_users( $args ), '' );
		$this->authors = array_map( [ $this, 'map_authors' ], $authors );

		// Map over every match and apply it via the
		// preg_replace_callback method.
		$entry['content'] = preg_replace_callback(
			$this->get_regex(),
			[ $this, 'preg_replace_callback' ],
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
		if ( ! in_array( $author, $this->authors, true ) ) {
			return $match[0];
		}

		// Replace the @author content with a link to
		// the author's post listing page.
		return str_replace(
			$match[1],
			'<a href="' . get_author_posts_url( -1, $author ) . '" class="liveblog-author ' . $this->class_prefix . $author . '">' . $author . '</a>',
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
		return preg_replace( '~' . $this->revert_regex . '~', '@$1', $content );
	}

	/**
	 * Adds author-{author} class to entry
	 *
	 * @param array  $classes
	 * @param string $class
	 * @param int    $entry_id
	 * @return array
	 */
	public function add_author_class_to_entry( $classes, $class, $entry_id ) {
		if ( ! $entry_id ) {
			return $classes;
		}

		$entry = get_post( $entry_id );
		if ( ! is_object( $entry ) ) {
			return $classes;
		}

		// Grab all the prefixed classes applied.
		$authors = [];
		preg_match_all( '/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/', $entry->post_content, $authors );

		// Append the first class to the classes array.
		$classes = array_merge( $classes, $authors[0] );

		return $classes;
	}

	/**
	 * Returns an array of authors
	 *
	 * @return array
	 */
	public function ajax_authors() {
		$term  = filter_input( INPUT_GET, 'autocomplete', FILTER_SANITIZE_STRING );
		$users = $this->get_authors( $term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $users );
		exit;
	}

	public function get_authors( $term ) {

		// The args used in the get_users query.
		$args = [
			'who'    => 'authors',
			'fields' => [ 'ID', 'user_nicename', 'display_name' ],
			'number' => 10,
		];

		// If there is no search term then search
		// for nothing to get everything.
		// If there is a search term, then append
		// '*' to match chars after the term.
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term . '*';
		}

		// Map the authors into the expected format.
		$authors = apply_filters( 'liveblog_author_list', get_users( $args ), $term );
		$users   = array_map( [ $this, 'map_ajax_authors' ], $authors );

		return $users;
	}

	/**
	 * Maps the authors for ajax.
	 *
	 * @param string $author
	 * @return string
	 */
	public function map_ajax_authors( $author ) {
		return [
			'id'     => $author->ID,
			'key'    => strtolower( $author->user_nicename ),
			'name'   => $author->display_name,
			'avatar' => Liveblog::get_avatar( $author->ID, 20 ),
		];
	}
}
