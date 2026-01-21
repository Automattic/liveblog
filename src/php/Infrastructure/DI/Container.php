<?php
/**
 * Dependency injection container.
 *
 * @package Automattic\Liveblog\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\DI;

use Automattic\Liveblog\Application\Config\KeyEventConfiguration;
use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Filter\AuthorFilter;
use Automattic\Liveblog\Application\Filter\CommandFilter;
use Automattic\Liveblog\Application\Filter\ContentFilterRegistry;
use Automattic\Liveblog\Application\Filter\EmojiFilter;
use Automattic\Liveblog\Application\Filter\HashtagFilter;
use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Application\Service\AutoArchiveService;
use Automattic\Liveblog\Application\Service\ContentProcessor;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Application\Service\InputSanitizer;
use Automattic\Liveblog\Application\Service\KeyEventService;
use Automattic\Liveblog\Application\Service\KeyEventShortcodeHandler;
use Automattic\Liveblog\Application\Service\ShortcodeFilter;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Infrastructure\Cron\AutoArchiveCronHandler;
use Automattic\Liveblog\Infrastructure\Renderer\WordPressContentRenderer;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;

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
	 * Cached content renderer instance.
	 *
	 * @var ContentRendererInterface|null
	 */
	private ?ContentRendererInterface $content_renderer = null;

	/**
	 * Cached shortcode filter instance.
	 *
	 * @var ShortcodeFilter|null
	 */
	private ?ShortcodeFilter $shortcode_filter = null;

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
	 * Cached command filter instance.
	 *
	 * @var CommandFilter|null
	 */
	private ?CommandFilter $command_filter = null;

	/**
	 * Cached emoji filter instance.
	 *
	 * @var EmojiFilter|null
	 */
	private ?EmojiFilter $emoji_filter = null;

	/**
	 * Cached hashtag filter instance.
	 *
	 * @var HashtagFilter|null
	 */
	private ?HashtagFilter $hashtag_filter = null;

	/**
	 * Cached author filter instance.
	 *
	 * @var AuthorFilter|null
	 */
	private ?AuthorFilter $author_filter = null;

	/**
	 * Cached key event service instance.
	 *
	 * @var KeyEventService|null
	 */
	private ?KeyEventService $key_event_service = null;

	/**
	 * Cached key event configuration instance.
	 *
	 * @var KeyEventConfiguration|null
	 */
	private ?KeyEventConfiguration $key_event_configuration = null;

	/**
	 * Cached key event shortcode handler instance.
	 *
	 * @var KeyEventShortcodeHandler|null
	 */
	private ?KeyEventShortcodeHandler $key_event_shortcode_handler = null;

	/**
	 * Cached lazyload configuration instance.
	 *
	 * @var LazyloadConfiguration|null
	 */
	private ?LazyloadConfiguration $lazyload_configuration = null;

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
	 * Get the content processor.
	 *
	 * @return ContentProcessor
	 */
	public function content_processor(): ContentProcessor {
		if ( isset( $this->overrides['content_processor'] ) ) {
			return ( $this->overrides['content_processor'] )();
		}

		if ( null === $this->content_processor ) {
			$this->content_processor = new ContentProcessor();
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
	 * Get the shortcode filter.
	 *
	 * @return ShortcodeFilter
	 */
	public function shortcode_filter(): ShortcodeFilter {
		if ( isset( $this->overrides['shortcode_filter'] ) ) {
			return ( $this->overrides['shortcode_filter'] )();
		}

		if ( null === $this->shortcode_filter ) {
			$this->shortcode_filter = new ShortcodeFilter();
		}

		return $this->shortcode_filter;
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
	 * Get the command filter.
	 *
	 * @return CommandFilter
	 */
	public function command_filter(): CommandFilter {
		if ( isset( $this->overrides['command_filter'] ) ) {
			return ( $this->overrides['command_filter'] )();
		}

		if ( null === $this->command_filter ) {
			$this->command_filter = new CommandFilter();
		}

		return $this->command_filter;
	}

	/**
	 * Get the emoji filter.
	 *
	 * @return EmojiFilter
	 */
	public function emoji_filter(): EmojiFilter {
		if ( isset( $this->overrides['emoji_filter'] ) ) {
			return ( $this->overrides['emoji_filter'] )();
		}

		if ( null === $this->emoji_filter ) {
			$this->emoji_filter = new EmojiFilter();
		}

		return $this->emoji_filter;
	}

	/**
	 * Get the hashtag filter.
	 *
	 * @return HashtagFilter
	 */
	public function hashtag_filter(): HashtagFilter {
		if ( isset( $this->overrides['hashtag_filter'] ) ) {
			return ( $this->overrides['hashtag_filter'] )();
		}

		if ( null === $this->hashtag_filter ) {
			$this->hashtag_filter = new HashtagFilter();
		}

		return $this->hashtag_filter;
	}

	/**
	 * Get the author filter.
	 *
	 * @return AuthorFilter
	 */
	public function author_filter(): AuthorFilter {
		if ( isset( $this->overrides['author_filter'] ) ) {
			return ( $this->overrides['author_filter'] )();
		}

		if ( null === $this->author_filter ) {
			$this->author_filter = new AuthorFilter();
		}

		return $this->author_filter;
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
	 * Get the key event configuration.
	 *
	 * @return KeyEventConfiguration
	 */
	public function key_event_configuration(): KeyEventConfiguration {
		if ( isset( $this->overrides['key_event_configuration'] ) ) {
			return ( $this->overrides['key_event_configuration'] )();
		}

		if ( null === $this->key_event_configuration ) {
			$this->key_event_configuration = new KeyEventConfiguration();
		}

		return $this->key_event_configuration;
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
				$this->key_event_configuration()
			);
		}

		return $this->key_event_shortcode_handler;
	}

	/**
	 * Get the lazyload configuration.
	 *
	 * @return LazyloadConfiguration
	 */
	public function lazyload_configuration(): LazyloadConfiguration {
		if ( isset( $this->overrides['lazyload_configuration'] ) ) {
			return ( $this->overrides['lazyload_configuration'] )();
		}

		if ( null === $this->lazyload_configuration ) {
			$this->lazyload_configuration = new LazyloadConfiguration();
		}

		return $this->lazyload_configuration;
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
			$auto_archive_days          = \WPCOM_Liveblog::$auto_archive_days;
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
