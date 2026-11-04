<?php
/**
 * Tests for the per-post entry order setting.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use ReflectionProperty;
use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;
use WPCOM_Liveblog_Lazyloader;

/**
 * Entry order test case.
 */
final class EntryOrderTest extends TestCase {

	/**
	 * Liveblog post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Entry IDs, oldest first.
	 *
	 * @var int[]
	 */
	private array $entry_ids = array();

	/**
	 * Set up a liveblog post with seven entries, which is two pages at the default page size.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->reset_static( WPCOM_Liveblog::class, 'entry_query' );
		$this->reset_static( WPCOM_Liveblog_Lazyloader::class, 'number_of_entries' );

		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, WPCOM_Liveblog::KEY, 'enable' );
		WPCOM_Liveblog::$post_id = $this->post_id;

		$this->entry_ids = array();
		for ( $i = 1; $i <= 7; $i++ ) {
			$this->entry_ids[] = $this->insert_entry( $i );
		}
	}

	/**
	 * Reset the static state touched by these tests.
	 */
	public function tear_down(): void {
		WPCOM_Liveblog::$post_id = null;
		$this->reset_static( WPCOM_Liveblog::class, 'entry_query' );
		$this->reset_static( WPCOM_Liveblog_Lazyloader::class, 'number_of_entries' );

		parent::tear_down();
	}

	/**
	 * Posts without a saved order show newest first.
	 */
	public function test_entry_order_defaults_to_newest_first(): void {
		$this->assertSame( 'desc', WPCOM_Liveblog::get_entry_order( $this->post_id ) );
		$this->assertSame( 'desc', WPCOM_Liveblog::get_entry_order() );
	}

	/**
	 * The default order can be changed site wide with a filter.
	 */
	public function test_default_entry_order_can_be_filtered(): void {
		add_filter( 'liveblog_default_entry_order', array( $this, 'return_asc' ) );

		$this->assertSame( 'asc', WPCOM_Liveblog::get_entry_order( $this->post_id ) );
	}

	/**
	 * A filter returning something other than asc or desc falls back to newest first.
	 */
	public function test_invalid_filtered_entry_order_falls_back_to_desc(): void {
		add_filter( 'liveblog_default_entry_order', array( $this, 'return_invalid' ) );

		$this->assertSame( 'desc', WPCOM_Liveblog::get_entry_order( $this->post_id ) );
	}

	/**
	 * The order saved on a post wins over the filtered default.
	 */
	public function test_saved_entry_order_overrides_default(): void {
		update_post_meta( $this->post_id, WPCOM_Liveblog::ENTRY_ORDER_META_KEY, 'desc' );
		add_filter( 'liveblog_default_entry_order', array( $this, 'return_asc' ) );

		$this->assertSame( 'desc', WPCOM_Liveblog::get_entry_order( $this->post_id ) );
	}

	/**
	 * Newest first keeps the existing paging.
	 */
	public function test_get_entries_paged_newest_first(): void {
		$result = WPCOM_Liveblog::get_entries_paged( 1 );

		$this->assertSame( array( 7, 6, 5, 4, 3 ), $this->positions( $result ) );
		$this->assertSame( 2, $result['pages'] );
		$this->assertSame( 7, $result['total'] );
	}

	/**
	 * Oldest first puts the first entries on page 1 and the latest on the last page.
	 */
	public function test_get_entries_paged_oldest_first(): void {
		$this->set_order( 'asc' );

		$page_one = WPCOM_Liveblog::get_entries_paged( 1, 'latest' );
		$page_two = WPCOM_Liveblog::get_entries_paged( 2, 'latest' );

		$this->assertSame( array( 1, 2, 3, 4, 5 ), $this->positions( $page_one ) );
		$this->assertSame( array( 6, 7 ), $this->positions( $page_two ) );
		$this->assertSame( 2, $page_two['pages'] );
		$this->assertSame( 7, $page_two['total'] );
	}

	/**
	 * Entries newer than the last known entry are left out in both orders.
	 */
	public function test_get_entries_paged_oldest_first_respects_last_known_entry(): void {
		$this->set_order( 'asc' );
		$last_known = $this->entry_ids[5] . '-' . time();

		$result = WPCOM_Liveblog::get_entries_paged( 2, $last_known );

		$this->assertSame( array( 6 ), $this->positions( $result ) );
		$this->assertSame( 6, $result['total'] );
	}

