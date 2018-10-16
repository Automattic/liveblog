<?php

class Test_Entry_Query extends WP_UnitTestCase {
	const JAN_1_TIMESTAMP = 1325376000;
	const JAN_1_MYSQL     = '2012-01-01 00:00:00';

	const JAN_2_TIMESTAMP = 1325462400;
	const JAN_2_MYSQL     = '2012-01-02 00:00:00';

	public function setUp() {
		parent::setUp();
		wp_delete_comment( 1, true );
		$this->entry_query = new WPCOM_Liveblog_Entry_Query( 5, 'baba' );
	}

	public function test_get_latest_should_return_null_if_no_comments() {
		$this->assertNull( $this->entry_query->get_latest() );
	}

	public function test_get_latest_should_return_the_only_comment_if_one() {
		$id           = $this->create_comment();
		$latest_entry = $this->entry_query->get_latest();
		$this->assertEquals( $id, $latest_entry->get_id() );
	}

	public function test_get_latest_should_return_the_latest_comment_if_more_than_one() {
		$id_first     = $this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) ); // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
		$id_second    = $this->create_comment( array( 'comment_date_gmt' => self::JAN_2_MYSQL ) );
		$latest_entry = $this->entry_query->get_latest();
		$this->assertEquals( $id_second, $latest_entry->get_id() );
	}

	public function test_get_latest_timestamp_should_properly_convert_to_unix_timestamp() {
		$this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) );
		$this->assertEquals( self::JAN_1_TIMESTAMP, $this->entry_query->get_latest_timestamp() );
	}

	public function test_get_between_timestamps_should_return_an_entry_between_two_timestamps() {
		$id_first  = $this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) );
		$id_second = $this->create_comment( array( 'comment_date_gmt' => self::JAN_2_MYSQL ) );
		$entries   = $this->entry_query->get_between_timestamps( self::JAN_1_TIMESTAMP - 10, self::JAN_2_TIMESTAMP + 10 );
		$this->assertEquals( 2, count( $entries ) );
		$ids = $this->get_ids_from_entries( $entries );
		$this->assertContains( $id_first, $ids );
		$this->assertContains( $id_second, $ids );
	}

	public function test_get_between_timestamps_should_return_entries_on_the_border() {
		$id      = $this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) );
		$entries = $this->entry_query->get_between_timestamps( self::JAN_1_TIMESTAMP, self::JAN_1_TIMESTAMP + 1 );
		$ids     = $this->get_ids_from_entries( $entries );
		$this->assertEquals( array( $id ), $ids );
	}

	public function test_get_only_matches_comments_with_the_key_as_approved_status() {
		$this->create_comment( array( 'comment_approved' => 'wink' ) );
		$entries = $this->entry_query->get_all();
		$this->assertEquals( 0, count( $entries ) );
	}

	public function test_remove_replaced_entries_should_remove_entries_replacing_other_entries() {
		$entries = array();

		$entries[0] = new WPCOM_Liveblog_Entry( (object) array( 'comment_ID' => 1 ) );

		$entries[1]           = new WPCOM_Liveblog_Entry( (object) array( 'comment_ID' => 1000 ) );
		$entries[1]->replaces = 1;

		$filtered_entries = WPCOM_Liveblog_Entry_Query::remove_replaced_entries( $entries );
		$this->assertEquals( array( 1 ), $this->get_ids_from_entries( $filtered_entries ) );
	}

	public function test_remove_replaced_entries_should_not_remove_entries_replacing_non_existing_entries() {
		$entries = array();

		$entries[0] = new WPCOM_Liveblog_Entry( (object) array( 'comment_ID' => 1 ) );

		$entries[1]           = new WPCOM_Liveblog_Entry( (object) array( 'comment_ID' => 1000 ) );
		$entries[1]->replaces = 999;

		$filtered_entries = WPCOM_Liveblog_Entry_Query::remove_replaced_entries( $entries );
		$this->assertEquals( array( 1, 1000 ), $this->get_ids_from_entries( $filtered_entries ) );
	}

	public function test_get_by_id_should_return_the_entry() {
		$comment_id = $this->create_comment();
		$this->assertEquals( $comment_id, $this->entry_query->get_by_id( $comment_id )->get_id() );
	}

	public function test_get_by_id_should_not_return_entries_for_trashed_comments() {
		$comment_id = $this->create_comment();
		wp_delete_comment( $comment_id );
		$this->assertNull( $this->entry_query->get_by_id( $comment_id ) );
	}

	public function test_has_any_returns_false_if_no_entries() {
		$this->assertFalse( $this->entry_query->has_any() );
	}

	public function test_has_any_returns_true_if_we_add_some_entries() {
		$this->create_comment();
		$this->assertTrue( $this->entry_query->has_any() );
	}

	public function test_count_counts_all_entries() {
		$this->create_comment();
		$this->create_comment();
		$this->assertEquals( 2, $this->entry_query->count() );
	}

	public function test_count_returns_0_on_no_entries() {
		$this->assertEquals( 0, $this->entry_query->count() );
	}

	public function test_count_honors_the_query_args() {
		$this->create_comment( array( 'comment_author_email' => 'baba@example.org' ) );
		$this->create_comment( array( 'comment_author_email' => 'dyado@example.org' ) );
		$this->assertEquals( 1, $this->entry_query->count( array( 'author_email' => 'baba@example.org' ) ) );
	}

	private function create_comment( $args = array() ) {
		$defaults = array(
			'comment_post_ID'  => $this->entry_query->post_id,
			'comment_approved' => $this->entry_query->key,
			'comment_type'     => $this->entry_query->key,
		);
		$args     = array_merge( $defaults, $args );
		return $this->factory->comment->create( $args );
	}

	private function get_ids_from_entries( $entries ) {
		return array_values( array_map( array( __CLASS__, 'get_entry_id' ), $entries ) );
	}

	public static function get_entry_id( $entry ) {
		return $entry->get_id();
	}
}
