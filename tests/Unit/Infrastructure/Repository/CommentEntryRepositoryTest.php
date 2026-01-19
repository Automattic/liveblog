<?php
/**
 * Unit tests for CommentEntryRepository.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\Repository
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\Repository;

use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;
use Brain\Monkey\Functions;
use Mockery;
use RuntimeException;
use WP_Comment;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * CommentEntryRepository unit test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository
 */
final class CommentEntryRepositoryTest extends TestCase {

	/**
	 * Repository instance under test.
	 *
	 * @var CommentEntryRepository
	 */
	private CommentEntryRepository $repository;

	/**
	 * Set up test fixtures.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->repository = new CommentEntryRepository();
	}

	/**
	 * Test find_by_id returns comment when found.
	 */
	public function test_find_by_id_returns_comment(): void {
		$comment = $this->create_mock_comment( 123 );

		Functions\expect( 'get_comment' )
			->once()
			->with( 123 )
			->andReturn( $comment );

		$result = $this->repository->find_by_id( EntryId::from_int( 123 ) );

		$this->assertSame( $comment, $result );
	}

	/**
	 * Test find_by_id returns null when not found.
	 */
	public function test_find_by_id_returns_null_when_not_found(): void {
		Functions\expect( 'get_comment' )
			->once()
			->with( 999 )
			->andReturn( null );

		$result = $this->repository->find_by_id( EntryId::from_int( 999 ) );

		$this->assertNull( $result );
	}

	/**
	 * Test find_by_post_id returns comments.
	 */
	public function test_find_by_post_id_returns_comments(): void {
		$comments = array(
			$this->create_mock_comment( 1 ),
			$this->create_mock_comment( 2 ),
		);

		Functions\expect( 'get_comments' )
			->once()
			->with(
				Mockery::on(
					function ( $args ) {
						return 42 === $args['post_id']
							&& 'liveblog' === $args['type']
							&& 'liveblog' === $args['status'];
					}
				)
			)
			->andReturn( $comments );

		$result = $this->repository->find_by_post_id( 42 );

		$this->assertCount( 2, $result );
	}

