<?php
/**
 * Tests for the Liveblog Entry class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_Entry_Query;
use ReflectionProperty;

/**
 * Entry test case.
 */
final class EntryTest extends TestCase {

	/**
	 * Test that constructor sets replaces if there is replace meta.
	 */
	public function test_constructor_should_set_replace_if_there_is_replace_meta(): void {
		$comment = $this->create_and_get_comment_with_replaces( 5 );
		$entry   = new WPCOM_Liveblog_Entry( $comment );
		$this->assertEquals( 5, $entry->replaces );
	}

	/**
	 * Test that constructor sets replaces to false if no replace meta.
	 */
	public function test_constructor_should_set_replaces_to_false_if_no_replace_meta(): void {
		$comment = self::factory()->comment->create_and_get();
		$entry   = new WPCOM_Liveblog_Entry( $comment );
		$this->assertTrue( ! $entry->replaces );
	}

	/**
	 * Test that insert returns an entry.
	 */
	public function test_insert_should_return_entry(): void {
		$entry = $this->insert_entry();
		$this->assertInstanceOf( WPCOM_Liveblog_Entry::class, $entry );
	}

	/**
	 * Test that insert returns entry with type new.
	 */
	public function test_insert_should_return_entry_with_type_new(): void {
		$entry = $this->insert_entry();
		$this->assertEquals( 'new', $entry->get_type() );
	}

