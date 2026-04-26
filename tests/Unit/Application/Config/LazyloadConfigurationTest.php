<?php
/**
 * Unit tests for LazyloadConfiguration.
 *
 * @package Automattic\Liveblog\Tests\Unit\Application\Config
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Application\Config;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * LazyloadConfiguration unit test case.
 *
 * @covers \Automattic\Liveblog\Application\Config\LazyloadConfiguration
 */
final class LazyloadConfigurationTest extends TestCase {

	/**
	 * Configuration under test.
	 *
	 * @var LazyloadConfiguration
	 */
	private LazyloadConfiguration $config;

	/**
	 * Set up test fixtures.
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->config = new LazyloadConfiguration();
	}

	/**
	 * Set up mock for viewing an enabled liveblog post.
	 *
	 * Mocks all the WordPress functions called by is_enabled().
	 */
	private function mock_enabled_liveblog_context(): void {
		// Mock get_the_ID() to return a valid post ID.
		Functions\expect( 'get_the_ID' )
			->zeroOrMoreTimes()
			->andReturn( 123 );

		// Create a mock WP_Post (class is stubbed in wp-stubs.php).
		$mock_post            = new \WP_Post();
		$mock_post->ID        = 123;
		$mock_post->post_type = 'post';

		// Mock get_post() to return our mock post.
		Functions\expect( 'get_post' )
			->zeroOrMoreTimes()
			->with( 123 )
			->andReturn( $mock_post );

		// Mock post_type_supports() to return true.
		Functions\expect( 'post_type_supports' )
			->zeroOrMoreTimes()
			->with( 'post', 'liveblog' )
			->andReturn( true );

		// Mock get_post_meta() to return 'enable' state.
		Functions\expect( 'get_post_meta' )
			->zeroOrMoreTimes()
			->with( 123, 'liveblog', true )
			->andReturn( 'enable' );
	}

	/**
	 * Test is_enabled returns true by default for active liveblog.
	 */
	public function test_is_enabled_returns_true_by_default(): void {
		$this->mock_enabled_liveblog_context();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_enable_lazyloader', true )
			->andReturn( true );

		$this->assertTrue( $this->config->is_enabled() );
	}

	/**
	 * Test is_enabled caches the result.
	 */
	public function test_is_enabled_caches_result(): void {
		$this->mock_enabled_liveblog_context();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_enable_lazyloader', true )
			->andReturn( true );

		// Call twice - should only trigger filter once.
		$this->assertTrue( $this->config->is_enabled() );
		$this->assertTrue( $this->config->is_enabled() );
	}

	/**
	 * Test is_enabled returns false when filter disables it.
	 */
	public function test_is_enabled_returns_false_when_filter_disables(): void {
		$this->mock_enabled_liveblog_context();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_enable_lazyloader', true )
			->andReturn( false );

		$this->assertFalse( $this->config->is_enabled() );
	}

	/**
	 * Test get_initial_entries returns default.
	 */
	public function test_get_initial_entries_returns_default(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES );

		$this->assertSame( LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES, $this->config->get_initial_entries() );
	}

	/**
	 * Test get_initial_entries respects filter.
	 */
	public function test_get_initial_entries_respects_filter(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( 50 );

		$this->assertSame( 50, $this->config->get_initial_entries() );
	}

	/**
	 * Test get_initial_entries returns default for negative values.
	 */
	public function test_get_initial_entries_returns_default_for_negative(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( -5 );

		$this->assertSame( LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES, $this->config->get_initial_entries() );
	}

	/**
	 * Test get_initial_entries allows zero.
	 */
	public function test_get_initial_entries_allows_zero(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( 0 );

		$this->assertSame( 0, $this->config->get_initial_entries() );
	}

	/**
	 * Test get_entries_per_page returns default.
	 */
	public function test_get_entries_per_page_returns_default(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_entries', LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE )
			->andReturn( LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE );

		$this->assertSame( LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE, $this->config->get_entries_per_page() );
	}

	/**
	 * Test get_entries_per_page respects filter.
	 */
	public function test_get_entries_per_page_respects_filter(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_entries', LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE )
			->andReturn( 50 );

		$this->assertSame( 50, $this->config->get_entries_per_page() );
	}

	/**
	 * Test get_entries_per_page caps at maximum.
	 */
	public function test_get_entries_per_page_caps_at_maximum(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_entries', LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE )
			->andReturn( 200 );

		$this->assertSame( LazyloadConfiguration::MAX_ENTRIES_PER_PAGE, $this->config->get_entries_per_page() );
	}

	/**
	 * Test get_entries_per_page returns default for non-positive values.
	 */
	public function test_get_entries_per_page_returns_default_for_non_positive(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_entries', LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE )
			->andReturn( 0 );

		$this->assertSame( LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE, $this->config->get_entries_per_page() );
	}

	/**
	 * Test filter_archive_query_args sets number.
	 */
	public function test_filter_archive_query_args_sets_number(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( 30 );

		$args   = array( 'orderby' => 'date' );
		$result = $this->config->filter_archive_query_args( $args );

		$this->assertSame( 30, $result['number'] );
		$this->assertSame( 'date', $result['orderby'] );
	}

	/**
	 * Test reset clears cached values.
	 */
	public function test_reset_clears_cached_values(): void {
		// First call - caches values.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( 10 );

		$this->assertSame( 10, $this->config->get_initial_entries() );

		// Reset.
		$this->config->reset();

		// Second call - should re-fetch.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'liveblog_number_of_default_entries', LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES )
			->andReturn( 25 );

		$this->assertSame( 25, $this->config->get_initial_entries() );
	}

	/**
	 * Test constants have expected values.
	 */
	public function test_constants(): void {
		$this->assertSame( 20, LazyloadConfiguration::DEFAULT_INITIAL_ENTRIES );
		$this->assertSame( 20, LazyloadConfiguration::DEFAULT_ENTRIES_PER_PAGE );
		$this->assertSame( 100, LazyloadConfiguration::MAX_ENTRIES_PER_PAGE );
	}
}
