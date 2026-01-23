<?php
/**
 * Tests for schema.org LiveBlogPosting structured data output.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Schema metadata test case.
 *
 * @coversDefaultClass \Automattic\Liveblog\Application\Presenter\MetadataPresenter
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Schema.org properties use camelCase.
 */
final class SchemaMetadataTest extends IntegrationTestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Entry service.
	 *
	 * @var EntryService
	 */
	private EntryService $entry_service;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->entry_service = $this->container()->entry_service();

		// Create a post and enable liveblog.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Liveblog',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
			)
		);

		// Enable liveblog on the post.
		update_post_meta( $this->post_id, LiveblogConfiguration::KEY, 'enable' );

		// Simulate viewing the single post (required for is_singular() checks).
		$this->go_to( get_permalink( $this->post_id ) );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	/**
	 * Test that metadata includes required @context property.
	 *
	 * @covers ::generate
	 */
	public function test_metadata_includes_context(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( '@context', $metadata );
		$this->assertEquals( 'https://schema.org', $metadata['@context'] );
	}

	/**
	 * Test that metadata type is LiveBlogPosting.
	 *
	 * @covers ::generate
	 */
	public function test_metadata_type_is_live_blog_posting(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( '@type', $metadata );
		$this->assertEquals( 'LiveBlogPosting', $metadata['@type'] );
	}

	/**
	 * Test that metadata includes headline from post title.
	 *
	 * @covers ::generate
	 */
	public function test_metadata_includes_headline(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'headline', $metadata );
		$this->assertEquals( 'Test Liveblog', $metadata['headline'] );
	}

	/**
	 * Test that metadata includes coverageStartTime.
	 *
	 * @covers ::generate
	 */
	public function test_metadata_includes_coverage_start_time(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'coverageStartTime', $metadata );
	}

	/**
	 * Test that coverageEndTime is not present when liveblog is active.
	 *
	 * @covers ::generate
	 */
	public function test_coverage_end_time_not_present_when_active(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayNotHasKey( 'coverageEndTime', $metadata );
	}

	/**
	 * Test that coverageEndTime is present when liveblog is archived.
	 *
	 * @covers ::generate
	 */
	public function test_coverage_end_time_present_when_archived(): void {
		// Archive the liveblog.
		update_post_meta( $this->post_id, LiveblogConfiguration::KEY, 'archive' );

		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'coverageEndTime', $metadata );
	}

	/**
	 * Test that entry articleBody contains actual text content.
	 *
	 * @covers ::generate
	 */
	public function test_entry_article_body_contains_text(): void {
		$this->insert_entry( array( 'content' => '<p>This is test content</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertNotEmpty( $metadata['liveBlogUpdate'] );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertIsString( $entry->articleBody );
		$this->assertEquals( 'This is test content', $entry->articleBody );
	}

	/**
	 * Test that /key command is stripped from articleBody.
	 *
	 * @covers ::generate
	 */
	public function test_key_command_stripped_from_article_body(): void {
		$this->insert_entry( array( 'content' => '<p>/key Breaking news!</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertStringNotContainsString( '/key', $entry->articleBody );
		$this->assertStringContainsString( 'Breaking news', $entry->articleBody );
	}

	/**
	 * Test that /key span is stripped from articleBody.
	 *
	 * @covers ::generate
	 */
	public function test_key_span_stripped_from_article_body(): void {
		$this->insert_entry( array( 'content' => '<p><span class="liveblog-command type-key">key</span> Breaking news!</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertStringNotContainsString( 'type-key', $entry->articleBody );
		$this->assertStringContainsString( 'Breaking news', $entry->articleBody );
	}

	/**
	 * Test that HTML tags are replaced with spaces to preserve word boundaries.
	 *
	 * @covers ::generate
	 */
	public function test_html_tags_replaced_with_spaces(): void {
		$this->insert_entry( array( 'content' => '<ul><li>First</li><li>Second</li></ul>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		// Should have space between words, not "FirstSecond".
		$this->assertStringContainsString( 'First', $entry->articleBody );
		$this->assertStringContainsString( 'Second', $entry->articleBody );
		$this->assertStringNotContainsString( 'FirstSecond', $entry->articleBody );
	}

	/**
	 * Test that entries with empty content are skipped.
	 *
	 * @covers ::generate
	 */
	public function test_empty_entries_are_skipped(): void {
		$this->insert_entry( array( 'content' => '<p></p>' ) );
		$this->insert_entry( array( 'content' => '<p>Actual content</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		// Only the non-empty entry should be present.
		$this->assertCount( 1, $metadata['liveBlogUpdate'] );
	}

	/**
	 * Test that multiple authors are listed as array.
	 *
	 * @covers ::generate
	 */
	public function test_multiple_authors_listed_as_array(): void {
		// Create users.
		$user1 = self::factory()->user->create_and_get( array( 'display_name' => 'Author One' ) );
		$user2 = self::factory()->user->create_and_get( array( 'display_name' => 'Author Two' ) );

		// Insert entry with contributor.
		$entry_id = $this->insert_entry(
			array(
				'content' => '<p>Multi-author entry</p>',
				'user'    => $user1,
			)
		);

		// Add contributor meta.
		add_comment_meta( $entry_id->to_int(), 'liveblog_contributors', array( $user2->ID ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$blog_entry = $metadata['liveBlogUpdate'][0];

		// Should be an array of authors.
		$this->assertIsArray( $blog_entry->author );
		$this->assertCount( 2, $blog_entry->author );
	}

	/**
	 * Test that single author is object not array.
	 *
	 * @covers ::generate
	 */
	public function test_single_author_is_object(): void {
		$user = self::factory()->user->create_and_get( array( 'display_name' => 'Single Author' ) );

		$this->insert_entry(
			array(
				'content' => '<p>Single author entry</p>',
				'user'    => $user,
			)
		);

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		// Should be an object with @type Person, not an array.
		$this->assertIsObject( $entry->author );
		$this->assertEquals( 'Person', $entry->author->{'@type'} );
	}

	/**
	 * Test that entry type is BlogPosting.
	 *
	 * @covers ::generate
	 */
	public function test_entry_type_is_blog_posting(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertEquals( 'BlogPosting', $entry->{'@type'} );
	}

	/**
	 * Test that headline is truncated from content.
	 *
	 * @covers ::generate
	 */
	public function test_headline_is_truncated(): void {
		$long_content = '<p>This is a very long piece of content that should be truncated when used as a headline because it exceeds the word limit.</p>';
		$this->insert_entry( array( 'content' => $long_content ) );

		$metadata_presenter = Container::instance()->metadata_presenter();
		$metadata           = $metadata_presenter->generate( get_post( $this->post_id ), array() );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		// Headline should be truncated (10 words) and end with ellipsis.
		$this->assertStringContainsString( '…', $entry->headline );
	}

	/**
	 * Insert a liveblog entry.
	 *
	 * @param array $args Arguments for entry.
	 * @return EntryId The entry ID.
	 */
	private function insert_entry( array $args = array() ): EntryId {
		$user    = $args['user'] ?? self::factory()->user->create_and_get();
		$content = $args['content'] ?? 'Default content';

		return $this->entry_service->create( $this->post_id, $content, $user );
	}
}
