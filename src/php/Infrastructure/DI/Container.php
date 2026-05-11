<?php
/**
 * Dependency injection container.
 *
 * @package Automattic\Liveblog\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\DI;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\MetadataPresenter;
use Automattic\Liveblog\Application\Service\ContentProcessor;
use Automattic\Liveblog\Application\Service\EntryOperations;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Application\Service\InputSanitizer;
use Automattic\Liveblog\Application\Service\SettingsService;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Infrastructure\Repository\PostEntryRepository;
use Automattic\Liveblog\Infrastructure\WordPress\AdminController;
use Automattic\Liveblog\Infrastructure\WordPress\AssetManager;
use Automattic\Liveblog\Infrastructure\WordPress\RequestRouter;
use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;
use Automattic\Liveblog\Infrastructure\WordPress\SettingsPageController;
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
	 * Cached content processor instance.
	 *
	 * @var ContentProcessor|null
	 */
	private ?ContentProcessor $content_processor = null;

	/**
	 * Cached entry query service instance.
	 *
	 * @var EntryQueryService|null
	 */
	private ?EntryQueryService $entry_query_service = null;

	/**
	 * Cached input sanitizer instance.
	 *
	 * @var InputSanitizer|null
	 */
	private ?InputSanitizer $input_sanitizer = null;

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
	 * Cached settings service instance.
	 *
	 * @var SettingsService|null
	 */
	private ?SettingsService $settings_service = null;

	/**
	 * Cached settings page controller instance.
	 *
	 * @var SettingsPageController|null
	 */
	private ?SettingsPageController $settings_controller = null;

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
			$this->entry_repository = new PostEntryRepository();
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
	 * Get the content processor.
	 *
	 * @return ContentProcessor
	 */
	public function content_processor(): ContentProcessor {
		if ( isset( $this->overrides['content_processor'] ) ) {
			return ( $this->overrides['content_processor'] )();
		}

		if ( null === $this->content_processor ) {
			$this->content_processor = new ContentProcessor( null );
		}

		return $this->content_processor;
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
				defined( 'LIVEBLOG_FILE' ) ? LIVEBLOG_FILE : ''
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
			$this->admin_controller = new AdminController( $this->template_renderer(), $this->entry_query_service() );
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
				$this->entry_query_service()
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
				$this->entry_operations()
			);
		}

		return $this->request_router;
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
	/**
	 * Get the settings service.
	 *
	 * @return SettingsService
	 */
	public function settings_service(): SettingsService {
		if ( isset( $this->overrides['settings_service'] ) ) {
			return ( $this->overrides['settings_service'] )();
		}

		if ( null === $this->settings_service ) {
			$this->settings_service = new SettingsService();
		}

		return $this->settings_service;
	}

	/**
	 * Get the settings page controller.
	 *
	 * @return SettingsPageController
	 */
	public function settings_controller(): SettingsPageController {
		if ( isset( $this->overrides['settings_controller'] ) ) {
			return ( $this->overrides['settings_controller'] )();
		}

		if ( null === $this->settings_controller ) {
			$this->settings_controller = new SettingsPageController( $this->settings_service() );
		}

		return $this->settings_controller;
	}
}
