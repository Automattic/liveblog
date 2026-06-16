<?php
/**
 * Tests for schema.org LiveBlogPosting structured data output.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;
use WPCOM_Liveblog_Entry;

/**
 * Schema metadata test case.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Schema.org properties use camelCase.
 */
final class SchemaMetadataTest extends TestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create a post and enable liveblog.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Liveblog',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
			)
		);

		// Enable liveblog on the post.
		update_post_meta( $this->post_id, WPCOM_Liveblog::KEY, 'enable' );

		// Simulate viewing the single post (required for is_singular() checks).
		$this->go_to( get_permalink( $this->post_id ) );

		// Set the static post_id on WPCOM_Liveblog.
		$reflection = new \ReflectionProperty( WPCOM_Liveblog::class, 'post_id' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $this->post_id );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Reset the static post_id on WPCOM_Liveblog.
		$reflection = new \ReflectionProperty( WPCOM_Liveblog::class, 'post_id' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );

		// Reset the static entry_query to ensure fresh queries per test.
		$reflection = new \ReflectionProperty( WPCOM_Liveblog::class, 'entry_query' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );

		parent::tear_down();
	}

	/**
	 * Test that metadata includes required @context property.
	 */
	public function test_metadata_includes_context(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( '@context', $metadata );
		$this->assertEquals( 'https://schema.org', $metadata['@context'] );
	}

	/**
	 * Test that metadata type is LiveBlogPosting.
	 */
	public function test_metadata_type_is_live_blog_posting(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( '@type', $metadata );
		$this->assertEquals( 'LiveBlogPosting', $metadata['@type'] );
	}

	/**
	 * Test that metadata includes headline from post title.
	 */
	public function test_metadata_includes_headline(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'headline', $metadata );
		$this->assertEquals( 'Test Liveblog', $metadata['headline'] );
	}

	/**
	 * Test that metadata includes coverageStartTime.
	 */
	public function test_metadata_includes_coverage_start_time(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'coverageStartTime', $metadata );
	}

	/**
	 * Test that coverageEndTime is not present when liveblog is active.
	 */
	public function test_coverage_end_time_not_present_when_active(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayNotHasKey( 'coverageEndTime', $metadata );
	}

	/**
	 * Test that coverageEndTime is present when liveblog is archived.
	 */
	public function test_coverage_end_time_present_when_archived(): void {
		// Archive the liveblog.
		update_post_meta( $this->post_id, WPCOM_Liveblog::KEY, 'archive' );

		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'coverageEndTime', $metadata );
	}

	/**
	 * Test that entry articleBody contains actual text content.
	 */
	public function test_entry_article_body_contains_text(): void {
		$this->insert_entry( array( 'content' => '<p>This is test content</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertNotEmpty( $metadata['liveBlogUpdate'] );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertIsString( $entry->articleBody );
		$this->assertEquals( 'This is test content', $entry->articleBody );
	}

	/**
	 * Test that /key command is stripped from articleBody.
	 */
	public function test_key_command_stripped_from_article_body(): void {
		$this->insert_entry( array( 'content' => '<p>/key Breaking news!</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertStringNotContainsString( '/key', $entry->articleBody );
		$this->assertStringContainsString( 'Breaking news', $entry->articleBody );
	}

	/**
	 * Test that /key span is stripped from articleBody.
	 */
	public function test_key_span_stripped_from_article_body(): void {
		$this->insert_entry( array( 'content' => '<p><span class="liveblog-command type-key">key</span> Breaking news!</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertStringNotContainsString( 'type-key', $entry->articleBody );
		$this->assertStringContainsString( 'Breaking news', $entry->articleBody );
	}

	/**
	 * Test that HTML tags are replaced with spaces to preserve word boundaries.
	 */
	public function test_html_tags_replaced_with_spaces(): void {
		$this->insert_entry( array( 'content' => '<ul><li>First</li><li>Second</li></ul>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

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
	 */
	public function test_empty_entries_are_skipped(): void {
		$this->insert_entry( array( 'content' => '<p></p>' ) );
		$this->insert_entry( array( 'content' => '<p>Actual content</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		// Only the non-empty entry should be present.
		$this->assertCount( 1, $metadata['liveBlogUpdate'] );
	}

	/**
	 * Test that multiple authors are listed as array.
	 */
	public function test_multiple_authors_listed_as_array(): void {
		// Create users.
		$user1 = self::factory()->user->create_and_get( array( 'display_name' => 'Author One' ) );
		$user2 = self::factory()->user->create_and_get( array( 'display_name' => 'Author Two' ) );

		// Insert entry with contributor.
		$entry = $this->insert_entry(
			array(
				'content' => '<p>Multi-author entry</p>',
				'user'    => $user1,
			)
		);

		// Add contributor meta (using the correct meta key from WPCOM_Liveblog_Entry::CONTRIBUTORS_META_KEY).
		add_comment_meta( $entry->get_id(), 'liveblog_contributors', array( $user2->ID ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$blog_entry = $metadata['liveBlogUpdate'][0];

		// Should be an array of authors.
		$this->assertIsArray( $blog_entry->author );
		$this->assertCount( 2, $blog_entry->author );
	}

	/**
	 * Test that single author is object not array.
	 */
	public function test_single_author_is_object(): void {
		$user = self::factory()->user->create_and_get( array( 'display_name' => 'Single Author' ) );

		$this->insert_entry(
			array(
				'content' => '<p>Single author entry</p>',
				'user'    => $user,
			)
		);

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		// Should be an object with @type Person, not an array.
		$this->assertIsObject( $entry->author );
		$this->assertEquals( 'Person', $entry->author->{'@type'} );
	}

	/**
	 * Test that entry type is BlogPosting.
	 */
	public function test_entry_type_is_blog_posting(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		$this->assertEquals( 'BlogPosting', $entry->{'@type'} );
	}

	/**
	 * Test that headline is truncated from content.
	 */
	public function test_headline_is_truncated(): void {
		$long_content = '<p>This is a very long piece of content that should be truncated when used as a headline because it exceeds the word limit.</p>';
		$this->insert_entry( array( 'content' => $long_content ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'], 'liveBlogUpdate should not be empty' );
		$entry = $metadata['liveBlogUpdate'][0];

		// Headline should be truncated (10 words) and end with ellipsis.
		$this->assertStringContainsString( '…', $entry->headline );
	}

	/**
	 * Test that no entry metadata is emitted for a password-protected post.
	 *
	 * WordPress renders the password form (not the content) to visitors who have not
	 * supplied the password, so the JSON-LD/AMP metadata must not disclose the protected
	 * entries. Covers HackerOne report #3710276 (Vector 2, structured-data side channel).
	 */
	public function test_no_entries_for_password_protected_post_without_password(): void {
		wp_update_post(
			array(
				'ID'            => $this->post_id,
				'post_password' => 'secret-3710276',
			)
		);

		$this->insert_entry( array( 'content' => '<p>Protected entry body</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		// The filter bails before adding any liveblog metadata, so the passed-in array is
		// returned untouched: no entry bodies and none of the LiveBlogPosting properties.
		$this->assertArrayNotHasKey( 'liveBlogUpdate', $metadata );
		$this->assertArrayNotHasKey( '@type', $metadata );
	}

	/**
	 * Test that entry metadata is emitted once the password requirement is satisfied.
	 *
	 * The `post_password_required` filter stands in for a visitor who has supplied the
	 * correct password (the same mechanism WordPress core consults), keeping the test
	 * independent of cookie-hashing internals.
	 */
	public function test_entries_present_for_password_protected_post_with_password(): void {
		wp_update_post(
			array(
				'ID'            => $this->post_id,
				'post_password' => 'secret-3710276',
			)
		);

		$this->insert_entry( array( 'content' => '<p>Protected entry body</p>' ) );

		add_filter( 'post_password_required', '__return_false' );
		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertArrayHasKey( 'liveBlogUpdate', $metadata );
		$this->assertNotEmpty( $metadata['liveBlogUpdate'] );
	}

	/**
	 * Test that metadata generation does not fatal for an unpublished post.
	 *
	 * Drafts (and pending/auto-draft posts) store a `0000-00-00 00:00:00` GMT
	 * date, for which get_post_datetime() returns false. Previously this caused
	 * a fatal `Call to a member function format() on false` when such a post was
	 * previewed. See https://github.com/Automattic/liveblog/issues/919.
	 */
	public function test_metadata_omits_dates_for_unpublished_post(): void {
		$draft_id = self::factory()->post->create(
			array(
				'post_title'  => 'Draft Liveblog',
				'post_status' => 'draft',
			)
		);
		update_post_meta( $draft_id, WPCOM_Liveblog::KEY, 'enable' );

		// Confirm the fixture reproduces the original conditions: the GMT date is
		// unavailable, so get_post_datetime() returns false.
		$this->assertFalse( get_post_datetime( $draft_id, 'date', 'gmt' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $draft_id ) );

		// The metadata is still generated (proving the date code did not fatal)...
		$this->assertEquals( 'LiveBlogPosting', $metadata['@type'] );

		// ...but the date-derived properties are omitted rather than fatalling.
		$this->assertArrayNotHasKey( 'datePublished', $metadata );
		$this->assertArrayNotHasKey( 'dateModified', $metadata );
		$this->assertArrayNotHasKey( 'coverageStartTime', $metadata );
	}

	/**
	 * Test that a published post still includes the date-derived properties.
	 */
	public function test_metadata_includes_dates_for_published_post(): void {
		$this->insert_entry( array( 'content' => '<p>Test entry</p>' ) );

		$metadata = WPCOM_Liveblog::get_liveblog_metadata( array(), get_post( $this->post_id ) );

		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );
		$this->assertArrayHasKey( 'coverageStartTime', $metadata );
	}

	/**
	 * Insert a liveblog entry.
	 *
	 * @param array $args Arguments for entry.
	 * @return WPCOM_Liveblog_Entry
	 */
	private function insert_entry( array $args = array() ): WPCOM_Liveblog_Entry {
		$entry = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		return $entry;
	}

	/**
	 * Build entry args.
	 *
	 * @param array $args Arguments to merge.
	 * @return array
	 */
	private function build_entry_args( array $args = array() ): array {
		$user     = $args['user'] ?? self::factory()->user->create_and_get();
		$defaults = array(
			'post_id' => $this->post_id,
			'content' => 'Default content',
			'user'    => $user,
		);
		return array_merge( $defaults, $args );
	}
}
