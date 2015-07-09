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
		$this->class_prefix = apply_filters( 'liveblog_hashtag_class', $this->class_prefix );

		add_filter( 'comment_class',          array( $this, 'add_term_class_to_entry' ), 10, 3 );
		add_action( 'init',                   array( $this, 'add_hashtag_taxonomy') );
		add_action( 'wp_ajax_liveblog_terms', array( $this, 'ajax_terms') );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {
		$config[] = apply_filters( 'liveblog_hashtag_config', array(
			'type'        => 'ajax',
			'cache'       => 1000 * 60,
			'regex'       => '#(\w*)$',
			'url'         => admin_url( 'admin-ajax.php' ) .'?action=liveblog_terms',
			'replacement' => '#${term}',
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
		$entry['content'] = preg_replace_callback( $this->get_regex(), function ($match) {
			$term = apply_filters( 'liveblog_hashtag_term', $match[1] );

			if ( ! term_exists( $term, self::$taxonomy ) ) {
				wp_insert_term( $term, self::$taxonomy );
			}

			return '<span class="liveblog-hash '.$this->class_prefix.$term.'">'.$term.'</span>';
		}, $entry['content']);

		return $entry;
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

		if ( 'liveblog' == $comment->comment_type ) {
			preg_match_all( '/(?<!\w)'.preg_quote( $this->class_prefix ).'\w+/', $comment->comment_content, $terms );
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
		$args = array(
			'hide_empty' => false,
			'fields'     => 'names',
			'number' 	 => 10,
		);

		$term = isset($_GET['autocomplete']) ? $_GET['autocomplete'] : '';
		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term;
		}

		$terms = get_terms( self::$taxonomy, $args );

		header( "Content-Type: application/json" );
		echo json_encode( $terms );

		exit;
	}

	/**
	 * Add hashtag taxonomy
	 *
	 * @return void
	 */
	public function add_hashtag_taxonomy() {
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

		$args = array(
			'show_ui' => true,
			'labels'  => $labels
		);

		register_taxonomy( self::$taxonomy, 'post', $args );
	}

}
