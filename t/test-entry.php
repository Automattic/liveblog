<?php
class Test_Entry extends WP_UnitTestCase {

	function test_constructor_should_set_replace_if_there_is_replace_meta() {
		$comment = $this->create_and_get_comment_with_replaces( 5 );
		$entry = new WPCOM_Liveblog_Entry( $comment );
		$this->assertEquals( 5, $entry->replaces );
	}

	function test_constructor_should_set_replaces_to_false_if_no_replace_meta() {
		$comment = $this->factory->comment->create_and_get();
		$entry = new WPCOM_Liveblog_Entry( $comment );
		$this->assertTrue( !$entry->replaces );
	}

	function test_insert_should_return_entry() {
		$entry = $this->insert_entry();
		$this->assertInstanceOf( 'WPCOM_Liveblog_Entry', $entry );
	}

	function test_insert_should_fire_liveblog_insert_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_insert_entry', function() { $GLOBALS['liveblog_hook_fired'] = true; } );
		$this->insert_entry();
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	function test_update_should_replace_the_content_in_the_query() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update( $entry->get_id(), $this->build_entry_args( array( 'content' => 'updated' ) ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
	}

	function test_update_should_fire_liveblog_update_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_update_entry', function() { $GLOBALS['liveblog_hook_fired'] = true; } );
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update( $entry->get_id(), $this->build_entry_args( array( 'content' => 'updated' ) ) );
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	function test_delete_should_replace_the_content_in_the_query() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $entry->get_id(), $this->build_entry_args( array() ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
		$this->assertEquals( '', $update_entry->render() );
	}

	private function insert_entry( $args = array() ) {
		$entry = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		return $entry;
	}

	private function build_entry_args( $args = array() ) {
		$user = $this->factory->user->create_and_get();
		$defaults = array( 'post_id' => 1, 'content' => 'baba', 'user' => $user, 'ip' => '127.0.0.1', 'user_agent' => 'phpunit'  );
		return array_merge( $defaults, $args );
	}

	private function create_and_get_comment_with_replaces( $replaces, $args = array() ) {
		$comment = $this->factory->comment->create_and_get( $args );
		add_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry::replaces_meta_key, $replaces );
		return $comment;
	}
}
