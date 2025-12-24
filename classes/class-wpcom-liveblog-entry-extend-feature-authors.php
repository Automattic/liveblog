<?php
/**
 * Authors autocomplete feature for liveblog entries.
 *
 * @package Liveblog
 */

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

		// Allow plugins, themes, etc. to change the generated author class.
		$this->class_prefix = apply_filters( 'liveblog_author_class', $this->class_prefix );

		// This is the regex used to revert the generated author html back to
		// the raw input format (e.g. @author).
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<a href="', '~' ),
				'[^"]+',
				preg_quote( '" class="liveblog-author ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '"', '~' ),
				'[^>]*>\\1',
				preg_quote( '</a>', '~' ),
			)
		);

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_author_revert_regex', $this->revert_regex );

		// Hook into the comment_class filter to alter the comment content.
		add_filter( 'comment_class', array( $this, 'add_author_class_to_entry' ), 10, 3 );

		// Add an ajax endpoint to find authors for frontend autocomplete.
		add_action( 'wp_ajax_liveblog_authors', array( $this, 'ajax_authors' ) );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config The existing autocomplete configuration.
	 * @return array Updated configuration.
	 */
	public function get_config( $config ) {

		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_authors';

		if ( WPCOM_Liveblog::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( WPCOM_Liveblog_Rest_Api::build_endpoint_base() ) . 'authors' );
		}

		// Add config to frontend autocomplete after allowing modifications.
		$config[] = apply_filters(
			'liveblog_author_config',
			array(
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
			)
		);

		return $config;
	}

	/**
	 * Filters the input.
	 *
	 * @param array $entry The liveblog entry.
	 * @return array Filtered entry.
	 */
	public function filter( $entry ) {

		// The args used in the get_users query.
		$args = array(
			'capability' => 'edit_posts',
			'fields'     => array( 'user_nicename' ),
		);

		// Map authors and store them on the object for use in callback.
		$authors       = apply_filters( 'liveblog_author_list', get_users( $args ), '' );
		$this->authors = array_map( array( $this, 'map_authors' ), $authors );

		// Map over every match via the preg_replace_callback method.
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
	 * @param object $author The author user object.
	 * @return string Lowercased nicename.
	 */
	public function map_authors( $author ) {
		return strtolower( $author->user_nicename );
	}

	/**
	 * The preg replace callback for the filter.
	 *
	 * @param array $regex_match The regex match array.
	 * @return string Replacement string.
	 */
	public function preg_replace_callback( $regex_match ) {

		// Allow any plugins, themes, etc. to modify the match.
		$author = apply_filters( 'liveblog_author', $regex_match[2] );

		// If the match isn't an author, return unchanged.
		if ( ! in_array( $author, $this->authors, true ) ) {
			return $regex_match[0];
		}

		// Get the user object to retrieve display name.
		$user         = get_user_by( 'slug', $author );
		$display_name = $user ? $user->display_name : $author;

		// Replace @author with a link to the author's post listing page.
		return str_replace(
			$regex_match[1],
			'<a href="' . get_author_posts_url( -1, $author ) . '" class="liveblog-author ' . $this->class_prefix . $author . '">' . esc_html( $display_name ) . '</a>',
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
		return preg_replace( '~' . $this->revert_regex . '~', '@$1', $content );
	}

	/**
	 * Adds author-{author} class to entry.
	 *
	 * @param array  $classes    The existing classes.
	 * @param string $css_class  The class name.
	 * @param int    $comment_id The comment ID.
	 * @return array Updated classes.
	 */
	public function add_author_class_to_entry( $classes, $css_class, $comment_id ) {
		$authors = array();
		$comment = get_comment( $comment_id );

		// Check if the comment is a live blog comment.
		if ( WPCOM_Liveblog::KEY === $comment->comment_type ) {

			// Grab all the prefixed classes applied.
			preg_match_all( '/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/', $comment->comment_content, $authors );

			// Append the first class to the classes array.
			$classes = array_merge( $classes, $authors[0] );
		}

		return $classes;
	}

	/**
	 * Returns an array of authors.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_authors() {

		// Sanitize the input safely.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public autocomplete endpoint.
		if ( isset( $_GET['autocomplete'] ) ) {
			$term = sanitize_text_field( wp_unslash( $_GET['autocomplete'] ) );
		// phpcs:enable
		} else {
			$term = '';
		}

		$users = $this->get_authors( $term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $users );
		exit;
	}

	/**
	 * Get authors matching a search term.
	 *
	 * @param string $term The search term.
	 * @return array Array of authors.
	 */
	public function get_authors( $term ) {

		// The args used in the get_users query.
		$args = array(
			'capability' => 'edit_posts',
			'fields'     => array( 'ID', 'user_nicename', 'display_name' ),
			'number'     => 10,
		);

		// If there is a search term, append '*' to match chars after the term.
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term . '*';
		}

		// Map the authors into the expected format.
		$authors = apply_filters( 'liveblog_author_list', get_users( $args ), $term );
		$users   = array_map( array( $this, 'map_ajax_authors' ), $authors );

		return $users;
	}

	/**
	 * Maps the authors for ajax.
	 *
	 * @param object $author The author user object.
	 * @return array Author data array.
	 */
	public function map_ajax_authors( $author ) {
		return array(
			'id'     => $author->ID,
			'key'    => strtolower( $author->user_nicename ),
			'name'   => $author->display_name,
			'avatar' => get_avatar( $author->ID, 20 ),
		);
	}
}
