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
use WP_Post;
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

		$entry     = $this->create_test_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );

		$result = $presenter->for_json();

		$this->assertIsObject( $result );
		$this->assertTrue( isset( $result->id ) );
		$this->assertTrue( isset( $result->type ) );
		$this->assertTrue( isset( $result->render ) );
		$this->assertTrue( isset( $result->content ) );
		$this->assertTrue( isset( $result->css_classes ) );
		$this->assertTrue( isset( $result->timestamp ) );
		$this->assertTrue( isset( $result->authors ) );
		$this->assertTrue( isset( $result->entry_time ) );
		$this->assertTrue( isset( $result->share_link ) );
	}

	/**
	 * Test for_json uses replaces id for updates.
	 */
	public function test_for_json_uses_replaces_id_for_updates(): void {
		$this->setup_wordpress_mocks();

		$entry     = $this->create_update_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );

		$result = $presenter->for_json();

		$this->assertSame( 123, $result->id );
	}

	/**
	 * Test constructor with content renderer.
	 */
	public function test_renderer_called_with_correct_arguments(): void {
		$this->setup_wordpress_mocks();

		$entry    = $this->create_test_entry();
		$renderer = Mockery::mock( ContentRendererInterface::class );
		$renderer->shouldReceive( 'render' )
			->once()
			->with( 'Test content' )
			->andReturn( 'Rendered: Test content' );

		$presenter = new EntryPresenter( $entry, $this->create_mock_post(), $renderer );
		$result    = $presenter->for_json();

		$this->assertSame( 'Rendered: Test content', $result->render );
	}

	/**
	 * Test authors array populated.
	 */
	public function test_authors_array_populated(): void {
		$this->setup_wordpress_mocks();

		$entry     = $this->create_test_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );

		$result = $presenter->for_json();

		$this->assertIsArray( $result->authors );
		$this->assertNotEmpty( $result->authors );
	}

	/**
	 * Test entry type values.
	 */
	public function test_entry_type_values(): void {
		$this->setup_wordpress_mocks();

		$entry     = $this->create_test_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );
		$result    = $presenter->for_json();
		$this->assertSame( 'new', $result->type );

		$entry     = $this->create_update_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );
		$result    = $presenter->for_json();
		$this->assertSame( 'update', $result->type );

		$entry     = $this->create_delete_entry();
		$presenter = new EntryPresenter( $entry, $this->create_mock_post() );
		$result    = $presenter->for_json();
		$this->assertSame( 'delete', $result->type );
	}

	/**
	 * Create a test entry.
	 *
	 * @return Entry
	 */
	private function create_test_entry(): Entry {
		$entry_id   = EntryId::from_int( 456 );
		$content    = EntryContent::from_raw( 'Test content' );
		$authors    = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'    => 1,
					'name'  => 'Test Author',
					'email' => 'author@example.com',
				)
			)
		);
		$created_at = new DateTimeImmutable( '2026-05-06 12:00:00', new DateTimeZone( 'UTC' ) );

		return Entry::create( $entry_id, 123, $content, $authors, null, $created_at );
	}

	/**
	 * Create an update entry (with replaces).
	 *
	 * @return Entry
	 */
	private function create_update_entry(): Entry {
		$entry_id   = EntryId::from_int( 789 );
		$content    = EntryContent::from_raw( 'Updated content' );
		$authors    = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Test Author',
				) 
			)
		);
		$replaces   = EntryId::from_int( 123 );
		$created_at = new DateTimeImmutable( '2026-05-06 13:00:00', new DateTimeZone( 'UTC' ) );

		return Entry::create( $entry_id, 123, $content, $authors, $replaces, $created_at );
	}

	/**
	 * Create a delete entry (empty content with replaces).
	 *
	 * @return Entry
	 */
	private function create_delete_entry(): Entry {
		$entry_id   = EntryId::from_int( 999 );
		$content    = EntryContent::from_raw( '' );
		$authors    = AuthorCollection::from_authors(
			Author::from_array(
				array(
					'id'   => 1,
					'name' => 'Test Author',
				) 
			)
		);
		$replaces   = EntryId::from_int( 456 );
		$created_at = new DateTimeImmutable( '2026-05-06 14:00:00', new DateTimeZone( 'UTC' ) );

		return Entry::create( $entry_id, 123, $content, $authors, $replaces, $created_at );
	}

	/**
	 * Create a mock WP_Post.
	 *
	 * @return WP_Post
	 */
	private function create_mock_post(): WP_Post {
		$post                = Mockery::mock( 'WP_Post' );
		$post->ID            = 456;
		$post->post_parent   = 123;
		$post->post_author   = 1;
		$post->post_content  = 'Test content';
		$post->post_date     = '2026-05-06 12:00:00';
		$post->post_date_gmt = '2026-05-06 12:00:00';
		$post->post_type     = 'post';
		return $post;
	}

	/**
	 * Set up common WordPress mock functions.
	 */
	private function setup_wordpress_mocks(): void {
		Functions\expect( 'get_permalink' )
			->zeroOrMoreTimes()
			->andReturn( 'https://example.com/post' );

		Functions\expect( 'get_avatar' )
			->zeroOrMoreTimes()
			->andReturn( '<img alt="" src="https://example.com/avatar.jpg" />' );

		Functions\expect( 'get_avatar_url' )
			->zeroOrMoreTimes()
			->andReturn( 'https://example.com/avatar.jpg' );

		Functions\expect( '__' )
			->zeroOrMoreTimes()
			->andReturnFirstArg();

		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->with( 'time_format' )
			->andReturn( 'H:i' );

		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->with( 'date_format' )
			->andReturn( 'F j, Y' );

		Functions\expect( 'get_the_date' )
			->zeroOrMoreTimes()
			->andReturn( 'May 6, 2026' );

		Functions\expect( 'get_post_time' )
			->zeroOrMoreTimes()
			->andReturn( '12:00' );

		Functions\expect( 'sanitize_html_class' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				function ( $css_class ) {
					return $css_class;
				}
			);

		Functions\expect( 'apply_filters' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				function ( $hook, $value ) {
					return $value;
				}
			);
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		\Mockery::close();
		parent::tearDown();
	}
}
