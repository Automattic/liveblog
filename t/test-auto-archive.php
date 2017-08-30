<?php
class Test_Auto_Archive extends WP_UnitTestCase {

	static function set_liveblog_hook_fired() {
		$GLOBALS['liveblog_hook_fired'] = true;
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
