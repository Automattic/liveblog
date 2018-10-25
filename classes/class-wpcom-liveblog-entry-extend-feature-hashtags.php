<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature_Hashtags
 *
 * The base class for autocomplete features.
 */
class WPCOM_Liveblog_Entry_Extend_Feature_Hashtags extends WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The taxonomy name.
	 *
	 * @var string
	 */
	protected static $taxonomy = 'hashtags';

	/**
	 * The class prefix.
	 *
	 * @var string
	 */
	protected $class_prefix = 'term-';

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array( '#', '\x{ff03}' );

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 */
	public function load() {

		// Allow plugins, themes, etc. to change
		// the generated hashtag class.
		$this->class_prefix = apply_filters( 'liveblog_hashtag_class', $this->class_prefix );

		$prefixes = implode( '|', $this->get_prefixes() );

		// Set a better regex for hashtags to allow for hex values in content -- see https://regex101.com/r/CLWsCo/
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- ignore indentation
		$this->set_regex(
			'~'
			. '(?:'
			.     '(?<!\S)'          // any visible character
			. '|'
			.     '>?'               // possible right angle bracket(s)
			. ')'
			. '(?:'
			.     '(?<!'
			.         '&'            // literal ampersand
			.     '|'
			.         '&amp;'        // encoded ampersand
			.     ')'
			. ')'
			. '('
			.     "(?:{$prefixes})"  // hashtag prefixes
			.     '([0-9_\-\p{L}]*)' // 1: numerals, underscores, dashes, and any letter in any language
			. ')'
			. '~um'
		);
		// phpcs:enable

		// This is the regex used to revert the
		// generated hashtag html back to the
		// raw input format (e.g #hashtag).
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<span class="liveblog-hash ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '">', '~' ),
				'([^"]+)',
				preg_quote( '</span>', '~' ),
			)
		);

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_hashtag_revert_regex', $this->revert_regex );

		// We hook into the comment_class filter to
		// be able to alter the comment content.
		add_filter( 'comment_class', array( $this, 'add_term_class_to_entry' ), 10, 3 );

		// Hook into the WordPress init method to
		// make sure the taxonomy is created.
		add_action( 'init', array( $this, 'add_hashtag_taxonomy' ) );

		// Add an ajax endpoint to find the hashtags
		// which is to be used on the front end.
		add_action( 'wp_ajax_liveblog_terms', array( $this, 'ajax_terms' ) );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {

		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_terms';

		if ( WPCOM_Liveblog::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( WPCOM_Liveblog_Rest_Api::build_endpoint_base() ) . 'hashtags' );
		}

		// Add our config to the front end autocomplete
		// config, after first allowing other plugins,
		// themes, etc. to modify it as required
		$config[] = apply_filters(
			'liveblog_hashtag_config',
			array(
				'type'        => 'ajax',
				'cache'       => 1000 * 60,
				'regex'       => '#([\w\d\-]*)$',
				'replacement' => '#${slug}',
				'trigger'     => '#',
				'displayKey'  => 'slug',
				'name'        => 'Hashtag',
				'template'    => '${slug}',
				'replaceText' => '#$',
				'url'         => esc_url( $endpoint_url ),
			)
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
	 * The preg replace callback for the filter.
	 *
	 * @param array $match
	 * @return string
	 */
	public function preg_replace_callback( $match ) {
		// Remove any special characters and convert them
		// to their non-accented equivalents
		$hashtag = iconv( 'UTF-8', 'ASCII//TRANSLIT', $match[2] );

		// Sanitize the hashtag in the way it's expected.
		$hashtag = sanitize_term( array( 'slug' => $hashtag ), self::$taxonomy, 'db' );

		// Grab the hastag as a slug.
		$hashtag = $hashtag['slug'];

		// If it doesn't exist, then make it.
		if ( ! get_term_by( 'slug', $hashtag, self::$taxonomy ) ) {
			wp_insert_term( $hashtag, self::$taxonomy );
		}

		// Replace the #hashtag content with a styled
		// span with the hashtag as content.
		return str_replace(
			$match[1],
			'<span class="liveblog-hash ' . $this->class_prefix . $hashtag . '">' . $hashtag . '</span>',
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
		return preg_replace( '~' . $this->revert_regex . '~', '#$1', $content );
	}

	/**
	 * Adds term-{hashtag} class to entry
	 *
	 * @param array  $classes
	 * @param string $class
	 * @param int    $comment_id
	 * @return array
	 */
	public function add_term_class_to_entry( $classes, $class, $comment_id ) {
		$terms   = array();
		$comment = get_comment( $comment_id );

		// Check if the comment is a live blog comment.
		if ( WPCOM_Liveblog::KEY === $comment->comment_type ) {

			// Grab all the prefixed classes applied.
			preg_match_all( '/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '(\w\-?)+/', $comment->comment_content, $terms );

			// Append the first class to the classes array.
			$classes = array_merge( $classes, $terms[0] );
		}

		return $classes;
	}

	/**
	 * Returns an array of terms
	 *
	 * @return array
	 */
	public function ajax_terms() {

		//Sanitize the input safely.
		if ( isset( $_GET['autocomplete'] ) ) { // input var ok
			$search_term = sanitize_text_field( wp_unslash( $_GET['autocomplete'] ) ); // input var ok
		} else {
			$search_term = '';
		}

		// Get a list of hashtags matching the 'autocomplete' request variable
		$terms = $this->get_hashtag_terms( $search_term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $terms );

		exit;
	}

	/**
	 * Get a list of hashtags matching the search term
	 *
	 * @param string $term The term to search for
	 *
	 * @return array Array of matching hastags
	 */
	public function get_hashtag_terms( $term ) {

		// The args used in the get_terms query.
		$args = array(
			'hide_empty' => false,
			'number'     => 10,
		);

		// If there is no search term then search
		// for nothing to get everything.
		// If there is a search term, then add it
		// to the get_terms query args.
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term;
		}

		// We add a filter to strip out the name search.
		add_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		// Grab the terms from the query results.
		$terms = get_terms( self::$taxonomy, $args );

		// Remove the filter just to clean up.
		remove_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		return $terms;

	}

	/**
	 * Removes the name search from the clauses.
	 *
	 * @param array $clauses
	 *
	 * @return array
	 */
	public function remove_name_search( $clauses ) {
		// Remove the where clause's section about the name.
		$clauses['where'] = preg_replace(
			array(
				'~\\(\\(.*(?=' . preg_quote( "(t.slug LIKE '", '~' ) . ')~',
				'~(%\'\\))\\)~',
			),
			'($1',
			$clauses['where']
		);

		return $clauses;
	}

	/**
	 * Add hashtag taxonomy
	 *
	 * @return void
	 */
	public function add_hashtag_taxonomy() {

		// All the taxonomy related labels.
		$labels = array(
			'name'              => _x( 'Hashtags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Hashtag', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Hashtags' ),
			'all_items'         => __( 'All Hashtags' ),
			'parent_item'       => __( 'Parent Hashtag' ),
			'parent_item_colon' => __( 'Parent Hashtag:' ),
			'edit_item'         => __( 'Edit Hashtag' ),
			'update_item'       => __( 'Update Hashtag' ),
			'add_new_item'      => __( 'Add New Hashtag' ),
			'new_item_name'     => __( 'New Hashtag' ),
			'menu_name'         => __( 'Hashtags' ),
		);

		// The args to pass to the register_taxonomy function.
		$args = array(
			'show_ui' => true,
			'labels'  => $labels,
		);

		// Register the taxonomy.
		register_taxonomy( self::$taxonomy, 'post', $args );
	}

}