	/**
	 * Jumping to an entry finds the page it is on in the chosen order.
	 */
	public function test_get_entries_paged_oldest_first_finds_entry_page(): void {
		$this->set_order( 'asc' );

		$oldest = WPCOM_Liveblog::get_entries_paged( false, false, $this->entry_ids[0] );
		$newest = WPCOM_Liveblog::get_entries_paged( false, false, $this->entry_ids[6] );

		$this->assertSame( 1, $oldest['page'] );
		$this->assertSame( 2, $newest['page'] );
		$this->assertContains( 7, $this->positions( $newest ) );
	}

	/**
	 * Passing an order overrides the post's order. AMP relies on this to stay newest first.
	 */
	public function test_get_entries_paged_order_argument_overrides_post_order(): void {
		$this->set_order( 'asc' );

		$result = WPCOM_Liveblog::get_entries_paged( 1, false, false, 'desc' );

		$this->assertSame( array( 7, 6, 5, 4, 3 ), $this->positions( $result ) );
	}

	/**
	 * Saving the metabox stores a valid order.
	 */
	public function test_admin_settings_save_entry_order(): void {
		WPCOM_Liveblog::admin_set_liveblog_state_for_post(
			$this->post_id,
			'liveblog-entry-order-save',
			array(
				'state'                => 'liveblog-entry-order-save',
				'liveblog-entry-order' => 'asc',
			)
		);

		$this->assertSame( 'asc', get_post_meta( $this->post_id, WPCOM_Liveblog::ENTRY_ORDER_META_KEY, true ) );
		$this->assertSame( 'enable', get_post_meta( $this->post_id, WPCOM_Liveblog::KEY, true ) );
	}

	/**
	 * Saving the metabox ignores values other than asc or desc.
	 */
	public function test_admin_settings_ignore_invalid_entry_order(): void {
		$this->set_order( 'asc' );

		WPCOM_Liveblog::admin_set_liveblog_state_for_post(
			$this->post_id,
			'enable',
			array(
				'state'                => 'enable',
				'liveblog-entry-order' => 'random',
			)
		);

		$this->assertSame( 'asc', get_post_meta( $this->post_id, WPCOM_Liveblog::ENTRY_ORDER_META_KEY, true ) );
	}

	/**
	 * The metabox shows the order select with the saved value picked.
	 */
	public function test_meta_box_shows_entry_order_select(): void {
		$this->set_order( 'asc' );

		$meta_box = WPCOM_Liveblog::get_meta_box( get_post( $this->post_id ) );

		$this->assertStringContainsString( 'id="liveblog-entry-order"', $meta_box );
		$this->assertMatchesRegularExpression( '/<option value="asc"\s+selected/', $meta_box );
	}

	/**
	 * Filter callback returning 'asc'.
	 *
	 * @return string
	 */
	public function return_asc(): string {
		return 'asc';
	}

	/**
	 * Filter callback returning an unsupported value.
	 *
	 * @return string
	 */
	public function return_invalid(): string {
		return 'sideways';
	}

	/**
	 * Save an entry order on the test post.
	 *
	 * @param string $order Either 'asc' or 'desc'.
	 */
	private function set_order( string $order ): void {
		update_post_meta( $this->post_id, WPCOM_Liveblog::ENTRY_ORDER_META_KEY, $order );
	}

	/**
	 * Map the entries in a paged result to their 1-based insert position.
	 *
	 * @param array $result Result of get_entries_paged().
	 * @return int[]
	 */
	private function positions( array $result ): array {
		$positions = array();
		foreach ( $result['entries'] as $entry ) {
			$positions[] = array_search( (int) $entry->id, $this->entry_ids, true ) + 1;
		}
		return $positions;
	}

	/**
	 * Insert an entry, one minute after the previous one so the order is stable.
	 *
	 * @param int $minute Minutes after the base time.
	 * @return int The entry ID.
	 */
	private function insert_entry( int $minute ): int {
		$date = gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01 10:00:00' ) + ( $minute * MINUTE_IN_SECONDS ) );

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Entry ' . $minute,
				'comment_type'     => WPCOM_Liveblog::KEY,
				'comment_approved' => WPCOM_Liveblog::KEY,
				'comment_date'     => $date,
				'comment_date_gmt' => $date,
			)
		);
	}

	/**
	 * Reset a private static property to null.
	 *
	 * @param string $class_name Class name.
	 * @param string $property   Property name.
	 */
	private function reset_static( string $class_name, string $property ): void {
		$reflection = new ReflectionProperty( $class_name, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );
	}
}
