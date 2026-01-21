<?php
/**
 * Tests for the KeyEventService class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Service\KeyEventService;

/**
 * Key Event Service test case.
 *
 * Tests the key event detection and meta sync functionality.
 *
 * @covers \Automattic\Liveblog\Application\Service\KeyEventService
 */
final class EntryKeyEventsTest extends IntegrationTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * The key event service.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $service;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->post_id = self::factory()->post->create();
		$this->service = $this->container()->key_event_service();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		$this->reset_container();
		parent::tearDown();
	}

	/**
	 * Test content_has_key_command returns true for plain /key command.
	 */
	public function test_content_has_key_command_true_for_plain_key(): void {
		$this->assertTrue( $this->service->content_has_key_command( 'Breaking news! /key' ) );
	}

	/**
	 * Test content_has_key_command returns true for /key at start.
	 */
	public function test_content_has_key_command_true_for_key_at_start(): void {
		$this->assertTrue( $this->service->content_has_key_command( '/key This is important' ) );
	}

	/**
	 * Test content_has_key_command returns true for transformed span.
	 */
	public function test_content_has_key_command_true_for_transformed_span(): void {
		$content = 'Important update <span class="liveblog-command type-key">key</span>';
		$this->assertTrue( $this->service->content_has_key_command( $content ) );
	}

	/**
	 * Test content_has_key_command returns true for span with multiple classes.
	 */
	public function test_content_has_key_command_true_for_span_with_multiple_classes(): void {
		$content = '<span class="liveblog-command special type-key active">key</span> News';
		$this->assertTrue( $this->service->content_has_key_command( $content ) );
	}

	/**
	 * Test content_has_key_command returns false when no key command.
	 */
	public function test_content_has_key_command_false_when_no_key(): void {
		$this->assertFalse( $this->service->content_has_key_command( 'Regular entry content' ) );
	}

	/**
	 * Test content_has_key_command returns false when /key is part of word.
	 */
	public function test_content_has_key_command_false_when_key_part_of_word(): void {
		$this->assertFalse( $this->service->content_has_key_command( 'This is a keyboard test' ) );
	}

	/**
	 * Test content_has_key_command returns false for /keyboard.
	 */
	public function test_content_has_key_command_false_for_keyboard(): void {
		$this->assertFalse( $this->service->content_has_key_command( 'Check out /keyboard layout' ) );
	}

	/**
	 * Test sync_key_event_meta adds meta when content has /key and no meta exists.
	 */
	public function test_sync_key_event_meta_adds_meta_when_key_without_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Important /key event',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$comment_id = (int) $comment->comment_ID;

		// Verify no meta exists initially.
		$this->assertFalse( $this->service->is_key_event( $comment_id ) );

		// Trigger the sync.
		$this->service->sync_key_event_meta( $comment_id, $this->post_id );

		// Verify meta was added.
		$this->assertTrue( $this->service->is_key_event( $comment_id ) );
	}

	/**
	 * Test sync_key_event_meta removes meta when content lacks /key but has meta.
	 */
	public function test_sync_key_event_meta_removes_meta_when_no_key_but_has_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Regular content without key command',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$comment_id = (int) $comment->comment_ID;

		// Add meta manually to simulate existing key event.
		add_comment_meta(
			$comment_id,
			KeyEventService::META_KEY,
			KeyEventService::META_VALUE
		);

		// Verify meta exists.
		$this->assertTrue( $this->service->is_key_event( $comment_id ) );

		// Trigger the sync.
		$this->service->sync_key_event_meta( $comment_id, $this->post_id );

		// Verify meta was removed.
		$this->assertFalse( $this->service->is_key_event( $comment_id ) );
	}

	/**
	 * Test sync_key_event_meta does nothing when key and meta both exist.
	 */
	public function test_sync_key_event_meta_does_nothing_when_key_and_meta_exist(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Important /key event',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$comment_id = (int) $comment->comment_ID;

		// Add meta manually.
		add_comment_meta(
			$comment_id,
			KeyEventService::META_KEY,
			KeyEventService::META_VALUE
		);

		// Trigger the sync.
		$this->service->sync_key_event_meta( $comment_id, $this->post_id );

		// Verify meta still exists (not duplicated).
		$this->assertTrue( $this->service->is_key_event( $comment_id ) );
		$meta_values = get_comment_meta( $comment_id, KeyEventService::META_KEY, false );
		$this->assertCount( 1, $meta_values, 'Meta should not be duplicated' );
	}

	/**
	 * Test sync_key_event_meta does nothing when no key and no meta.
	 */
	public function test_sync_key_event_meta_does_nothing_when_no_key_no_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Regular content without key',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$comment_id = (int) $comment->comment_ID;

		// Verify no meta exists.
		$this->assertFalse( $this->service->is_key_event( $comment_id ) );

		// Trigger the sync.
		$this->service->sync_key_event_meta( $comment_id, $this->post_id );

		// Verify still no meta.
		$this->assertFalse( $this->service->is_key_event( $comment_id ) );
	}

	/**
	 * Test sync_key_event_meta handles transformed span content.
	 */
	public function test_sync_key_event_meta_handles_transformed_span(): void {
		$content = '<span class="liveblog-command type-key">key</span> Important update';
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => $content,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$comment_id = (int) $comment->comment_ID;

		// Trigger the sync.
		$this->service->sync_key_event_meta( $comment_id, $this->post_id );

		// Verify meta was added.
		$this->assertTrue( $this->service->is_key_event( $comment_id ) );
	}

	/**
	 * Test is_key_event returns true when meta exists.
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
			KeyEventService::META_KEY,
			KeyEventService::META_VALUE
		);

		$this->assertTrue( $this->service->is_key_event( (int) $comment->comment_ID ) );
	}

	/**
	 * Test is_key_event returns false when meta absent.
	 */
	public function test_is_key_event_returns_false_when_meta_absent(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$this->assertFalse( $this->service->is_key_event( (int) $comment->comment_ID ) );
	}

	/**
	 * Test mark_as_key_event adds meta.
	 */
	public function test_mark_as_key_event_adds_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$this->assertFalse( $this->service->is_key_event( (int) $comment->comment_ID ) );

		$this->service->mark_as_key_event( (int) $comment->comment_ID );

		$this->assertTrue( $this->service->is_key_event( (int) $comment->comment_ID ) );
	}

	/**
	 * Test remove_key_event removes meta.
	 */
	public function test_remove_key_event_removes_meta(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$this->service->mark_as_key_event( (int) $comment->comment_ID );
		$this->assertTrue( $this->service->is_key_event( (int) $comment->comment_ID ) );

		$this->service->remove_key_event( (int) $comment->comment_ID );
		$this->assertFalse( $this->service->is_key_event( (int) $comment->comment_ID ) );
	}

	/**
	 * Test remove_key_action removes meta and strips /key from content.
	 */
	public function test_remove_key_action_removes_meta_and_strips_key(): void {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Important /key event',
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
			)
		);

		$this->service->mark_as_key_event( (int) $comment->comment_ID );
		$this->assertTrue( $this->service->is_key_event( (int) $comment->comment_ID ) );

		$result = $this->service->remove_key_action( 'Important /key event', (int) $comment->comment_ID );

		$this->assertFalse( $this->service->is_key_event( (int) $comment->comment_ID ) );
		$this->assertSame( 'Important  event', $result );
	}
}
