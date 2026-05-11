<?php
/**
 * Tests for the EntryQueryService.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Infrastructure\DI\Container;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Entry Query Service test case.
 */
final class EntryQueryTest extends TestCase {

	private const JAN_1_TIMESTAMP = 1325376000;
	private const JAN_1_DATE      = '2012-01-01 00:00:00';
	private const JAN_2_TIMESTAMP = 1325462400;
	private const JAN_2_DATE      = '2012-01-02 00:00:00';

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Entry query service instance.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $query_service;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure post 5 exists for our entries.
		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$container           = Container::instance();
		$this->query_service = $container->entry_query_service();

		// Delete any existing entry posts for this post.
		$existing_entries = get_posts(
			array(
				'post_parent' => $this->post_id,
				'post_type'   => 'post',
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $existing_entries as $entry_post ) {
			wp_delete_post( $entry_post->ID, true );
		}

		// Clear the cache to ensure tests start with a clean slate.
		wp_cache_delete( 'liveblog_entries_asc_' . $this->post_id, 'liveblog' );
	}

	/**
	 * Test get_latest returns null if no entries.
	 */
	public function test_get_latest_should_return_null_if_no_entries(): void {
		$this->assertNull( $this->query_service->get_latest( $this->post_id ) );
	}

	/**
	 * Test get_latest returns the only entry if one.
	 */
	public function test_get_latest_should_return_the_only_entry_if_one(): void {
		$id           = $this->create_entry();
		$latest_entry = $this->query_service->get_latest( $this->post_id );
		$this->assertEquals( $id, $latest_entry->id()->to_int() );
	}

	/**
	 * Test get_latest returns the latest entry if more than one.
	 */
	public function test_get_latest_should_return_the_latest_entry_if_more_than_one(): void {
		$this->create_entry( array( 'post_date_gmt' => self::JAN_1_DATE ) );
		$id_second    = $this->create_entry( array( 'post_date_gmt' => self::JAN_2_DATE ) );
		$latest_entry = $this->query_service->get_latest( $this->post_id );
		$this->assertEquals( $id_second, $latest_entry->id()->to_int() );
	}

	/**
	 * Test get_latest_timestamp properly converts to unix timestamp.
	 */
	public function test_get_latest_timestamp_should_properly_convert_to_unix_timestamp(): void {
		$this->create_entry( array( 'post_date_gmt' => self::JAN_1_DATE ) );
		$this->assertEquals( self::JAN_1_TIMESTAMP, $this->query_service->get_latest_timestamp( $this->post_id ) );
	}

	/**
	 * Test get_between_timestamps returns entries between two timestamps.
	 */
	public function test_get_between_timestamps_should_return_entries_between_two_timestamps(): void {
		$id_first  = $this->create_entry( array( 'post_date_gmt' => self::JAN_1_DATE ) );
		$id_second = $this->create_entry( array( 'post_date_gmt' => self::JAN_2_DATE ) );
		$entries   = $this->query_service->get_between_timestamps( $this->post_id, self::JAN_1_TIMESTAMP - 10, self::JAN_2_TIMESTAMP + 10 );
		$this->assertEquals( 2, count( $entries ) );
		$ids = $this->get_ids_from_entries( $entries );
		// Sort both arrays for comparison since order may vary.
		sort( $ids );
		$expected_ids = array( $id_first, $id_second );
		sort( $expected_ids );
		$this->assertEquals( $expected_ids, $ids );
	}

	/**
	 * Test get_between_timestamps returns entries on the border.
	 */
	public function test_get_between_timestamps_should_return_entries_on_the_border(): void {
		$id      = $this->create_entry( array( 'post_date_gmt' => self::JAN_1_DATE ) );
		$entries = $this->query_service->get_between_timestamps( $this->post_id, self::JAN_1_TIMESTAMP, self::JAN_1_TIMESTAMP + 1 );
		$ids     = $this->get_ids_from_entries( $entries );
		$this->assertEquals( array( $id ), $ids );
	}

