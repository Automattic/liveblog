<?php
/**
 * Unit tests for EntryService.
 *
 * @package Automattic\Liveblog\Tests\Unit\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Application\Service;

use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use InvalidArgumentException;
use Mockery;
use WP_Comment;
use WP_User;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * EntryService unit test case.
 *
 * @covers \Automattic\Liveblog\Application\Service\EntryService
 */
final class EntryServiceTest extends TestCase {

	/**
	 * Mock repository.
	 *
	 * @var EntryRepositoryInterface&Mockery\MockInterface
	 */
	private $repository;

	/**
	 * Service under test.
	 *
	 * @var EntryService
	 */
	private EntryService $service;

	/**
	 * Set up test fixtures.
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->repository = Mockery::mock( EntryRepositoryInterface::class );
		$this->service    = new EntryService( $this->repository );
	}

	/**
	 * Test create inserts entry and returns ID.
	 */
	public function test_create_inserts_entry(): void {
		$author   = $this->create_mock_user( 1, 'John Doe' );
		$entry_id = EntryId::from_int( 100 );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return 42 === $data['post_id']
							&& 'Test content' === $data['content']
							&& 1 === $data['user_id']
							&& 'John Doe' === $data['author_name'];
					}
				)
			)
			->andReturn( $entry_id );

		$result = $this->service->create( 42, 'Test content', $author );

		$this->assertSame( $entry_id, $result );
	}

	/**
	 * Test create with hidden author sets meta.
	 */
	public function test_create_with_hidden_author(): void {
		$author   = $this->create_mock_user( 1, 'John Doe' );
		$entry_id = EntryId::from_int( 100 );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->andReturn( $entry_id );

		$this->repository
			->shouldReceive( 'set_authors_hidden' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				true
			)
			->andReturn( true );

		$result = $this->service->create( 42, 'Test content', $author, true );

		$this->assertSame( $entry_id, $result );
	}

	/**
	 * Test create with contributors sets meta.
	 */
	public function test_create_with_contributors(): void {
		$author   = $this->create_mock_user( 1, 'John Doe' );
		$entry_id = EntryId::from_int( 100 );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->andReturn( $entry_id );

		$this->repository
			->shouldReceive( 'set_contributors' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				array( 2, 3 )
			)
			->andReturn( true );

		$result = $this->service->create( 42, 'Test content', $author, false, array( 2, 3 ) );

		$this->assertSame( $entry_id, $result );
	}

	/**
	 * Test create throws on invalid post ID.
	 */
	public function test_create_throws_on_invalid_post_id(): void {
		$author = $this->create_mock_user( 1, 'John Doe' );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid post ID' );

		$this->service->create( 0, 'Test content', $author );
	}

	/**
	 * Test update creates replacement entry.
	 */
	public function test_update_creates_replacement(): void {
		$author       = $this->create_mock_user( 1, 'John Doe' );
		$entry_id     = EntryId::from_int( 100 );
		$new_entry_id = EntryId::from_int( 101 );
		$comment      = $this->create_mock_comment( 100 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->with( Mockery::on( fn( $id ) => $id->to_int() === 100 ) )
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->andReturn( $new_entry_id );

		$this->repository
			->shouldReceive( 'set_replaces_id' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 101 ),
				Mockery::on( fn( $id ) => $id->to_int() === 100 )
			)
			->andReturn( true );

		$this->repository
			->shouldReceive( 'update' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				array( 'content' => 'Updated content' )
			)
			->andReturn( true );

		$result = $this->service->update( 42, $entry_id, 'Updated content', $author );

		$this->assertSame( 101, $result->to_int() );
	}

	/**
	 * Test update throws when entry not found.
	 */
	public function test_update_throws_when_entry_not_found(): void {
		$author   = $this->create_mock_user( 1, 'John Doe' );
		$entry_id = EntryId::from_int( 999 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->andReturn( null );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Entry not found' );

		$this->service->update( 42, $entry_id, 'Updated content', $author );
	}

	/**
	 * Test delete creates delete marker.
	 */
	public function test_delete_creates_marker(): void {
		$author           = $this->create_mock_user( 1, 'John Doe' );
		$entry_id         = EntryId::from_int( 100 );
		$delete_marker_id = EntryId::from_int( 101 );
		$comment          = $this->create_mock_comment( 100 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->with( Mockery::on( fn( $id ) => $id->to_int() === 100 ) )
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->with( Mockery::on( fn( $data ) => '' === $data['content'] ) )
			->andReturn( $delete_marker_id );

		$this->repository
			->shouldReceive( 'set_replaces_id' )
			->once()
			->andReturn( true );

		$this->repository
			->shouldReceive( 'find_referencing_entries' )
			->once()
			->andReturn( array() );

		$this->repository
			->shouldReceive( 'delete' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				false
			)
			->andReturn( true );

		$result = $this->service->delete( 42, $entry_id, $author );

		$this->assertSame( 101, $result->to_int() );
	}

	/**
	 * Test delete cleans up orphaned entries.
	 */
	public function test_delete_cleans_up_orphans(): void {
		$author           = $this->create_mock_user( 1, 'John Doe' );
		$entry_id         = EntryId::from_int( 100 );
		$delete_marker_id = EntryId::from_int( 103 );
		$comment          = $this->create_mock_comment( 100 );
		$orphan1          = $this->create_mock_comment( 101 );
		$orphan2          = $this->create_mock_comment( 102 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'insert' )
			->once()
			->andReturn( $delete_marker_id );

		$this->repository
			->shouldReceive( 'set_replaces_id' )
			->once()
			->andReturn( true );

		$this->repository
			->shouldReceive( 'find_referencing_entries' )
			->once()
			->andReturn( array( $orphan1, $orphan2 ) );

		// Orphans should be force-deleted.
		$this->repository
			->shouldReceive( 'delete' )
			->with( Mockery::on( fn( $id ) => $id->to_int() === 101 ), true )
			->once()
			->andReturn( true );

		$this->repository
			->shouldReceive( 'delete' )
			->with( Mockery::on( fn( $id ) => $id->to_int() === 102 ), true )
			->once()
			->andReturn( true );

		// Original entry soft-deleted.
		$this->repository
			->shouldReceive( 'delete' )
			->with( Mockery::on( fn( $id ) => $id->to_int() === 100 ), false )
			->once()
			->andReturn( true );

		$result = $this->service->delete( 42, $entry_id, $author );

		$this->assertSame( 103, $result->to_int() );
	}

	/**
	 * Test update_author with user updates entry.
	 */
	public function test_update_author_with_user(): void {
		$author   = $this->create_mock_user( 2, 'Jane Doe' );
		$entry_id = EntryId::from_int( 100 );
		$comment  = $this->create_mock_comment( 100 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'update' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				Mockery::on(
					function ( $data ) {
						return 2 === $data['user_id']
							&& 'Jane Doe' === $data['author_name'];
					}
				)
			)
			->andReturn( true );

		$this->repository
			->shouldReceive( 'set_authors_hidden' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				false
			)
			->andReturn( true );

		$result = $this->service->update_author( $entry_id, $author );

		$this->assertTrue( $result );
	}

	/**
	 * Test update_author with null hides authors.
	 */
	public function test_update_author_with_null_hides(): void {
		$entry_id = EntryId::from_int( 100 );
		$comment  = $this->create_mock_comment( 100 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'set_authors_hidden' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				true
			)
			->andReturn( true );

		$result = $this->service->update_author( $entry_id, null );

		$this->assertTrue( $result );
	}

	/**
	 * Test set_contributors delegates to repository.
	 */
	public function test_set_contributors(): void {
		$entry_id = EntryId::from_int( 100 );
		$comment  = $this->create_mock_comment( 100 );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->andReturn( $comment );

		$this->repository
			->shouldReceive( 'set_contributors' )
			->once()
			->with(
				Mockery::on( fn( $id ) => $id->to_int() === 100 ),
				array( 2, 3, 4 )
			)
			->andReturn( true );

		$result = $this->service->set_contributors( $entry_id, array( 2, 3, 4 ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test get_contributors delegates to repository.
	 */
	public function test_get_contributors(): void {
		$entry_id = EntryId::from_int( 100 );

		$this->repository
			->shouldReceive( 'get_contributors' )
			->once()
			->with( Mockery::on( fn( $id ) => $id->to_int() === 100 ) )
			->andReturn( array( 2, 3 ) );

		$result = $this->service->get_contributors( $entry_id );

		$this->assertSame( array( 2, 3 ), $result );
	}

	/**
	 * Test is_authors_hidden delegates to repository.
	 */
	public function test_is_authors_hidden(): void {
		$entry_id = EntryId::from_int( 100 );

		$this->repository
			->shouldReceive( 'is_authors_hidden' )
			->once()
			->with( Mockery::on( fn( $id ) => $id->to_int() === 100 ) )
			->andReturn( true );

		$this->assertTrue( $this->service->is_authors_hidden( $entry_id ) );
	}

	/**
	 * Create a mock WP_User object.
	 *
	 * @param int    $id           User ID.
	 * @param string $display_name Display name.
	 * @return WP_User
	 */
	private function create_mock_user( int $id, string $display_name ): WP_User {
		$user               = Mockery::mock( WP_User::class );
		$user->ID           = $id;
		$user->display_name = $display_name;
		$user->user_email   = strtolower( str_replace( ' ', '.', $display_name ) ) . '@example.com';
		$user->user_url     = '';

		return $user;
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
}
