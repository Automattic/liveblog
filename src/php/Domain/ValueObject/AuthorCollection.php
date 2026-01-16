<?php
/**
 * Author collection value object.
 *
 * @package Automattic\Liveblog\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\ValueObject;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Represents a collection of authors for a liveblog entry.
 *
 * Handles the distinction between primary author and contributors.
 * Immutable once created.
 *
 * @implements IteratorAggregate<int, Author>
 */
final class AuthorCollection implements Countable, IteratorAggregate {

	/**
	 * The authors in the collection.
	 *
	 * @var Author[]
	 */
	private array $authors;

	/**
	 * Constructor.
	 *
	 * @param Author[] $authors Array of Author objects.
	 */
	private function __construct( array $authors ) {
		$this->authors = array_values(
			array_filter(
				$authors,
				static fn( $author ) => $author instanceof Author
			)
		);
	}

	/**
	 * Create a collection from Author objects.
	 *
	 * The first author is considered the primary author.
	 *
	 * @param Author ...$authors Author objects.
	 * @return self
	 */
	public static function from_authors( Author ...$authors ): self {
		return new self( $authors );
	}

	/**
	 * Create an empty collection.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( array() );
	}

	/**
	 * Create a collection from an array of author data arrays.
	 *
	 * Useful for backwards compatibility with existing code.
	 *
	 * @param array[] $data Array of author data arrays.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$authors = array_map(
			static fn( array $item ) => Author::from_array( $item ),
			$data
		);

		return new self( $authors );
	}

	/**
	 * Get the primary author.
	 *
	 * The primary author is the first author in the collection.
	 *
	 * @return Author|null
	 */
	public function primary(): ?Author {
		return $this->authors[0] ?? null;
	}

	/**
	 * Get the contributors (all authors except the primary).
	 *
	 * @return Author[]
	 */
	public function contributors(): array {
		return array_slice( $this->authors, 1 );
	}

	/**
	 * Get all authors.
	 *
	 * @return Author[]
	 */
	public function all(): array {
		return $this->authors;
	}

	/**
	 * Check if the collection is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return 0 === count( $this->authors );
	}

	/**
	 * Check if the collection has multiple authors.
	 *
	 * @return bool
	 */
	public function has_multiple(): bool {
		return count( $this->authors ) > 1;
	}

	/**
	 * Count the authors in the collection.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->authors );
	}

	/**
	 * Get an iterator for the collection.
	 *
	 * @return Traversable<int, Author>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->authors );
	}

	/**
	 * Convert to array for backwards compatibility.
	 *
	 * Matches the format used by WPCOM_Liveblog_Entry::get_authors().
	 *
	 * @param int $avatar_size Avatar size in pixels.
	 * @return array[]
	 */
	public function to_array( int $avatar_size = Author::DEFAULT_AVATAR_SIZE ): array {
		return array_map(
			static fn( Author $author ) => $author->to_array( $avatar_size ),
			$this->authors
		);
	}

	/**
	 * Convert to schema.org format.
	 *
	 * Returns a single Person object for single authors, or an array of Person
	 * objects for multiple authors (per Google's recommendation).
	 *
	 * @return object|array
	 */
	public function to_schema() {
		if ( $this->is_empty() ) {
			return (object) array(
				'@type' => 'Person',
				'name'  => '',
			);
		}

		if ( ! $this->has_multiple() ) {
			return $this->primary()->to_schema();
		}

		return array_map(
			static fn( Author $author ) => $author->to_schema(),
			$this->authors
		);
	}

	/**
	 * Add an author to the collection.
	 *
	 * Returns a new collection with the author added.
	 *
	 * @param Author $author Author to add.
	 * @return self
	 */
	public function with( Author $author ): self {
		return new self( array_merge( $this->authors, array( $author ) ) );
	}

	/**
	 * Check equality with another collection.
	 *
	 * @param self $other The other collection to compare.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		if ( count( $this->authors ) !== count( $other->authors ) ) {
			return false;
		}

		foreach ( $this->authors as $index => $author ) {
			if ( ! $author->equals( $other->authors[ $index ] ) ) {
				return false;
			}
		}

		return true;
	}
}