	/**
	 * Test that insert fires liveblog_insert_entry hook.
	 */
	public function test_insert_should_fire_liveblog_insert_entry(): void {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_insert_entry', array( self::class, 'set_liveblog_hook_fired' ) );
		$this->insert_entry();
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	/**
	 * Test that update replaces the content in the query.
	 */
	public function test_update_should_replace_the_content_in_the_query(): void {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
	}

	/**
	 * Test that update returns entry with type update.
	 */
	public function test_update_should_return_entry_with_type_update(): void {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertEquals( 'update', $update_entry->get_type() );
	}

	/**
	 * Test that update fires liveblog_update_entry hook.
	 */
	public function test_update_should_fire_liveblog_update_entry(): void {
		unset( $GLOBALS['liveblog_hook_fired'] );
		add_action( 'liveblog_update_entry', array( self::class, 'set_liveblog_hook_fired' ) );
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$this->assertTrue( isset( $GLOBALS['liveblog_hook_fired'] ) );
	}

	/**
	 * Test that update updates the original entry.
	 */
	public function test_update_should_update_original_entry(): void {
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::update(
			$this->build_entry_args(
				array(
					'entry_id' => $entry->get_id(),
					'content'  => 'updated',
				)
			)
		);
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertEquals( 'updated', $query->get_by_id( $entry->get_id() )->get_content() );
	}

	/**
	 * Test that delete replaces the content in the query.
	 */
	public function test_delete_should_replace_the_content_in_the_query(): void {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$this->assertEquals( $entry->get_id(), $update_entry->replaces );
		$this->assertEquals( '', $update_entry->get_content() );
	}

	/**
	 * Test that delete returns entry with type delete.
	 */
	public function test_delete_should_return_entry_with_type_delete(): void {
		$entry        = $this->insert_entry();
		$update_entry = WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$this->assertEquals( 'delete', $update_entry->get_type() );
	}

	/**
	 * Test that delete deletes the original entry.
	 */
	public function test_delete_should_delete_original_entry(): void {
		$entry = $this->insert_entry();
		WPCOM_Liveblog_Entry::delete( $this->build_entry_args( array( 'entry_id' => $entry->get_id() ) ) );
		$query = new WPCOM_Liveblog_Entry_Query( $entry->get_post_id(), 'liveblog' );
		$this->assertNull( $query->get_by_id( $entry->get_id() ) );
	}

	/**
	 * Test that dangerous script tags are stripped by wp_filter_post_kses().
	 */
	public function test_user_input_sanity_check(): void {
		// Test that dangerous script tags are stripped by wp_filter_post_kses()
		// Note: embed and object tags are allowed in WordPress 'post' context.
		$user_input      = '<script>alert("xss")</script>';
		$user_input     .= '<applet code="malicious"></applet>';
		$user_input     .= '<form><input name="test"></form>';
		$content         = array(
			'post_id' => 1,
			'content' => $user_input,
		);
		$live_blog_entry = $this->insert_entry( $content );
		// Content should be empty or significantly sanitized (scripts/applets/forms removed).
		$sanitized_content = $live_blog_entry->get_content();
		$this->assertStringNotContainsString( '<script', $sanitized_content );
		$this->assertStringNotContainsString( '<applet', $sanitized_content );
		$this->assertStringNotContainsString( '<form', $sanitized_content );
	}

	/**
	 * Test that shortcodes are excluded from entry.
	 *
	 * Test to ensure that all [shortcode] formats are stripped.
	 * Uses the default exclusion [liveblog_key_events] which should
	 * be replaced with "We Are Blogging Live! Check Out The Key Events in The Sidebar"
	 * if successful.
	 *
	 * @author  Olly Warren, Big Bite Creative
	 */
	public function test_shortcode_excluded_from_entry(): void {
		// Insert a new entries with a shortcode body content to test each type of shortcode format.
		$formats = array(
			'[liveblog_key_events]',
			'[liveblog_key_events][/liveblog_key_events]',
			'[liveblog_key_events arg="30"]',
			'[liveblog_key_events arg="30"][/liveblog_key_events]',
			'[liveblog_key_events]Test Input Inbetween Tags[/liveblog_key_events]',
			'[liveblog_key_events arg="30"]Test Input Inbetween Tags[/liveblog_key_events]',
		);

		// Loop through each format and create a new comment to check if it gets stripped before hitting the DB.
		foreach ( $formats as $shortcode ) {
			// Create a new entry.
			$entry = $this->insert_entry( array( 'content' => $shortcode ) );

			// Lets setup a Reflection class so we can access the private object properties and check our comment body.
			$comment = new ReflectionProperty( $entry, 'comment' );
			$comment->setAccessible( true );
			$comment_content = $comment->getValue( $entry );

			// Define a check variable and see if the returned object content has been set as the default string replacement.
			$check = '' === $comment_content->comment_content;

			// Assert we have a match. If we do then the shortcode was successfully stripped.
			$this->assertTrue( $check );
		}
	}

	/**
	 * Set liveblog hook fired global.
	 */
	public static function set_liveblog_hook_fired(): void {
		$GLOBALS['liveblog_hook_fired'] = true;
	}

	/**
	 * Insert a liveblog entry.
	 *
	 * @param array $args Arguments for entry.
	 * @return WPCOM_Liveblog_Entry
	 */
	private function insert_entry( array $args = array() ): WPCOM_Liveblog_Entry {
		$entry = WPCOM_Liveblog_Entry::insert( $this->build_entry_args( $args ) );
		return $entry;
	}

	/**
	 * Build entry args.
	 *
	 * @param array $args Arguments to merge.
	 * @return array
	 */
	private function build_entry_args( array $args = array() ): array {
		$user     = self::factory()->user->create_and_get();
		$defaults = array(
			'post_id' => 1,
			'content' => 'baba',
			'user'    => $user,
		);
		return array_merge( $defaults, $args );
	}

	/**
	 * Test that get_comment_date_gmt returns correct Unix timestamp.
	 *
	 * This tests the timezone fix where mysql2date() was replaced with
	 * DateTimeImmutable to avoid timezone conversion issues.
	 */
	public function test_get_comment_date_gmt_returns_correct_unix_timestamp(): void {
		// Create a comment with a known GMT date.
		$gmt_date = '2024-06-15 14:30:00';
		$comment  = self::factory()->comment->create_and_get(
			array(
				'comment_date_gmt' => $gmt_date,
			)
		);

		$entry = new WPCOM_Liveblog_Entry( $comment );

		// Get the Unix timestamp.
		$timestamp = $entry->get_comment_date_gmt( 'U', $comment->comment_ID );

		// The expected timestamp for 2024-06-15 14:30:00 UTC.
		$expected = ( new \DateTimeImmutable( $gmt_date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();

		$this->assertEquals( $expected, $timestamp );
	}

	/**
	 * Test that get_comment_date_gmt with 'G' format returns correct timestamp.
	 */
	public function test_get_comment_date_gmt_with_g_format_returns_correct_timestamp(): void {
		$gmt_date = '2024-12-25 08:00:00';
		$comment  = self::factory()->comment->create_and_get(
			array(
				'comment_date_gmt' => $gmt_date,
			)
		);

		$entry     = new WPCOM_Liveblog_Entry( $comment );
		$timestamp = $entry->get_comment_date_gmt( 'G', $comment->comment_ID );

		$expected = ( new \DateTimeImmutable( $gmt_date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();

		$this->assertEquals( $expected, $timestamp );
	}

	/**
	 * Test that get_comment_date_gmt still works with other formats.
	 */
	public function test_get_comment_date_gmt_with_date_format_returns_formatted_string(): void {
		$gmt_date = '2024-06-15 14:30:00';
		$comment  = self::factory()->comment->create_and_get(
			array(
				'comment_date_gmt' => $gmt_date,
			)
		);

		$entry = new WPCOM_Liveblog_Entry( $comment );
		$date  = $entry->get_comment_date_gmt( 'Y-m-d', $comment->comment_ID );

		$this->assertEquals( '2024-06-15', $date );
	}

	/**
	 * Test that filter_image_attributes preserves only src and alt by default.
	 */
	public function test_filter_image_attributes_default(): void {
		$content  = '<p>Text</p><img src="test.jpg" alt="Test" class="wp-image" width="100" height="50" data-id="123">';
		$filtered = WPCOM_Liveblog_Entry::filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );
		$this->assertStringNotContainsString( 'width=', $filtered );
		$this->assertStringNotContainsString( 'height=', $filtered );
		$this->assertStringNotContainsString( 'data-id=', $filtered );
		$this->assertStringContainsString( '<p>Text</p>', $filtered );
	}

	/**
	 * Test that filter_image_attributes allows additional attributes via filter.
	 */
	public function test_filter_image_attributes_with_filter(): void {
		add_filter(
			'liveblog_image_allowed_attributes',
			function ( $attrs ) {
				return array_merge( $attrs, array( 'class', 'width', 'height' ) );
			}
		);

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" width="100" height="50" data-id="123">';
		$filtered = WPCOM_Liveblog_Entry::filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringContainsString( 'class="wp-image"', $filtered );
		$this->assertStringContainsString( 'width="100"', $filtered );
		$this->assertStringContainsString( 'height="50"', $filtered );
		$this->assertStringNotContainsString( 'data-id=', $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes supports wildcard patterns.
	 */
	public function test_filter_image_attributes_with_wildcard_pattern(): void {
		add_filter(
			'liveblog_image_allowed_attributes',
			function () {
				return array( 'src', 'alt', 'data-*' );
			}
		);

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" data-id="123" data-size="large">';
		$filtered = WPCOM_Liveblog_Entry::filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="test.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Test"', $filtered );
		$this->assertStringContainsString( 'data-id="123"', $filtered );
		$this->assertStringContainsString( 'data-size="large"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes allows all attributes with wildcard.
	 */
	public function test_filter_image_attributes_allow_all(): void {
		add_filter( 'liveblog_image_allowed_attributes', fn() => array( '*' ) );

		$content  = '<img src="test.jpg" alt="Test" class="wp-image" width="100" data-id="123">';
		$filtered = WPCOM_Liveblog_Entry::filter_image_attributes( $content );

		// Content should be unchanged.
		$this->assertEquals( $content, $filtered );

		remove_all_filters( 'liveblog_image_allowed_attributes' );
	}

	/**
	 * Test that filter_image_attributes handles multiple images.
	 */
	public function test_filter_image_attributes_multiple_images(): void {
		$content  = '<img src="one.jpg" alt="One" class="first"><p>Text</p><img src="two.jpg" alt="Two" width="200">';
		$filtered = WPCOM_Liveblog_Entry::filter_image_attributes( $content );

		$this->assertStringContainsString( 'src="one.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="One"', $filtered );
		$this->assertStringContainsString( 'src="two.jpg"', $filtered );
		$this->assertStringContainsString( 'alt="Two"', $filtered );
		$this->assertStringNotContainsString( 'class=', $filtered );
		$this->assertStringNotContainsString( 'width=', $filtered );
	}

	/**
	 * Create and get a comment with replaces meta.
	 *
	 * @param int   $replaces The replaces value.
	 * @param array $args     Arguments for comment.
	 * @return object
	 */
	private function create_and_get_comment_with_replaces( int $replaces, array $args = array() ): object {
		$comment = self::factory()->comment->create_and_get( $args );
		add_comment_meta( $comment->comment_ID, WPCOM_Liveblog_Entry::REPLACES_META_KEY, $replaces );
		return $comment;
	}
}
