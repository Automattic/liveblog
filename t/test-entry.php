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
