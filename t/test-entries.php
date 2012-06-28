<?php

class Test_Entries extends WP_UnitTestCase {
	var $plugin_slug = 'liveblog';

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
		$id_first = $this->create_comment( array( 'comment_date_gmt' => '2012-01-01 00:00:00' ) );
		$id_second = $this->create_comment( array( 'comment_date_gmt' => '2012-01-02 00:00:00' ) );
		$latest_entry = $this->entries->get_latest();
		$this->assertEquals( $id_second, $latest_entry->comment_ID );
	}

	function test_get_latest_timestamp_should_properly_convert_to_unix_timestamp() {
		$this->create_comment( array( 'comment_date_gmt' => '2012-01-01 00:00:00' ) );
		$this->assertEquals( 1325376000, $this->entries->get_latest_timestamp() );
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
