<?php

/**
 * Class WPCOM_Liveblog_Entry_Key_Events
 *
 * Adds the /key command which attaches meta data to that
 * liveblog entry. This meta data is then used by the
 * shortcode [liveblog_key_events] to build a list of
 * key events.
 */
class WPCOM_Liveblog_Entry_Key_Events {

	/**
	 * Set the meta_key and meta_value
	 */
	const meta_key   = 'liveblog_key_entry';
	const meta_value = 'true';

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * new command and shortcode.
	 */
	public static function load() {
		add_filter( 'liveblog_active_commands', array( __CLASS__, 'add_key_command' ), 10 );
		add_shortcode( 'liveblog_key_events', array( __CLASS__, 'shortcode' ) );
		add_action( 'liveblog_command_key_after', array( __CLASS__, 'add_key_action' ), 10, 3 );
	}

	/**
	 * Adds the /key command and sets which function
	 * is to handle the action.
	 *
	 * @param $commands
	 * @return mixed
	 */
	public static function add_key_command( $commands ) {
		$commands[] = 'key';

		return $commands;
	}

	/**
	 * Called when the /key command is used in an entry,
	 * it attaches meta to set it as a key entry.
	 *
	 * @param $content
	 * @param $id
	 * @param $post_id
	 */
	public static function add_key_action( $content, $id, $post_id ) {
		add_comment_meta( $id, self::meta_key, self::meta_value );
	}

	/**
	 * Builds the box to display key entries
	 *
	 * @param $commands
	 */
	public static function shortcode( $atts ) {
		global $post;

		$atts = shortcode_atts( array(
			'title' => 'Key Events',
		), $atts );

		if ( ! is_single() ) {
			return;
		}

		$args = array(
			'meta_key'   => self::meta_key,
			'meta_value' => self::meta_value,
		);
		$entry_query = new WPCOM_Liveblog_Entry_Query( $post->ID, WPCOM_Liveblog::key );
		$entries     = (array) $entry_query->get_all( $args );

		if ( WPCOM_Liveblog::get_liveblog_state( $post->ID ) ) {
			return WPCOM_Liveblog::get_template_part( 'liveblog-key-events.php', array(
				'entries' => $entries,
				'title'   => $atts['title'],
			) );
		}
	}
}
