<?php
/**
 * Settings service for plugin configuration.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

/**
 * Service for managing plugin settings.
 */
final class SettingsService {

	/**
	 * Get the polling interval in seconds.
	 *
	 * @return int Polling interval (1-60 seconds, default 10).
	 */
	public function get_polling_interval(): int {
		return (int) get_option( 'liveblog_polling_interval', 10 );
	}

	/**
	 * Set the polling interval in seconds.
	 *
	 * @param int $seconds Polling interval (will be sanitized to 1-60).
	 */
	public function set_polling_interval( int $seconds ): void {
		update_option( 'liveblog_polling_interval', $this->sanitize_polling_interval( $seconds ) );
	}

	/**
	 * Sanitize polling interval to valid range.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return int Sanitized value (1-60).
	 */
	public function sanitize_polling_interval( $value ): int {
		$value = absint( $value );

		if ( $value < 1 ) {
			return 1;
		}

		if ( $value > 60 ) {
			return 60;
		}

		return $value;
	}
}
