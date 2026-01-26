<?php
/**
 * Socket.IO Manager for WebSocket support.
 *
 * @package Automattic\Liveblog\Infrastructure\SocketIO
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\SocketIO;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\WordPress\TemplateRenderer;
use Exception;
use Predis\Client as RedisClient;
use SocketIO\Emitter;

/**
 * Manages Socket.IO WebSocket support.
 *
 * Provides real-time updates to connected clients via Redis
 * and socket.io-php-emitter. This feature is opt-in and requires
 * the LIVEBLOG_USE_SOCKETIO constant to be enabled.
 */
final class SocketioManager {

	/**
	 * Minimum PHP version required for socket.io-php-emitter.
	 *
	 * @var string
	 */
	private const MIN_PHP_VERSION = '5.3.0';

	/**
	 * Template renderer for error messages.
	 *
	 * @var TemplateRenderer
	 */
	private TemplateRenderer $template_renderer;

	/**
	 * Socket.IO emitter instance.
	 *
	 * @var Emitter|null
	 */
	private ?Emitter $emitter = null;

	/**
	 * Redis client instance.
	 *
	 * @var RedisClient|null
	 */
	private ?RedisClient $redis_client = null;

	/**
	 * Socket.IO server URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Redis server host.
	 *
	 * @var string
	 */
	private string $redis_host;

	/**
	 * Redis server port.
	 *
	 * @var int
	 */
	private int $redis_port;

	/**
	 * Whether initialization has been attempted.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor.
	 *
	 * @param TemplateRenderer $template_renderer Template renderer for errors.
	 */
	public function __construct( TemplateRenderer $template_renderer ) {
		$this->template_renderer = $template_renderer;
		$this->load_settings();
	}

	/**
	 * Initialize Socket.IO support.
	 *
	 * Checks requirements and establishes Redis connection if possible.
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		if ( ! $this->is_constant_enabled() ) {
			return;
		}

		if ( $this->is_php_too_old() ) {
			add_action( 'admin_notices', array( $this, 'show_php_version_error' ) );
			return;
		}

		if ( ! $this->emitter_exists() ) {
			add_action( 'admin_notices', array( $this, 'show_emitter_required_error' ) );
			return;
		}

		$this->connect_to_redis();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Check if Socket.IO support is fully enabled and connected.
	 *
	 * @return bool True if enabled and connected.
	 */
	public function is_enabled(): bool {
		return LiveblogPost::is_viewing_liveblog_post()
			&& $this->is_constant_enabled()
			&& ! $this->is_php_too_old()
			&& $this->emitter_exists()
			&& $this->is_connected();
	}

	/**
	 * Check if connected to Redis with a valid emitter.
	 *
	 * @return bool True if connected.
	 */
	public function is_connected(): bool {
		if ( null === $this->redis_client || null === $this->emitter ) {
			return false;
		}

		return $this->redis_client->isConnected();
	}

	/**
	 * Emit a message to Socket.IO clients.
	 *
	 * @param string       $name The message name.
	 * @param string|array $data The message data.
	 */
	public function emit( string $name, $data ): void {
		if ( $this->is_connected() && null !== $this->emitter ) {
			$this->emitter->to( $this->get_post_key() )->json->emit( $name, wp_json_encode( $data ) );
		}

		exit;
	}

	/**
	 * Get the Socket.IO server URL.
	 *
	 * @return string The Socket.IO URL.
	 */
	public function get_url(): string {
		return $this->url;
	}

	/**
	 * Enqueue Socket.IO scripts.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$handle = 'liveblog-socket.io';

		wp_enqueue_script(
			'socket.io',
			plugins_url( 'js/socket.io.min.js', dirname( __DIR__, 3 ) . '/liveblog.php' ),
			array(),
			'1.4.4',
			true
		);

		wp_enqueue_script(
			$handle,
			plugins_url( 'js/liveblog-socket.io.js', dirname( __DIR__, 3 ) . '/liveblog.php' ),
			array( 'jquery', 'socket.io', LiveblogConfiguration::KEY ),
			LiveblogConfiguration::VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'liveblog_socketio_settings',
			apply_filters(
				'liveblog_socketio_settings',
				array(
					'url'               => $this->url,
					'post_key'          => $this->get_post_key(),
					'unable_to_connect' => esc_html__( 'Unable to connect to the server to get new entries', 'liveblog' ),
				)
			)
		);
	}

	/**
	 * Show PHP version error notice.
	 */
	public function show_php_version_error(): void {
		$message = sprintf(
			/* translators: 1: current PHP version, 2: minimum required PHP version */
			__( 'Your current PHP is version %1$s, which is too old to run the Liveblog plugin with WebSocket support enabled. The minimum required version is %2$s. Please, either update PHP or disable WebSocket support by removing or setting to false the constant LIVEBLOG_USE_SOCKETIO in wp-config.php.', 'liveblog' ),
			PHP_VERSION,
			self::MIN_PHP_VERSION
		);

		$this->show_error_message( $message );
	}

