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
     *
     * @var array
     */
    public static $autocomplete = array();

    /**
     * Autocomplete features
     *
     * @var array
     */
    protected static $features = array( 'hashtags', 'commands', 'emojis', 'authors' );

    /**
     * Called by WPCOM_Liveblog::load(),
     * it attaches the new command.
     */
    public static function load() {
        add_action( 'wp_enqueue_scripts',           array( __CLASS__, 'enqueue_scripts' ) );
        add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'strip_input' ), 1 );
        add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'strip_input' ), 1 );

        $regex_prefix  = '~(?<!\S)(?:';
        $regex_postfix = '){1}([0-9_\p{L}]*[_\p{L}][0-9_\p{L}]*)~um';

        foreach ( self::$features as $name ) {
        	$class = __CLASS__.'_Feature_'.ucfirst( $name );
        	$feature = new $class;

	        add_filter( 'liveblog_extend_autocomplete',  array( $feature, 'get_config' ), 10 );
	        add_filter( 'liveblog_before_insert_entry',  array( $feature, 'filter' ), 10 );
	        add_filter( 'liveblog_before_update_entry',  array( $feature, 'filter' ), 10 );
	        add_filter( 'liveblog_before_preview_entry', array( $feature, 'filter' ), 10 );
	        add_filter( 'liveblog_before_edit_entry',    array( $feature, 'revert' ), 10 );

	        $feature->set_prefixes( apply_filters( 'liveblog_'.$name.'_prefixes', $feature->get_prefixes() ) );

	        $regex = $regex_prefix.implode( '|', $feature->get_prefixes() ).$regex_postfix;
	        $feature->set_regex( apply_filters( 'liveblog_'.$name.'_regex', $regex ) );

        	$feature->load();
        }

        self::$autocomplete = apply_filters( 'liveblog_extend_autocomplete', self::$autocomplete );
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
            wp_enqueue_style(  'textcomplete-css',    plugins_url( '../css/jquery.textcomplete.css',   __FILE__ ) );
            wp_enqueue_script( 'textcomplete-script', plugins_url( '../js/jquery.textcomplete.min.js', __FILE__ ), false, true );
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
