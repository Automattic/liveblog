<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature_Commands
 *
 * The base class for autocomplete features.
 */
class WPCOM_Liveblog_Entry_Extend_Feature_Commands extends WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The static class prefix base.
	 *
	 * @var string
	 */
	public static $class_prefix = 'type-';

	/**
	 * The class prefix.
	 *
	 * @var string
	 */
	protected $class_prefix_local;

	/**
	 * The commands.
	 *
	 * @var string
	 */
	protected $commands = array();

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array( '/', '\x{002f}' );

	/**
	 * An filters cache for the filter.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 */
	public function load() {
		$this->class_prefix_local = apply_filters( 'liveblog_command_class',   self::$class_prefix );
		$this->commands     	  = apply_filters( 'liveblog_active_commands', $this->commands );

		add_filter( 'comment_class',          array( $this, 'add_type_class_to_entry' ), 10, 3 );
		add_action( 'liveblog_insert_entry',  array( $this, 'do_action_per_type' ), 10, 2 );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {
		$config[] = apply_filters( 'liveblog_command_config', array(
			'type'        => 'static',
			'regex'       => '/(\w*)$',
			'data'        => $this->get_commands(),
			'replacement' => '/${term}',
		) );

		return $config;
	}

	/**
	 * Get all the available commands.
	 *
	 * @return array
	 */
	public function get_commands() {
		return $this->commands;
	}

	/**
	 * Filters the input.
	 *
	 * @param mixed $entry
	 * @return mixed
	 */
	public function filter( $entry ) {
		$this->filters = array();

        $entry['content'] = preg_replace_callback(
        	$this->get_regex(),
        	array( $this, 'preg_replace_callback' ),
        	$entry['content']
        );

		foreach ( $this->filters as $filter ) {
			$entry['content'] = apply_filters( $filter, $entry['content'] );
		}

		return $entry;
	}

	/**
	 * The preg replace callback for the filter.
	 *
	 * @param array $match
	 * @return string
	 */
	public function preg_replace_callback( $match ) {
		$type = apply_filters( 'liveblog_command_type', $match[2] );

		if ( ! in_array( $type, $this->commands ) ) {
		    return $match[0];
		}

		$this->filters[] = "liveblog_command_{$type}_before";

		return str_replace(
			$match[1],
			'<span class="liveblog-command '.$this->class_prefix_local.$type.'">'.$type.'</span>',
			$match[0]
		);
	}

	/**
	 * Adds type-{command} class to entry
	 *
	 * @param array  $classes
	 * @param string $class
	 * @param int    $comment_id
	 * @return array
	 */
	public function add_type_class_to_entry( $classes, $class, $comment_id ) {
		$types   = array();
		$comment = get_comment( $comment_id );

		if ( WPCOM_Liveblog::key == $comment->comment_type ) {
			preg_match_all( '/(?<!\w)'.$this->class_prefix_local.'\w+/', $comment->comment_content, $types );
			$classes = array_merge( $classes, $types[0] );
		}

		return $classes;
	}

	/**
	 * Runs action after entry save
	 *
	 * @param int $id
	 * @param int $post_id
	 * @return void
	 */
	public function do_action_per_type( $id, $post_id ) {
		$types   = array();
		$content = get_comment_text( $id );

		preg_match_all( '/(?<!\w)'.$this->class_prefix_local.'\w+/', $content, $types );

		foreach ( $types[0] as $type ) {
			$type = ltrim( $type, $this->class_prefix_local );

			do_action( "liveblog_command_${type}_after" , $content, $id, $post_id );
		}
	}

}
