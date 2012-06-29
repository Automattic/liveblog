<?php

class Test_Entries extends WP_UnitTestCase {
	var $plugin_slug = 'liveblog';

	const JAN_1_TIMESTAMP = 1325376000;
	const JAN_1_MYSQL = '2012-01-01 00:00:00';

	const JAN_2_TIMESTAMP = 1325462400;
	const JAN_2_MYSQL = '2012-01-02 00:00:00';

	function setUp() {
		parent::setUp();
		wp_delete_comment( 1, true );
		$this->entries = new WPCOM_Liveblog_Entries( 5, 'baba' );
	}

	function test_get_latest_should_return_null_if_no_comments() {
		$this->assertNull( $this->entries->get_latest() );
	}

	function test_get_latest_should_return_the_only_comment_if_one() {
		$id = $this->create_comment();
		$latest_entry = $this->entries->get_latest();
		$this->assertEquals( $id, $latest_entry->comment_ID );
	}

	function test_get_latest_should_return_the_latest_comment_if_more_than_one() {
		$id_first = $this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) );
		$id_second = $this->create_comment( array( 'comment_date_gmt' => self::JAN_2_MYSQL ) );
		$latest_entry = $this->entries->get_latest();
		$this->assertEquals( $id_second, $latest_entry->comment_ID );
	}

	function test_get_latest_timestamp_should_properly_convert_to_unix_timestamp() {
		$this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL) );
		$this->assertEquals( self::JAN_1_TIMESTAMP, $this->entries->get_latest_timestamp() );
	}

	function test_get_between_timestamps_should_return_an_entry_between_two_timestamps() {
		$id_first = $this->create_comment( array( 'comment_date_gmt' => self::JAN_1_MYSQL ) );
		$id_second = $this->create_comment( array( 'comment_date_gmt' => self::JAN_2_MYSQL ) );
		$entries = $this->entries->get_between_timestamps( self::JAN_1_TIMESTAMP - 10, self::JAN_2_TIMESTAMP + 10 );
		$this->assertEquals( 2, count( $entries )  );
		$ids = wp_list_pluck( $entries, 'comment_ID' );
		$this->assertContains( $id_first, $ids );
		$this->assertContains( $id_second, $ids );
	}

	function test_get_only_matches_comments_with_the_key_as_approved_status() {
		$id = $this->create_comment( array( 'comment_approved' => 'wink' ) );
		$entries = $this->entries->get();
		$this->assertEquals( 0, count( $entries ) );
	}

	private function create_comment( $args = array() ) {
		static $number = 0;
		$number++;
		$defaults = array(
			'comment_post_ID' => $this->entries->post_id,
			'comment_content' => 'Comment Text ' . $number,
			'comment_approved' => $this->entries->key,
			'comment_type' => $this->entries->key,
			'user_id' => 1,
			'comment_author' => 'Baba',
			'comment_author_email' => 'baba@baba.net',
		);
		$args = array_merge( $defaults, $args );
		// TODO: addslashes deep
		return wp_insert_comment( $args );
	}

}
