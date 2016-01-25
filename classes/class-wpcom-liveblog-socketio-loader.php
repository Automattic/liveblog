<?php

/**
 * Class used to decide whether to load or not
 * Socket.io support.
 *
 * This code needs to be in a separate class since
 * Liveblog plugin supports PHP 5.2 and Socket.io
 * support requires PHP >= 5.3 (because of socket.io-php-emitter).
 * So we need to check the PHP version before requiring
 * WPCOM_Liveblog_Socketio class.
 */
class WPCOM_Liveblog_Socketio_Loader {

	/**
	 * Minimum PHP version required to run socket.io-php-emitter.
	 */
	const socketio_min_php_version = '5.3.0';

	/**
	 * Load Socket.io main class if constant is true and
	 * running minimum required PHP version.
	 *
	 * @return void
	 */
	public static function load() {
		if ( self::is_socketio_constant_enabled() ) {
			if ( self::is_php_too_old_for_socketio() ) {
				self::add_old_php_for_socketio_notice();
			} else {
				require( dirname( __FILE__ ) . '/class-wpcom-liveblog-socketio.php' );
				WPCOM_Liveblog_Socketio::load();
			}
		}
	}

	/**
	 * Check whether the PHP version is too old to use
	 * socket.io-php-emitter which requires at least PHP
	 * 5.3.
	 *
	 * @return bool
	 */
	private static function is_php_too_old_for_socketio() {
		return version_compare( PHP_VERSION, self::socketio_min_php_version, '<' );
	}

	/**
	 * Check if the constant LIVEBLOG_USE_SOCKETIO is true
	 *
	 * @return bool
	 */
	private static function is_socketio_constant_enabled() {
		return defined( 'LIVEBLOG_USE_SOCKETIO' ) && LIVEBLOG_USE_SOCKETIO;
	}

	/**
	 * Use socket.io instead of AJAX to update clients
	 * when new entries are created?
	 *
	 * @return bool whether socket.io is enabled or not
	 */
	public static function is_socketio_enabled() {
		return self::is_socketio_constant_enabled() && ! self::is_php_too_old_for_socketio();
	}

	/**
	 * Add message to warn the user that the PHP version is to old to run socket.io-php-emitter.
	 *
	 * @return void
	 */
	private static function add_old_php_for_socketio_notice() {
		add_action( 'admin_notices', array( __CLASS__, 'show_old_php_for_socketio_notice' ) );
	}

	/**
	 * Display message to warn the user that the PHP version is to old to run socket.io-php-emitter.
	 *
	 * @return void
	 */
	public static function show_old_php_for_socketio_notice() {
		echo WPCOM_Liveblog::get_template_part(
			'old-php-notice.php',
			array( 'php_version' => PHP_VERSION, 'php_min_version' => self::socketio_min_php_version )
		);
	}

	/**
	 * Return true if viewing a liveblog post, Socket.io support is
	 * enabled and post is public. For now we only support Socket.io
	 * for public posts since there is no way to tell in the Socket.io
	 * server if a client has permission or not to see a Liveblog post.
	 *
	 * @return bool
	 */
	public static function should_use_socketio() {
		return WPCOM_Liveblog::is_viewing_liveblog_post()
		       && self::is_socketio_enabled()
		       && 'publish' === get_post_status();
	}
}
