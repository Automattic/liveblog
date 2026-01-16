<?php
/**
 * Unit tests for EntryType value object.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\ValueObject;

use Automattic\Liveblog\Domain\ValueObject\EntryType;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * EntryType unit test case.
 *
 * @covers \Automattic\Liveblog\Domain\ValueObject\EntryType
 */
final class EntryTypeTest extends TestCase {

	/**
	 * Test that New type has correct value.
	 */
	public function test_new_type_value(): void {
		$this->assertSame( 'new', EntryType::new_type()->value );
	}

	/**
	 * Test that Update type has correct value.
	 */
	public function test_update_type_value(): void {
		$this->assertSame( 'update', EntryType::update()->value );
	}

	/**
	 * Test that Delete type has correct value.
	 */
	public function test_delete_type_value(): void {
		$this->assertSame( 'delete', EntryType::delete()->value );
	}

	/**
	 * Test that from_replaces_and_content returns New when no replaces ID.
	 */
	public function test_from_replaces_and_content_returns_new_when_no_replaces(): void {
		$type = EntryType::from_replaces_and_content( null, 'Some content' );

		$this->assertSame( EntryType::new_type(), $type );
	}

	/**
	 * Test that from_replaces_and_content returns Update when replaces ID and content.
	 */
	public function test_from_replaces_and_content_returns_update_when_has_both(): void {
		$type = EntryType::from_replaces_and_content( 123, 'Updated content' );

		$this->assertSame( EntryType::update(), $type );
	}

	/**
	 * Test that from_replaces_and_content returns Delete when replaces ID but no content.
	 */
	public function test_from_replaces_and_content_returns_delete_when_empty_content(): void {
		$type = EntryType::from_replaces_and_content( 123, '' );

		$this->assertSame( EntryType::delete(), $type );
	}

	/**
	 * Test that from_replaces_and_content returns New when zero replaces ID.
	 */
	public function test_from_replaces_and_content_returns_new_when_zero_replaces(): void {
		$type = EntryType::from_replaces_and_content( 0, 'Some content' );

		$this->assertSame( EntryType::new_type(), $type );
	}

	/**
	 * Test is_new method.
	 */
	public function test_is_new(): void {
		$this->assertTrue( EntryType::new_type()->is_new() );
		$this->assertFalse( EntryType::update()->is_new() );
		$this->assertFalse( EntryType::delete()->is_new() );
	}

	/**
	 * Test is_update method.
	 */
	public function test_is_update(): void {
		$this->assertFalse( EntryType::new_type()->is_update() );
		$this->assertTrue( EntryType::update()->is_update() );
		$this->assertFalse( EntryType::delete()->is_update() );
	}

	/**
	 * Test is_delete method.
	 */
	public function test_is_delete(): void {
		$this->assertFalse( EntryType::new_type()->is_delete() );
		$this->assertFalse( EntryType::update()->is_delete() );
		$this->assertTrue( EntryType::delete()->is_delete() );
	}
}
