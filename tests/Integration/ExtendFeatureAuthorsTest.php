<?php
/**
 * Tests for the Liveblog Entry Extend Feature Authors class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Entry_Extend_Feature_Authors;

/**
 * Extend Feature Authors test case.
 */
final class ExtendFeatureAuthorsTest extends TestCase {

	/**
	 * Tests the returned config includes the test filter injection and returns an array.
	 *
	 * @covers WPCOM_Liveblog_Entry_Extend_Feature_Authors::get_config()
	 */
	public function test_get_config_filter_executes(): void {
		add_filter( 'liveblog_author_config', array( $this, 'example_test_filter' ), 1, 10 );
		$class  = new WPCOM_Liveblog_Entry_Extend_Feature_Authors();
		$config = array();
		$test   = $class->get_config( $config );

		$this->assertTrue( is_array( $test ) );
		$this->assertArrayHasKey( 'testCase', $test[0] );
		$this->assertTrue( true === $test[0]['testCase'] );
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

	/**
	 * The author autocomplete must not match the search term against user_email.
	 *
	 * Without an explicit search_columns, WP_User_Query searches user_email when
	 * the term contains an '@', turning the picker into a blind email-existence
	 * oracle for users holding `edit_posts` (CWE-203). An email-prefix search must
	 * therefore return nothing, while a nicename / display-name search must still
	 * resolve the user.
	 *
	 * @covers WPCOM_Liveblog_Entry_Extend_Feature_Authors::get_authors()
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

		$feature = new WPCOM_Liveblog_Entry_Extend_Feature_Authors();

		// An email-prefix search must not reveal the user.
		$by_email = array_map( 'intval', wp_list_pluck( $feature->get_authors( 'secret.address@example' ), 'id' ) );
		$this->assertNotContains( (int) $user_id, $by_email, 'Author autocomplete must not match on user_email.' );

		// A legitimate nicename / display-name search must still find the user.
		$by_name = array_map( 'intval', wp_list_pluck( $feature->get_authors( 'liveblog' ), 'id' ) );
		$this->assertContains( (int) $user_id, $by_name, 'Author autocomplete should still match on nicename and display name.' );
	}
}
