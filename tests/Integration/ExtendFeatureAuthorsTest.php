<?php
/**
 * Tests for the AuthorFilter class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Filter\AuthorFilter;
use Automattic\Liveblog\Infrastructure\ServiceContainer;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Author Filter test case.
 *
 * @covers \Automattic\Liveblog\Application\Filter\AuthorFilter
 */
final class ExtendFeatureAuthorsTest extends TestCase {

	/**
	 * Tests the returned config includes the test filter injection and returns an array.
	 *
	 * @covers \Automattic\Liveblog\Application\Filter\AuthorFilter::get_autocomplete_config()
	 */
	public function test_get_autocomplete_config_filter_executes(): void {
		add_filter( 'liveblog_author_config', array( $this, 'example_test_filter' ), 1, 10 );

		$filter = ServiceContainer::instance()->author_filter();
		$config = $filter->get_autocomplete_config();

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'testCase', $config );
		$this->assertTrue( $config['testCase'] );
	}

	/**
	 * Tests that the filter name is correct.
	 */
	public function test_filter_name(): void {
		$filter = new AuthorFilter();

		$this->assertSame( 'authors', $filter->get_name() );
	}

	/**
	 * Tests that the default prefix is @.
	 */
	public function test_default_prefix(): void {
		$filter   = new AuthorFilter();
		$prefixes = $filter->get_prefixes();

		$this->assertContains( '@', $prefixes );
	}

	/**
	 * Defines a test filter to check filters are being executed correctly.
	 *
	 * @param mixed $example The example value.
	 * @return mixed The modified value.
	 */
	public function example_test_filter( $example ) {
		if ( is_array( $example ) ) {
			$example['testCase'] = true;
		} elseif ( is_string( $example ) ) {
			$example = 'testCase';
		}
		return $example;
	}
}
