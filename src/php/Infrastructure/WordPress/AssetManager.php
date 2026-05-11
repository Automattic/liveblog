<?php
/**
 * Asset manager for liveblog scripts and styles.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_Post;

/**
 * Handles enqueuing of scripts and styles for the liveblog plugin.
 */
final class AssetManager {

	/**
	 * Default social embed SDKs.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_EMBED_SDKS = array(
		'facebook'  => 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v2.5',
		'twitter'   => 'https://platform.twitter.com/widgets.js',
		'instagram' => 'https://www.instagram.com/embed.js',
		'reddit'    => 'https://embed.reddit.com/widgets.js',
	);

	/**
	 * Social embed SDKs to enqueue.
	 *
	 * @var array<string, string>
	 */
	private array $embed_sdks = array();

	/**
	 * Entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $entry_query_service;

	/**
	 * The plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService $entry_query_service The entry query service.
	 * @param string            $plugin_file         The main plugin file path.
	 */
	public function __construct(
		EntryQueryService $entry_query_service,
		string $plugin_file
	) {
		$this->entry_query_service = $entry_query_service;
		$this->plugin_file         = $plugin_file;
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 * @param int    $post_id     The current post ID (if available).
	 * @param string $post_type   The current post type.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix, int $post_id, string $post_type ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! post_type_supports( $post_type, LiveblogConfiguration::KEY ) ) {
			return;
		}

