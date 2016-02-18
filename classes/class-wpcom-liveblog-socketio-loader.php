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
				self::add_old_php_for_socketio_error();
			} else if ( ! self::socketio_emitter_exists() ) {
				self::add_socketio_emitter_required_error();
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
	 * Check whether socket.io-php-emitter is installed.
	 *
	 * @return bool
	 */
	private static function socketio_emitter_exists() {
		return file_exists( dirname( __FILE__ ) . '/../vendor/autoload.php' )
		       && file_exists( dirname( __FILE__ ) . '/../vendor/rase/socket.io-emitter/src/Emitter.php' );
	}

	/**
	 * Load error message template with a particular message.
	 *
	 * @param string $message
	 */
	public static function show_error_message( $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			echo WPCOM_Liveblog::get_template_part(
				'liveblog-socketio-error.php',
				array( 'message' => $message )
			);
		}
	}

	/**
	 * Add message to warn the user that socket.io-php-emitter is required.
	 *
	 * @return void
	 */
	private static function add_socketio_emitter_required_error() {
		add_action( 'admin_notices', array( __CLASS__, 'show_socketio_emitter_required_error' ) );
	}

	/**
	 * Display message to warn the user that socket.io-php-emitter is required.
	 *
	 * @return void
	 */
	public static function show_socketio_emitter_required_error() {
		$message = __( 'It is necessary to install socket.io-php-emitter in order to use Liveblog plugin with WebSocket support enabled.', 'liveblog' );

		self::show_error_message( $message );
	}

	/**
	 * Add message to warn the user that the PHP version is too old to run socket.io-php-emitter.
	 *
	 * @return void
	 */
	private static function add_old_php_for_socketio_error() {
		add_action( 'admin_notices', array( __CLASS__, 'show_old_php_for_socketio_error' ) );
	}

	/**
	 * Display message to warn the user that the PHP version is to old to run socket.io-php-emitter.
	 *
	 * @return void
	 */
	public static function show_old_php_for_socketio_error() {
		$message = sprintf(
			__( 'Your current PHP is version %1$s, which is too old to run the Liveblog plugin with WebSocket support enabled. The minimum required version is %2$s. Please, either update PHP or disable WebSocket support by removing or setting to false the constant LIVEBLOG_USE_SOCKETIO in wp-config.php.', 'liveblog' ),
			PHP_VERSION,
			self::socketio_min_php_version
		);

		self::show_error_message( $message );
	}

	/**
	 * Return true if viewing a liveblog post, Socket.io support is
	 * enabled and socket.io-php-emitter is installed and connected to the
	 * Redis server.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$redis_client_connected = false;

		// It is necessary to check if the class exists since if running PHP <= 5.2 we don't include it
		if ( class_exists( 'WPCOM_Liveblog_Socketio' ) && WPCOM_Liveblog_Socketio::is_connected() ) {
			$redis_client_connected = true;
		}

		return WPCOM_Liveblog::is_viewing_liveblog_post()
		       && self::is_socketio_constant_enabled()
		       && ! self::is_php_too_old_for_socketio()
		       && self::socketio_emitter_exists()
		       && $redis_client_connected;
	}
}
