<?php
/**
 * Tests for the WPCOM_Liveblog_Entry_Key_Events class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_Entry_Key_Events;

/**
 * Entry Key Events test case.
 *
 * Tests the key event detection and meta sync functionality.
 */
final class EntryKeyEventsTest extends TestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->post_id = self::factory()->post->create();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		parent::tearDown();
	}

	/**
	 * Test render_key_template sets key_event true when content contains plain /key command.
	 */
	public function test_render_key_template_sets_key_event_true_for_plain_key_command(): void {
		$entry = $this->insert_entry( array( 'content' => 'Breaking news! /key' ) );

		$entry_data   = array( 'id' => $entry->get_id() );
		$result       = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertTrue( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event true when /key is at start of content.
	 */
	public function test_render_key_template_sets_key_event_true_for_key_at_start(): void {
		$entry = $this->insert_entry( array( 'content' => '/key This is important' ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertTrue( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event true when content contains transformed span.
	 */
	public function test_render_key_template_sets_key_event_true_for_transformed_span(): void {
		$content = 'Important update <span class="liveblog-command type-key">key</span>';
		$entry   = $this->insert_entry( array( 'content' => $content ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertTrue( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event true for span with multiple classes.
	 */
	public function test_render_key_template_sets_key_event_true_for_span_with_multiple_classes(): void {
		$content = '<span class="liveblog-command special type-key active">key</span> News';
		$entry   = $this->insert_entry( array( 'content' => $content ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertTrue( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event false when content has neither /key nor span.
	 */
	public function test_render_key_template_sets_key_event_false_when_no_key_command(): void {
		$entry = $this->insert_entry( array( 'content' => 'Regular entry content without key command' ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertFalse( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event false when /key is part of a word.
	 */
	public function test_render_key_template_sets_key_event_false_when_key_is_part_of_word(): void {
		$entry = $this->insert_entry( array( 'content' => 'This is a keyboard test' ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertFalse( $result['key_event'] );
	}

	/**
	 * Test render_key_template sets key_event false for /keyboard (key followed by word chars).
	 */
	public function test_render_key_template_sets_key_event_false_for_key_followed_by_word_chars(): void {
		$entry = $this->insert_entry( array( 'content' => 'Check out /keyboard layout' ) );

		$entry_data = array( 'id' => $entry->get_id() );
		$result     = WPCOM_Liveblog_Entry_Key_Events::render_key_template( $entry_data, $entry );

		$this->assertFalse( $result['key_event'] );
	}

	/**
	 * Test sync_key_event_meta adds meta when content has /key and comment does not have meta.
	 */
	public function test_sync_key_event_meta_adds_meta_when_content_has_key_without_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Important /key event',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		// Verify no meta exists initially.
		$this->assertFalse( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );

		// Trigger the sync.
		WPCOM_Liveblog_Entry_Key_Events::sync_key_event_meta( $comment->comment_ID, $this->post_id );

		// Verify meta was added.
		$this->assertTrue( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Test sync_key_event_meta removes meta when content has no /key and comment has meta.
	 */
	public function test_sync_key_event_meta_removes_meta_when_content_lacks_key_but_has_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Regular content without key command',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		// Add meta manually to simulate existing key event.
		add_comment_meta(
			$comment->comment_ID,
			WPCOM_Liveblog_Entry_Key_Events::META_KEY,
			WPCOM_Liveblog_Entry_Key_Events::META_VALUE
		);

		// Verify meta exists.
		$this->assertTrue( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );

		// Trigger the sync.
		WPCOM_Liveblog_Entry_Key_Events::sync_key_event_meta( $comment->comment_ID, $this->post_id );

		// Verify meta was removed.
		$this->assertFalse( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Test sync_key_event_meta does nothing when content has /key and meta already exists.
	 */
	public function test_sync_key_event_meta_does_nothing_when_key_and_meta_both_exist(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Important /key event',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		// Add meta manually.
		add_comment_meta(
			$comment->comment_ID,
			WPCOM_Liveblog_Entry_Key_Events::META_KEY,
			WPCOM_Liveblog_Entry_Key_Events::META_VALUE
		);

		// Trigger the sync.
		WPCOM_Liveblog_Entry_Key_Events::sync_key_event_meta( $comment->comment_ID, $this->post_id );

		// Verify meta still exists (not duplicated).
		$this->assertTrue( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
		$meta_values = get_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry_Key_Events::META_KEY, false );
		$this->assertCount( 1, $meta_values, 'Meta should not be duplicated' );
	}

	/**
	 * Test sync_key_event_meta does nothing when content lacks /key and meta does not exist.
	 */
	public function test_sync_key_event_meta_does_nothing_when_no_key_and_no_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Regular content without key',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		// Verify no meta exists.
		$this->assertFalse( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );

		// Trigger the sync.
		WPCOM_Liveblog_Entry_Key_Events::sync_key_event_meta( $comment->comment_ID, $this->post_id );

		// Verify still no meta.
		$this->assertFalse( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Test sync_key_event_meta handles transformed span content.
	 */
	public function test_sync_key_event_meta_adds_meta_for_transformed_span(): void {
		$content = '<span class="liveblog-command type-key">key</span> Important update';
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => $content,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		// Trigger the sync.
		WPCOM_Liveblog_Entry_Key_Events::sync_key_event_meta( $comment->comment_ID, $this->post_id );

		// Verify meta was added.
		$this->assertTrue( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Test is_key_event returns true when meta exists with correct value.
	 */
	public function test_is_key_event_returns_true_when_meta_exists(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		add_comment_meta(
			$comment->comment_ID,
			WPCOM_Liveblog_Entry_Key_Events::META_KEY,
			WPCOM_Liveblog_Entry_Key_Events::META_VALUE
		);

		$this->assertTrue( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Test is_key_event returns false when meta does not exist.
	 */
	public function test_is_key_event_returns_false_when_meta_absent(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$this->assertFalse( WPCOM_Liveblog_Entry_Key_Events::is_key_event( $comment->comment_ID ) );
	}

	/**
	 * Insert a liveblog entry.
	 *
	 * @param array $args Arguments for entry.
	 * @return WPCOM_Liveblog_Entry
	 */
	private function insert_entry( array $args = array() ): WPCOM_Liveblog_Entry {
		$user     = self::factory()->user->create_and_get();
		$defaults = array(
			'post_id' => $this->post_id,
			'content' => 'Test content',
			'user'    => $user,
		);
		$args     = array_merge( $defaults, $args );

		return WPCOM_Liveblog_Entry::insert( $args );
	}
}