		// Don't enqueue scripts on child posts (entries).
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && $post->post_parent > 0 ) {
				return;
			}
		}

		// Also check URL parameter for new posts.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'post-new.php' === $hook_suffix && isset( $_GET['post_parent'] ) ) {
			// This is a child post being created.
			return;
		}

		$endpoint_url = RestApiController::build_endpoint_base() . $post_id . '/post_state';

		$asset       = $this->load_asset_file( 'admin/admin' );
		$style_asset = $this->load_asset_file( 'admin/admin-style' );

		// Admin metabox layout styles.
		wp_enqueue_style(
			LiveblogConfiguration::KEY . '-admin',
			plugins_url( 'build/admin/admin-style.css', $this->plugin_file ),
			array( 'wp-components' ),
			$style_asset['version']
		);

		wp_enqueue_script(
			'liveblog-admin',
			plugins_url( 'build/admin/admin.js', $this->plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'liveblog-admin',
			'liveblog_admin_settings',
			array(
				'nonce_key'                    => LiveblogConfiguration::NONCE_KEY,
				'nonce'                        => wp_create_nonce( LiveblogConfiguration::NONCE_ACTION ),
				'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
				'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
				'endpoint_url'                 => $endpoint_url,
			)
		);

		wp_localize_script(
			'liveblog-admin',
			'liveblog_settings',
			array(
				'plugin_dir' => plugin_dir_url( $this->plugin_file ),
			)
		);
	}

	/**
	 * Hook callback for wp_enqueue_scripts action.
	 *
	 * This method can be added/removed via add_action/remove_action,
	 * unlike closures.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_scripts(): void {
		if ( ! LiveblogPost::is_viewing_liveblog_post() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$liveblog_post = LiveblogPost::from_post( $post );
		$state         = $liveblog_post->state();
		$is_editable   = EntryPresenter::is_liveblog_editable( $post->ID );
		$endpoint_url  = RestApiController::build_endpoint_base() . $post->ID . '/';

		$this->enqueue_frontend_scripts( $post->ID, $state, $is_editable, $endpoint_url );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @param int    $post_id         The post ID.
	 * @param string $state           The liveblog state.
	 * @param bool   $is_editable     Whether the liveblog is editable.
	 * @param string $endpoint_url    The entries endpoint URL.
	 * @return void
	 */
	public function enqueue_frontend_scripts(
		int $post_id,
		string $state,
		bool $is_editable,
		string $endpoint_url
	): void {
		$asset = $this->load_asset_file( 'frontend/app' );

		wp_enqueue_script(
			LiveblogConfiguration::KEY,
			plugins_url( 'build/frontend/app.js', $this->plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		/**
		 * Filters whether to load default liveblog styles.
		 *
		 * @param bool $load Whether to load default styles. Default true.
		 */
		if ( apply_filters( 'liveblog_load_default_styles', true ) ) {
			$style_asset = $this->load_asset_file( 'frontend/style' );

			wp_enqueue_style(
				LiveblogConfiguration::KEY,
				plugins_url( 'build/frontend/style.css', $this->plugin_file ),
				array(),
				$style_asset['version']
			);
		}

		if ( $is_editable ) {
			$this->add_plupload_settings();
		}

		$latest_timestamp = $this->entry_query_service->get_latest_timestamp( $post_id );
		$latest_id        = $this->entry_query_service->get_latest_id( $post_id );
		$lazyload_config  = new LazyloadConfiguration();

		$settings = $this->build_frontend_settings(
			$post_id,
			$state,
			$is_editable,
			$endpoint_url,
			$latest_timestamp,
			$latest_id,
			$lazyload_config->get_entries_per_page()
		);

		wp_localize_script( LiveblogConfiguration::KEY, 'liveblog_settings', $settings );

		// Pass polling interval for vanilla JS poller.
		$container        = \Automattic\Liveblog\Infrastructure\DI\Container::instance();
		$settings_service = $container->settings_service();
		$polling_interval = $settings_service->get_polling_interval();

		wp_localize_script(
			LiveblogConfiguration::KEY,
			'liveblogPollingConfig',
			array(
				'polling_interval' => $polling_interval,
			)
		);

		wp_localize_script(
			'liveblog-publisher',
			'liveblog_publisher_settings',
			array(
				'loading_preview'         => __( 'Loading preview...', 'liveblog' ),
				'new_entry_tab_label'     => __( 'New Entry', 'liveblog' ),
				'new_entry_submit_label'  => __( 'Publish Update', 'liveblog' ),
				'edit_entry_tab_label'    => __( 'Edit Entry', 'liveblog' ),
				'edit_entry_submit_label' => __( 'Update', 'liveblog' ),
			)
		);
	}

	/**
	 * Build the frontend JavaScript settings array.
	 *
	 * @param int      $post_id          Post ID.
	 * @param string   $state            Liveblog state.
	 * @param bool     $is_editable      Whether editable.
	 * @param string   $endpoint_url     Endpoint URL.
	 * @param int|null $latest_timestamp Latest entry timestamp.
	 * @param int|null $latest_id        Latest entry ID.
	 * @param int      $entries_per_page Entries per page.
	 * @return array The settings array.
	 */
	private function build_frontend_settings(
		int $post_id,
		string $state,
		bool $is_editable,
		string $endpoint_url,
		?int $latest_timestamp,
		?int $latest_id,
		int $entries_per_page
	): array {
		$settings = array(
			'permalink'                    => get_permalink( $post_id ),
			'plugin_dir'                   => plugin_dir_url( $this->plugin_file ),
			'post_id'                      => $post_id,
			'state'                        => $state,
			'is_liveblog_editable'         => $is_editable,
			'current_user'                 => $this->get_current_user_data(),

			'key'                          => LiveblogConfiguration::KEY,
			'nonce_key'                    => LiveblogConfiguration::NONCE_KEY,
			'nonce'                        => wp_create_nonce( LiveblogConfiguration::NONCE_ACTION ),

			'image_nonce'                  => wp_create_nonce( 'media-form' ),
			'default_image_size'           => apply_filters( 'liveblog_default_image_size', LiveblogConfiguration::DEFAULT_IMAGE_SIZE ),

			'latest_entry_timestamp'       => $latest_timestamp,
			'latest_entry_id'              => $latest_id,
			'timestamp'                    => time(),
			'utc_offset'                   => get_option( 'gmt_offset' ) * 60,
			'timezone_string'              => wp_timezone_string(),
			'locale'                       => get_locale(),
			'date_format'                  => get_option( 'date_format' ),
			'time_format'                  => apply_filters( 'liveblog_timestamp_format', get_option( 'time_format' ) ),
			'entries_per_page'             => $entries_per_page,

			'refresh_interval'             => LiveblogConfiguration::get_refresh_interval( $post_id ),
			'focus_refresh_interval'       => LiveblogConfiguration::FOCUS_REFRESH_INTERVAL,
			'max_consecutive_retries'      => LiveblogConfiguration::MAX_CONSECUTIVE_RETRIES,
			'delay_threshold'              => LiveblogConfiguration::DELAY_THRESHOLD,
			'delay_multiplier'             => LiveblogConfiguration::DELAY_MULTIPLIER,
			'fade_out_duration'            => LiveblogConfiguration::FADE_OUT_DURATION,

			'use_rest_api'                 => intval( LiveblogConfiguration::use_rest_api() ),
			'endpoint_url'                 => $endpoint_url,
			'cross_domain'                 => false,

			'features'                     => array(),
			'autocomplete'                 => array(),

			// Internationalization strings.
			'delete_confirmation'          => __( 'Do you really want to delete this entry? There is no way back.', 'liveblog' ),
			'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
			'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
			'new_update'                   => __( 'Liveblog: {number} new update', 'liveblog' ),
			'new_updates'                  => __( 'Liveblog: {number} new updates', 'liveblog' ),
			'create_link_prompt'           => __( 'Provide URL for link:', 'liveblog' ),

			// CSS class names.
			'class_term_prefix'            => __( 'term-', 'liveblog' ),
			'class_alert'                  => __( 'type-alert', 'liveblog' ),
			'class_key'                    => __( 'type-key', 'liveblog' ),

			'author_list_debounce_time'    => apply_filters(
				'liveblog_author_list_debounce_time',
				LiveblogConfiguration::AUTHOR_LIST_DEBOUNCE_TIME
			),
		);

		return apply_filters( 'liveblog_settings', $settings );
	}

	/**
	 * Get current user data for JS.
	 *
	 * @return array|false User data or false if not logged in.
	 */
	private function get_current_user_data() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		return array(
			'id'         => $user->ID,
			'key'        => strtolower( $user->user_nicename ),
			'name'       => $user->display_name,
			'avatar_img' => get_avatar( $user->ID, 30 ),
		);
	}

	/**
	 * Add Plupload settings for media uploads.
	 *
	 * @return void
	 */
	private function add_plupload_settings(): void {
		global $wp_scripts;

		PluploadCompat::ensure_functions();

		$defaults = array(
			'runtimes'            => 'html5,silverlight,flash,html4',
			'file_data_name'      => 'async-upload',
			'multiple_queues'     => true,
			'max_file_size'       => wp_max_upload_size() . 'b',
			'url'                 => admin_url( 'admin-ajax.php', 'relative' ),
			'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'             => array(
				array(
					'title'      => __( 'Allowed Files', 'liveblog' ),
					'extensions' => '*',
				),
			),
			'multipart'           => true,
			'urlstream_upload'    => true,
			'multipart_params'    => array(
				'action'   => 'upload-attachment',
				'_wpnonce' => wp_create_nonce( 'media-form' ),
			),
		);

		$settings = array(
			'defaults' => $defaults,
			'browser'  => array(
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_is_mobile_wp_is_mobile
				'mobile'    => function_exists( 'jetpack_is_mobile' ) ? jetpack_is_mobile() : wp_is_mobile(),
				'supported' => _device_can_upload(),
			),
		);

		$script = 'var _wpPluploadSettings = ' . wp_json_encode( $settings ) . ';';
		$data   = $wp_scripts->get_data( 'wp-plupload', 'data' );

		if ( ! empty( $data ) ) {
			$script = "$data\n$script";
		}

		$wp_scripts->add_data( 'wp-plupload', 'data', $script );
	}

	/**
	 * Load asset file metadata.
	 *
	 * @param string $name Asset name (without extension).
	 * @return array{dependencies: array, version: string}
	 */
	private function load_asset_file( string $name ): array {
		$asset_path = plugin_dir_path( $this->plugin_file ) . 'build/' . $name . '.asset.php';

		if ( file_exists( $asset_path ) ) {
			$asset = include $asset_path;
			return array(
				'dependencies' => $asset['dependencies'] ?? array(),
				'version'      => $asset['version'] ?? LiveblogConfiguration::VERSION,
			);
		}

		return array(
			'dependencies' => array(),
			'version'      => LiveblogConfiguration::VERSION,
		);
	}

	/**
	 * Initialise embed SDKs with filter support.
	 *
	 * Should be called once during plugin initialisation.
	 *
	 * @return void
	 */
	public function init_embed_sdks(): void {
		/**
		 * Filters the social embed SDKs to load on liveblog posts.
		 *
		 * @param array<string, string> $sdks Map of handle => URL.
		 */
		$this->embed_sdks = apply_filters( 'liveblog_embed_sdks', self::DEFAULT_EMBED_SDKS );
	}

	/**
	 * Enqueue social embed SDKs.
	 *
	 * As entries are rendered with server-side rendering, social embeds require their SDKs
	 * to be loaded for proper rendering of embedded content (tweets, Instagram, etc.).
	 *
	 * @return void
	 */
	public function enqueue_embed_sdks(): void {
		if ( ! LiveblogPost::is_viewing_liveblog_post() ) {
			return;
		}

		foreach ( $this->embed_sdks as $handle => $url ) {
			// Reddit's JS fails with version query string - returns 404.
			$version = 'reddit' === $handle ? null : LiveblogConfiguration::VERSION;
			wp_enqueue_script( $handle, esc_url( $url ), array(), $version, false );
		}
	}

	/**
	 * Add async attribute to embed SDK scripts.
	 *
	 * @param string $tag    The script tag HTML.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_async_to_embed_sdks( string $tag, string $handle ): string {
		if ( ! array_key_exists( $handle, $this->embed_sdks ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async="async" src', $tag );
	}

	/**
	 * Enqueue the React DataViews component for the entries metabox.
	 *
	 * Loads the compiled entries-view bundle along with the required
	 *
	 * @wordpress/components and @wordpress/dataviews stylesheets.
	 *
	 * @return void
	 */
	public function enqueue_entries_dataview_assets(): void {
		$asset_file = $this->load_asset_file( 'admin/entries-view' );

		wp_enqueue_style(
			'liveblog-entries-dataview',
			plugins_url( 'build/admin/entries-view.css', $this->plugin_file ),
			array(),
			$asset_file['version']
		);

		wp_enqueue_script(
			'liveblog-entries-dataview',
			plugins_url( 'build/admin/entries-view.js', $this->plugin_file ),
			$this->filter_registered_dependencies( $asset_file['dependencies'] ),
			$asset_file['version'],
			true
		);
	}

	/**
	 * Filter out script dependencies that are not registered in WordPress.
	 *
	 * The @wordpress/dependency-extraction-webpack-plugin can include handles
	 * for packages that exist on disk but haven't yet had their script
	 * registration added to the WordPress version in use.
	 *
	 * @param string[] $dependencies Script dependency handles.
	 * @return string[] Only registered dependency handles.
	 */
	private function filter_registered_dependencies( array $dependencies ): array {
		global $wp_scripts;

		return array_filter(
			$dependencies,
			function ( string $handle ) use ( $wp_scripts ): bool {
				return isset( $wp_scripts->registered[ $handle ] );
			}
		);
	}
}
