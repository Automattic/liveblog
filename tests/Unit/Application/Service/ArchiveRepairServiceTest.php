<?php
/**
 * Unit tests for ArchiveRepairService.
 *
 * @package Automattic\Liveblog\Tests\Unit\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Application\Service;

use Automattic\Liveblog\Application\Service\ArchiveRepairService;
use Automattic\Liveblog\Application\Service\ArchiveRepairServiceInterface;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * ArchiveRepairService unit test case.
 *
 * Tests that the service implements the expected interface.
 * Full behaviour testing is done via integration tests.
 *
 * @covers \Automattic\Liveblog\Application\Service\ArchiveRepairService
 */
final class ArchiveRepairServiceTest extends TestCase {

	/**
	 * Test service implements interface.
	 */
	public function test_implements_interface(): void {
		$service = new ArchiveRepairService();

		$this->assertInstanceOf( ArchiveRepairServiceInterface::class, $service );
	}

	/**
	 * Test service has required methods.
	 */
	public function test_has_required_methods(): void {
		$service = new ArchiveRepairService();

		$this->assertTrue( method_exists( $service, 'find_liveblog_posts' ) );
		$this->assertTrue( method_exists( $service, 'repair_post' ) );
	}
}
