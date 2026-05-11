<?php
/**
 * Plugin bootstrapper - wires services to WordPress hooks.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Service\ShortcodeFilter;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\AddCommand;
use Automattic\Liveblog\Infrastructure\CLI\ArchiveCommand;
use Automattic\Liveblog\Infrastructure\CLI\ArchiveOldCommand;
use Automattic\Liveblog\Infrastructure\CLI\DisableCommand;
use Automattic\Liveblog\Infrastructure\CLI\EnableCommand;
use Automattic\Liveblog\Infrastructure\CLI\EntriesCommand;
use Automattic\Liveblog\Infrastructure\CLI\ListCommand;
use Automattic\Liveblog\Infrastructure\CLI\MigrateToTaxonomyCommand;
use Automattic\Liveblog\Infrastructure\CLI\StatsCommand;
use Automattic\Liveblog\Infrastructure\CLI\StatusCommand;
use Automattic\Liveblog\Infrastructure\CLI\UnarchiveCommand;
use Automattic\Liveblog\Infrastructure\DI\Container;
use WP_CLI;
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
		$this->init_taxonomy();
		$this->init_cli();
		$this->init_shortcode_filter();
		$this->init_settings_page();
		$this->init_lazyload();
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
		// Enable hierarchical for 'post' post type after registration.
		add_action(
			'registered_post_type',
			function ( string $post_type ): void {
				// Return if not post type 'post'.
				if ( 'post' !== $post_type ) {
					return;
				}

				// Access $wp_post_types global variable.
				global $wp_post_types;

				// Set post type "post" to be hierarchical.
				$wp_post_types['post']->hierarchical = true;

				// Add page attributes support for parent dropdown in admin.
				add_post_type_support( 'post', 'page-attributes' );
			},
			10,
			1
		);

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
	 * Initialize the hidden taxonomy for liveblog state.
	 *
	 * Registers a non-public taxonomy with idempotent term creation
	 * so terms self-heal if accidentally deleted.
	 *
	 * @return void
	 */
	private function init_taxonomy(): void {
		add_action(
			'init',
			function () {
				$this->register_liveblog_taxonomy();
			},
			9
		);
	}

	/**
	 * Register the liveblog state taxonomy and ensure terms exist.
	 *
	 * @return void
	 */
	public function register_liveblog_taxonomy(): void {
		register_taxonomy(
			LiveblogConfiguration::TAXONOMY,
			$this->get_supported_post_types(),
			array(
				'label'              => __( 'Liveblog State', 'liveblog' ),
				'public'             => false,
				'show_ui'            => false,
				'show_in_nav_menus'  => false,
				'show_in_quick_edit' => false,
				'show_admin_column'  => false,
				'hierarchical'       => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);

		wp_insert_term( __( 'Enabled', 'liveblog' ), LiveblogConfiguration::TAXONOMY, array( 'slug' => LiveblogConfiguration::TERM_ENABLED ) );
		wp_insert_term( __( 'Archived', 'liveblog' ), LiveblogConfiguration::TAXONOMY, array( 'slug' => LiveblogConfiguration::TERM_ARCHIVED ) );
	}

	/**
	 * Get supported post types for liveblog.
	 *
	 * @return string[] Array of post type names.
	 */
	private function get_supported_post_types(): array {
		/**
		 * Filters the post types that support liveblog functionality.
		 *
		 * @param string[] $post_types Array of post type names.
		 */
		return apply_filters( 'liveblog_supported_post_types', array( 'post' ) );
	}

	/**
	 * Initialize WP-CLI commands.
	 *
	 * Each command is a separate class with injected dependencies.
	 * Commands handle only CLI concerns; business logic lives in services.
	 *
	 * @return void
	 */
	private function init_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Listing and status commands (no service dependencies).
		WP_CLI::add_command( 'liveblog list', new ListCommand() );
		WP_CLI::add_command( 'liveblog status', new StatusCommand() );
		WP_CLI::add_command( 'liveblog stats', new StatsCommand() );

		// State management commands (no service dependencies).
		WP_CLI::add_command( 'liveblog enable', new EnableCommand() );
		WP_CLI::add_command( 'liveblog archive', new ArchiveCommand() );
		WP_CLI::add_command( 'liveblog unarchive', new UnarchiveCommand() );
		WP_CLI::add_command( 'liveblog disable', new DisableCommand() );

		// Entry commands (with service dependencies).
		WP_CLI::add_command(
			'liveblog entries',
			new EntriesCommand( $this->container->entry_query_service() )
		);
		WP_CLI::add_command(
			'liveblog add',
			new AddCommand( $this->container->entry_service() )
		);

		// Bulk operations.
		WP_CLI::add_command( 'liveblog archive-old', new ArchiveOldCommand() );

		// Migration commands.
		WP_CLI::add_command( 'liveblog migrate-to-taxonomy', new MigrateToTaxonomyCommand() );
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
	 * Initialize frontend hooks.
	 *
	 * @return void
	 */
	private function init_frontend(): void {
		$asset_manager      = $this->container->asset_manager();
		$metadata_presenter = $this->container->metadata_presenter();
		$template_renderer  = $this->container->template_renderer();

		// Initialise embed SDKs (applies filter for customisation).
		$asset_manager->init_embed_sdks();

		// Enqueue frontend scripts (uses named method so AMP can remove it).
		add_action( 'wp_enqueue_scripts', array( $asset_manager, 'maybe_enqueue_frontend_scripts' ) );

		// Enqueue social embed SDKs (Facebook, Twitter, Instagram, Reddit).
		add_action( 'wp_enqueue_scripts', array( $asset_manager, 'enqueue_embed_sdks' ) );
		add_filter( 'script_loader_tag', array( $asset_manager, 'add_async_to_embed_sdks' ), 10, 2 );

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
	}

	/**
	 * Get IDs of child posts belonging to liveblog-enabled parents.
	 *
	 * Uses a direct DB query with object cache (transient not needed; wp_cache is fine).
	 *
	 * @return int[] Child post IDs to exclude.
	 */
	private function get_liveblog_child_ids(): array {
		global $wpdb;

		$cache_key = 'liveblog_child_post_ids';
		$child_ids = wp_cache_get( $cache_key, 'liveblog' );

		if ( false !== $child_ids ) {
			return $child_ids;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result is object-cached.
		$child_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM $wpdb->posts p
				INNER JOIN $wpdb->term_relationships tr ON p.post_parent = tr.object_id
				INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE p.post_type = %s AND p.post_parent > 0
				AND tt.taxonomy = %s",
				'post',
				LiveblogConfiguration::TAXONOMY
			)
		);

		$child_ids = array_map( 'intval', is_array( $child_ids ) ? $child_ids : array() );
		wp_cache_set( $cache_key, $child_ids, 'liveblog', HOUR_IN_SECONDS );

		return $child_ids;
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

		// Add combined meta box (toggle + entries DataViews).
		add_action( 'add_meta_boxes', array( $admin_controller, 'add_meta_box' ) );

		// Hide metaboxes on child posts.
		add_action( 'add_meta_boxes', array( $admin_controller, 'hide_metaboxes_on_child_posts' ), 99 );

		// Add breakout settings metabox for child posts.
		add_action( 'add_meta_boxes', array( $admin_controller, 'add_breakout_metabox' ) );
		add_action( 'save_post', array( $admin_controller, 'save_breakout_settings' ) );

		// Store parent ID from URL for use during auto-draft creation.
		add_action( 'admin_init', array( $admin_controller, 'store_parent_from_url' ) );

		// Bump entry post_modified when a breakout post is published so the
		// poller re-delivers the entry with breakout badge/footer/classes.
		add_action( 'transition_post_status', array( $admin_controller, 'bump_entry_on_breakout_publish' ), 10, 3 );

		// Set post_parent on auto-draft creation.
		add_filter( 'wp_insert_post_data', array( $admin_controller, 'set_parent_on_auto_draft' ), 10, 2 );

		// Liveblog state filter dropdown on admin post list.
		add_action( 'restrict_manage_posts', array( $admin_controller, 'add_liveblog_state_filter' ) );
		add_filter( 'parse_query', array( $admin_controller, 'apply_liveblog_state_filter' ) );
	}

	/**
	 * Initialize settings page.
	 *
	 * @return void
	 */
	private function init_settings_page(): void {
		$settings_controller = $this->container->settings_controller();
		$settings_controller->init();
	}
}
