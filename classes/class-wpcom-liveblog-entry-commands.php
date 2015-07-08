<?php

/**
 * Class WPCOM_Liveblog_Entry_Commands
 *
 * Adds the ability to have commands to entry,
 * an example would be /key which would add the
 * class type-key then firing a supplied action.
 */
class WPCOM_Liveblog_Entry_Commands {

    /**
     * Configure defaults
     */
    public static $active_commands = array();
    public static $class_prefix    = 'type-';
    public static $character       = array('/', '\/', '002f');
    public static $regex_filter;
    public static $regex_render;

    /**
     * Called by WPCOM_Liveblog::load(), it applies three
     * filters allowing the following to be changed:
     * The character '/'
     * The class prefix 'type-'
     * The final filter is for adding commands, each commands'
     * filter or action is added if supplied.
     */
    public static function load() {
        self::$character       = apply_filters( 'liveblog_command_character', self::$character );
        self::$class_prefix    = apply_filters( 'liveblog_command_class',     self::$class_prefix );
        self::$active_commands = apply_filters( 'liveblog_active_commands',   self::$active_commands );

        self::$regex_filter    = '/(?<!\S)(?=.{2,140}$)(?:' . self::$character[1]  . '|\x{' . self::$character[2] . '}){1}[0-9_\p{L}]*[_\p{L}][0-9_\p{L}]*/um';
        self::$regex_render    = '/(?<!\w)' . self::$class_prefix  . '\w+/';

        foreach ( self::$active_commands as $name => $callbacks ) {
            if ( $callbacks[0] ) {
                add_filter( 'liveblog_command_filter_' . $name, $callbacks[0], 10 );
            }
            if ( $callbacks[1] ) {
                add_action( 'liveblog_command_action_' . $name, $callbacks[1], 10, 3 );
            }
        }

        add_filter( 'liveblog_extend_autocomplete', array( __CLASS__, 'autocomplete' ), 10 );
        add_filter( 'comment_class',                array( __CLASS__, 'add_type_class_to_entry' ), 10, 3 );
        add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'filter' ), 10 );
        add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'filter' ), 10 );
        add_action( 'liveblog_insert_entry',        array( __CLASS__, 'do_action_per_type' ), 10, 2 );
    }

    /**
     * Returns an array of commands
     *
     * @return array
     */
    public static function get_commands() {
        return array_keys( self::$active_commands );
    }

    /**
     * Returns the character used to define a command
     *
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
            'at'  => self::get_character(),
            'data'  => self::get_commands(),
        );
        return $autocomplete;
    }

    /**
     * Adds type-{command-name} class to entry
     *
     * @param $classes
     * @param $class
     * @param $comment_id
     * @return array
     */
    public static function add_type_class_to_entry( $classes, $class, $comment_id ) {
        $types   = array();
        $comment = get_comment( $comment_id );

        if ( 'liveblog' == $comment->comment_type ) {
            preg_match_all( self::$regex_render, $comment->comment_content, $types );
            $classes = array_merge($classes, $types[0]);
        }
        return $classes;
    }

    /**
     * Runs action after entry save
     *
     * @param $id
     * @param $post_id
     */
    public static function do_action_per_type( $id, $post_id ) {
        $types   = array();
        $content = get_comment_text( $id );

        preg_match_all( self::$regex_render, $content, $types );
        
        foreach ( $types[0] as $type ) {
            $type = ltrim( $type, self::$class_prefix );
            if ( ! empty( self::$active_commands[$type][1] ) ) {
                do_action( 'liveblog_command_action_' . $type, $content, $id, $post_id );
            }
        }
    }

    /**
     * Filters entry before save, converts /command
     * to <span>command</span>
     *
     * @param $entry
     * @return mixed
     */
    public static function filter( $entry ) {
        $commands         = array();
        $filtered_content = strip_tags( $entry['content'] );

        preg_match_all( self::$regex_filter, $filtered_content, $commands );

        foreach ( $commands[0] as $tag ) {
            $type = ltrim( $tag, self::$character[0] );
            $type = apply_filters( 'liveblog_command_type', $type );
            $html = '<span class="liveblog-command ' . self::$class_prefix . $type . '">' . $type . '</span>';

            $entry['content'] = str_replace( $tag, $html, $entry['content'] );

            if ( ! empty( self::$active_commands[$type][0] ) ) {
                $entry['content'] = apply_filters( 'liveblog_command_filter_' . $type, $entry['content'] );
            }
        }
        return $entry;
    }

}