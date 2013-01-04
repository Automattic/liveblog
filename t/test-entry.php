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

	function test_insert_should_return_entry_with_type_new() {
		$entry = $this->insert_entry();
		$this->assertEquals( 'new', $entry->get_type() );
	}

	function test_insert_should_fire_liveblog_insert_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_insert_entry', function() { $GLOBALS['liveblog_hook_fired'] = true; } );
		$this->insert_entry();
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	function test_update_should_replace_the_content_in_the_query() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update( $this->build_entry_args( array( 'entry_id' => $entry->get_id(), 'content' => 'updated' ) ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
	}

	function test_update_should_return_entry_with_type_update() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update( $this->build_entry_args( array( 'entry_id' => $entry->get_id(), 'content' => 'updated' ) ) );
		$this->assertEquals( 'update', $update_entry->get_type() );
	}

	function test_update_should_fire_liveblog_update_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_update_entry', function() { $GLOBALS['liveblog_hook_fired'] = true; } );
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update( $this->build_entry_args( array( 'entry_id' => $entry->get_id(), 'content' => 'updated' ) ) );
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	function test_update_should_update_original_entry() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update( $this->build_entry_args( array( 'entry_id' => $entry->get_id(), 'content' => 'updated' ) ) );
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertEquals( 'updated', $query->get_by_id( $entry->get_id() )->get_content() );
	}

	function test_delete_should_replace_the_content_in_the_query() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id()) ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
		$this->assertEquals( '', $update_entry->get_content() );
	}

	function test_delete_should_return_entry_with_type_delete() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$this->assertEquals( 'delete', $update_entry->get_type() );
	}

	function test_delete_should_delete_original_entry() {
		$entry = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertNull( $query->get_by_id( $entry->get_id() ) );
	}

	private function insert_entry( $args = array() ) {
		$entry = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		return $entry;
	}

	private function build_entry_args( $args = array() ) {
		$user = $this->factory->user->create_and_get();
		$defaults = array( 'post_id' => 1, 'content' => 'baba', 'user' => $user, );
		return array_merge( $defaults, $args );
	}

	private function create_and_get_comment_with_replaces( $replaces, $args = array() ) {
		$comment = $this->factory->comment->create_and_get( $args );
		add_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry::replaces_meta_key, $replaces );
		return $comment;
	}
}
