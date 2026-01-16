<?php
/**
 * Entry ID value object.
 *
 * @package Automattic\Liveblog\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Represents a liveblog entry identifier.
 *
 * Provides type safety for entry IDs and ensures they are always valid positive integers.
 */
final class EntryId {

	/**
	 * The ID value.
	 *
	 * @var int
	 */
	private int $value;

	/**
	 * Constructor.
	 *
	 * @param int $value The entry ID.
	 * @throws InvalidArgumentException If ID is not positive.
	 */
	private function __construct( int $value ) {
		if ( $value <= 0 ) {
			throw new InvalidArgumentException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer value in developer-facing exception.
				sprintf( 'Entry ID must be a positive integer, got %d', $value )
			);
		}

		$this->value = $value;
	}

	/**
	 * Create an EntryId from an integer.
	 *
	 * @param int $id The entry ID.
	 * @return self
	 * @throws InvalidArgumentException If ID is not positive.
	 */
	public static function from_int( int $id ): self {
		return new self( $id );
	}

	/**
	 * Get the ID as an integer.
	 *
	 * @return int
	 */
	public function to_int(): int {
		return $this->value;
	}

	/**
	 * Check equality with another EntryId.
	 *
	 * @param self $other The other EntryId to compare.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Get the ID as a string.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return (string) $this->value;
	}
}
