<?php
/**
 * Tests for Entry entity.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\Entity
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\Entity;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\ValueObject\Author;
use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Automattic\Liveblog\Domain\ValueObject\EntryContent;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests for the Entry entity.
 *
 * @covers \Automattic\Liveblog\Domain\Entity\Entry
 */
final class EntryTest extends TestCase {

	/**
	 * Test creating a new entry.
	 */
	public function test_create_new_entry(): void {
		$id         = EntryId::from_int( 123 );
		$post_id    = 456;
		$content    = EntryContent::from_raw( 'Test content' );
		$authors    = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Test Author',
				) 
			)
		);
		$created_at = new DateTimeImmutable( '2024-01-15 10:30:00', new DateTimeZone( 'UTC' ) );

		$entry = Entry::create( $id, $post_id, $content, $authors, null, $created_at );

		$this->assertSame( 123, $entry->id()->to_int() );
		$this->assertSame( 456, $entry->post_id() );
		$this->assertSame( 'Test content', $entry->content()->raw() );
		$this->assertTrue( $entry->type()->is_new() );
		$this->assertFalse( $entry->authors()->is_empty() );
		$this->assertNull( $entry->replaces() );
		$this->assertSame( $created_at, $entry->created_at() );
	}

	/**
	 * Test creating an update entry.
	 */
	public function test_create_update_entry(): void {
		$id         = EntryId::from_int( 124 );
		$replaces   = EntryId::from_int( 123 );
		$content    = EntryContent::from_raw( 'Updated content' );
		$authors    = AuthorCollection::empty();
		$created_at = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$entry = Entry::create( $id, 456, $content, $authors, $replaces, $created_at );

		$this->assertTrue( $entry->type()->is_update() );
		$this->assertFalse( $entry->type()->is_new() );
		$this->assertFalse( $entry->type()->is_delete() );
		$this->assertSame( 123, $entry->replaces()->to_int() );
	}

	/**
	 * Test creating a delete entry.
	 */
	public function test_create_delete_entry(): void {
		$id         = EntryId::from_int( 125 );
		$replaces   = EntryId::from_int( 123 );
		$content    = EntryContent::empty();
		$authors    = AuthorCollection::empty();
		$created_at = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$entry = Entry::create( $id, 456, $content, $authors, $replaces, $created_at );

		$this->assertTrue( $entry->type()->is_delete() );
		$this->assertFalse( $entry->type()->is_new() );
		$this->assertFalse( $entry->type()->is_update() );
	}

	/**
	 * Test is_new helper.
	 */
	public function test_is_new(): void {
		$entry = $this->create_entry();

		$this->assertTrue( $entry->is_new() );
		$this->assertFalse( $entry->is_update() );
		$this->assertFalse( $entry->is_delete() );
	}

	/**
	 * Test is_update helper.
	 */
	public function test_is_update(): void {
		$entry = $this->create_entry( null, null, null, null, EntryId::from_int( 100 ) );

		$this->assertFalse( $entry->is_new() );
		$this->assertTrue( $entry->is_update() );
		$this->assertFalse( $entry->is_delete() );
	}

	/**
	 * Test is_delete helper.
	 */
	public function test_is_delete(): void {
		$entry = $this->create_entry(
			null,
			null,
			EntryContent::empty(),
			null,
			EntryId::from_int( 100 )
		);

		$this->assertFalse( $entry->is_new() );
		$this->assertFalse( $entry->is_update() );
		$this->assertTrue( $entry->is_delete() );
	}

	/**
	 * Test display_id returns own ID for new entries.
	 */
	public function test_display_id_for_new_entry(): void {
		$entry = $this->create_entry( EntryId::from_int( 123 ) );

		$this->assertSame( 123, $entry->display_id()->to_int() );
	}

	/**
	 * Test display_id returns replaces ID for update entries.
	 */
	public function test_display_id_for_update_entry(): void {
		$entry = $this->create_entry(
			EntryId::from_int( 124 ),
			null,
			null,
			null,
			EntryId::from_int( 123 )
		);

		$this->assertSame( 123, $entry->display_id()->to_int() );
	}

	/**
	 * Test timestamp returns Unix timestamp.
	 */
	public function test_timestamp(): void {
		$created_at = new DateTimeImmutable( '2024-01-15 10:30:00', new DateTimeZone( 'UTC' ) );
		$entry      = $this->create_entry( null, null, null, null, null, $created_at );

		$this->assertSame( $created_at->getTimestamp(), $entry->timestamp() );
	}

	/**
	 * Test has_authors returns true when authors exist.
	 */
	public function test_has_authors_true(): void {
		$authors = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Test',
				)
			)
		);
		$entry   = $this->create_entry( null, null, null, $authors );

		$this->assertTrue( $entry->has_authors() );
	}

	/**
	 * Test has_authors returns false when no authors.
	 */
	public function test_has_authors_false(): void {
		$entry = $this->create_entry( null, null, null, AuthorCollection::empty() );

		$this->assertFalse( $entry->has_authors() );
	}

	/**
	 * Test with_authors returns new instance.
	 */
	public function test_with_authors_returns_new_instance(): void {
		$original_authors = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Original',
				) 
			)
		);
		$new_authors      = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 2,
					'name' => 'New',
				) 
			)
		);

		$original = $this->create_entry( null, null, null, $original_authors );
		$modified = $original->with_authors( $new_authors );

		// Original unchanged.
		$this->assertSame( 'Original', $original->authors()->primary()->display_name() );

		// New instance has new authors.
		$this->assertSame( 'New', $modified->authors()->primary()->display_name() );

		// Other properties preserved.
		$this->assertSame( $original->id()->to_int(), $modified->id()->to_int() );
		$this->assertSame( $original->post_id(), $modified->post_id() );
		$this->assertSame( $original->content()->raw(), $modified->content()->raw() );
	}

	/**
	 * Test with_content returns new instance.
	 */
	public function test_with_content_returns_new_instance(): void {
		$original_content = EntryContent::from_raw( 'Original content' );
		$new_content      = EntryContent::from_raw( 'New content' );

		$original = $this->create_entry( null, null, $original_content );
		$modified = $original->with_content( $new_content );

		// Original unchanged.
		$this->assertSame( 'Original content', $original->content()->raw() );

		// New instance has new content.
		$this->assertSame( 'New content', $modified->content()->raw() );

		// Other properties preserved.
		$this->assertSame( $original->id()->to_int(), $modified->id()->to_int() );
		$this->assertSame( $original->post_id(), $modified->post_id() );
	}

	/**
	 * Test all getters.
	 */
	public function test_getters(): void {
		$id         = EntryId::from_int( 999 );
		$post_id    = 888;
		$content    = EntryContent::from_raw( 'Getter test' );
		$authors    = AuthorCollection::empty();
		$replaces   = EntryId::from_int( 777 );
		$created_at = new DateTimeImmutable( '2024-06-01 12:00:00', new DateTimeZone( 'UTC' ) );

		$entry = Entry::create( $id, $post_id, $content, $authors, $replaces, $created_at );

		$this->assertSame( $id, $entry->id() );
		$this->assertSame( $post_id, $entry->post_id() );
		$this->assertSame( $content, $entry->content() );
		$this->assertSame( $authors, $entry->authors() );
		$this->assertSame( $replaces, $entry->replaces() );
		$this->assertSame( $created_at, $entry->created_at() );
	}

	/**
	 * Test to_array serialization.
	 */
	public function test_to_array(): void {
		Functions\expect( 'get_avatar' )
			->once()
			->andReturn( '<img />' );

		$id         = EntryId::from_int( 123 );
		$post_id    = 456;
		$content    = EntryContent::from_raw( 'Test content' );
		$authors    = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Test Author',
					'key'  => 'test-author',
				)
			)
		);
		$replaces   = EntryId::from_int( 100 );
		$created_at = new DateTimeImmutable( '2024-01-15 10:30:00', new DateTimeZone( 'UTC' ) );

		$entry = Entry::create( $id, $post_id, $content, $authors, $replaces, $created_at );
		$array = $entry->to_array();

		$this->assertSame( 123, $array['id'] );
		$this->assertSame( 456, $array['post_id'] );
		$this->assertSame( 'update', $array['type'] );
		$this->assertSame( 'Test content', $array['content'] );
		$this->assertSame( 100, $array['replaces'] );
		$this->assertSame( $created_at->getTimestamp(), $array['timestamp'] );
		$this->assertSame( '2024-01-15T10:30:00+00:00', $array['created_at'] );
		$this->assertIsArray( $array['authors'] );
		$this->assertCount( 1, $array['authors'] );
	}

	/**
	 * Test to_array with null replaces.
	 */
	public function test_to_array_null_replaces(): void {
		Functions\expect( 'get_avatar' )
			->once()
			->andReturn( '<img />' );

		$entry = $this->create_entry();
		$array = $entry->to_array();

		$this->assertNull( $array['replaces'] );
		$this->assertSame( 'new', $array['type'] );
	}

	/**
	 * Helper to create an entry with defaults.
	 *
	 * @param EntryId|null           $id         Entry ID.
	 * @param int|null               $post_id    Post ID.
	 * @param EntryContent|null      $content    Content.
	 * @param AuthorCollection|null  $authors    Authors.
	 * @param EntryId|null           $replaces   Replaces ID.
	 * @param DateTimeImmutable|null $created_at Created timestamp.
	 * @return Entry
	 */
	private function create_entry(
		?EntryId $id = null,
		?int $post_id = null,
		?EntryContent $content = null,
		?AuthorCollection $authors = null,
		?EntryId $replaces = null,
		?DateTimeImmutable $created_at = null
	): Entry {
		return Entry::create(
			$id ?? EntryId::from_int( 1 ),
			$post_id ?? 1,
			$content ?? EntryContent::from_raw( 'Test content' ),
			$authors ?? AuthorCollection::from_authors(
				Author::from_array(
					array(
						'id'   => 1,
						'name' => 'Test Author',
					) 
				)
			),
			$replaces,
			$created_at ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);
	}
}
