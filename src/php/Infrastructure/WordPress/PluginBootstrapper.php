<?php
/**
 * Plugin bootstrapper - wires services to WordPress hooks.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\KeyEventConfiguration;
use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Filter\AuthorFilter;
use Automattic\Liveblog\Application\Filter\CommandFilter;
use Automattic\Liveblog\Application\Filter\EmojiFilter;
use Automattic\Liveblog\Application\Filter\HashtagFilter;
use Automattic\Liveblog\Application\Service\ShortcodeFilter;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;
use WP_Post;

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
		$this->load_textdomain();
		$this->init_core();
		$this->init_legacy_classes();
		$this->init_shortcode_filter();
		$this->init_content_filters();
		$this->init_key_events();
		$this->init_lazyload();
		$this->init_auto_archive();
		$this->init_rest_api();
		$this->init_frontend();
		$this->init_admin();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'liveblog',
			false,
			dirname( plugin_basename( LIVEBLOG_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Initialize core plugin functionality.
	 *
	 * @return void
	 */
	private function init_core(): void {
		add_action( 'init', array( $this, 'register_post_type_support' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'permalink_structure_changed', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'flush_rewrite_rules' ), 1000 );

		// Register image embed handler.
		add_action(
			'init',
			function () {
				wp_embed_register_handler(
					'liveblog_image',
					'/\.(png|jpe?g|gif)(\?.*)?$/',
					array( $this->container->template_renderer(), 'image_embed_handler' ),
					99
				);
			}
		);
	}

	/**
	 * Register post type support for liveblog.
	 *
	 * @return void
	 */
	public function register_post_type_support(): void {
		$post_types = array( 'post' );

		/**
		 * Filters the post types that support liveblog functionality.
		 *
		 * @param string[] $post_types Array of post type names.
		 */
		$post_types = apply_filters( 'liveblog_supported_post_types', $post_types );

		foreach ( $post_types as $post_type ) {
			add_post_type_support( $post_type, LiveblogConfiguration::KEY );
		}

		// Initialize auto-archive days from filter.
		$auto_archive_days = apply_filters( 'liveblog_auto_archive_days', null );
		LiveblogConfiguration::set_auto_archive_days( $auto_archive_days );

		do_action( 'after_liveblog_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
	}

	/**
	 * Add rewrite rules for liveblog endpoints.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_endpoint( LiveblogConfiguration::URL_ENDPOINT, EP_PERMALINK );
	}

	/**
	 * Flush rewrite rules if needed.
	 *
	 * @return void
	 */
	public function flush_rewrite_rules(): void {
		$rewrites_version = (int) get_option( 'liveblog_rewrites_version', 0 );
		if ( $rewrites_version < LiveblogConfiguration::REWRITES_VERSION ) {
			flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Required for plugin activation.
			update_option( 'liveblog_rewrites_version', LiveblogConfiguration::REWRITES_VERSION );
		}
	}

	/**
	 * Initialize legacy classes with injected dependencies.
	 *
	 * @return void
	 */
	private function init_legacy_classes(): void {
		\WPCOM_Liveblog_Socketio_Loader::load();
		\WPCOM_Liveblog_Entry_Embed_SDKs::load();
		\WPCOM_Liveblog_AMP::load();
	}

	/**
	 * Initialize the REST API with injected services.
	 *
	 * @return void
	 */
	private function init_rest_api(): void {
		if ( LiveblogConfiguration::use_rest_api() ) {
			$this->container->rest_api_controller()->init();
		}
	}

	/**
	 * Initialize the shortcode filter.
	 *
	 * Strips restricted shortcodes before entry insert/update.
	 *
	 * @return void
	 */
	private function init_shortcode_filter(): void {
		// Create directly - stateless filter with no dependencies.
		$shortcode_filter = new ShortcodeFilter();
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
		// Filters are created directly here - they're stateless with no dependencies.
		$registry = $this->container->content_filter_registry();
		$registry->register( new CommandFilter() );
		$registry->register( new EmojiFilter() );
		$registry->register( new HashtagFilter() );
		$registry->register( new AuthorFilter() );

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
		// Create configuration directly - it just reads options, no dependencies.
		$configuration     = new KeyEventConfiguration();
		$key_event_service = $this->container->key_event_service();
		$shortcode_handler = $this->container->key_event_shortcode_handler();

		// Initialize the key events widget with the shortcode handler.
		\WPCOM_Liveblog_Entry_Key_Events_Widget::init( $shortcode_handler );

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
			function ( $entry, $entry_object ) use ( $key_event_service ) {
				if ( ! $entry_object instanceof Entry ) {
					return $entry;
				}

				$post_id = $entry_object->post_id();

				return $key_event_service->enrich_entry_for_json( $entry, $entry_object, $post_id );
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
		// Create configuration directly - it just reads options, no dependencies.
		$configuration = new LazyloadConfiguration();

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

		// Extend auto-archive expiry when entries are inserted.
		add_filter(
			'liveblog_before_insert_entry',
			function ( $args ) {
				$post_id = $args['post_id'] ?? 0;
				if ( $post_id && LiveblogConfiguration::is_auto_archive_enabled() ) {
					$liveblog_post = LiveblogPost::from_id( (int) $post_id );
					if ( null !== $liveblog_post ) {
						$days = LiveblogConfiguration::get_auto_archive_days();
						if ( null !== $days ) {
							$liveblog_post->extend_auto_archive_expiry( $days );
						}
					}
				}
				return $args;
			},
			10,
			1
		);
	}

	/**
	 * Initialize frontend hooks.
	 *
	 * @return void
	 */
	private function init_frontend(): void {
		$asset_manager      = $this->container->asset_manager();
		$metadata_presenter = $this->container->metadata_presenter();
		$template_renderer  = $this->container->template_renderer();

		// Enqueue frontend scripts (uses named method so AMP can remove it).
		add_action( 'wp_enqueue_scripts', array( $asset_manager, 'maybe_enqueue_frontend_scripts' ) );

		// Print liveblog metadata in head.
		add_action(
			'wp_head',
			function () use ( $metadata_presenter ) {
				$post = get_post();
				if ( $post instanceof WP_Post ) {
					$liveblog_post = LiveblogPost::from_post( $post );
					if ( $liveblog_post->is_liveblog() ) {
						$metadata_presenter->print_json_ld( $post );
					}
				}
			}
		);

		// Add liveblog content filter.
		add_filter( 'the_content', array( $template_renderer, 'filter_the_content' ) );

		// Add comment CSS class filter.
		add_filter(
			'comment_class',
			function ( $classes, $css_class, $comment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by filter signature.
				if ( LiveblogPost::is_viewing_liveblog_post() ) {
					$classes[] = 'liveblog-entry';
				}
				return $classes;
			},
			10,
			3
		);

		// Protect liveblog meta key.
		add_filter(
			'is_protected_meta',
			function ( $is_protected, $meta_key ) {
				if ( LiveblogConfiguration::KEY === $meta_key ) {
					return true;
				}
				return $is_protected;
			},
			10,
			2
		);
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	private function init_admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin_controller = $this->container->admin_controller();
		$asset_manager    = $this->container->asset_manager();

		// Enqueue admin scripts.
		add_action(
			'admin_enqueue_scripts',
			function ( $hook_suffix ) use ( $asset_manager ) {
				$post      = get_post();
				$post_id   = $post instanceof WP_Post ? $post->ID : 0;
				$post_type = get_post_type( $post_id );
				$post_type = $post_type ? $post_type : 'post';
				$asset_manager->enqueue_admin_scripts( $hook_suffix, $post_id, $post_type );
			}
		);

		// Add meta box.
		add_action( 'add_meta_boxes', array( $admin_controller, 'add_meta_box' ) );

		// Handle AJAX state change.
		add_action(
			'wp_ajax_set_liveblog_state_for_post',
			function () use ( $admin_controller ) {
				// Verify nonce first.
				$nonce = isset( $_REQUEST['_ajax_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
					? sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					: '';

				if ( ! wp_verify_nonce( $nonce, 'liveblog_admin_nonce' ) ) {
					wp_send_json_error( 'Invalid nonce' );
				}

				// Now safe to access other request data.
				$post_id   = isset( $_REQUEST['post_id'] ) ? (int) $_REQUEST['post_id'] : 0;
				$new_state = isset( $_REQUEST['state'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['state'] ) ) : '';

				if ( ! $post_id || ! AdminController::current_user_can_edit() ) {
					wp_send_json_error( 'Unauthorized' );
				}

				$result = $admin_controller->set_liveblog_state( $post_id, $new_state, $_REQUEST );
				if ( false === $result ) {
					wp_send_json_error( 'Failed to update state' );
				}
				wp_send_json_success( $result );
			}
		);

		// Admin list filters.
		add_filter( 'display_post_states', array( $admin_controller, 'add_display_post_state' ), 10, 2 );
		add_filter( 'query_vars', array( $admin_controller, 'add_query_var' ) );
		add_action( 'restrict_manage_posts', array( $admin_controller, 'render_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $admin_controller, 'handle_filter_query' ) );
	}
}
