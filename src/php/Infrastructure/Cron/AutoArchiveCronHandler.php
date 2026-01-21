<?php
/**
 * WordPress cron handler for auto-archiving liveblogs.
 *
 * @package Automattic\Liveblog\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\Cron;

use Automattic\Liveblog\Application\Service\AutoArchiveService;

/**
 * Handles WordPress cron integration for auto-archiving liveblogs.
 */
class AutoArchiveCronHandler {

	/**
	 * The cron hook name.
	 *
	 * @var string
	 */
	public const HOOK_NAME = 'liveblog_auto_archive_check';

	/**
	 * Legacy hook name for backwards compatibility.
	 *
	 * @var string
	 */
	private const LEGACY_HOOK_NAME = 'auto_archive_check_hook';

	/**
	 * The auto-archive service.
	 *
	 * @var AutoArchiveService
	 */
	private AutoArchiveService $service;

	/**
	 * Constructor.
	 *
	 * @param AutoArchiveService $service The auto-archive service.
	 */
	public function __construct( AutoArchiveService $service ) {
		$this->service = $service;
	}

	/**
	 * Register the cron hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->schedule_cron_event();
		$this->register_cron_callback();
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 *
	 * @return void
	 */
	private function schedule_cron_event(): void {
		// Check both new and legacy hook names.
		if ( ! wp_next_scheduled( self::HOOK_NAME ) && ! wp_next_scheduled( self::LEGACY_HOOK_NAME ) ) {
			wp_schedule_event( strtotime( 'today midnight' ), 'daily', self::HOOK_NAME );
		}
	}

	/**
	 * Register the cron callback.
	 *
	 * @return void
	 */
	private function register_cron_callback(): void {
		// Register for both new and legacy hook names for backwards compatibility.
		add_action( self::HOOK_NAME, array( $this, 'execute' ) );
		add_action( self::LEGACY_HOOK_NAME, array( $this, 'execute' ) );
	}

	/**
	 * Execute the auto-archive cron task.
	 *
	 * @return void
	 */
	public function execute(): void {
		$this->service->execute_housekeeping();
	}

	/**
	 * Unschedule the cron event.
	 *
	 * Useful for plugin deactivation.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
		}

		// Also unschedule legacy hook if present.
		$legacy_timestamp = wp_next_scheduled( self::LEGACY_HOOK_NAME );
		if ( $legacy_timestamp ) {
			wp_unschedule_event( $legacy_timestamp, self::LEGACY_HOOK_NAME );
		}
	}
}
