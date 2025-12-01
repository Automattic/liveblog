<?php

declare( strict_types=1 );

/**
 * Tests for the Liveblog Entry Query class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_Entry_Query;

/**
 * Entry Query test case.
 */
final class EntryQueryTest extends TestCase {

	private const JAN_1_TIMESTAMP = 1325376000;
	private const JAN_1_MYSQL     = '2012-01-01 00:00:00';
	private const JAN_2_TIMESTAMP = 1325462400;
	private const JAN_2_MYSQL     = '2012-01-02 00:00:00';

	/**
	 * Entry query instance.
	 *
	 * @var WPCOM_Liveblog_Entry_Query
	 */
	private WPCOM_Liveblog_Entry_Query $entry_query;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure post 5 exists for our comments.
		wp_insert_post(
			[
				'ID'          => 5,
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$this->entry_query = new WPCOM_Liveblog_Entry_Query( 5, 'liveblog' );

		// Delete any existing liveblog comments for post 5.
		$existing_comments = get_comments(
			[
				'post_id' => 5,
				'type'    => 'liveblog',
				'status'  => 'all',
			]
		);
		foreach ( $existing_comments as $comment ) {
			wp_delete_comment( $comment->comment_ID, true );
		}

		// Clear the cache to ensure tests start with a clean slate.
		wp_cache_delete( 'liveblog_entries_asc_5', 'liveblog' );
	}

	/**
	 * Test get_latest returns null if no comments.
	 */
	public function test_get_latest_should_return_null_if_no_comments(): void {
		$this->assertNull( $this->entry_query->get_latest() );
	}

	/**
	 * Test get_latest returns the only comment if one.
	 */
	public function test_get_latest_should_return_the_only_comment_if_one(): void {
		$id           = $this->create_comment();
		$latest_entry = $this->entry_query->get_latest();
		$this->assertEquals( $id, $latest_entry->get_id() );
	}

	/**
	 * Test get_latest returns the latest comment if more than one.
	 */
	public function test_get_latest_should_return_the_latest_comment_if_more_than_one(): void {
		$id_first     = $this->create_comment( [ 'comment_date_gmt' => self::JAN_1_MYSQL ] ); // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
		$id_second    = $this->create_comment( [ 'comment_date_gmt' => self::JAN_2_MYSQL ] );
		$latest_entry = $this->entry_query->get_latest();
		$this->assertEquals( $id_second, $latest_entry->get_id() );
	}

	/**
	 * Test get_latest_timestamp properly converts to unix timestamp.
	 */
	public function test_get_latest_timestamp_should_properly_convert_to_unix_timestamp(): void {
		$this->create_comment( [ 'comment_date_gmt' => self::JAN_1_MYSQL ] );
		$this->assertEquals( self::JAN_1_TIMESTAMP, $this->entry_query->get_latest_timestamp() );
	}

	/**
	 * Test get_between_timestamps returns an entry between two timestamps.
	 */
	public function test_get_between_timestamps_should_return_an_entry_between_two_timestamps(): void {
		$id_first  = $this->create_comment( [ 'comment_date_gmt' => self::JAN_1_MYSQL ] );
		$id_second = $this->create_comment( [ 'comment_date_gmt' => self::JAN_2_MYSQL ] );
		$entries   = $this->entry_query->get_between_timestamps( self::JAN_1_TIMESTAMP - 10, self::JAN_2_TIMESTAMP + 10 );
		$this->assertEquals( 2, count( $entries ) );
		$ids = $this->get_ids_from_entries( $entries );
		// Sort both arrays for comparison since order may vary.
		sort( $ids );
		$expected_ids = [ $id_first, $id_second ];
		sort( $expected_ids );
		$this->assertEquals( $expected_ids, $ids );
	}

	/**
	 * Test get_between_timestamps returns entries on the border.
	 */
	public function test_get_between_timestamps_should_return_entries_on_the_border(): void {
		$id      = $this->create_comment( [ 'comment_date_gmt' => self::JAN_1_MYSQL ] );
		$entries = $this->entry_query->get_between_timestamps( self::JAN_1_TIMESTAMP, self::JAN_1_TIMESTAMP + 1 );
		$ids     = $this->get_ids_from_entries( $entries );
		$this->assertEquals( [ $id ], $ids );
	}

	/**
	 * Test that get only matches comments with the key as approved status.
	 */
	public function test_get_only_matches_comments_with_the_key_as_approved_status(): void {
		$this->create_comment( [ 'comment_approved' => 'wink' ] );
		$entries = $this->entry_query->get_all();
		$this->assertEquals( 0, count( $entries ) );
	}

	/**
	 * Test remove_replaced_entries removes entries replacing other entries.
	 */
	public function test_remove_replaced_entries_should_remove_entries_replacing_other_entries(): void {
		$entries = [];

		$entries[0] = new WPCOM_Liveblog_Entry( (object) [ 'comment_ID' => 1 ] );

		$entries[1]           = new WPCOM_Liveblog_Entry( (object) [ 'comment_ID' => 1000 ] );
		$entries[1]->replaces = 1;

		$filtered_entries = WPCOM_Liveblog_Entry_Query::remove_replaced_entries( $entries );
		$this->assertEquals( [ 1 ], $this->get_ids_from_entries( $filtered_entries ) );
	}

	/**
	 * Test remove_replaced_entries does not remove entries replacing non-existing entries.
	 */
	public function test_remove_replaced_entries_should_not_remove_entries_replacing_non_existing_entries(): void {
		$entries = [];

		$entries[0] = new WPCOM_Liveblog_Entry( (object) [ 'comment_ID' => 1 ] );

		$entries[1]           = new WPCOM_Liveblog_Entry( (object) [ 'comment_ID' => 1000 ] );
		$entries[1]->replaces = 999;

		$filtered_entries = WPCOM_Liveblog_Entry_Query::remove_replaced_entries( $entries );
		$this->assertEquals( [ 1, 1000 ], $this->get_ids_from_entries( $filtered_entries ) );
	}

	/**
	 * Test get_by_id returns the entry.
	 */
	public function test_get_by_id_should_return_the_entry(): void {
		$comment_id = $this->create_comment();
		$this->assertEquals( $comment_id, $this->entry_query->get_by_id( $comment_id )->get_id() );
	}

	/**
	 * Test get_by_id does not return entries for trashed comments.
	 */
	public function test_get_by_id_should_not_return_entries_for_trashed_comments(): void {
		$comment_id = $this->create_comment();
		wp_delete_comment( $comment_id );
		$this->assertNull( $this->entry_query->get_by_id( $comment_id ) );
	}

	/**
	 * Test has_any returns false if no entries.
	 */
	public function test_has_any_returns_false_if_no_entries(): void {
		$this->assertFalse( $this->entry_query->has_any() );
	}

	/**
	 * Test has_any returns true if we add some entries.
	 */
	public function test_has_any_returns_true_if_we_add_some_entries(): void {
		$this->create_comment();
		$this->assertTrue( $this->entry_query->has_any() );
	}

	/**
	 * Test count counts all entries.
	 */
	public function test_count_counts_all_entries(): void {
		$this->create_comment();
		$this->create_comment();
		$this->assertEquals( 2, $this->entry_query->count() );
	}

	/**
	 * Test count returns 0 on no entries.
	 */
	public function test_count_returns_0_on_no_entries(): void {
		$this->assertEquals( 0, $this->entry_query->count() );
	}

	/**
	 * Test count honors the query args.
	 */
	public function test_count_honors_the_query_args(): void {
		$this->create_comment( [ 'comment_author_email' => 'baba@example.org' ] );
		$this->create_comment( [ 'comment_author_email' => 'dyado@example.org' ] );
		$this->assertEquals( 1, $this->entry_query->count( [ 'author_email' => 'baba@example.org' ] ) );
	}

	/**
	 * Create a comment.
	 *
	 * @param array $args Comment arguments.
	 * @return int Comment ID.
	 */
	private function create_comment( array $args = [] ): int {
		$defaults = [
			'comment_post_ID'  => $this->entry_query->post_id,
			'comment_approved' => $this->entry_query->key,
			'comment_type'     => $this->entry_query->key,
		];
		$args     = array_merge( $defaults, $args );
		return self::factory()->comment->create( $args );
	}

	/**
	 * Get IDs from entries.
	 *
	 * @param array $entries Array of entries.
	 * @return array Array of IDs.
	 */
	private function get_ids_from_entries( array $entries ): array {
		return array_values( array_map( [ self::class, 'get_entry_id' ], $entries ) );
	}

	/**
	 * Get entry ID.
	 *
	 * @param WPCOM_Liveblog_Entry $entry The entry.
	 * @return int Entry ID.
	 */
	public static function get_entry_id( WPCOM_Liveblog_Entry $entry ): int {
		return (int) $entry->get_id();
	}
}
