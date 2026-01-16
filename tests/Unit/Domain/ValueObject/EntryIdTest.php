<?php
/**
 * Unit tests for EntryId value object.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\ValueObject;

use Automattic\Liveblog\Domain\ValueObject\EntryId;
use InvalidArgumentException;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * EntryId unit test case.
 *
 * @covers \Automattic\Liveblog\Domain\ValueObject\EntryId
 */
final class EntryIdTest extends TestCase {

	/**
	 * Test that from_int creates EntryId with valid positive ID.
	 */
	public function test_from_int_with_valid_id(): void {
		$id = EntryId::from_int( 123 );

		$this->assertSame( 123, $id->to_int() );
	}

	/**
	 * Test that from_int throws exception for zero ID.
	 */
	public function test_from_int_throws_exception_for_zero(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Entry ID must be a positive integer, got 0' );

		EntryId::from_int( 0 );
	}

	/**
	 * Test that from_int throws exception for negative ID.
	 */
	public function test_from_int_throws_exception_for_negative(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Entry ID must be a positive integer, got -5' );

		EntryId::from_int( -5 );
	}

	/**
	 * Test to_int returns the integer value.
	 */
	public function test_to_int(): void {
		$id = EntryId::from_int( 456 );

		$this->assertSame( 456, $id->to_int() );
	}

	/**
	 * Test equals returns true for same ID.
	 */
	public function test_equals_returns_true_for_same_id(): void {
		$id1 = EntryId::from_int( 100 );
		$id2 = EntryId::from_int( 100 );

		$this->assertTrue( $id1->equals( $id2 ) );
	}

	/**
	 * Test equals returns false for different IDs.
	 */
	public function test_equals_returns_false_for_different_ids(): void {
		$id1 = EntryId::from_int( 100 );
		$id2 = EntryId::from_int( 200 );

		$this->assertFalse( $id1->equals( $id2 ) );
	}

	/**
	 * Test __toString returns string representation.
	 */
	public function test_to_string(): void {
		$id = EntryId::from_int( 789 );

		$this->assertSame( '789', (string) $id );
	}
}
