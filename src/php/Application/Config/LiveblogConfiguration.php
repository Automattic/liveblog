<?php
/**
 * Liveblog configuration constants and settings.
 *
 * @package Automattic\Liveblog\Application\Config
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Config;

/**
 * Centralized configuration for liveblog plugin.
 *
 * Provides access to all plugin constants and configuration values.
 */
final class LiveblogConfiguration {

	/**
	 * Plugin version.
	 */
	public const VERSION = '2.0.0';

	/**
	 * Rewrites version for flushing rewrite rules.
	 */
	public const REWRITES_VERSION = 1;

	/**
	 * Minimum WordPress version required.
	 */
	public const MIN_WP_VERSION = '6.4';

	/**
	 * Minimum WordPress REST API version required.
	 */
	public const MIN_WP_REST_API_VERSION = '6.4';

	/**
	 * Plugin feature identifier.
	 *
	 * Used for post type support registration, metabox ID,
	 * and script/style handles. Not used as a meta key.
	 */
	public const KEY = 'liveblog';

	/**
	 * Hidden taxonomy for liveblog state classification.
	 */
	public const TAXONOMY = 'liveblog_state';

	/**
	 * Taxonomy term slugs.
	 */
	public const TERM_ENABLED  = 'enabled';
	public const TERM_ARCHIVED = 'archived';

	/**
	 * URL endpoint for liveblog.
	 */
	public const URL_ENDPOINT = 'liveblog';

	/**
	 * Capability required to edit liveblog entries.
	 */
	public const EDIT_CAP = 'publish_posts';

	/**
	 * Nonce key for REST API requests.
	 */
	public const NONCE_KEY = '_wpnonce';

	/**
	 * Nonce action for REST API requests.
	 */
	public const NONCE_ACTION = 'wp_rest';

	/**
	 * How often to refresh in seconds.
	 */
	public const REFRESH_INTERVAL = 10;

	/**
	 * How often to refresh in development mode in seconds.
	 */
	public const DEBUG_REFRESH_INTERVAL = 2;

	/**
	 * How often to refresh when window not in focus in seconds.
	 */
	public const FOCUS_REFRESH_INTERVAL = 30;

	/**
	 * Max number of failed tries before polling is disabled.
	 */
	public const MAX_CONSECUTIVE_RETRIES = 100;

	/**
	 * How often we change the entry human timestamps in seconds.
	 */
	public const HUMAN_TIME_DIFF_UPDATE_INTERVAL = 60;

	/**
	 * How many failed tries after which we should increase the refresh interval.
	 */
	public const DELAY_THRESHOLD = 5;

	/**
	 * By how much to increase the refresh interval.
	 */
	public const DELAY_MULTIPLIER = 2;

	/**
	 * How much time fading out the background of new entries should take.
	 */
	public const FADE_OUT_DURATION = 5;

	/**
	 * Cache-Control max-age value for cacheable JSON responses.
	 */
	public const RESPONSE_CACHE_MAX_AGE = DAY_IN_SECONDS;

	/**
	 * Whether to use the REST API.
	 */
	public const USE_REST_API = true;

	/**
	 * The default image size to use when inserting media from the media library.
	 */
	public const DEFAULT_IMAGE_SIZE = 'full';

	/**
	 * Time in ms to debounce the async author list.
	 */
	public const AUTHOR_LIST_DEBOUNCE_TIME = 500;

	/**
	 * Liveblog state: enabled.
	 */
	public const STATE_ENABLED = 'enable';

	/**
	 * Liveblog state: archived.
	 */
	public const STATE_ARCHIVED = 'archive';

	/**
	 * Liveblog state: disabled (empty string).
	 */
	public const STATE_DISABLED = '';

	/**
	 * Supported post types for liveblog.
	 *
	 * @var string[]
	 */
	private static array $supported_post_types = array();

	/**
	 * Post types that were added by set_supported_post_types().
	 * Tracked separately to enable proper cleanup in teardown.
	 *
	 * @var string[]
	 */
	private static array $managed_post_types = array();

	/**
	 * Get the refresh interval based on debug mode and filters.
	 *
	 * @param int|null $post_id Optional post ID for post-specific filters.
	 * @return int Refresh interval in seconds.
	 */
	public static function get_refresh_interval( ?int $post_id = null ): int {
		$refresh_interval = (int) get_option( 'liveblog_polling_interval', 0 );
		if ( $refresh_interval < 1 ) {
			$refresh_interval = WP_DEBUG ? self::DEBUG_REFRESH_INTERVAL : self::REFRESH_INTERVAL;
		}
		$refresh_interval = (int) apply_filters( 'liveblog_refresh_interval', $refresh_interval );

		if ( null !== $post_id ) {
			$refresh_interval = (int) apply_filters( 'liveblog_post_' . $post_id . '_refresh_interval', $refresh_interval );
		}

		return $refresh_interval;
	}

	/**
	 * Set the supported post types.
	 *
	 * Updates both the internal cache and WordPress post type support.
	 * Tracks which types were added by this method to enable proper cleanup
	 * in test teardown without affecting support added by PluginBootstrapper.
	 *
	 * @param string[] $post_types Array of post type names.
	 * @return void
	 */
	public static function set_supported_post_types( array $post_types ): void {
		// Remove support from types that were previously added by this method.
		foreach ( self::$managed_post_types as $managed_type ) {
			if ( ! in_array( $managed_type, $post_types, true ) ) {
				remove_post_type_support( $managed_type, self::KEY );
			}
		}

		// Track and add support for new types.
		self::$managed_post_types = array();
		foreach ( $post_types as $post_type ) {
			// Only track if this method is adding the support.
			if ( ! post_type_supports( $post_type, self::KEY ) ) {
				self::$managed_post_types[] = $post_type;
			}
			add_post_type_support( $post_type, self::KEY );
		}

		self::$supported_post_types = $post_types;
	}

	/**
	 * Check if REST API should be used.
	 *
	 * @return bool True if REST API should be used.
	 */
	public static function use_rest_api(): bool {
		return self::USE_REST_API && self::can_use_rest_api();
	}

	/**
	 * Check if WordPress version supports REST API.
	 *
	 * @return bool True if REST API is supported.
	 */
	public static function can_use_rest_api(): bool {
		global $wp_version;
		return version_compare( $wp_version, self::MIN_WP_REST_API_VERSION, '>=' );
	}

	/**
	 * Check if WordPress version is too old.
	 *
	 * @return bool True if WordPress is too old.
	 */
	public static function is_wp_too_old(): bool {
		global $wp_version;

		if ( ! isset( $wp_version ) || ! $wp_version ) {
			return false;
		}

		return version_compare( $wp_version, self::MIN_WP_VERSION, '<' );
	}

	/**
	 * Check if a CRUD action is valid.
	 *
	 * @param string $action The CRUD action to check.
	 * @return bool True if valid.
	 */
	public static function is_valid_crud_action( string $action ): bool {
		return in_array( $action, array( 'insert', 'update', 'delete' ), true );
	}

	/**
	 * Get the edit capability (with filter applied).
	 *
	 * @return string The capability name.
	 */
	public static function get_edit_capability(): string {
		return (string) apply_filters( 'liveblog_edit_cap', self::EDIT_CAP );
	}
}
