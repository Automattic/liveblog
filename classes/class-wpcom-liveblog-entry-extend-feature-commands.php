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
	 * @codeCoverageIgnore
	 */
	public function load() {

		// Allow plugins, themes, etc. to change
		// the generated command class.
		$this->class_prefix_local = apply_filters( 'liveblog_command_class', self::$class_prefix );

		// Allow plugins, themes, etc. to change
		// the current command set.
		add_action( 'after_setup_theme', array( $this, 'custom_commands' ), 10 );

		// This is the regex used to revert the
		// generated author html back to the
		// raw input format (e.g /key).
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<span class="liveblog-command ', '~' ),
				preg_quote( $this->class_prefix_local, '~' ),
				'([^"]+)',
				preg_quote( '">', '~' ),
				'([^"]+)',
				preg_quote( '</span>', '~' ),
			)
		);

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_command_revert_regex', $this->revert_regex );

		// We hook into the comment_class filter to
		// be able to alter the comment content.
		add_filter( 'comment_class', array( $this, 'add_type_class_to_entry' ), 10, 3 );

		// Hook into the entry saving to
		// execute the command logic.
		add_action( 'liveblog_insert_entry', array( $this, 'do_action_per_type' ), 10, 2 );
	}

	/**
	 * Returns the custom commands and allows for customizing the command set
	 *
	 */
	public function custom_commands() {
		$this->commands = apply_filters( 'liveblog_active_commands', $this->commands );
	}
	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {

		// Add our config to the front end autocomplete
		// config, after first allowing other plugins,
		// themes, etc. to modify it as required
		$config[] = apply_filters(
			'liveblog_command_config',
			array(
				'trigger'     => '/',
				'data'        => $this->get_commands(),
				'displayKey'  => false,
				'regex'       => '/(\w*)$',
				'replacement' => '/${term}',
				'replaceText' => '/$',
				'name'        => 'Command',
				'template'    => false,
			)
		);

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

		// Store the filters on the object for
		// use in another function.
		$this->filters = array();

		// Map over every match and apply it via the
		// preg_replace_callback method.
		$entry['content'] = preg_replace_callback(
			$this->get_regex(),
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		);

		// For all the filters found,
		// apply them to the content.
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

		// Allow any plugins, themes, etc. to modify the match.
		$type = apply_filters( 'liveblog_command_type', $match[2] );

		// If it's not a command that's been registered then skip it.
		if ( ! in_array( $type, $this->commands, true ) ) {
			return $match[0];
		}

		// Append the filter to the filters array.
		$this->filters[] = "liveblog_command_{$type}_before";

		// Replace the content with a hidden span
		// to show the command was matched.
		return str_replace(
			$match[1],
			'<span class="liveblog-command ' . $this->class_prefix_local . $type . '">' . $type . '</span>',
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

		// Check if the comment is a live blog comment.
		if ( WPCOM_Liveblog::KEY === $comment->comment_type ) {

			// Grab all the prefixed classes applied.
			preg_match_all( '/(?<!\w)' . $this->class_prefix_local . '\w+/', $comment->comment_content, $types );

			// Append the first class to the classes array.
			$classes = array_merge( $classes, $types[0] );
		}

		return $classes;
	}

	/**
	 * Reverts the input.
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function revert( $content ) {
		return preg_replace( '~' . $this->revert_regex . '~', '/$1', $content );
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

		// Match all of the command types.
		preg_match_all( '/(?<!\w)' . $this->class_prefix_local . '\w+/', $content, $types );

		foreach ( $types[0] as $type ) {
			$type = ltrim( $type, $this->class_prefix_local );

			// Run the command_after action on the
			// content for the current type.
			do_action( "liveblog_command_${type}_after", $content, $id, $post_id );
		}
	}

}