	/**
	 * Test insert creates comment and returns ID.
	 */
	public function test_insert_creates_comment(): void {
		Functions\expect( 'wp_filter_post_kses' )
			->once()
			->with( 'Test content' )
			->andReturn( 'Test content' );

		Functions\expect( 'wp_insert_comment' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 42 === $data['comment_post_ID']
							&& 'Test content' === $data['comment_content']
							&& 'liveblog' === $data['comment_type']
							&& 'liveblog' === $data['comment_approved']
							&& 1 === $data['user_id'];
					}
				)
			)
			->andReturn( 100 );

		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'liveblog_entries_asc_42', 'liveblog' );

		$entry_id = $this->repository->insert(
			array(
				'post_id'      => 42,
				'user_id'      => 1,
				'content'      => 'Test content',
				'author_name'  => 'Test Author',
				'author_email' => 'test@example.com',
			)
		);

		$this->assertSame( 100, $entry_id->to_int() );
	}

	/**
	 * Test insert throws exception on failure.
	 */
	public function test_insert_throws_on_failure(): void {
		Functions\expect( 'wp_filter_post_kses' )
			->andReturn( '' );

		Functions\expect( 'wp_insert_comment' )
			->andReturn( 0 );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to insert liveblog entry' );

		$this->repository->insert(
			array(
				'post_id' => 42,
				'user_id' => 1,
			)
		);
	}

	/**
	 * Test insert throws exception when post_id missing.
	 */
	public function test_insert_throws_when_post_id_missing(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Missing required field: post_id' );

		$this->repository->insert( array( 'user_id' => 1 ) );
	}

	/**
	 * Test insert throws exception when user_id missing.
	 */
	public function test_insert_throws_when_user_id_missing(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Missing required field: user_id' );

		$this->repository->insert( array( 'post_id' => 42 ) );
	}

	/**
	 * Test update modifies comment.
	 */
	public function test_update_modifies_comment(): void {
		$comment                  = $this->create_mock_comment( 123 );
		$comment->comment_post_ID = 42;

		Functions\expect( 'get_comment' )
			->once()
			->with( 123 )
			->andReturn( $comment );

		Functions\expect( 'wp_filter_post_kses' )
			->once()
			->with( 'Updated content' )
			->andReturn( 'Updated content' );

		Functions\expect( 'wp_update_comment' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 123 === $data['comment_ID']
							&& 'Updated content' === $data['comment_content'];
					}
				)
			)
			->andReturn( 1 );

		Functions\expect( 'wp_cache_delete' )
			->once();

		$result = $this->repository->update(
			EntryId::from_int( 123 ),
			array( 'content' => 'Updated content' )
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test update throws when entry not found.
	 */
	public function test_update_throws_when_not_found(): void {
		Functions\expect( 'get_comment' )
			->once()
			->andReturn( null );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Entry not found' );

		$this->repository->update( EntryId::from_int( 999 ), array() );
	}

	/**
	 * Test delete removes comment.
	 */
	public function test_delete_removes_comment(): void {
		$comment                  = $this->create_mock_comment( 123 );
		$comment->comment_post_ID = 42;

		Functions\expect( 'get_comment' )
			->once()
			->with( 123 )
			->andReturn( $comment );

		Functions\expect( 'wp_delete_comment' )
			->once()
			->with( 123, false )
			->andReturn( true );

		Functions\expect( 'wp_cache_delete' )
			->once();

		$result = $this->repository->delete( EntryId::from_int( 123 ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete returns false when not found.
	 */
	public function test_delete_returns_false_when_not_found(): void {
		Functions\expect( 'get_comment' )
			->once()
			->andReturn( null );

		$result = $this->repository->delete( EntryId::from_int( 999 ) );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_replaces_id returns entry ID when set.
	 */
	public function test_get_replaces_id_returns_id(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( 456 );

		$result = $this->repository->get_replaces_id( EntryId::from_int( 123 ) );

		$this->assertInstanceOf( EntryId::class, $result );
		$this->assertSame( 456, $result->to_int() );
	}

	/**
	 * Test get_replaces_id returns null when not set.
	 */
	public function test_get_replaces_id_returns_null(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->andReturn( '' );

		$result = $this->repository->get_replaces_id( EntryId::from_int( 123 ) );

		$this->assertNull( $result );
	}

	/**
	 * Test set_replaces_id adds meta.
	 */
	public function test_set_replaces_id(): void {
		Functions\expect( 'add_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::REPLACES_META_KEY, 456 )
			->andReturn( 1 );

		$result = $this->repository->set_replaces_id(
			EntryId::from_int( 123 ),
			EntryId::from_int( 456 )
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test get_contributors returns user IDs.
	 */
	public function test_get_contributors(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array( 1, 2, 3 ) );

		$result = $this->repository->get_contributors( EntryId::from_int( 123 ) );

		$this->assertSame( array( 1, 2, 3 ), $result );
	}

	/**
	 * Test get_contributors returns empty array when not set.
	 */
	public function test_get_contributors_empty(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->andReturn( '' );

		$result = $this->repository->get_contributors( EntryId::from_int( 123 ) );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test set_contributors updates meta.
	 */
	public function test_set_contributors(): void {
		Functions\expect( 'metadata_exists' )
			->once()
			->with( 'comment', 123, CommentEntryRepository::CONTRIBUTORS_META_KEY )
			->andReturn( true );

		Functions\expect( 'update_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::CONTRIBUTORS_META_KEY, array( 1, 2 ) )
			->andReturn( true );

		$result = $this->repository->set_contributors(
			EntryId::from_int( 123 ),
			array( 1, 2 )
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test set_contributors deletes meta when empty.
	 */
	public function test_set_contributors_deletes_when_empty(): void {
		Functions\expect( 'delete_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::CONTRIBUTORS_META_KEY )
			->andReturn( true );

		$result = $this->repository->set_contributors(
			EntryId::from_int( 123 ),
			array()
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test is_authors_hidden returns true when hidden.
	 */
	public function test_is_authors_hidden_true(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( true );

		$this->assertTrue( $this->repository->is_authors_hidden( EntryId::from_int( 123 ) ) );
	}

	/**
	 * Test is_authors_hidden returns false when not hidden.
	 */
	public function test_is_authors_hidden_false(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->andReturn( '' );

		$this->assertFalse( $this->repository->is_authors_hidden( EntryId::from_int( 123 ) ) );
	}

	/**
	 * Test set_authors_hidden sets meta when hiding.
	 */
	public function test_set_authors_hidden_true(): void {
		Functions\expect( 'update_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( true );

		$result = $this->repository->set_authors_hidden( EntryId::from_int( 123 ), true );

		$this->assertTrue( $result );
	}

	/**
	 * Test set_authors_hidden deletes meta when showing.
	 */
	public function test_set_authors_hidden_false(): void {
		Functions\expect( 'delete_comment_meta' )
			->once()
			->with( 123, CommentEntryRepository::HIDE_AUTHORS_KEY )
			->andReturn( true );

		$result = $this->repository->set_authors_hidden( EntryId::from_int( 123 ), false );

		$this->assertTrue( $result );
	}

	/**
	 * Test invalidate_cache clears cache.
	 */
	public function test_invalidate_cache(): void {
		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'liveblog_entries_asc_42', 'liveblog' );

		$this->repository->invalidate_cache( 42 );

		// No return value to assert, just verify function was called.
		$this->assertTrue( true );
	}

	/**
	 * Test get_entry returns Entry entity when found.
	 */
	public function test_get_entry_returns_entry(): void {
		$comment = $this->create_full_mock_comment( 123, 42, 'Test content' );

		Functions\expect( 'get_comment' )
			->once()
			->with( 123 )
			->andReturn( $comment );

		// Mock meta calls for hydration.
		Functions\expect( 'get_comment_meta' )
			->with( 123, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( '' );

		Functions\expect( 'get_comment_meta' )
			->with( 123, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( '' );

		Functions\expect( 'get_comment_meta' )
			->with( 123, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array() );

		// Mock sanitize_title for Author::from_comment.
		Functions\expect( 'sanitize_title' )
			->andReturnUsing( fn( $title ) => strtolower( str_replace( ' ', '-', $title ) ) );

		$result = $this->repository->get_entry( EntryId::from_int( 123 ) );

		$this->assertInstanceOf( Entry::class, $result );
		$this->assertSame( 123, $result->id()->to_int() );
		$this->assertSame( 42, $result->post_id() );
		$this->assertSame( 'Test content', $result->content()->raw() );
		$this->assertTrue( $result->is_new() );
	}

	/**
	 * Test get_entry returns null when not found.
	 */
	public function test_get_entry_returns_null_when_not_found(): void {
		Functions\expect( 'get_comment' )
			->once()
			->with( 999 )
			->andReturn( null );

		$result = $this->repository->get_entry( EntryId::from_int( 999 ) );

		$this->assertNull( $result );
	}

	/**
	 * Test get_entry correctly hydrates update entry type.
	 */
	public function test_get_entry_hydrates_update_type(): void {
		$comment = $this->create_full_mock_comment( 124, 42, 'Updated content' );

		Functions\expect( 'get_comment' )
			->once()
			->with( 124 )
			->andReturn( $comment );

		// Entry replaces another entry - makes it an update.
		Functions\expect( 'get_comment_meta' )
			->with( 124, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( 123 );

		Functions\expect( 'get_comment_meta' )
			->with( 124, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( '' );

		Functions\expect( 'get_comment_meta' )
			->with( 124, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array() );

		Functions\expect( 'sanitize_title' )
			->andReturnUsing( fn( $title ) => strtolower( str_replace( ' ', '-', $title ) ) );

		$result = $this->repository->get_entry( EntryId::from_int( 124 ) );

		$this->assertTrue( $result->is_update() );
		$this->assertSame( 123, $result->replaces()->to_int() );
	}

	/**
	 * Test get_entry correctly hydrates delete entry type.
	 */
	public function test_get_entry_hydrates_delete_type(): void {
		$comment = $this->create_full_mock_comment( 125, 42, '' );

		Functions\expect( 'get_comment' )
			->once()
			->with( 125 )
			->andReturn( $comment );

		// Entry replaces another entry with empty content - makes it a delete.
		Functions\expect( 'get_comment_meta' )
			->with( 125, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( 123 );

		Functions\expect( 'get_comment_meta' )
			->with( 125, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( '' );

		Functions\expect( 'get_comment_meta' )
			->with( 125, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array() );

		Functions\expect( 'sanitize_title' )
			->andReturnUsing( fn( $title ) => strtolower( str_replace( ' ', '-', $title ) ) );

		$result = $this->repository->get_entry( EntryId::from_int( 125 ) );

		$this->assertTrue( $result->is_delete() );
	}

	/**
	 * Test get_entry returns empty authors when hidden.
	 */
	public function test_get_entry_hides_authors_when_flag_set(): void {
		$comment = $this->create_full_mock_comment( 123, 42, 'Test content' );

		Functions\expect( 'get_comment' )
			->once()
			->with( 123 )
			->andReturn( $comment );

		// Use a callback to return different values based on meta key.
		Functions\expect( 'get_comment_meta' )
			->andReturnUsing(
				function ( $comment_id, $meta_key, $single ) {
					if ( CommentEntryRepository::HIDE_AUTHORS_KEY === $meta_key ) {
						return 1; // Authors are hidden.
					}
					return '';
				}
			);

		$result = $this->repository->get_entry( EntryId::from_int( 123 ) );

		$this->assertTrue( $result->authors()->is_empty() );
		$this->assertFalse( $result->has_authors() );
	}

	/**
	 * Test get_entries returns array of Entry entities.
	 */
	public function test_get_entries_returns_entries(): void {
		$comments = array(
			$this->create_full_mock_comment( 1, 42, 'First entry' ),
			$this->create_full_mock_comment( 2, 42, 'Second entry' ),
		);

		Functions\expect( 'get_comments' )
			->once()
			->with(
				Mockery::on(
					function ( $args ) {
						return 42 === $args['post_id']
							&& 'liveblog' === $args['type']
							&& 'liveblog' === $args['status'];
					}
				)
			)
			->andReturn( $comments );

		// Mock meta calls for both entries.
		Functions\expect( 'get_comment_meta' )
			->with( 1, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( '' );
		Functions\expect( 'get_comment_meta' )
			->with( 1, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( '' );
		Functions\expect( 'get_comment_meta' )
			->with( 1, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array() );

		Functions\expect( 'get_comment_meta' )
			->with( 2, CommentEntryRepository::REPLACES_META_KEY, true )
			->andReturn( '' );
		Functions\expect( 'get_comment_meta' )
			->with( 2, CommentEntryRepository::HIDE_AUTHORS_KEY, true )
			->andReturn( '' );
		Functions\expect( 'get_comment_meta' )
			->with( 2, CommentEntryRepository::CONTRIBUTORS_META_KEY, true )
			->andReturn( array() );

		Functions\expect( 'sanitize_title' )
			->andReturnUsing( fn( $title ) => strtolower( str_replace( ' ', '-', $title ) ) );

		$result = $this->repository->get_entries( 42 );

		$this->assertCount( 2, $result );
		$this->assertContainsOnlyInstancesOf( Entry::class, $result );
		$this->assertSame( 'First entry', $result[0]->content()->raw() );
		$this->assertSame( 'Second entry', $result[1]->content()->raw() );
	}

	/**
	 * Test get_entries returns empty array when no entries.
	 */
	public function test_get_entries_returns_empty_array(): void {
		Functions\expect( 'get_comments' )
			->once()
			->andReturn( array() );

		$result = $this->repository->get_entries( 42 );

		$this->assertSame( array(), $result );
	}

	/**
	 * Test find_referencing_entries queries correctly.
	 */
	public function test_find_referencing_entries(): void {
		$comments = array( $this->create_mock_comment( 200 ) );

		Functions\expect( 'get_comments' )
			->once()
			->with(
				Mockery::on(
					function ( $args ) {
						return 42 === $args['post_id']
							&& 'liveblog' === $args['type']
							&& CommentEntryRepository::REPLACES_META_KEY === $args['meta_key']
							&& 123 === $args['meta_value']
							&& array( 456 ) === $args['comment__not_in'];
					}
				)
			)
			->andReturn( $comments );

		$result = $this->repository->find_referencing_entries(
			42,
			EntryId::from_int( 123 ),
			EntryId::from_int( 456 )
		);

		$this->assertCount( 1, $result );
	}

	/**
	 * Create a mock WP_Comment object.
	 *
	 * @param int $id Comment ID.
	 * @return WP_Comment
	 */
	private function create_mock_comment( int $id ): WP_Comment {
		$comment             = Mockery::mock( WP_Comment::class );
		$comment->comment_ID = $id;

		return $comment;
	}

	/**
	 * Create a full mock WP_Comment with all fields for hydration.
	 *
	 * @param int    $id      Comment ID.
	 * @param int    $post_id Post ID.
	 * @param string $content Content.
	 * @return WP_Comment
	 */
	private function create_full_mock_comment( int $id, int $post_id, string $content ): WP_Comment {
		$comment                       = Mockery::mock( WP_Comment::class );
		$comment->comment_ID           = $id;
		$comment->comment_post_ID      = $post_id;
		$comment->comment_content      = $content;
		$comment->comment_date_gmt     = '2024-01-15 10:30:00';
		$comment->comment_author       = 'Test Author';
		$comment->comment_author_email = 'test@example.com';
		$comment->comment_author_url   = 'https://example.com';
		$comment->user_id              = 1;

		return $comment;
	}
}
