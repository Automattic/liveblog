<?php
/**
 * Hashtags autocomplete feature for liveblog entries.
 *
 * @package Liveblog
 */

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

		// Allow plugins, themes, etc. to change the generated hashtag class.
		$this->class_prefix = apply_filters( 'liveblog_hashtag_class', $this->class_prefix );

		$prefixes = implode( '|', $this->get_prefixes() );

		// Set a better regex for hashtags to allow for hex values in content. See https://regex101.com/r/CLWsCo/.
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound, Squiz.Commenting.InlineComment.InvalidEndChar -- ignore indentation and inline comments
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

		// This is the regex used to revert the generated hashtag html back to
		// the raw input format (e.g. #hashtag).
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

		// Hook into the comment_class filter to alter the comment content.
		add_filter( 'comment_class', array( $this, 'add_term_class_to_entry' ), 10, 3 );

		// Hook into the WordPress init method to create the taxonomy.
		add_action( 'init', array( $this, 'add_hashtag_taxonomy' ) );

		// Add an ajax endpoint to find hashtags for frontend autocomplete.
		add_action( 'wp_ajax_liveblog_terms', array( $this, 'ajax_terms' ) );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config The existing autocomplete configuration.
	 * @return array Updated configuration.
	 */
	public function get_config( $config ) {

		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_terms';

		if ( WPCOM_Liveblog::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( WPCOM_Liveblog_Rest_Api::build_endpoint_base() ) . 'hashtags' );
		}

		// Add config to frontend autocomplete after allowing modifications.
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
	 * The current post ID being processed.
	 *
	 * @var int
	 */
	protected $current_post_id = 0;

	/**
	 * Filters the input.
	 *
	 * @param array $entry The liveblog entry.
	 * @return array Filtered entry.
	 */
	public function filter( $entry ) {
		// Store the post ID for use in the callback.
		$this->current_post_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;

		// Map over every match via the preg_replace_callback method.
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
	 * @param array $regex_match The regex match array.
	 * @return string Replacement string.
	 */
	public function preg_replace_callback( $regex_match ) {
		// Remove any special characters and convert them to non-accented equivalents.
		$hashtag = iconv( 'UTF-8', 'ASCII//TRANSLIT', $regex_match[2] );

		// Sanitize the hashtag in the way it's expected.
		$hashtag = sanitize_term( array( 'slug' => $hashtag ), self::$taxonomy, 'db' );

		// Grab the hashtag as a slug.
		$hashtag = $hashtag['slug'];

		// If it doesn't exist, then make it.
		$term = get_term_by( 'slug', $hashtag, self::$taxonomy );
		if ( ! $term ) {
			$result = wp_insert_term( $hashtag, self::$taxonomy );
			if ( ! is_wp_error( $result ) ) {
				$term = get_term( $result['term_id'], self::$taxonomy );
			}
		}

		// Assign the hashtag term to the liveblog post so it appears in archives.
		if ( $term && $this->current_post_id ) {
			wp_set_object_terms( $this->current_post_id, $term->term_id, self::$taxonomy, true );
		}

		// Get the term link for the hashtag.
		$term_link = $term ? get_term_link( $term, self::$taxonomy ) : '';

		// Replace the #hashtag content with a link to the hashtag archive.
		if ( $term_link && ! is_wp_error( $term_link ) ) {
			return str_replace(
				$regex_match[1],
				'<a href="' . esc_url( $term_link ) . '" class="liveblog-hash ' . $this->class_prefix . $hashtag . '">' . esc_html( $hashtag ) . '</a>',
				$regex_match[0]
			);
		}

		// Fallback to span if term link fails.
		return str_replace(
			$regex_match[1],
			'<span class="liveblog-hash ' . $this->class_prefix . $hashtag . '">' . esc_html( $hashtag ) . '</span>',
			$regex_match[0]
		);
	}

	/**
	 * Reverts the input.
	 *
	 * @param string $content The content to revert.
	 * @return string Reverted content.
	 */
	public function revert( $content ) {
		return preg_replace( '~' . $this->revert_regex . '~', '#$1', $content );
	}

	/**
	 * Adds term-{hashtag} class to entry.
	 *
	 * @param array  $classes    The existing classes.
	 * @param string $css_class  The class name.
	 * @param int    $comment_id The comment ID.
	 * @return array Updated classes.
	 */
	public function add_term_class_to_entry( $classes, $css_class, $comment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Required by WordPress comment_class filter.
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
	 * Returns an array of terms.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_terms() {

		// Sanitize the input safely.
		if ( isset( $_GET['autocomplete'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public autocomplete endpoint.
			$search_term = sanitize_text_field( wp_unslash( $_GET['autocomplete'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public autocomplete endpoint.
		} else {
			$search_term = '';
		}

		// Get a list of hashtags matching the 'autocomplete' request variable.
		$terms = $this->get_hashtag_terms( $search_term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $terms );

		exit;
	}

	/**
	 * Get a list of hashtags matching the search term.
	 *
	 * @param string $term The term to search for.
	 *
	 * @return array Array of matching hashtags.
	 */
	public function get_hashtag_terms( $term ) {

		// The args used in the get_terms query.
		$args = array(
			'hide_empty' => false,
			'number'     => 10,
		);

		// If there is a search term, add it to the get_terms query args.
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term;
		}

		// Add a filter to strip out the name search.
		add_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		// Grab the terms from the query results.
		$args['taxonomy'] = self::$taxonomy;
		$terms            = get_terms( $args );

		// Remove the filter to clean up.
		remove_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		return $terms;
	}

	/**
	 * Removes the name search from the clauses.
	 *
	 * @param array $clauses The query clauses.
	 *
	 * @return array Modified clauses.
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
	 * Add hashtag taxonomy.
	 *
	 * @return void
	 */
	public function add_hashtag_taxonomy() {

		// All the taxonomy related labels.
		$labels = array(
			'name'              => _x( 'Hashtags', 'taxonomy general name', 'liveblog' ),
			'singular_name'     => _x( 'Hashtag', 'taxonomy singular name', 'liveblog' ),
			'search_items'      => __( 'Search Hashtags', 'liveblog' ),
			'all_items'         => __( 'All Hashtags', 'liveblog' ),
			'parent_item'       => __( 'Parent Hashtag', 'liveblog' ),
			'parent_item_colon' => __( 'Parent Hashtag:', 'liveblog' ),
			'edit_item'         => __( 'Edit Hashtag', 'liveblog' ),
			'update_item'       => __( 'Update Hashtag', 'liveblog' ),
			'add_new_item'      => __( 'Add New Hashtag', 'liveblog' ),
			'new_item_name'     => __( 'New Hashtag', 'liveblog' ),
			'menu_name'         => __( 'Hashtags', 'liveblog' ),
		);

		// The args to pass to the register_taxonomy function.
		$args = array(
			'show_ui' => true,
			'public'  => true,
			'labels'  => $labels,
		);

		// Register the taxonomy.
		register_taxonomy( self::$taxonomy, 'post', $args );
	}
}
