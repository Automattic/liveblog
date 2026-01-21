<?php
/**
 * Integration tests for AutoArchiveService.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Service\AutoArchiveService;
use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Integration tests for AutoArchiveService.
 *
 * @coversDefaultClass \Automattic\Liveblog\Application\Service\AutoArchiveService
 */
final class AutoArchiveServiceTest extends TestCase {

	/**
	 * Test that a post with expired date should be archived.
	 *
	 * @covers ::should_archive_post
	 */
	public function test_should_archive_post_when_expiry_has_passed(): void {
		$service = new AutoArchiveService( 7 );
		$post_id = self::factory()->post->create();

		// Set expiry to yesterday.
		$yesterday = strtotime( '-1 day' );
		update_post_meta( $post_id, AutoArchiveService::EXPIRY_META_KEY, $yesterday );

		$now = time();

		$this->assertTrue( $service->should_archive_post( $post_id, $now ) );
	}

	/**
	 * Test that a post with future expiry should not be archived.
	 *
	 * @covers ::should_archive_post
	 */
	public function test_should_not_archive_post_when_expiry_is_future(): void {
		$service = new AutoArchiveService( 7 );
		$post_id = self::factory()->post->create();

		// Set expiry to tomorrow.
		$tomorrow = strtotime( '+1 day' );
		update_post_meta( $post_id, AutoArchiveService::EXPIRY_META_KEY, $tomorrow );

		$now = time();

		$this->assertFalse( $service->should_archive_post( $post_id, $now ) );
	}

	/**
	 * Test that a post without expiry should not be archived.
	 *
	 * @covers ::should_archive_post
	 */
	public function test_should_not_archive_post_without_expiry(): void {
		$service = new AutoArchiveService( 7 );
		$post_id = self::factory()->post->create();

		// No expiry meta set.
		$now = time();

		$this->assertFalse( $service->should_archive_post( $post_id, $now ) );
	}

	/**
	 * Test that execute_housekeeping archives expired liveblogs.
	 *
	 * @covers ::execute_housekeeping
	 */
	public function test_execute_housekeeping_archives_expired_liveblogs(): void {
		$service = new AutoArchiveService( 7 );

		// Create a liveblog post with expired date.
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, WPCOM_Liveblog::KEY, 'enable' );

		$yesterday = strtotime( '-1 day' );
		update_post_meta( $post_id, AutoArchiveService::EXPIRY_META_KEY, $yesterday );

		$archived_count = $service->execute_housekeeping();

		$this->assertSame( 1, $archived_count );
		$this->assertSame( 'archive', get_post_meta( $post_id, WPCOM_Liveblog::KEY, true ) );
	}

	/**
	 * Test that execute_housekeeping does not archive non-expired liveblogs.
	 *
	 * @covers ::execute_housekeeping
	 */
	public function test_execute_housekeeping_skips_non_expired_liveblogs(): void {
		$service = new AutoArchiveService( 7 );

		// Create a liveblog post with future expiry.
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, WPCOM_Liveblog::KEY, 'enable' );

		$tomorrow = strtotime( '+1 day' );
		update_post_meta( $post_id, AutoArchiveService::EXPIRY_META_KEY, $tomorrow );

		$archived_count = $service->execute_housekeeping();

		$this->assertSame( 0, $archived_count );
		$this->assertSame( 'enable', get_post_meta( $post_id, WPCOM_Liveblog::KEY, true ) );
	}

	/**
	 * Test that execute_housekeeping handles multiple liveblogs correctly.
	 *
	 * @covers ::execute_housekeeping
	 */
	public function test_execute_housekeeping_handles_multiple_liveblogs(): void {
		$service = new AutoArchiveService( 7 );

		$yesterday = strtotime( '-1 day' );
		$tomorrow  = strtotime( '+1 day' );

		// Create expired liveblog.
		$expired_post = self::factory()->post->create();
		update_post_meta( $expired_post, WPCOM_Liveblog::KEY, 'enable' );
		update_post_meta( $expired_post, AutoArchiveService::EXPIRY_META_KEY, $yesterday );

		// Create non-expired liveblog.
		$active_post = self::factory()->post->create();
		update_post_meta( $active_post, WPCOM_Liveblog::KEY, 'enable' );
		update_post_meta( $active_post, AutoArchiveService::EXPIRY_META_KEY, $tomorrow );

		// Create liveblog without expiry.
		$no_expiry_post = self::factory()->post->create();
		update_post_meta( $no_expiry_post, WPCOM_Liveblog::KEY, 'enable' );

		$archived_count = $service->execute_housekeeping();

		$this->assertSame( 1, $archived_count );
		$this->assertSame( 'archive', get_post_meta( $expired_post, WPCOM_Liveblog::KEY, true ) );
		$this->assertSame( 'enable', get_post_meta( $active_post, WPCOM_Liveblog::KEY, true ) );
		$this->assertSame( 'enable', get_post_meta( $no_expiry_post, WPCOM_Liveblog::KEY, true ) );
	}
}
