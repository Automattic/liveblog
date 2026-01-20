<?php
/**
 * Tests for EntryPresenter.
 *
 * @package Automattic\Liveblog\Tests\Unit\Application\Presenter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Application\Presenter;

use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\ValueObject\Author;
use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Automattic\Liveblog\Domain\ValueObject\EntryContent;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Mockery;
use WP_Comment;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests for the EntryPresenter class.
 *
 * @covers \Automattic\Liveblog\Application\Presenter\EntryPresenter
 */
final class EntryPresenterTest extends TestCase {

	/**
	 * Test for_json returns expected structure.
	 */
	public function test_for_json_returns_expected_structure(): void {
		$this->setup_wordpress_mocks();

		$entry     = $this->create_entry();
		$comment   = $this->create_mock_comment();
		$renderer  = $this->create_mock_renderer( '<p>Rendered content</p>' );
		$presenter = new EntryPresenter( $entry, $comment, $renderer );

		$json = $presenter->for_json();

		$this->assertIsObject( $json );
		$this->assertSame( 123, $json->id );
		$this->assertSame( 'new', $json->type );
		$this->assertSame( '<p>Rendered content</p>', $json->render );
		$this->assertSame( 'Test content', $json->content );
		$this->assertSame( 'comment liveblog-entry', $json->css_classes );
		$this->assertSame( 'https://example.com/post/#123', $json->share_link );
		$this->assertIsArray( $json->authors );
		$this->assertArrayHasKey( 'entry_time', (array) $json );
		$this->assertArrayHasKey( 'timestamp', (array) $json );
	}

	/**
	 * Test for_json with update entry uses replaces ID.
	 */
	public function test_for_json_uses_replaces_id_for_updates(): void {
		$this->setup_wordpress_mocks( 100 ); // Display ID is 100 (the replaced entry).

		$entry = Entry::create(
			EntryId::from_int( 200 ),
			456,
			EntryContent::from_raw( 'Updated content' ),
			AuthorCollection::empty(),
			EntryId::from_int( 100 ), // Replaces entry 100.
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);

		$comment   = $this->create_mock_comment();
		$renderer  = $this->create_mock_renderer( '<p>Updated</p>' );
		$presenter = new EntryPresenter( $entry, $comment, $renderer );

		$json = $presenter->for_json();

		$this->assertSame( 100, $json->id ); // Uses replaces ID.
		$this->assertSame( 'update', $json->type );
	}

	/**
	 * Test renderer is called with correct arguments.
	 */
	public function test_renderer_called_with_correct_arguments(): void {
		$this->setup_wordpress_mocks();

		$entry   = $this->create_entry();
		$comment = $this->create_mock_comment();

		$renderer = Mockery::mock( ContentRendererInterface::class );
		$renderer->shouldReceive( 'render' )
			->once()
			->with( 'Test content', $comment )
			->andReturn( '<p>Rendered</p>' );

		$presenter = new EntryPresenter( $entry, $comment, $renderer );
		$presenter->for_json();

		// Mockery verifies the expectation automatically.
		$this->assertTrue( true );
	}

	/**
	 * Test authors array is populated.
	 */
	public function test_authors_array_populated(): void {
		$this->setup_wordpress_mocks();

		Functions\expect( 'get_avatar' )
			->once()
			->andReturn( '<img src="avatar.jpg" />' );

		$entry = Entry::create(
			EntryId::from_int( 123 ),
			456,
			EntryContent::from_raw( 'Test' ),
			AuthorCollection::from_authors(
				Author::from_array(
					array(
						'id'   => 1,
						'name' => 'John Doe',
						'key'  => 'john-doe',
					)
				)
			),
			null,
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);

		$comment   = $this->create_mock_comment();
		$renderer  = $this->create_mock_renderer( '<p>Test</p>' );
		$presenter = new EntryPresenter( $entry, $comment, $renderer );

		$json = $presenter->for_json();

		$this->assertCount( 1, $json->authors );
		$this->assertSame( 'John Doe', $json->authors[0]['name'] );
		$this->assertSame( 'john-doe', $json->authors[0]['key'] );
	}

