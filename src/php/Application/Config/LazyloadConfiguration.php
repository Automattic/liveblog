<?php
/**
 * Lazyload configuration for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Config
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Config;

use WPCOM_Liveblog;

/**
 * Configuration for lazy loading liveblog entries.
 *
 * Manages settings for initial entry display count and
 * per-request loading limits.
 */
final class LazyloadConfiguration {

	/**
	 * Default number of entries to display initially.
	 *
	 * @var int
	 */
	public const DEFAULT_INITIAL_ENTRIES = 20;

	/**
	 * Default number of entries per lazy load request.
	 *
	 * @var int
	 */
	public const DEFAULT_ENTRIES_PER_PAGE = 20;

	/**
	 * Maximum entries allowed per lazy load request.
	 *
	 * @var int
	 */
	public const MAX_ENTRIES_PER_PAGE = 100;

	/**
	 * Whether lazy loading is enabled.
	 *
	 * @var bool|null
	 */
	private ?bool $enabled = null;

	/**
	 * Number of entries to display initially.
	 *
	 * @var int|null
	 */
	private ?int $initial_entries = null;

	/**
	 * Number of entries per lazy load request.
	 *
	 * @var int|null
	 */
	private ?int $entries_per_page = null;

	/**
	 * Check if lazy loading is enabled.
	 *
	 * Lazy loading is enabled by default but disabled for archived liveblogs.
	 *
	 * @return bool True if lazy loading is enabled.
	 */
	public function is_enabled(): bool {
		if ( null === $this->enabled ) {
			/**
			 * Enables/Disables lazy loading for Liveblog entries.
			 *
			 * @param bool $enabled Enable lazy loading for Liveblog entries?
			 */
			$this->enabled = (bool) apply_filters( 'liveblog_enable_lazyloader', true );

			// Disable lazy loading on archived liveblogs.
			if ( 'enable' !== WPCOM_Liveblog::get_liveblog_state() ) {
				$this->enabled = false;
			}
		}

		return $this->enabled;
	}

	/**
	 * Get the number of entries to display initially.
	 *
	 * @return int Number of initial entries.
	 */
	public function get_initial_entries(): int {
		if ( null === $this->initial_entries ) {
			/**
			 * Filters the number of initially displayed Liveblog entries.
			 *
			 * @param int $number Number of initially displayed Liveblog entries.
			 */
			$number = (int) apply_filters( 'liveblog_number_of_default_entries', self::DEFAULT_INITIAL_ENTRIES );

			$this->initial_entries = $number >= 0 ? $number : self::DEFAULT_INITIAL_ENTRIES;
		}

		return $this->initial_entries;
	}

	/**
	 * Get the number of entries per lazy load request.
	 *
	 * @return int Number of entries per page, capped at MAX_ENTRIES_PER_PAGE.
	 */
	public function get_entries_per_page(): int {
		if ( null === $this->entries_per_page ) {
			/**
			 * Filters the number of Liveblog entries used for lazy loading.
			 *
			 * @param int $number Number of Liveblog entries.
			 */
			$number = (int) apply_filters( 'liveblog_number_of_entries', self::DEFAULT_ENTRIES_PER_PAGE );

			if ( $number > 0 ) {
				$this->entries_per_page = min( $number, self::MAX_ENTRIES_PER_PAGE );
			} else {
				$this->entries_per_page = self::DEFAULT_ENTRIES_PER_PAGE;
			}
		}

		return $this->entries_per_page;
	}

	/**
	 * Initialize lazy loading hooks.
	 *
	 * Should be called during template_redirect to set up lazy loading.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->check_deprecated_plugin();

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_filter( 'liveblog_display_archive_query_args', array( $this, 'filter_archive_query_args' ), 20 );
	}

	/**
	 * Filter the archive query args to limit initial entries.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed> Modified arguments.
	 */
	public function filter_archive_query_args( array $args ): array {
		$args['number'] = $this->get_initial_entries();

		return $args;
	}

	/**
	 * Check for and disable the deprecated Lazyload Liveblog Entries plugin.
	 *
	 * @return void
	 */
	private function check_deprecated_plugin(): void {
		if ( ! has_action( 'init', 'Lazyload_Liveblog_Entries' ) ) {
			return;
		}

		if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_notices', array( $this, 'render_deprecated_plugin_notice' ) );
		}

		// Disable the deprecated plugin.
		remove_action( 'init', 'Lazyload_Liveblog_Entries' );
	}

	/**
	 * Render the admin notice for deprecated plugin.
	 *
	 * @return void
	 */
	public function render_deprecated_plugin_notice(): void {
		echo wp_kses_post(
			WPCOM_Liveblog::get_template_part(
				'lazyload-notice.php',
				array(
					'plugin' => 'Lazyload Liveblog Entries',
				)
			)
		);
	}

	/**
	 * Reset cached values.
	 *
	 * Useful for testing or when filter values may have changed.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->enabled          = null;
		$this->initial_entries  = null;
		$this->entries_per_page = null;
	}
}
