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
use Automattic\Liveblog\Application\Filter\CommandFilter;
use Automattic\Liveblog\Application\Filter\ContentFilterRegistry;
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
	 * Content filter registry.
	 *
	 * @var ContentFilterRegistry
	 */
	private ContentFilterRegistry $content_filter_registry;

	/**
	 * The plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService     $entry_query_service     The entry query service.
	 * @param ContentFilterRegistry $content_filter_registry The content filter registry.
	 * @param string                $plugin_file             The main plugin file path.
	 */
	public function __construct(
		EntryQueryService $entry_query_service,
		ContentFilterRegistry $content_filter_registry,
		string $plugin_file
	) {
		$this->entry_query_service     = $entry_query_service;
		$this->content_filter_registry = $content_filter_registry;
		$this->plugin_file             = $plugin_file;
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

		$endpoint_url = '';
		$use_rest_api = 0;

		if ( LiveblogConfiguration::use_rest_api() ) {
			$endpoint_url = RestApiController::build_endpoint_base() . $post_id . '/post_state';
			$use_rest_api = 1;
		}

		$asset = $this->load_asset_file( 'admin' );

		wp_enqueue_style(
			LiveblogConfiguration::KEY,
			plugins_url( 'build/admin.css', $this->plugin_file ),
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'liveblog-admin',
			plugins_url( 'build/admin.js', $this->plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			false
		);

		wp_localize_script(
			'liveblog-admin',
			'liveblog_admin_settings',
			array(
				'nonce_key'                    => LiveblogConfiguration::NONCE_KEY,
				'nonce'                        => wp_create_nonce( LiveblogConfiguration::NONCE_ACTION ),
				'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
				'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
				'use_rest_api'                 => $use_rest_api,
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
	 * unlike closures. Used by AMP to disable standard liveblog scripts.
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
		$endpoint_url  = RestApiController::build_endpoint_base();

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
		$asset = $this->load_asset_file( 'app' );

		/**
		 * Filters whether to load default liveblog styles.
		 *
		 * @param bool $load Whether to load default styles. Default true.
		 */
		if ( apply_filters( 'liveblog_load_default_styles', true ) ) {
			wp_enqueue_style(
				LiveblogConfiguration::KEY,
				plugins_url( 'build/app.css', $this->plugin_file ),
				array(),
				$asset['version']
			);
		}

		wp_enqueue_script(
			LiveblogConfiguration::KEY,
			plugins_url( 'build/app.js', $this->plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

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
			'socketio_enabled'             => class_exists( 'WPCOM_Liveblog_Socketio_Loader' )
				? \WPCOM_Liveblog_Socketio_Loader::is_enabled()
				: false,

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

			'features'                     => $this->content_filter_registry->get_enabled_features(),
			'autocomplete'                 => $this->content_filter_registry->get_autocomplete_config(),
			'command_class'                => apply_filters( 'liveblog_command_class', CommandFilter::DEFAULT_CLASS_PREFIX ),

			// Internationalization strings.
			'delete_confirmation'          => __( 'Do you really want to delete this entry? There is no way back.', 'liveblog' ),
			'delete_key_confirm'           => __( 'Do you want to delete this key entry?', 'liveblog' ),
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
	 * Enqueue social embed SDKs on liveblog posts.
	 *
	 * As entries are rendered in React, social embeds require their SDKs
	 * to be loaded on page load rather than dynamically.
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
}