	/**
	 * Test that get only matches posts with publish status.
	 */
	public function test_get_only_matches_published_entries(): void {
		$this->create_entry( array( 'post_status' => 'draft' ) );
		$entries = $this->query_service->get_all( $this->post_id );
		$this->assertEquals( 0, count( $entries ) );
	}

	/**
	 * Test remove_replaced_entries removes the original when an update exists.
	 */
	public function test_remove_replaced_entries_should_remove_original_when_update_exists(): void {
		// Create original entry.
		$original_id = $this->create_entry( array( 'post_date_gmt' => self::JAN_1_DATE ) );

		// Create update entry that replaces the original.
		$update_id = $this->create_entry( array( 'post_date_gmt' => self::JAN_2_DATE ) );
		update_post_meta( $update_id, 'liveblog_replaces', $original_id );

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'liveblog_entries_asc_' . $this->post_id, 'liveblog' );

		$entries = $this->query_service->get_all( $this->post_id );
		// The original should be filtered out, keeping only the update.
		$this->assertEquals( 1, count( $entries ) );
		$ids = $this->get_ids_from_entries( $entries );
		$this->assertEquals( array( $update_id ), $ids );
	}

	/**
	 * Test remove_replaced_entries does not remove entries replacing non-existing entries.
	 */
	public function test_remove_replaced_entries_should_not_remove_entries_replacing_non_existing_entries(): void {
		// Create an entry.
		$entry_id = $this->create_entry();

		// Create another entry that claims to replace a non-existent entry.
		$update_id = $this->create_entry( array( 'post_date_gmt' => self::JAN_2_DATE ) );
		update_post_meta( $update_id, 'liveblog_replaces', 99999 ); // Non-existent ID.

		// Clear cache to ensure fresh query.
		wp_cache_delete( 'liveblog_entries_asc_' . $this->post_id, 'liveblog' );

		$entries = $this->query_service->get_all( $this->post_id );
		// Both entries should be present since the replaced entry doesn't exist.
		$this->assertEquals( 2, count( $entries ) );
	}

	/**
	 * Test has_any returns false if no entries.
	 */
	public function test_has_any_returns_false_if_no_entries(): void {
		$this->assertFalse( $this->query_service->has_any( $this->post_id ) );
	}

	/**
	 * Test has_any returns true if we add some entries.
	 */
	public function test_has_any_returns_true_if_we_add_some_entries(): void {
		$this->create_entry();
		$this->assertTrue( $this->query_service->has_any( $this->post_id ) );
	}

	/**
	 * Test count counts all entries.
	 */
	public function test_count_counts_all_entries(): void {
		$this->create_entry();
		$this->create_entry( array( 'post_date_gmt' => self::JAN_2_DATE ) );
		$this->assertEquals( 2, $this->query_service->count( $this->post_id ) );
	}

	/**
	 * Test count returns 0 on no entries.
	 */
	public function test_count_returns_0_on_no_entries(): void {
		$this->assertEquals( 0, $this->query_service->count( $this->post_id ) );
	}

	/**
	 * Create an entry post in the database.
	 *
	 * @param array $args Post arguments.
	 * @return int Post ID.
	 */
	private function create_entry( array $args = array() ): int {
		$defaults = array(
			'post_parent' => $this->post_id,
			'post_status' => 'publish',
			'post_type'   => 'post',
		);
		$args     = array_merge( $defaults, $args );
		return wp_insert_post( $args );
	}

	/**
	 * Get IDs from entries.
	 *
	 * @param array $entries Array of entries.
	 * @return array Array of IDs.
	 */
	private function get_ids_from_entries( array $entries ): array {
		return array_values(
			array_map(
				function ( Entry $entry ): int {
					return $entry->id()->to_int();
				},
				$entries
			)
		);
	}
}
