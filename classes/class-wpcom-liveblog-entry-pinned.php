<?php
/**
 * Pinned entries functionality for liveblog entries.
 *
 * @package Liveblog
 */

/**
 * Class WPCOM_Liveblog_Entry_Pinned
 *
 * Adds the /pin command so an entry stays at the top of the
 * liveblog. The pinned state is read from the entry content
 * rather than stored as meta, so each entry (and every cached
 * polling response that holds it) keeps a fixed value. Editing
 * an entry to remove /pin unpins it.
 *
 * @since 1.13.0
 */
class WPCOM_Liveblog_Entry_Pinned {

	/**
	 * The command name.
	 *
	 * @var string
	 */
	const COMMAND = 'pin';

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * new command and the JSON flag.
	 *
	 * @return void
	 */
	public static function load() {

		// Hook into the liveblog_active_commands
		// filter to append the /pin command.
		add_filter( 'liveblog_active_commands', array( __CLASS__, 'add_pin_command' ), 10 );

		// Hook into the liveblog_entry_for_json filter
		// to flag pinned entries.
		add_filter( 'liveblog_entry_for_json', array( __CLASS__, 'add_pinned_flag' ), 10, 2 );
	}

	/**
	 * Adds the /pin command.
	 *
	 * @param array $commands The existing commands.
	 * @return array Modified commands.
	 */
	public static function add_pin_command( $commands ) {
		$commands[] = self::COMMAND;

		return $commands;
	}

	/**
	 * Adds the pinned flag to the entry JSON.
	 *
	 * @param array                $entry        The entry data.
	 * @param WPCOM_Liveblog_Entry $entry_object The entry object.
	 * @return array Modified entry data.
	 */
	public static function add_pinned_flag( $entry, $entry_object ) {
		$entry['pinned'] = self::is_pinned( $entry_object );

		return $entry;
	}

	/**
	 * Check if an entry is pinned by looking for the
	 * /pin command markup in its content.
	 *
	 * @param WPCOM_Liveblog_Entry $entry The entry object.
	 * @return bool True if pinned.
	 */
	public static function is_pinned( $entry ) {
		$prefix = apply_filters( 'liveblog_command_class', WPCOM_Liveblog_Entry_Extend_Feature_Commands::$class_prefix );

		return false !== strpos( $entry->get_content(), 'liveblog-command ' . $prefix . self::COMMAND . '"' );
	}

	/**
	 * Get the pinned entries from a list of entries.
	 *
	 * @param array $entries Entry objects keyed by entry ID.
	 * @return array Pinned entry objects, keys preserved.
	 */
	public static function filter_pinned( $entries ) {
		return array_filter( $entries, array( __CLASS__, 'is_pinned' ) );
	}
}
