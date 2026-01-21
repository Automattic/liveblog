<?php
/**
 * Unit tests for AutoArchiveService.
 *
 * @package Automattic\Liveblog\Tests\Unit\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Application\Service;

use Automattic\Liveblog\Application\Service\AutoArchiveService;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests for AutoArchiveService configuration logic.
 *
 * @coversDefaultClass \Automattic\Liveblog\Application\Service\AutoArchiveService
 */
final class AutoArchiveServiceTest extends TestCase {

	/**
	 * Test that service is disabled when constructed with null.
	 *
	 * @covers ::is_enabled
	 */
	public function test_is_disabled_when_days_is_null(): void {
		$service = new AutoArchiveService( null );

		$this->assertFalse( $service->is_enabled() );
	}

	/**
	 * Test that service is enabled when constructed with a value.
	 *
	 * @covers ::is_enabled
	 */
	public function test_is_enabled_when_days_is_set(): void {
		$service = new AutoArchiveService( 7 );

		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Test that service is enabled even with zero days.
	 *
	 * @covers ::is_enabled
	 */
	public function test_is_enabled_with_zero_days(): void {
		$service = new AutoArchiveService( 0 );

		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Test get_auto_archive_days returns configured value.
	 *
	 * @covers ::get_auto_archive_days
	 */
	public function test_get_auto_archive_days_returns_configured_value(): void {
		$service = new AutoArchiveService( 30 );

		$this->assertSame( 30, $service->get_auto_archive_days() );
	}

	/**
	 * Test get_auto_archive_days returns null when disabled.
	 *
	 * @covers ::get_auto_archive_days
	 */
	public function test_get_auto_archive_days_returns_null_when_disabled(): void {
		$service = new AutoArchiveService( null );

		$this->assertNull( $service->get_auto_archive_days() );
	}

	/**
	 * Test execute_housekeeping returns zero when disabled.
	 *
	 * @covers ::execute_housekeeping
	 */
	public function test_execute_housekeeping_returns_zero_when_disabled(): void {
		$service = new AutoArchiveService( null );

		$this->assertSame( 0, $service->execute_housekeeping() );
	}
}
