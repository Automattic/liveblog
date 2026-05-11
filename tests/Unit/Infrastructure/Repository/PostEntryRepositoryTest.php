<?php
/**
 * Tests for the PostEntryRepository.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\Repository;

use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;
use Mockery;
use Automattic\Liveblog\Infrastructure\Repository\PostEntryRepository;
use Automattic\Liveblog\Domain\ValueObject\EntryId;

/**
 * Unit tests for the PostEntryRepository.
 */
final class PostEntryRepositoryTest extends TestCase {

	/**
	 * Repository instance under test.
	 *
	 * @var PostEntryRepository
	 */
	private PostEntryRepository $repository;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->repository = new PostEntryRepository();
	}

	/**
	 * Test that insert creates a post with the correct parent relationship.
	 */
	public function test_insert_creates_post_with_parent(): void {
		Functions\expect( 'wp_insert_post' )
			->once()
			->with(
				Mockery::on(
					function ( $args ) {
						return 'post' === $args['post_type']
						&& 123 === $args['post_parent']
						&& 'Test entry content' === $args['post_content']
						&& 1 === $args['post_author']
						&& 'publish' === $args['post_status'];
					}
				),
				true
			)
			->andReturn( 456 );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( 456 )
			->andReturn( false );

		Functions\expect( 'update_post_meta' )
			->once()
			->with( 456, 'liveblog_hide_authors', false )
			->andReturn( true );

		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'liveblog_entries_asc_123', 'liveblog' );

		$data = array(
			'post_id'   => 123,
			'content'   => 'Test entry content',
			'author_id' => 1,
		);

		$result = $this->repository->insert( $data );

		$this->assertInstanceOf( EntryId::class, $result );
		$this->assertSame( 456, $result->to_int() );
	}

	/**
	 * Test that get_entry hydrates a WP_Post into a valid Entry entity.
	 */
	public function test_get_entry_hydrates_from_post(): void {
		// Create a mock WP_Post.
		$user_data = (object) array(
			'ID'           => 1,
			'display_name' => 'Test User',
			'user_email'   => 'test@example.com',
			'user_url'     => 'http://example.com/author/test-user',
		);
		
		$post                = Mockery::mock( 'WP_Post' );
		$post->ID            = 456;
		$post->post_parent   = 123;
		$post->post_content  = 'Test content';
		$post->post_author   = 1;
		$post->post_date_gmt = '2026-05-06 14:30:00';
		$post->post_type     = 'post';

		Functions\expect( 'get_post' )
			->once()
			->with( 456 )
			->andReturn( $post );

		Functions\expect( 'get_post_meta' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				function ( $id, $key, $single ) {
					if ( 'liveblog_replaces' === $key ) {
						return '';
					}
					if ( 'liveblog_hide_authors' === $key ) {
						return false;
					}
					if ( 'liveblog_contributors' === $key ) {
							return array();
					}
						return '';
				}
			);

		Functions\expect( 'get_userdata' )
			->zeroOrMoreTimes()
			->andReturn( $user_data );

		$entry = $this->repository->get_entry( EntryId::from_int( 456 ) );

		$this->assertNotNull( $entry );
		$this->assertSame( 456, $entry->id()->to_int() );
		$this->assertSame( 123, $entry->post_id() );
	}

	/**
	 * Test that get_entries queries by parent post ID.
	 */
	public function test_get_entries_queries_by_parent(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with(
				Mockery::on(
					function ( $args ) {
						return 123 === $args['post_parent']
						&& 'post' === $args['post_type']
						&& 'publish' === $args['post_status']
						&& 'date' === $args['orderby']
						&& 'DESC' === $args['order'];
					}
				)
			)
			->andReturn( array() );

		$result = $this->repository->get_entries( 123 );

		$this->assertIsArray( $result );
	}

	/**
	 * Test that delete sends the post to trash (soft delete).
	 */
	public function test_delete_trashes_post(): void {
		Functions\expect( 'wp_trash_post' )
			->once()
			->with( 456 )
			->andReturn( true );

		Functions\expect( 'get_post' )
			->once()
			->with( 456 )
			->andReturn(
				(object) array(
					'ID'          => 456,
					'post_parent' => 123,
				) 
			);

		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'liveblog_entries_asc_123', 'liveblog' );

		$result = $this->repository->delete( EntryId::from_int( 456 ), false );

		$this->assertTrue( $result );
	}

	/**
	 * Test that get_replaces_id returns the correct value from post meta.
	 */
	public function test_get_replaces_id_returns_meta(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 456, 'liveblog_replaces', true )
			->andReturn( 789 );

		$result = $this->repository->get_replaces_id( EntryId::from_int( 456 ) );

		$this->assertInstanceOf( EntryId::class, $result );
		$this->assertSame( 789, $result->to_int() );
	}
	
	/**
	 * Test that get_replaces_id returns null when meta value is zero.
	 */
	public function test_get_replaces_id_returns_null_when_zero(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 456, 'liveblog_replaces', true )
			->andReturn( 0 );

		$result = $this->repository->get_replaces_id( EntryId::from_int( 456 ) );

		$this->assertNull( $result );
	}

	/**
	 * Test that set_contributors updates the post meta correctly.
	 */
	public function test_set_contributors_updates_meta(): void {
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 456, 'liveblog_contributors', array( 2, 3, 4 ) )
			->andReturn( true );

		$result = $this->repository->set_contributors( EntryId::from_int( 456 ), array( 2, 3, 4 ) );

		$this->assertTrue( $result );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		\Mockery::close();
		parent::tearDown();
	}
}
