<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend
 *
 * This extends the entry box with an
 * autocomplete system.
 */
class WPCOM_Liveblog_Entry_Extend {

    /**
     * Autocomplete settings
     */
    public static $autocomplete = array();

    /**
     * Called by WPCOM_Liveblog::load(),
     * it attaches the new command.
     */
    public static function load() {
        self::$autocomplete = apply_filters( 'liveblog_extend_autocomplete', self::$autocomplete );

        add_action( 'wp_enqueue_scripts',           array( __CLASS__, 'enqueue_scripts' ) );
        add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'strip_input' ), 1 );
        add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'strip_input' ), 1 );
    }

    /**
     * Returns the settings for autocomplete
     *
     * @return array
     */
    public static function get_autocomplete() {
        return self::$autocomplete;
    }

    /**
     * Loads in the scripts and styles for autocomplete
     */
    public static function enqueue_scripts() {
        if ( WPCOM_Liveblog::is_liveblog_editable() )  {
            wp_enqueue_style(  'at.js-css',    plugins_url( '../css/jquery.atwho.css',   __FILE__ ) );
            wp_enqueue_script( 'caret.js',     plugins_url( '../js/jquery.caret.min.js', __FILE__ ), false, true );
            wp_enqueue_script( 'at.js-script', plugins_url( '../js/jquery.atwho.min.js', __FILE__ ), false, true );
        }
    }

    /**
     * Strips out unneeded spans
     *
     * @param $entry
     * @return mixed
     */
    public static function strip_input( $entry ) {
        $entry['content'] = str_replace( '&nbsp;', ' ', $entry['content'] );
        $entry['content'] = preg_replace( '~\\<span\\s+class\\=\\\\?"atwho\\-\\w+\\\\?"\\s*>([^<]*)\\</span\\>~', '$1', $entry['content'] );
        return $entry;
    }

}
