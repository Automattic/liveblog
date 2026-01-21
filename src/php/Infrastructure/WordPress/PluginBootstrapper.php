<?php
/**
 * Plugin bootstrapper - wires services to WordPress hooks.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Plugin bootstrapper - centralises hook wiring for DDD services.
 *
 * This class is the composition root for wiring DDD services to WordPress hooks.
 * It should be the only place (besides tests) where Container::instance() is called
 * from plugin code.
 */
final class PluginBootstrapper {

	/**
	 * DI container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container DI container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize all DDD service integrations.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_shortcode_filter();
		$this->init_content_filters();
		$this->init_key_events();
		$this->init_lazyload();
		$this->init_auto_archive();
	}

	/**
	 * Initialize the shortcode filter.
	 *
	 * Strips restricted shortcodes before entry insert/update.
	 *
	 * @return void
	 */
	private function init_shortcode_filter(): void {
		$shortcode_filter = $this->container->shortcode_filter();
		add_filter( 'liveblog_before_insert_entry', array( $shortcode_filter, 'filter' ), 10, 1 );
		add_filter( 'liveblog_before_update_entry', array( $shortcode_filter, 'filter' ), 10, 1 );
	}

	/**
	 * Initialize the DDD-based content filter system.
	 *
	 * Registers all content filters with the ContentFilterRegistry and sets up
	 * the input sanitizer hooks. Handles commands, emojis, hashtags, and authors.
	 *
	 * @return void
	 */
	private function init_content_filters(): void {
		// Register all content filters with the registry.
		$registry = $this->container->content_filter_registry();
		$registry->register( $this->container->command_filter() );
		$registry->register( $this->container->emoji_filter() );
		$registry->register( $this->container->hashtag_filter() );
		$registry->register( $this->container->author_filter() );

		// Initialise the registry (sets up prefixes, regex, loads filters).
		$registry->initialise( 'commands, emojis, hashtags, authors' );

		// Add input sanitizer hooks (runs before content filters).
		$input_sanitizer = $this->container->input_sanitizer();
		add_filter( 'liveblog_before_insert_entry', array( $input_sanitizer, 'sanitize' ), 1 );
		add_filter( 'liveblog_before_update_entry', array( $input_sanitizer, 'sanitize' ), 1 );
		add_filter( 'liveblog_before_insert_entry', array( $input_sanitizer, 'fix_links_wrapped_in_div' ), 1 );
		add_filter( 'liveblog_before_update_entry', array( $input_sanitizer, 'fix_links_wrapped_in_div' ), 1 );
		add_filter( 'liveblog_before_preview_entry', array( $input_sanitizer, 'fix_links_wrapped_in_div' ), 1 );

		// Add content filter hooks (processes commands, emojis, hashtags, authors).
		add_filter( 'liveblog_before_insert_entry', array( $registry, 'apply_filters' ), 10 );
		add_filter( 'liveblog_before_update_entry', array( $registry, 'apply_filters' ), 10 );
		add_filter( 'liveblog_before_preview_entry', array( $registry, 'apply_filters' ), 10 );

		// Add revert filter hook (for edit mode).
		add_filter( 'liveblog_before_edit_entry', array( $registry, 'revert_all' ), 10 );

		// Allow emoji image attributes (class, width, height, data-emoji) to pass through.
		add_filter(
			'liveblog_image_allowed_attributes',
			function ( $attrs ) {
				return array_merge( $attrs, array( 'class', 'width', 'height', 'data-emoji' ) );
			}
		);
	}

	/**
	 * Initialize the DDD-based key events system.
	 *
	 * Sets up the key event configuration, shortcode, and all related hooks.
	 *
	 * @return void
	 */
	private function init_key_events(): void {
		$configuration     = $this->container->key_event_configuration();
		$key_event_service = $this->container->key_event_service();
		$shortcode_handler = $this->container->key_event_shortcode_handler();

		// Initialize key event configuration (sets up templates/formats).
		add_action( 'init', array( $configuration, 'initialize' ), 11 );

		// Register the /key command.
		add_filter(
			'liveblog_active_commands',
			function ( $commands ) {
				$commands[] = 'key';
				return $commands;
			}
		);

		// Enrich entry JSON with key event data.
		add_filter(
			'liveblog_entry_for_json',
			function ( $entry, $entry_object ) use ( $key_event_service, $configuration ) {
				// Support both legacy WPCOM_Liveblog_Entry and domain Entry objects.
				if ( $entry_object instanceof Entry ) {
					$post_id = $entry_object->post_id();
					return $key_event_service->enrich_entry_for_json( $entry, $entry_object, $post_id );
				}

				// Handle legacy WPCOM_Liveblog_Entry.
				$post_id = (int) $entry_object->get_post_id();
				$content = $entry_object->get_content();

				// Detect key event from content (more reliable than meta for updates).
				$is_key_event       = $key_event_service->content_has_key_command( $content );
				$entry['key_event'] = $is_key_event;

				if ( $is_key_event ) {
					$entry['key_event_content'] = $configuration->format_content( $content, $post_id );
				}

				return $entry;
			},
			10,
			2
		);

		// Handle /key command action (add meta when command is used).
		add_action(
			'liveblog_command_key_after',
			function ( $content, $entry_id, $post_id ) use ( $key_event_service ) {
				$key_event_service->handle_key_command( $content, $entry_id, $post_id );
			},
			10,
			3
		);

		// Sync key event meta when entry is updated.
		add_action(
			'liveblog_update_entry',
			array( $key_event_service, 'sync_key_event_meta' ),
			10,
			2
		);

		// Add admin options for key event settings.
		add_filter(
			'liveblog_admin_add_settings',
			function ( $extra_fields, $post_id ) use ( $shortcode_handler ) {
				$extra_fields[] = $shortcode_handler->get_admin_options( (int) $post_id );
				return $extra_fields;
			},
			10,
			2
		);

		// Save admin options.
		add_action(
			'liveblog_admin_settings_update',
			function ( $response, $post_id ) use ( $shortcode_handler ) {
				$shortcode_handler->save_admin_options( $response, (int) $post_id );
			},
			10,
			2
		);

		// Register the shortcode.
		$shortcode_handler->register();

		// Hook configuration for content formatting.
		add_filter(
			'liveblog_key_event_content',
			function ( $content, $post_id ) use ( $configuration ) {
				return $configuration->format_content( $content, (int) $post_id );
			},
			10,
			2
		);
	}

	/**
	 * Initialize the DDD-based lazyload configuration.
	 *
	 * Sets up lazy loading configuration for entries including initial
	 * display count and per-page limits for lazy loading requests.
	 *
	 * @return void
	 */
	private function init_lazyload(): void {
		$configuration = $this->container->lazyload_configuration();

		// Initialize on template_redirect when liveblog state is available.
		add_action( 'template_redirect', array( $configuration, 'initialize' ) );
	}

	/**
	 * Initialize the auto-archive cron handler.
	 *
	 * @return void
	 */
	private function init_auto_archive(): void {
		$this->container->auto_archive_cron_handler()->register();
	}
}