	/**
	 * Test entry type value is correct.
	 */
	public function test_entry_type_values(): void {
		$this->setup_wordpress_mocks();

		// New entry.
		$new_entry  = $this->create_entry();
		$presenter1 = new EntryPresenter( $new_entry, $this->create_mock_comment(), $this->create_mock_renderer() );
		$this->assertSame( 'new', $presenter1->for_json()->type );

		// Update entry.
		$this->setup_wordpress_mocks( 100 );
		$update_entry = Entry::create(
			EntryId::from_int( 200 ),
			456,
			EntryContent::from_raw( 'Updated' ),
			AuthorCollection::empty(),
			EntryId::from_int( 100 ),
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);
		$presenter2   = new EntryPresenter( $update_entry, $this->create_mock_comment(), $this->create_mock_renderer() );
		$this->assertSame( 'update', $presenter2->for_json()->type );

		// Delete entry.
		$this->setup_wordpress_mocks( 100 );
		$delete_entry = Entry::create(
			EntryId::from_int( 300 ),
			456,
			EntryContent::empty(),
			AuthorCollection::empty(),
			EntryId::from_int( 100 ),
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);
		$presenter3   = new EntryPresenter( $delete_entry, $this->create_mock_comment(), $this->create_mock_renderer() );
		$this->assertSame( 'delete', $presenter3->for_json()->type );
	}

	/**
	 * Set up WordPress function mocks for for_json.
	 *
	 * @param int $entry_id Entry ID for mocks.
	 */
	private function setup_wordpress_mocks( int $entry_id = 123 ): void {
		Functions\expect( 'get_comment_class' )
			->andReturn( array( 'comment', 'liveblog-entry' ) );

		Functions\expect( 'get_permalink' )
			->andReturn( 'https://example.com/post/' );

		Functions\expect( 'get_comment_date' )
			->andReturn( '1234567890' );

		Functions\expect( 'apply_filters' )
			->andReturnUsing(
				function ( $hook, ...$args ) {
					return match ( $hook ) {
						'liveblog_entry_avatar_size' => 30,
						'liveblog_before_edit_entry' => $args[0] ?? '',
						'liveblog_entry_for_json'    => $args[0] ?? array(),
						default                      => $args[0] ?? null,
					};
				}
			);
	}

	/**
	 * Create a mock WP_Comment.
	 *
	 * @return WP_Comment
	 */
	private function create_mock_comment(): WP_Comment {
		$comment                       = Mockery::mock( WP_Comment::class );
		$comment->comment_ID           = 123;
		$comment->comment_post_ID      = 456;
		$comment->comment_author       = 'Test Author';
		$comment->comment_author_email = 'test@example.com';
		$comment->comment_content      = 'Test content';

		return $comment;
	}

	/**
	 * Create a mock content renderer.
	 *
	 * @param string $output Output to return from render.
	 * @return ContentRendererInterface
	 */
	private function create_mock_renderer( string $output = '<p>Rendered</p>' ): ContentRendererInterface {
		$renderer = Mockery::mock( ContentRendererInterface::class );
		$renderer->shouldReceive( 'render' )->andReturn( $output );

		return $renderer;
	}

	/**
	 * Create a test entry without author.
	 *
	 * @return Entry
	 */
	private function create_entry(): Entry {
		return Entry::create(
			EntryId::from_int( 123 ),
			456,
			EntryContent::from_raw( 'Test content' ),
			AuthorCollection::empty(),
			null,
			new DateTimeImmutable( '2024-01-15 10:30:00', new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Create a test entry with an author.
	 *
	 * @return Entry
	 */
	private function create_entry_with_author(): Entry {
		return Entry::create(
			EntryId::from_int( 123 ),
			456,
			EntryContent::from_raw( 'Test content' ),
			AuthorCollection::from_authors(
				Author::from_array(
					array(
						'id'    => 1,
						'name'  => 'Test Author',
						'key'   => 'test-author',
						'email' => 'test@example.com',
					)
				)
			),
			null,
			new DateTimeImmutable( '2024-01-15 10:30:00', new DateTimeZone( 'UTC' ) )
		);
	}
}
