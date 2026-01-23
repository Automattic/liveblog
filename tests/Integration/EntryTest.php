<?php
/**
 * Tests for liveblog entry operations using domain services.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Service\ContentProcessor;
use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Application\Service\ShortcodeFilter;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryType;

/**
 * Entry test case.
 */
final class EntryTest extends IntegrationTestCase {

	/**
	 * Entry service.
	 *
	 * @var EntryService
	 */
	private EntryService $entry_service;

	/**
	 * Entry repository.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * Content processor.
	 *
	 * @var ContentProcessor
	 */
	private ContentProcessor $content_processor;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$container               = $this->container();
		$this->entry_service     = $container->entry_service();
		$this->repository        = $container->entry_repository();
		$this->content_processor = $container->content_processor();
	}

	/**
	 * Test that entry is created with type 'new'.
	 */
	public function test_insert_should_return_entry_with_type_new(): void {
		$entry = $this->insert_entry();
		$this->assertTrue( $entry->type()->is_new() );
		$this->assertSame( 'new', $entry->type()->value );
	}

	/**
	 * Test that update replaces the original entry.
	 */
	public function test_update_should_replace_the_original_entry(): void {
		$entry     = $this->insert_entry();
		$update_id = $this->entry_service->update(
			1,
			$entry->id(),
			'updated content',
			$this->create_user()
		);
		$updated = $this->repository->get_entry( $update_id );
		$this->assertNotNull( $updated->replaces() );
		$this->assertSame( $entry->id()->to_int(), $updated->replaces()->to_int() );
	}

	/**
	 * Test that update returns entry with type update.
	 */
	public function test_update_should_return_entry_with_type_update(): void {
		$entry     = $this->insert_entry();
		$update_id = $this->entry_service->update(
			1,
			$entry->id(),
			'updated content',
			$this->create_user()
		);
		$updated = $this->repository->get_entry( $update_id );
		$this->assertTrue( $updated->type()->is_update() );
		$this->assertSame( 'update', $updated->type()->value );
	}

	/**
	 * Test that update updates the original entry content.
	 */
	public function test_update_should_update_original_entry(): void {
		$entry = $this->insert_entry();
		$this->entry_service->update( 1, $entry->id(), 'updated', $this->create_user() );
		$updated = $this->repository->get_entry( $entry->id() );
		$this->assertSame( 'updated', $updated->content()->raw() );
	}

	/**
	 * Test that delete replaces the original entry.
	 */
	public function test_delete_should_replace_the_original_entry(): void {
		$entry     = $this->insert_entry();
		$delete_id = $this->entry_service->delete( 1, $entry->id(), $this->create_user() );
		$deleted   = $this->repository->get_entry( $delete_id );
		$this->assertNotNull( $deleted->replaces() );
		$this->assertSame( $entry->id()->to_int(), $deleted->replaces()->to_int() );
		$this->assertSame( '', $deleted->content()->raw() );
	}

	/**
	 * Test that delete returns entry with type delete.
	 */
	public function test_delete_should_return_entry_with_type_delete(): void {
		$entry     = $this->insert_entry();
		$delete_id = $this->entry_service->delete( 1, $entry->id(), $this->create_user() );
		$deleted   = $this->repository->get_entry( $delete_id );
		$this->assertTrue( $deleted->type()->is_delete() );
		$this->assertSame( 'delete', $deleted->type()->value );
	}

	/**
	 * Test that delete removes entry from query results.
	 */
	public function test_delete_should_delete_original_entry(): void {
		$entry = $this->insert_entry();
		$this->entry_service->delete( 1, $entry->id(), $this->create_user() );
		$query_service = $this->container()->entry_query_service();
		// After delete, the entry should be filtered out of the get_all results.
		$entries = $query_service->get_all( 1 );
		$ids     = array_map(
			fn( Entry $e ) => $e->id()->to_int(),
			$entries
		);
		$this->assertNotContains( $entry->id()->to_int(), $ids );
	}

	/**
	 * Test that dangerous script tags are stripped by wp_filter_post_kses().
	 */
	public function test_user_input_sanity_check(): void {
		// Test that dangerous script tags are stripped by wp_filter_post_kses()
		// Note: embed and object tags are allowed in WordPress 'post' context.
		$user_input  = '<script>alert("xss")</script>';
		$user_input .= '<applet code="malicious"></applet>';
		$user_input .= '<form><input name="test"></form>';
		$entry       = $this->insert_entry( array( 'content' => $user_input ) );
		// Content should be empty or significantly sanitized (scripts/applets/forms removed).
		$sanitized_content = $entry->content()->raw();
		$this->assertStringNotContainsString( '<script', $sanitized_content );
		$this->assertStringNotContainsString( '<applet', $sanitized_content );
		$this->assertStringNotContainsString( '<form', $sanitized_content );
	}

	/**
	 * Test that shortcode filter strips restricted shortcodes.
	 *
	 * This tests the ShortcodeFilter service directly.
	 * In production, this filter is applied via WordPress hooks.
	 */
	public function test_shortcode_filter_strips_restricted_shortcodes(): void {
		$shortcode_filter = new ShortcodeFilter();
		$formats          = array(
			'[liveblog_key_events]',
			'[liveblog_key_events][/liveblog_key_events]',
			'[liveblog_key_events arg="30"]',
			'[liveblog_key_events arg="30"][/liveblog_key_events]',
			'[liveblog_key_events]Test Input Inbetween Tags[/liveblog_key_events]',
			'[liveblog_key_events arg="30"]Test Input Inbetween Tags[/liveblog_key_events]',
		);

		foreach ( $formats as $shortcode ) {
			$args     = array( 'content' => $shortcode );
			$filtered = $shortcode_filter->filter( $args );
			$this->assertSame( '', $filtered['content'] );
		}
	}

	/**
	 * Insert a liveblog entry using domain service.
	 *
	 * @param array $args Arguments for entry.
	 * @return Entry The created entry.
	 */
	private function insert_entry( array $args = array() ): Entry {
		$user    = $args['user'] ?? $this->create_user();
		$post_id = $args['post_id'] ?? 1;
		$content = $args['content'] ?? 'baba';

		$entry_id = $this->entry_service->create( $post_id, $content, $user );
		return $this->repository->get_entry( $entry_id );
	}

	/**
	 * Create a test user.
	 *
	 * @return \WP_User
	 */
	private function create_user(): \WP_User {
		return self::factory()->user->create_and_get();
	}

	/**
	 * Test that filter_image_attributes preserves only src and alt by default.
	 */
	public function test_filter_image_attributes_default(): void {
		// Remove any filters added by the plugin (e.g., emoji image attribute filter).
		remove_all_filters( 'liveblog_image_allowed_attributes' );

		$content  = '<p>Text</p><img src="test.jpg" alt="Test" class="wp-image" width="100" height="50" data-id="123">';
		$filtered = $this->content_processor->filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );
		$this->assertStringNotContainsString( 'width=', $filtered );
		$this->assertStringNotContainsString( 'height=', $filtered );
		$this->assertStringNotContainsString( 'data-id=', $filtered );
		$this->assertStringContainsString( '<p>Text</p>', $filtered );
	}

	/**
	 * Test that filter_image_attributes allows additional attributes via filter.
	 */
	public function test_filter_image_attributes_with_filter(): void {
		add_filter(
			'liveblog_image_allowed_attributes',
			function ( $attrs ) {
				return array_merge( $attrs, array( 'class', 'width', 'height' ) );
			}
		);

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" width="100" height="50" data-id="123">';
		$filtered = $this->content_processor->filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringContainsString( 'class="wp-image"', $filtered );
		$this->assertStringContainsString( 'width="100"', $filtered );
		$this->assertStringContainsString( 'height="50"', $filtered );
		$this->assertStringNotContainsString( 'data-id=', $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes supports wildcard patterns.
	 */
	public function test_filter_image_attributes_with_wildcard_pattern(): void {
		add_filter(
			'liveblog_image_allowed_attributes',
			function () {
				return array( 'src', 'alt', 'data-*' );
			}
		);

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" data-id="123" data-size="large">';
		$filtered = $this->content_processor->filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringContainsString( 'data-id="123"', $filtered );
		$this->assertStringContainsString( 'data-size="large"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes allows all attributes with wildcard.
	 */
	public function test_filter_image_attributes_allow_all(): void {
		add_filter( 'liveblog_image_allowed_attributes', fn() => array( '*' ) );

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" width="100" data-id="123">';
		$filtered = $this->content_processor->filter_image_attributes( $content );

		// Content should be unchanged.
		$this->assertSame( $content, $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes handles multiple images.
	 */
	public function test_filter_image_attributes_multiple_images(): void {
		// Remove any filters added by the plugin (e.g., emoji image attribute filter).
		remove_all_filters( 'liveblog_image_allowed_attributes' );

		$content  = '<img src="one.jpg" alt="One" class="first"><p>Text</p><img src="two.jpg" alt="Two" width="200">';
		$filtered = $this->content_processor->filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="one.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="One"', $filtered );
		$this->assertStringContainsString( 'src="two.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Two"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );
		$this->assertStringNotContainsString( 'width=', $filtered );
	}

	/**
	 * Test entry replaces property is set from meta.
	 */
	public function test_entry_replaces_is_set_from_meta(): void {
		// Create original entry.
		$original = $this->insert_entry();
		// Update creates a new entry that replaces the original.
		$update_id = $this->entry_service->update(
			1,
			$original->id(),
			'updated content',
			$this->create_user()
		);
		$updated = $this->repository->get_entry( $update_id );
		$this->assertNotNull( $updated->replaces() );
		$this->assertSame( $original->id()->to_int(), $updated->replaces()->to_int() );
	}

	/**
	 * Test entry replaces is null for new entries.
	 */
	public function test_entry_replaces_is_null_for_new_entries(): void {
		$entry = $this->insert_entry();
		$this->assertNull( $entry->replaces() );
	}
}
