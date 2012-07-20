<?php
class Test_Entry extends WP_UnitTestCase {

	function test_constructor_should_set_replace_if_there_is_replace_meta_and_comment_has_text() {
		$comment = $this->create_and_get_comment_with_replaces( 5 );
		$entry = new WPCOM_Liveblog_Entry( $comment );
		$this->assertEquals( 5, $entry->replaces );
	}

	function test_constructor_should_set_deletes_if_there_is_replace_meta_and_comment_is_empty() {
		$comment = $this->create_and_get_comment_with_replaces( 5, array( 'comment_content' => '' ) );
		$entry = new WPCOM_Liveblog_Entry( $comment );
		$this->assertEquals( 5, $entry->deletes );
	}

	function test_render_should_not_render_deletes() {
		$entry = new WPCOM_Liveblog_Entry( $this->factory->comment->create_and_get() );
		$entry->deletes = 5;
		$this->assertEquals( '', $entry->render() );
	}

	private function create_and_get_comment_with_replaces( $replaces, $args = array() ) {
		$comment = $this->factory->comment->create_and_get( $args );
		add_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry::replaces_meta_key, $replaces );
		return $comment;
	}
}
