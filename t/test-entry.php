<?php
class Test_Entry extends WP_UnitTestCase {

	public function test_constructor_should_set_replace_if_there_is_replace_meta() {
		$comment = $this->create_and_get_comment_with_replaces( 5 );
		$entry   = new WPCOM_Liveblog_Entry( $comment );
		$this->assertEquals( 5, $entry->replaces );
	}

	public function test_constructor_should_set_replaces_to_false_if_no_replace_meta() {
		$comment = $this->factory->comment->create_and_get();
		$entry   = new WPCOM_Liveblog_Entry( $comment );
		$this->assertTrue( ! $entry->replaces );
	}

	public function test_insert_should_return_entry() {
		$entry = $this->insert_entry();
		$this->assertInstanceOf( 'WPCOM_Liveblog_Entry', $entry );
	}

	public function test_insert_should_return_entry_with_type_new() {
		$entry = $this->insert_entry();
		$this->assertEquals( 'new', $entry->get_type() );
	}

	public function test_insert_should_fire_liveblog_insert_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_insert_entry', array( __CLASS__, 'set_liveblog_hook_fired' ) );
		$this->insert_entry();
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	public function test_update_should_replace_the_content_in_the_query() {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
	}

	public function test_update_should_return_entry_with_type_update() {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertEquals( 'update', $update_entry->get_type() );
	}

	public function test_update_should_fire_liveblog_update_entry() {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_update_entry', array( __CLASS__, 'set_liveblog_hook_fired' ) );
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	public function test_update_should_update_original_entry() {
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertEquals( 'updated', $query->get_by_id( $entry->get_id() )->get_content() );
	}

	public function test_delete_should_replace_the_content_in_the_query() {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
		$this->assertEquals( '', $update_entry->get_content() );
	}

	public function test_delete_should_return_entry_with_type_delete() {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$this->assertEquals( 'delete', $update_entry->get_type() );
	}

	public function test_delete_should_delete_original_entry() {
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertNull( $query->get_by_id( $entry->get_id() ) );
	}

	public function test_user_input_sanity_check() {
		$user_input      = '<iframe></iframe>';
		$user_input     .= '<script></script>';
		$user_input     .= '<applet></applet>';
		$user_input     .= '<embed></embed>';
		$user_input     .= '<object></object>';
		$content         = array(
			'post_id' => 1,
			'content' => $user_input,
		);
		$live_blog_entry = $this->insert_entry( $content );
		$this->assertEmpty( $live_blog_entry->get_content() );
	}

	/**
	 * test_shortcode_excluded_from_entry
	 *
	 * Test to ensure that all [shortcode] formats are stripped.
	 * Uses the default exclusion [liveblog_key_events] which should
	 * be replaced with "We Are Blogging Live! Check Out The Key Events in The Sidebar"
	 * if successful.
	 *
	 * @author  Olly Warren, Big Bite Creative
	 * @package WPCOM_Liveblog
	 */
	public function test_shortcode_excluded_from_entry() {

		// Insert a new entries with a shortcode body content to test each type of shortcode format.
		$formats = array(
			'[liveblog_key_events]',
			'[liveblog_key_events][/liveblog_key_events]',
			'[liveblog_key_events arg="30"]',
			'[liveblog_key_events arg="30"][/liveblog_key_events]',
			'[liveblog_key_events]Test Input Inbetween Tags[/liveblog_key_events]',
			'[liveblog_key_events arg="30"]Test Input Inbetween Tags[/liveblog_key_events]',
		);

		// Loop through each format and create a new comment to check if it gets stripped before hitting the DB.
		foreach ( $formats as $shortcode ) {

			// Create a new entry.
			$entry = $this->insert_entry(
				array(
					'content' => $shortcode,
				)
			);

			// Lets setup a Reflection class so we can access the private object properties and check our comment body.
			$comment = new ReflectionProperty( $entry, 'comment' );
			$comment->setAccessible( true );
			$comment_content = $comment->getValue( $entry );

			// Define a check varibale and see if the returned object content has been set as the default string replacement.
			$check = '' === $comment_content->comment_content;

			//Assert we have a match. If we do then the shortcode was successfully stripped.
			$this->assertTrue( $check );
		}
	}

	public static function set_liveblog_hook_fired() {
		$GLOBALS['liveblog_hook_fired'] = true;
	}

	private function insert_entry( $args = array() ) {
		$entry = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		return $entry;
	}

	private function build_entry_args( $args = array() ) {
		$user     = $this->factory->user->create_and_get();
		$defaults = array(
			'post_id' => 1,
			'content' => 'baba',
			'user'    => $user,
		);
		return array_merge( $defaults, $args );
	}

	private function create_and_get_comment_with_replaces( $replaces, $args = array() ) {
		$comment = $this->factory->comment->create_and_get( $args );
		add_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry::REPLACES_META_KEY, $replaces );
		return $comment;
	}


}