	/**
	 * Show emitter required error notice.
	 */
	public function show_emitter_required_error(): void {
		$message = __( 'It is necessary to install socket.io-php-emitter in order to use Liveblog plugin with WebSocket support enabled.', 'liveblog' );
		$this->show_error_message( $message );
	}

	/**
	 * Show Redis connection error notice.
	 */
	public function show_redis_error(): void {
		$message = __( 'Liveblog was unable to connect to the Redis server. Please check your configuration.', 'liveblog' );
		$this->show_error_message( $message );
	}

	/**
	 * Show an error message to administrators.
	 *
	 * @param string $message The error message.
	 */
	public function show_error_message( string $message ): void {
		if ( current_user_can( 'manage_options' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping is handled by TemplateRenderer.
			echo $this->template_renderer->render(
				'liveblog-socketio-error.php',
				array( 'message' => esc_html( $message ) )
			);
		}
	}

	/**
	 * Load settings from constants or use defaults.
	 */
	private function load_settings(): void {
		if ( defined( 'LIVEBLOG_SOCKETIO_URL' ) ) {
			$this->url = LIVEBLOG_SOCKETIO_URL;
		} else {
			$parsed_url = wp_parse_url( site_url() );
			$this->url  = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ':3000';
		}

		$this->redis_host = defined( 'LIVEBLOG_REDIS_HOST' ) ? LIVEBLOG_REDIS_HOST : 'localhost';
		$this->redis_port = defined( 'LIVEBLOG_REDIS_PORT' ) ? (int) LIVEBLOG_REDIS_PORT : 6379;
	}

	/**
	 * Connect to Redis and create the emitter.
	 */
	private function connect_to_redis(): void {
		// Load socket.io-php-emitter autoloader.
		$autoload_path = dirname( __DIR__, 3 ) . '/vendor/autoload.php';
		if ( file_exists( $autoload_path ) ) {
			require_once $autoload_path;
		}

		$this->redis_client = new RedisClient(
			array(
				'host' => $this->redis_host,
				'port' => $this->redis_port,
			)
		);

		try {
			$this->redis_client->connect();
			$this->emitter = new Emitter( $this->redis_client );
		} catch ( Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			add_action( 'admin_notices', array( $this, 'show_redis_error' ) );
		}
	}

	/**
	 * Check if LIVEBLOG_USE_SOCKETIO constant is enabled.
	 *
	 * @return bool True if enabled.
	 */
	private function is_constant_enabled(): bool {
		return defined( 'LIVEBLOG_USE_SOCKETIO' ) && LIVEBLOG_USE_SOCKETIO;
	}

	/**
	 * Check if PHP version is too old for socket.io-php-emitter.
	 *
	 * @return bool True if PHP is too old.
	 */
	private function is_php_too_old(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' );
	}

	/**
	 * Check if socket.io-php-emitter is installed.
	 *
	 * @return bool True if emitter exists.
	 */
	private function emitter_exists(): bool {
		$base_dir = dirname( __DIR__, 3 );
		return file_exists( $base_dir . '/vendor/autoload.php' )
			&& file_exists( $base_dir . '/vendor/rase/socket.io-emitter/src/Emitter.php' );
	}

	/**
	 * Get a unique key for the current post.
	 *
	 * @param int|null $post_id The post ID, or null for current post.
	 * @return string The post key.
	 */
	private function get_post_key( ?int $post_id = null ): string {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$post_key = wp_hash( $post_id . get_post_status( $post_id ), 'liveblog-socket' );

		/**
		 * Filter the post key for Socket.IO room identification.
		 *
		 * @param string $post_key Generated post key.
		 * @param int    $post_id  The post ID.
		 */
		return apply_filters( 'liveblog_socketio_post_key', $post_key, $post_id );
	}
}
