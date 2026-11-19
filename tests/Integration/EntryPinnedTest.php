<?php
/**
 * Tests for pinned liveblog entries.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_Lazyloader;

/**
 * Pinned entries test case.
 */
final class EntryPinnedTest extends TestCase {

	private const PINNED_CONTENT = 'Pinned <span class="liveblog-command type-pin">pin</span>';

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, WPCOM_Liveblog::KEY, 'enable' );

		$this->set_static( 'post_id', $this->post_id );
		$this->set_static( 'entry_query', null );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		$this->set_static( 'post_id', null );
		$this->set_static( 'entry_query', null );

		parent::tear_down();
	}

	/**
	 * Test that /pin is a registered command.
	 */
	public function test_pin_command_is_registered(): void {
		$this->assertContains( 'pin', apply_filters( 'liveblog_active_commands', array() ) );
	}

	/**
	 * Test that the pinned flag follows the entry content.
	 */
	public function test_pinned_flag_in_json(): void {
		$pinned = $this->insert_entry( self::PINNED_CONTENT );
		$normal = $this->insert_entry( 'Not pinned' );

		$this->assertTrue( $pinned->for_json()->pinned );
		$this->assertFalse( $normal->for_json()->pinned );
	}

	/**
	 * Test that editing out /pin unpins the entry.
	 */
	public function test_edit_removes_pin(): void {
		$pinned = $this->insert_entry( self::PINNED_CONTENT );

		$update = WPCOM_Liveblog_Entry::update(
			array(
				'post_id'  => $this->post_id,
				'entry_id' => $pinned->get_id(),
				'content'  => 'No longer pinned',
			)
		);

		$this->assertFalse( $update->for_json()->pinned );

		$result = WPCOM_Liveblog::get_entries_paged( 1 );
		$this->assertFalse( $result['entries'][0]->pinned );
	}

	/**
	 * Test that an old pinned entry is added to the end of the first page only.
	 */
	public function test_old_pinned_entry_added_to_first_page(): void {
		$per_page = WPCOM_Liveblog_Lazyloader::get_number_of_entries();
		$pinned   = $this->insert_entry( self::PINNED_CONTENT );
		for ( $i = 0; $i < $per_page; $i++ ) {
			$newest = $this->insert_entry( 'Entry ' . $i );
		}

		$page_one = WPCOM_Liveblog::get_entries_paged( 1 );
		$ids      = wp_list_pluck( $page_one['entries'], 'id' );

		$this->assertCount( $per_page + 1, $ids );
		$this->assertSame( (int) $newest->get_id(), (int) $ids[0], 'The newest entry should stay first.' );
		$this->assertSame( (int) $pinned->get_id(), (int) end( $ids ) );
		$this->assertSame( $per_page + 1, $page_one['total'] );
		$this->assertSame( 2, $page_one['pages'] );

		$page_two = WPCOM_Liveblog::get_entries_paged( 2 );
		$this->assertSame( array( (int) $pinned->get_id() ), array_map( 'intval', wp_list_pluck( $page_two['entries'], 'id' ) ) );
	}

	/**
	 * Test that a pinned entry already on the first page is not repeated.
	 */
	public function test_pinned_entry_on_first_page_not_duplicated(): void {
		$this->insert_entry( 'Older entry' );
		$pinned = $this->insert_entry( self::PINNED_CONTENT );

		$ids = wp_list_pluck( WPCOM_Liveblog::get_entries_paged( 1 )['entries'], 'id' );

		$this->assertCount( 2, $ids );
		$this->assertSame( (int) $pinned->get_id(), (int) $ids[0] );
	}

	/**
	 * Insert an entry on the test post.
	 *
	 * @param string $content Entry content.
	 * @return WPCOM_Liveblog_Entry
	 */
	private function insert_entry( string $content ): WPCOM_Liveblog_Entry {
		return WPCOM_Liveblog_Entry::insert(
			array(
				'post_id' => $this->post_id,
				'content' => $content,
				'user'    => self::factory()->user->create_and_get(),
			)
		);
	}

	/**
	 * Set a private static property on WPCOM_Liveblog.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 */
	private function set_static( string $name, $value ): void {
		$reflection = new \ReflectionProperty( WPCOM_Liveblog::class, $name );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}
}
