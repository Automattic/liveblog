<?php
/**
 * Dependency injection container.
 *
 * @package Automattic\Liveblog\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\DI;

use Automattic\Liveblog\Application\Config\KeyEventConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Filter\ContentFilterRegistry;
use Automattic\Liveblog\Application\Presenter\MetadataPresenter;
use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Application\Renderer\EmbedHandlerInterface;
use Automattic\Liveblog\Application\Service\AutoArchiveService;
use Automattic\Liveblog\Application\Service\ContentProcessor;
use Automattic\Liveblog\Application\Service\EntryOperations;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Application\Service\InputSanitizer;
use Automattic\Liveblog\Application\Service\KeyEventService;
use Automattic\Liveblog\Application\Service\KeyEventShortcodeHandler;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Infrastructure\Cron\AutoArchiveCronHandler;
use Automattic\Liveblog\Infrastructure\Renderer\WordPressContentRenderer;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;
use Automattic\Liveblog\Infrastructure\WordPress\AdminController;
use Automattic\Liveblog\Infrastructure\SocketIO\SocketioManager;
use Automattic\Liveblog\Infrastructure\WordPress\AmpIntegration;
use Automattic\Liveblog\Infrastructure\WordPress\AssetManager;
use Automattic\Liveblog\Infrastructure\WordPress\CommentEmbed;
use Automattic\Liveblog\Infrastructure\WordPress\RequestRouter;
use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;
use Automattic\Liveblog\Infrastructure\WordPress\TemplateRenderer;

/**
 * Dependency injection container for wiring up services.
 *
 * This acts as the composition root for the application, creating and
 * providing access to service instances. Services are lazily instantiated
 * and cached for the lifetime of the request.
 *
 * Usage:
 *   $container = Container::instance();
 *   $service   = $container->entry_service();
 */
final class Container {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Custom factory overrides for testing.
	 *
	 * @var array<string, callable>
	 */
	private array $overrides = array();

	/**
	 * Cached entry repository instance.
	 *
	 * @var EntryRepositoryInterface|null
	 */
	private ?EntryRepositoryInterface $entry_repository = null;

	/**
	 * Cached entry service instance.
	 *
	 * @var EntryService|null
	 */
	private ?EntryService $entry_service = null;

	/**
	 * Cached embed handler instance.
	 *
	 * @var EmbedHandlerInterface|null
	 */
	private ?EmbedHandlerInterface $embed_handler = null;

	/**
	 * Cached content processor instance.
	 *
	 * @var ContentProcessor|null
	 */
	private ?ContentProcessor $content_processor = null;

	/**
	 * Cached content renderer instance.
	 *
	 * @var ContentRendererInterface|null
	 */
	private ?ContentRendererInterface $content_renderer = null;

	/**
	 * Cached entry query service instance.
	 *
	 * @var EntryQueryService|null
	 */
	private ?EntryQueryService $entry_query_service = null;

	/**
	 * Cached content filter registry instance.
	 *
	 * @var ContentFilterRegistry|null
	 */
	private ?ContentFilterRegistry $content_filter_registry = null;

	/**
	 * Cached input sanitizer instance.
	 *
	 * @var InputSanitizer|null
	 */
	private ?InputSanitizer $input_sanitizer = null;

	/**
	 * Cached key event service instance.
	 *
	 * @var KeyEventService|null
	 */
	private ?KeyEventService $key_event_service = null;

	/**
	 * Cached key event shortcode handler instance.
	 *
	 * @var KeyEventShortcodeHandler|null
	 */
	private ?KeyEventShortcodeHandler $key_event_shortcode_handler = null;

	/**
	 * Cached auto-archive service instance.
	 *
	 * @var AutoArchiveService|null
	 */
	private ?AutoArchiveService $auto_archive_service = null;

	/**
	 * Cached auto-archive cron handler instance.
	 *
	 * @var AutoArchiveCronHandler|null
	 */
	private ?AutoArchiveCronHandler $auto_archive_cron_handler = null;

	/**
	 * Cached entry operations instance.
	 *
	 * @var EntryOperations|null
	 */
	private ?EntryOperations $entry_operations = null;

	/**
	 * Cached REST API controller instance.
	 *
	 * @var RestApiController|null
	 */
	private ?RestApiController $rest_api_controller = null;

