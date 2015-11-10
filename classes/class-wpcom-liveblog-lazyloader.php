<?php

/**
 * Class WPCOM_Liveblog_Lazyloader
 *
 * Handles lazyloading of Liveblog entries.
 */
class WPCOM_Liveblog_Lazyloader {

	/**
	 * @var bool
	 */
	private static $enabled = true;

	/**
	 * @var int
	 */
	protected static $number_of_entries = 5;

	/**
	 * Checks if the lazyload feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {

		return self::$enabled;
	}

	/**
	 * Returns the number of Liveblog entries used both for initial display, and lazyloading.
	 *
	 * @return int
	 */
	public static function get_number_of_entries() {

		return self::$number_of_entries;
	}

	/**
	 * Called by WPCOM_Liveblog::load(), defers partloading to when Liveblog has been initialized.
	 *
	 * @return void
	 */
	public static function load() {

		add_action( 'after_liveblog_init', array( __CLASS__, 'late_load' ) );
	}

	/**
	 * Wires up the lazyload functions.
	 *
	 * @wp-hook after_liveblog_init
	 *
	 * @return void
	 */
	public static function late_load() {

		if ( has_action( 'init', 'Lazyload_Liveblog_Entries' ) ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
			}

			// Disable the Lazyload Liveblog Entries plugin.
			remove_action( 'init', 'Lazyload_Liveblog_Entries' );
		}

		/**
		 * Enables/Disables the lazyload feature for Liveblog entries.
		 *
		 * @param bool $enabled Enable the lazyload feature for Liveblog entries?
		 */
		self::$enabled = (bool) apply_filters( 'liveblog_enable_lazyloader', self::$enabled );
		if ( ! self::$enabled ) {
			return;
		}

		/**
		 * Filters the number of Liveblog entries used both for initial display, and lazyloading.
		 *
		 * @param int $number_of_entries Number of Liveblog entries.
		 */
		self::$number_of_entries = (int) apply_filters( 'liveblog_number_of_entries', self::$number_of_entries );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );

		add_filter( 'liveblog_display_archive_query_args', array( __CLASS__, 'display_archive_query_args' ), 20 );
	}

	/**
	 * Renders lazyloading-specific admin notices.
	 *
	 * @wp-hook admin_notices
	 *
	 * @return void
	 */
	public static function admin_notices() {

		echo WPCOM_Liveblog::get_template_part( 'lazyload-notice.php', array( 'plugin' => 'Lazyload Liveblog Entries' ) );
	}

	/**
	 * Limits the initially displayed Liveblog entries.
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	public static function display_archive_query_args( $args ) {

		$args['number'] = self::$number_of_entries;

		return $args;
	}

	/**
	 * Enqueues the lazyload script file.
	 *
	 * @wp-hook wp_enqueue_scripts
	 *
	 * @return void
	 */
	public static function enqueue_script() {

		if ( ! WPCOM_Liveblog::is_viewing_liveblog_post() ) {
			return;
		}

		$handle = 'liveblog-lazyloader';
		$path = 'js/liveblog-lazyloader.js';
		$plugin_path = dirname( __FILE__ );
		$temp = plugin_dir_path( $plugin_path ) . $path;
		wp_enqueue_script( $handle, plugins_url( $path, $plugin_path ), array( 'liveblog' ), filemtime( $temp ), true );
		wp_localize_script( $handle, 'liveblogLazyloaderSettings', array(
			'loadMoreButtonText' => esc_html__( 'Load more entries&hellip;', 'liveblog' ),
			'numberOfEntries'    => self::$number_of_entries,
			'url'                => get_permalink() . WPCOM_Liveblog::url_endpoint . '/lazyload/',
		) );
	}
}
