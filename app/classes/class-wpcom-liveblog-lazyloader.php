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
	private static $enabled;

	/**
	 * @var int
	 */
	private static $number_of_default_entries;

	/**
	 * @var int
	 */
	private static $number_of_entries;

	/**
	 * Checks if lazyloading is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {

		if ( ! isset( self::$enabled ) ) {
			/**
			 * Enables/Disables lazyloading for Liveblog entries.
			 *
			 * @param bool $enabled Enable lazyloading for Liveblog entries?
			 */
			self::$enabled = (bool) apply_filters( 'liveblog_enable_lazyloader', true );

			// Disable lazy loading for robots
			if ( self::$enabled && self::is_robot() ) {
				self::$enabled = false;
			}

			// Disable lazy loading on archived liveblogs
			if ( 'enable' != WPCOM_Liveblog::get_liveblog_state() ) {
				self::$enabled = false;
			}
		}

		return self::$enabled;
	}

	/**
	 * Checks if the current user is a robot.
	 *
	 * @return bool
	 */
	private static function is_robot() {

		// Variant determiner for caches.
		if ( function_exists( 'vary_cache_on_function' ) ) {
			vary_cache_on_function(
				'return isset( $_SERVER[\'HTTP_USER_AGENT\'] ) && preg_match( \'/bot|crawl|slurp|spider/i\', $_SERVER[\'HTTP_USER_AGENT\'] );'
			);
		}

		return isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Returns the number of initially displayed Liveblog entries.
	 *
	 * @return int
	 */
	private static function get_number_of_default_entries() {

		if ( ! isset( self::$number_of_default_entries ) ) {
			self::$number_of_default_entries = 5;

			/**
			 * Filters the number of initially displayed Liveblog entries.
			 *
			 * @param int $number_of_default_entries Number of initially displayed Liveblog entries.
			 */
			$number = (int) apply_filters( 'liveblog_number_of_default_entries', self::$number_of_default_entries );
			if ( $number >= 0 ) {
				self::$number_of_default_entries = $number;
			}
		}

		return self::$number_of_default_entries;
	}

	/**
	 * Returns the number of Liveblog entries used for lazyloading.
	 *
	 * @return int
	 */
	public static function get_number_of_entries() {

		if ( ! isset( self::$number_of_entries ) ) {
			self::$number_of_entries = 5;

			/**
			 * Filters the number of Liveblog entries used for lazyloading.
			 *
			 * @param int $number_of_entries Number of Liveblog entries.
			 */
			$number = (int) apply_filters( 'liveblog_number_of_entries', self::$number_of_entries );
			if ( $number > 0 ) {
				// Limit the number of Liveblog entries used for lazyloading to 100.
				self::$number_of_entries = min( $number, 100 );
			}
		}

		return self::$number_of_entries;
	}

	/**
	 * Called by WPCOM_Liveblog::load(), defers loading to when Liveblog has been initialized.
	 *
	 * @return void
	 */
	public static function load() {

		add_action( 'template_redirect', array( __CLASS__, 'late_load' ) );
	}

	/**
	 * Wires up the lazyloading functions.
	 *
	 * @wp-hook after_liveblog_init
	 *
	 * @return void
	 */
	public static function late_load() {

		if ( has_action( 'init', 'Lazyload_Liveblog_Entries' ) ) {
			if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
			}

			// Disable the Lazyload Liveblog Entries plugin.
			remove_action( 'init', 'Lazyload_Liveblog_Entries' );
		}

		if ( ! self::is_enabled() ) {
			return;
		}

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

		echo WPCOM_Liveblog::get_template_part( 'lazyload-notice.php', array(
			'plugin' => 'Lazyload Liveblog Entries',
		) );
	}

	/**
	 * Enqueues the lazyloader script file.
	 *
	 * @wp-hook wp_enqueue_scripts
	 *
	 * @return void
	 */
	public static function enqueue_script() {

		if ( ! WPCOM_Liveblog::is_viewing_liveblog_post() ) {
			return;
		}

		$handle      = 'liveblog-lazyloader';
		$path        = 'js/liveblog-lazyloader.js';
		$plugin_path = dirname( __FILE__ );
		$temp        = plugin_dir_path( $plugin_path ) . $path;
		wp_enqueue_script( $handle, plugins_url( $path, $plugin_path ), array( 'liveblog' ), filemtime( $temp ), true );
		wp_localize_script( $handle, 'liveblogLazyloaderSettings', array(
			'loadMoreText' => esc_html__( 'Load more entries&hellip;', 'liveblog' ),
		) );
	}

	/**
	 * Limits the initially displayed Liveblog entries.
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	public static function display_archive_query_args( $args ) {

		$args['number'] = (int) self::get_number_of_default_entries();

		return $args;
	}
}