	/**
	 * Cached asset manager instance.
	 *
	 * @var AssetManager|null
	 */
	private ?AssetManager $asset_manager = null;

	/**
	 * Cached template renderer instance.
	 *
	 * @var TemplateRenderer|null
	 */
	private ?TemplateRenderer $template_renderer = null;

	/**
	 * Cached admin controller instance.
	 *
	 * @var AdminController|null
	 */
	private ?AdminController $admin_controller = null;

	/**
	 * Cached metadata presenter instance.
	 *
	 * @var MetadataPresenter|null
	 */
	private ?MetadataPresenter $metadata_presenter = null;

	/**
	 * Cached request router instance.
	 *
	 * @var RequestRouter|null
	 */
	private ?RequestRouter $request_router = null;

	/**
	 * Cached AMP integration instance.
	 *
	 * @var AmpIntegration|null
	 */
	private ?AmpIntegration $amp_integration = null;

	/**
	 * Cached Socket.IO manager instance.
	 *
	 * @var SocketioManager|null
	 */
	private ?SocketioManager $socketio_manager = null;

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the container (primarily for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Override a service registration for testing.
	 *
	 * @param string   $id      Service identifier (method name without parentheses).
	 * @param callable $factory Factory callable that returns the service instance.
	 * @return void
	 */
	public function set( string $id, callable $factory ): void {
		$this->overrides[ $id ] = $factory;
		// Clear cached instance if it exists.
		$property = $this->get_property_name( $id );
		if ( property_exists( $this, $property ) ) {
			$this->$property = null;
		}
	}

	/**
	 * Get property name from service ID.
	 *
	 * @param string $id Service identifier.
	 * @return string Property name.
	 */
	private function get_property_name( string $id ): string {
		return $id;
	}

	/**
	 * Get the entry repository.
	 *
	 * @return EntryRepositoryInterface
	 */
	public function entry_repository(): EntryRepositoryInterface {
		if ( isset( $this->overrides['entry_repository'] ) ) {
			return ( $this->overrides['entry_repository'] )();
		}

		if ( null === $this->entry_repository ) {
			$this->entry_repository = new CommentEntryRepository();
		}

		return $this->entry_repository;
	}

	/**
	 * Get the entry service.
	 *
	 * @return EntryService
	 */
	public function entry_service(): EntryService {
		if ( isset( $this->overrides['entry_service'] ) ) {
			return ( $this->overrides['entry_service'] )();
		}

		if ( null === $this->entry_service ) {
			$this->entry_service = new EntryService( $this->entry_repository() );
		}

		return $this->entry_service;
	}

	/**
	 * Get the embed handler.
	 *
	 * @return EmbedHandlerInterface
	 */
	public function embed_handler(): EmbedHandlerInterface {
		if ( isset( $this->overrides['embed_handler'] ) ) {
			return ( $this->overrides['embed_handler'] )();
		}

		if ( null === $this->embed_handler ) {
			$this->embed_handler = new CommentEmbed();
		}

		return $this->embed_handler;
	}

	/**
	 * Get the content processor.
	 *
	 * @return ContentProcessor
	 */
	public function content_processor(): ContentProcessor {
		if ( isset( $this->overrides['content_processor'] ) ) {
			return ( $this->overrides['content_processor'] )();
		}

		if ( null === $this->content_processor ) {
			$this->content_processor = new ContentProcessor(
				$this->embed_handler()
			);
		}

		return $this->content_processor;
	}

	/**
	 * Get the content renderer.
	 *
	 * @return ContentRendererInterface
	 */
	public function content_renderer(): ContentRendererInterface {
		if ( isset( $this->overrides['content_renderer'] ) ) {
			return ( $this->overrides['content_renderer'] )();
		}

		if ( null === $this->content_renderer ) {
			$this->content_renderer = new WordPressContentRenderer( $this->content_processor() );
		}

		return $this->content_renderer;
	}

	/**
	 * Get the entry query service.
	 *
	 * @return EntryQueryService
	 */
	public function entry_query_service(): EntryQueryService {
		if ( isset( $this->overrides['entry_query_service'] ) ) {
			return ( $this->overrides['entry_query_service'] )();
		}

		if ( null === $this->entry_query_service ) {
			$this->entry_query_service = new EntryQueryService( $this->entry_repository() );
		}

		return $this->entry_query_service;
	}

	/**
	 * Get the content filter registry.
	 *
	 * @return ContentFilterRegistry
	 */
	public function content_filter_registry(): ContentFilterRegistry {
		if ( isset( $this->overrides['content_filter_registry'] ) ) {
			return ( $this->overrides['content_filter_registry'] )();
		}

		if ( null === $this->content_filter_registry ) {
			$this->content_filter_registry = new ContentFilterRegistry();
		}

		return $this->content_filter_registry;
	}

	/**
	 * Get the input sanitizer.
	 *
	 * @return InputSanitizer
	 */
	public function input_sanitizer(): InputSanitizer {
		if ( isset( $this->overrides['input_sanitizer'] ) ) {
			return ( $this->overrides['input_sanitizer'] )();
		}

		if ( null === $this->input_sanitizer ) {
			$this->input_sanitizer = new InputSanitizer();
		}

		return $this->input_sanitizer;
	}

	/**
	 * Get the key event service.
	 *
	 * @return KeyEventService
	 */
	public function key_event_service(): KeyEventService {
		if ( isset( $this->overrides['key_event_service'] ) ) {
			return ( $this->overrides['key_event_service'] )();
		}

		if ( null === $this->key_event_service ) {
			$this->key_event_service = new KeyEventService(
				$this->entry_repository(),
				$this->entry_query_service()
			);
		}

		return $this->key_event_service;
	}

	/**
	 * Get the key event shortcode handler.
	 *
	 * @return KeyEventShortcodeHandler
	 */
	public function key_event_shortcode_handler(): KeyEventShortcodeHandler {
		if ( isset( $this->overrides['key_event_shortcode_handler'] ) ) {
			return ( $this->overrides['key_event_shortcode_handler'] )();
		}

		if ( null === $this->key_event_shortcode_handler ) {
			$this->key_event_shortcode_handler = new KeyEventShortcodeHandler(
				$this->key_event_service(),
				new KeyEventConfiguration()
			);
		}

		return $this->key_event_shortcode_handler;
	}

	/**
	 * Get the auto-archive service.
	 *
	 * @return AutoArchiveService
	 */
	public function auto_archive_service(): AutoArchiveService {
		if ( isset( $this->overrides['auto_archive_service'] ) ) {
			return ( $this->overrides['auto_archive_service'] )();
		}

		if ( null === $this->auto_archive_service ) {
			$auto_archive_days          = LiveblogConfiguration::get_auto_archive_days();
			$this->auto_archive_service = new AutoArchiveService( $auto_archive_days );
		}

		return $this->auto_archive_service;
	}

	/**
	 * Get the auto-archive cron handler.
	 *
	 * @return AutoArchiveCronHandler
	 */
	public function auto_archive_cron_handler(): AutoArchiveCronHandler {
		if ( isset( $this->overrides['auto_archive_cron_handler'] ) ) {
			return ( $this->overrides['auto_archive_cron_handler'] )();
		}

		if ( null === $this->auto_archive_cron_handler ) {
			$this->auto_archive_cron_handler = new AutoArchiveCronHandler(
				$this->auto_archive_service()
			);
		}

		return $this->auto_archive_cron_handler;
	}

	/**
	 * Get the entry operations service.
	 *
	 * @return EntryOperations
	 */
	public function entry_operations(): EntryOperations {
		if ( isset( $this->overrides['entry_operations'] ) ) {
			return ( $this->overrides['entry_operations'] )();
		}

		if ( null === $this->entry_operations ) {
			$this->entry_operations = new EntryOperations(
				$this->entry_service(),
				$this->key_event_service(),
				$this->entry_repository(),
				$this->content_processor()
			);
		}

		return $this->entry_operations;
	}

	/**
	 * Get the REST API controller.
	 *
	 * @return RestApiController
	 */
	public function rest_api_controller(): RestApiController {
		if ( isset( $this->overrides['rest_api_controller'] ) ) {
			return ( $this->overrides['rest_api_controller'] )();
		}

		if ( null === $this->rest_api_controller ) {
			$this->rest_api_controller = new RestApiController(
				$this->entry_query_service(),
				$this->entry_operations(),
				$this->key_event_service(),
				$this->request_router(),
				$this->admin_controller()
			);
		}

		return $this->rest_api_controller;
	}

	/**
	 * Get the asset manager.
	 *
	 * @return AssetManager
	 */
	public function asset_manager(): AssetManager {
		if ( isset( $this->overrides['asset_manager'] ) ) {
			return ( $this->overrides['asset_manager'] )();
		}

		if ( null === $this->asset_manager ) {
			$this->asset_manager = new AssetManager(
				$this->entry_query_service(),
				$this->content_filter_registry(),
				defined( 'LIVEBLOG_FILE' ) ? LIVEBLOG_FILE : '',
				$this->socketio_manager()
			);
		}

		return $this->asset_manager;
	}

	/**
	 * Get the template renderer.
	 *
	 * @return TemplateRenderer
	 */
	public function template_renderer(): TemplateRenderer {
		if ( isset( $this->overrides['template_renderer'] ) ) {
			return ( $this->overrides['template_renderer'] )();
		}

		if ( null === $this->template_renderer ) {
			$this->template_renderer = new TemplateRenderer(
				defined( 'LIVEBLOG_FILE' ) ? LIVEBLOG_FILE : ''
			);
		}

		return $this->template_renderer;
	}

	/**
	 * Get the admin controller.
	 *
	 * @return AdminController
	 */
	public function admin_controller(): AdminController {
		if ( isset( $this->overrides['admin_controller'] ) ) {
			return ( $this->overrides['admin_controller'] )();
		}

		if ( null === $this->admin_controller ) {
			$this->admin_controller = new AdminController(
				$this->template_renderer()
			);
		}

		return $this->admin_controller;
	}

	/**
	 * Get the metadata presenter.
	 *
	 * @return MetadataPresenter
	 */
	public function metadata_presenter(): MetadataPresenter {
		if ( isset( $this->overrides['metadata_presenter'] ) ) {
			return ( $this->overrides['metadata_presenter'] )();
		}

		if ( null === $this->metadata_presenter ) {
			$this->metadata_presenter = new MetadataPresenter(
				$this->entry_query_service(),
				$this->key_event_service()
			);
		}

		return $this->metadata_presenter;
	}

	/**
	 * Get the request router.
	 *
	 * @return RequestRouter
	 */
	public function request_router(): RequestRouter {
		if ( isset( $this->overrides['request_router'] ) ) {
			return ( $this->overrides['request_router'] )();
		}

		if ( null === $this->request_router ) {
			$this->request_router = new RequestRouter(
				$this->entry_query_service(),
				$this->entry_operations(),
				$this->key_event_service()
			);
		}

		return $this->request_router;
	}

	/**
	 * Get the AMP integration.
	 *
	 * @return AmpIntegration
	 */
	public function amp_integration(): AmpIntegration {
		if ( isset( $this->overrides['amp_integration'] ) ) {
			return ( $this->overrides['amp_integration'] )();
		}

		if ( null === $this->amp_integration ) {
			$plugin_dir = defined( 'LIVEBLOG_FILE' ) ? dirname( LIVEBLOG_FILE ) : '';

			$this->amp_integration = new AmpIntegration(
				$this->template_renderer(),
				$this->asset_manager(),
				$this->request_router(),
				$this->metadata_presenter(),
				$plugin_dir
			);
		}

		return $this->amp_integration;
	}

	/**
	 * Get the Socket.IO manager.
	 *
	 * @return SocketioManager
	 */
	public function socketio_manager(): SocketioManager {
		if ( isset( $this->overrides['socketio_manager'] ) ) {
			return ( $this->overrides['socketio_manager'] )();
		}

		if ( null === $this->socketio_manager ) {
			$this->socketio_manager = new SocketioManager(
				$this->template_renderer()
			);
		}

		return $this->socketio_manager;
	}

	/**
	 * Set a custom entry repository (for testing or alternative implementations).
	 *
	 * @deprecated Use set('entry_repository', fn() => $repository) instead.
	 * @param EntryRepositoryInterface $repository Repository instance.
	 * @return self
	 */
	public function set_entry_repository( EntryRepositoryInterface $repository ): self {
		$this->entry_repository = $repository;
		// Clear the service so it gets recreated with the new repository.
		$this->entry_service = null;

		return $this;
	}

	/**
	 * Set a custom entry service (for testing).
	 *
	 * @deprecated Use set('entry_service', fn() => $service) instead.
	 * @param EntryService $service Service instance.
	 * @return self
	 */
	public function set_entry_service( EntryService $service ): self {
		$this->entry_service = $service;

		return $this;
	}
}
