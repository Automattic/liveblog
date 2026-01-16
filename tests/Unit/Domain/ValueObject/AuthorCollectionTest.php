<?php
/**
 * Unit tests for AuthorCollection value object.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\ValueObject;

use Automattic\Liveblog\Domain\ValueObject\Author;
use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * AuthorCollection unit test case.
 *
 * @covers \Automattic\Liveblog\Domain\ValueObject\AuthorCollection
 */
final class AuthorCollectionTest extends TestCase {

	/**
	 * Test from_authors creates collection.
	 */
	public function test_from_authors(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);

		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$this->assertCount( 2, $collection );
	}

	/**
	 * Test empty creates empty collection.
	 */
	public function test_empty(): void {
		$collection = AuthorCollection::empty();

		$this->assertTrue( $collection->is_empty() );
		$this->assertCount( 0, $collection );
	}

	/**
	 * Test from_array creates collection from array data.
	 */
	public function test_from_array(): void {
		$data = array(
			array(
				'id'   => 1,
				'name' => 'First',
			),
			array(
				'id'   => 2,
				'name' => 'Second',
			),
		);

		$collection = AuthorCollection::from_array( $data );

		$this->assertCount( 2, $collection );
		$this->assertSame( 'First', $collection->primary()->name() );
	}

	/**
	 * Test primary returns first author.
	 */
	public function test_primary(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Primary',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Secondary',
			) 
		);

		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$this->assertSame( 'Primary', $collection->primary()->name() );
	}

	/**
	 * Test primary returns null for empty collection.
	 */
	public function test_primary_empty(): void {
		$collection = AuthorCollection::empty();

		$this->assertNull( $collection->primary() );
	}

	/**
	 * Test contributors returns all except first.
	 */
	public function test_contributors(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Primary',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Contrib1',
			) 
		);
		$author3 = Author::from_array(
			array(
				'id'   => 3,
				'name' => 'Contrib2',
			) 
		);

		$collection = AuthorCollection::from_authors( $author1, $author2, $author3 );

		$contributors = $collection->contributors();

		$this->assertCount( 2, $contributors );
		$this->assertSame( 'Contrib1', $contributors[0]->name() );
		$this->assertSame( 'Contrib2', $contributors[1]->name() );
	}

	/**
	 * Test contributors returns empty for single author.
	 */
	public function test_contributors_single_author(): void {
		$author     = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Alone',
			) 
		);
		$collection = AuthorCollection::from_authors( $author );

		$this->assertCount( 0, $collection->contributors() );
	}

	/**
	 * Test all returns all authors.
	 */
	public function test_all(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);

		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$all = $collection->all();

		$this->assertCount( 2, $all );
		$this->assertSame( 'One', $all[0]->name() );
		$this->assertSame( 'Two', $all[1]->name() );
	}

	/**
	 * Test is_empty returns true for empty collection.
	 */
	public function test_is_empty_true(): void {
		$this->assertTrue( AuthorCollection::empty()->is_empty() );
	}

	/**
	 * Test is_empty returns false for non-empty collection.
	 */
	public function test_is_empty_false(): void {
		$author     = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Test',
			) 
		);
		$collection = AuthorCollection::from_authors( $author );

		$this->assertFalse( $collection->is_empty() );
	}

	/**
	 * Test has_multiple returns true for multiple authors.
	 */
	public function test_has_multiple_true(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);

		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$this->assertTrue( $collection->has_multiple() );
	}

	/**
	 * Test has_multiple returns false for single author.
	 */
	public function test_has_multiple_false(): void {
		$author     = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Single',
			) 
		);
		$collection = AuthorCollection::from_authors( $author );

		$this->assertFalse( $collection->has_multiple() );
	}

	/**
	 * Test count returns correct count.
	 */
	public function test_count(): void {
		$author1    = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2    = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);
		$author3    = Author::from_array(
			array(
				'id'   => 3,
				'name' => 'Three',
			) 
		);
		$collection = AuthorCollection::from_authors( $author1, $author2, $author3 );

		$this->assertCount( 3, $collection );
		$this->assertSame( 3, $collection->count() );
	}

	/**
	 * Test collection is iterable.
	 */
	public function test_iterable(): void {
		$author1    = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2    = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);
		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$names = array();
		foreach ( $collection as $author ) {
			$names[] = $author->name();
		}

		$this->assertSame( array( 'One', 'Two' ), $names );
	}

	/**
	 * Test to_array returns expected format.
	 */
	public function test_to_array(): void {
		Functions\expect( 'get_avatar' )
			->twice()
			->andReturn( '<img />' );

		$author1    = Author::from_array(
			array(
				'id'    => 1,
				'key'   => 'one',
				'name'  => 'One',
				'email' => 'one@example.com',
			)
		);
		$author2    = Author::from_array(
			array(
				'id'    => 2,
				'key'   => 'two',
				'name'  => 'Two',
				'email' => 'two@example.com',
			)
		);
		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$array = $collection->to_array();

		$this->assertCount( 2, $array );
		$this->assertSame( 'One', $array[0]['name'] );
		$this->assertSame( 'Two', $array[1]['name'] );
	}

	/**
	 * Test to_schema returns single object for single author.
	 */
	public function test_to_schema_single_author(): void {
		$author     = Author::from_array( array( 'name' => 'Single' ) );
		$collection = AuthorCollection::from_authors( $author );

		$schema = $collection->to_schema();

		$this->assertIsObject( $schema );
		$this->assertSame( 'Person', $schema->{'@type'} );
		$this->assertSame( 'Single', $schema->name );
	}

	/**
	 * Test to_schema returns array for multiple authors.
	 */
	public function test_to_schema_multiple_authors(): void {
		$author1    = Author::from_array( array( 'name' => 'One' ) );
		$author2    = Author::from_array( array( 'name' => 'Two' ) );
		$collection = AuthorCollection::from_authors( $author1, $author2 );

		$schema = $collection->to_schema();

		$this->assertIsArray( $schema );
		$this->assertCount( 2, $schema );
		$this->assertSame( 'One', $schema[0]->name );
		$this->assertSame( 'Two', $schema[1]->name );
	}

	/**
	 * Test to_schema returns empty Person for empty collection.
	 */
	public function test_to_schema_empty(): void {
		$collection = AuthorCollection::empty();

		$schema = $collection->to_schema();

		$this->assertIsObject( $schema );
		$this->assertSame( 'Person', $schema->{'@type'} );
		$this->assertSame( '', $schema->name );
	}

	/**
	 * Test with adds author and returns new collection.
	 */
	public function test_with(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'Original',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Added',
			) 
		);

		$original = AuthorCollection::from_authors( $author1 );
		$modified = $original->with( $author2 );

		// Original unchanged.
		$this->assertCount( 1, $original );

		// New collection has both.
		$this->assertCount( 2, $modified );
		$this->assertSame( 'Added', $modified->all()[1]->name() );
	}

	/**
	 * Test equals returns true for same authors.
	 */
	public function test_equals_true(): void {
		$author1 = Author::from_array(
			array(
				'id'    => 1,
				'name'  => 'Same',
				'email' => 'same@example.com',
			)
		);
		$author2 = Author::from_array(
			array(
				'id'    => 1,
				'name'  => 'Same',
				'email' => 'same@example.com',
			)
		);

		$collection1 = AuthorCollection::from_authors( $author1 );
		$collection2 = AuthorCollection::from_authors( $author2 );

		$this->assertTrue( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test equals returns false for different counts.
	 */
	public function test_equals_false_different_count(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);

		$collection1 = AuthorCollection::from_authors( $author1 );
		$collection2 = AuthorCollection::from_authors( $author1, $author2 );

		$this->assertFalse( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test equals returns false for different authors.
	 */
	public function test_equals_false_different_authors(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			) 
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			) 
		);

		$collection1 = AuthorCollection::from_authors( $author1 );
		$collection2 = AuthorCollection::from_authors( $author2 );

		$this->assertFalse( $collection1->equals( $collection2 ) );
	}
}
