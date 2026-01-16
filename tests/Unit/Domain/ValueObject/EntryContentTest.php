<?php
/**
 * Unit tests for EntryContent value object.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\ValueObject;

use Automattic\Liveblog\Domain\ValueObject\EntryContent;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * EntryContent unit test case.
 *
 * @covers \Automattic\Liveblog\Domain\ValueObject\EntryContent
 */
final class EntryContentTest extends TestCase {

	/**
	 * Test from_raw creates content with raw value.
	 */
	public function test_from_raw(): void {
		$content = EntryContent::from_raw( '<p>Test content</p>' );

		$this->assertSame( '<p>Test content</p>', $content->raw() );
	}

	/**
	 * Test empty creates empty content.
	 */
	public function test_empty(): void {
		$content = EntryContent::empty();

		$this->assertSame( '', $content->raw() );
		$this->assertTrue( $content->is_empty() );
	}

	/**
	 * Test rendered returns raw content when no renderer provided.
	 */
	public function test_rendered_without_renderer(): void {
		$content = EntryContent::from_raw( '<p>Test</p>' );

		$this->assertSame( '<p>Test</p>', $content->rendered() );
	}

	/**
	 * Test rendered uses provided renderer.
	 */
	public function test_rendered_with_renderer(): void {
		$content = EntryContent::from_raw( '<p>Test</p>' );
		// phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter -- Simple test renderer, not processing user input.
		$renderer = static fn( string $raw ): string => strtoupper( strip_tags( $raw ) );

		$this->assertSame( 'TEST', $content->rendered( $renderer ) );
	}

	/**
	 * Test plain strips HTML tags.
	 */
	public function test_plain_strips_html(): void {
		$content = EntryContent::from_raw( '<p>Hello <strong>world</strong></p>' );

		$this->assertSame( 'Hello world', $content->plain() );
	}

	/**
	 * Test plain replaces HTML tags with spaces to preserve word boundaries.
	 */
	public function test_plain_preserves_word_boundaries(): void {
		$content = EntryContent::from_raw( '<ul><li>First</li><li>Second</li></ul>' );

		$plain = $content->plain();

		$this->assertStringContainsString( 'First', $plain );
		$this->assertStringContainsString( 'Second', $plain );
		$this->assertStringNotContainsString( 'FirstSecond', $plain );
	}

	/**
	 * Test plain decodes HTML entities.
	 */
	public function test_plain_decodes_entities(): void {
		$content = EntryContent::from_raw( '<p>Tom &amp; Jerry</p>' );

		$this->assertSame( 'Tom & Jerry', $content->plain() );
	}

	/**
	 * Test plain strips /key command (plain text version).
	 */
	public function test_plain_strips_key_command(): void {
		$content = EntryContent::from_raw( '<p>/key Breaking news!</p>' );

		$this->assertStringNotContainsString( '/key', $content->plain() );
		$this->assertStringContainsString( 'Breaking news', $content->plain() );
	}

	/**
	 * Test plain strips /key command (span version).
	 */
	public function test_plain_strips_key_span(): void {
		$content = EntryContent::from_raw(
			'<p><span class="liveblog-command type-key">key</span> Breaking news!</p>'
		);

		$this->assertStringNotContainsString( 'type-key', $content->plain() );
		$this->assertStringContainsString( 'Breaking news', $content->plain() );
	}

	/**
	 * Test is_empty returns true for empty string.
	 */
	public function test_is_empty_for_empty_string(): void {
		$content = EntryContent::from_raw( '' );

		$this->assertTrue( $content->is_empty() );
	}

	/**
	 * Test is_empty returns true for whitespace only.
	 */
	public function test_is_empty_for_whitespace(): void {
		$content = EntryContent::from_raw( '   ' );

		$this->assertTrue( $content->is_empty() );
	}

	/**
	 * Test is_empty returns true for empty HTML tags.
	 */
	public function test_is_empty_for_empty_html(): void {
		$content = EntryContent::from_raw( '<p></p>' );

		$this->assertTrue( $content->is_empty() );
	}

	/**
	 * Test is_empty returns false for content with text.
	 */
	public function test_is_empty_for_content_with_text(): void {
		$content = EntryContent::from_raw( '<p>Hello</p>' );

		$this->assertFalse( $content->is_empty() );
	}

	/**
	 * Test strip_command removes command and returns new instance.
	 */
	public function test_strip_command(): void {
		$original = EntryContent::from_raw( '<p>/key Important update</p>' );
		$stripped = $original->strip_command( 'key' );

		// Original unchanged.
		$this->assertStringContainsString( '/key', $original->raw() );

		// New instance has command stripped.
		$this->assertStringNotContainsString( '/key', $stripped->raw() );
		$this->assertStringContainsString( 'Important update', $stripped->raw() );
	}

	/**
	 * Test strip_command removes span-wrapped command.
	 */
	public function test_strip_command_removes_span(): void {
		$content  = EntryContent::from_raw(
			'<p><span class="liveblog-command type-key">key</span> News</p>'
		);
		$stripped = $content->strip_command( 'key' );

		$this->assertStringNotContainsString( 'type-key', $stripped->raw() );
		$this->assertStringContainsString( 'News', $stripped->raw() );
	}

	/**
	 * Test truncate returns full content when under word limit.
	 */
	public function test_truncate_under_limit(): void {
		$content = EntryContent::from_raw( '<p>Short text</p>' );

		$this->assertSame( 'Short text', $content->truncate( 10 ) );
	}

	/**
	 * Test truncate truncates and adds ellipsis when over word limit.
	 */
	public function test_truncate_over_limit(): void {
		$content = EntryContent::from_raw(
			'<p>This is a very long piece of content that exceeds the word limit</p>'
		);

		$truncated = $content->truncate( 5 );

		$this->assertSame( "This is a very long\u{2026}", $truncated );
	}

	/**
	 * Test truncate with custom ellipsis.
	 */
	public function test_truncate_custom_ellipsis(): void {
		$content = EntryContent::from_raw( '<p>One two three four five six</p>' );

		$truncated = $content->truncate( 3, '...' );

		$this->assertSame( 'One two three...', $truncated );
	}

	/**
	 * Test equals returns true for same content.
	 */
	public function test_equals_same_content(): void {
		$content1 = EntryContent::from_raw( '<p>Same</p>' );
		$content2 = EntryContent::from_raw( '<p>Same</p>' );

		$this->assertTrue( $content1->equals( $content2 ) );
	}

	/**
	 * Test equals returns false for different content.
	 */
	public function test_equals_different_content(): void {
		$content1 = EntryContent::from_raw( '<p>One</p>' );
		$content2 = EntryContent::from_raw( '<p>Two</p>' );

		$this->assertFalse( $content1->equals( $content2 ) );
	}

	/**
	 * Test __toString returns raw content.
	 */
	public function test_to_string(): void {
		$content = EntryContent::from_raw( '<p>Test</p>' );

		$this->assertSame( '<p>Test</p>', (string) $content );
	}
}
