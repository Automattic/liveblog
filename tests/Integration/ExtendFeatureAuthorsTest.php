<?php
/**
 * Tests for the AuthorFilter class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Filter\AuthorFilter;

/**
 * Author Filter test case.
 *
 * @covers \Automattic\Liveblog\Application\Filter\AuthorFilter
 */
final class ExtendFeatureAuthorsTest extends IntegrationTestCase {

	/**
	 * Tests the returned config includes the test filter injection and returns an array.
	 *
	 * @covers \Automattic\Liveblog\Application\Filter\AuthorFilter::get_autocomplete_config()
	 */
	public function test_get_autocomplete_config_filter_executes(): void {
		add_filter( 'liveblog_author_config', array( $this, 'example_test_filter' ), 1, 10 );

		// Create filter directly - stateless, no container needed.
		$filter = new AuthorFilter();
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
	 * The author autocomplete must not match the search term against user_email.
	 *
	 * Without an explicit search_columns, WP_User_Query searches user_email when
	 * the term contains an '@', turning the picker into a blind email-existence
	 * oracle for users holding `edit_posts` (CWE-203). An email-prefix search must
	 * therefore return nothing, while a nicename / display-name search must still
	 * resolve the user.
	 *
	 * @covers \Automattic\Liveblog\Application\Filter\AuthorFilter::get_authors()
	 */
	public function test_get_authors_does_not_match_on_email(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'liveblogeditor',
				'user_email'    => 'secret.address@example.com',
				'display_name'  => 'Liveblog Editor',
				'user_nicename' => 'liveblog-editor',
			)
		);

		$filter = new AuthorFilter();

		// An email-prefix search must not reveal the user.
		$by_email = array_map( 'intval', wp_list_pluck( $filter->get_authors( 'secret.address@example' ), 'id' ) );
		$this->assertNotContains( (int) $user_id, $by_email, 'Author autocomplete must not match on user_email.' );

		// A legitimate nicename / display-name search must still find the user.
		$by_name = array_map( 'intval', wp_list_pluck( $filter->get_authors( 'liveblog' ), 'id' ) );
		$this->assertContains( (int) $user_id, $by_name, 'Author autocomplete should still match on nicename and display name.' );
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
