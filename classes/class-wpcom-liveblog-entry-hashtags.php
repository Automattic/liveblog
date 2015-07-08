<?php

/**
 * Class WPCOM_Liveblog_Entry_Hashtags
 *
 * Adds the ability to have hashtags to entry,
 * an example would be #football which would add the
 * class term-football.
 */
class WPCOM_Liveblog_Entry_Hashtags {

    /**
     * Configure defaults
     */
    protected static $taxonomy  = 'hashtag';
    public static $class_prefix = 'term-';
    public static $character    = array( '#', '#', 'ff03' );
    public static $regex_filter;
    public static $regex_render;

    /**
     * Called by WPCOM_Liveblog::load(), it applies two
     * filters allowing the following to be changed:
     * The character '#'
     * The class prefix 'term-'
     * And finally filter it adds a taxonomy.
     */
    public static function load() {
        self::$character    = apply_filters( 'liveblog_hashtag_character', self::$character );
        self::$class_prefix = apply_filters( 'liveblog_hashtag_class',     self::$class_prefix );

        self::$regex_filter = '/(?<!\S)(?=.{2,140}$)(?:' . self::$character[1]  . '|\x{' . self::$character[2] . '}){1}[0-9_\p{L}]*[_\p{L}][0-9_\p{L}]*/um';
        self::$regex_render = '/(?<!\w)' . self::$class_prefix  . '\w+/';

        add_filter( 'liveblog_extend_autocomplete', array( __CLASS__, 'autocomplete' ), 11 );
        add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'filter' ), 10 );
        add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'filter' ), 10 );
        add_filter( 'comment_class',                array( __CLASS__, 'add_term_class_to_entry' ), 10, 3 );
        add_action( 'init',                         array( __CLASS__, 'add_hashtag_taxonomy') );
        add_action( 'wp_ajax_liveblog_terms',       array( __CLASS__, 'ajax_terms') );
    }

    /**
     * Returns an array of terms
     *
     * @return array
     */
    public static function ajax_terms() {
        $args = array(
            'hide_empty' => false,
            'fields'     => 'names',
        );
        $terms = get_terms( self::$taxonomy, $args );
        header( "Content-Type: application/json" );
        echo json_encode( $terms );
        exit;
    }

    /**
     * Returns the character used to define a hashtag
     * @return mixed
     */
    public static function get_character() {
        return self::$character[0];
    }

    /**
     * Supplies data to the js autocomplete
     *
     * @param $autocomplete
     * @return array
     */
    public static function autocomplete( $autocomplete ) {
        $autocomplete[] = array(
            'at'   => self::get_character(),
            'data' => admin_url( 'admin-ajax.php' ) .'?action=liveblog_terms',
        );
        return $autocomplete;
    }

    /**
     * Adds term-{hashtag} class to entry
     *
     * @param $classes
     * @param $class
     * @param $comment_id
     * @return array
     */
    public static function add_term_class_to_entry( $classes, $class, $comment_id ) {
        $terms   = array();
        $comment = get_comment( $comment_id );

        if ( 'liveblog' == $comment->comment_type ) {
            preg_match_all( self::$regex_render, $comment->comment_content, $terms );
            $classes = array_merge($classes, $terms[0]);
        }
        return $classes;
    }

    /**
     * Filters entry before save, converts #hashtag
     * to <span>hashtag</span>
     *
     * @param $entry
     * @return mixed
     */
    public static function filter( $entry ) {
        $hashtags         = array();
        $filtered_content = strip_tags( $entry['content'] );

        preg_match_all( self::$regex_filter, $filtered_content, $hashtags );

        foreach ( $hashtags[0] as $tag ) {
            $term = ltrim( $tag, self::$character[0] );
            $term = apply_filters( 'liveblog_hashtag_term', $term );
            $html = '<span class="liveblog-hash ' . self::$class_prefix . $term . '">' . $term . '</span>';

            $entry['content'] = str_replace( $tag, $html, $entry['content'] );

            if ( ! term_exists( $term, self::$taxonomy ) ) {
                wp_insert_term( $term, self::$taxonomy );
            }
        }
        return $entry;
    }

    /**
     * Add hashtag taxonomy
     */
    public static function add_hashtag_taxonomy() {
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