<?php
/**
 * Tests for the `wp liveblog fix-archive` WP-CLI command.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog_Entry;
use WPCOM_Liveblog_WP_CLI;

require_once __DIR__ . '/wp-cli-stub.php';

if ( ! class_exists( 'WPCOM_Liveblog_WP_CLI' ) ) {
	require_once dirname( __DIR__, 2 ) . '/classes/class-wpcom-liveblog-wp-cli.php';
}

/**
 * The fix-archive command test case.
 */
final class WpCliFixArchiveTest extends TestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Command instance under test.
	 *
	 * @var WPCOM_Liveblog_WP_CLI
	 */
	private WPCOM_Liveblog_WP_CLI $command;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		\WP_CLI::reset();

		$this->command = new WPCOM_Liveblog_WP_CLI();
		$this->post_id = self::factory()->post->create();
	}

	/**
	 * Create a root liveblog entry comment with no comment meta.
	 *
	 * The fix_archive() method treats any comment on the post with zero
	 * commentmeta rows as a "correct"/root entry, so this must stay meta-free.
	 *
	 * @param string $content Comment content.
	 * @return int Comment ID.
	 */
	private function create_root_entry( string $content ): int {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
				'comment_content'  => $content,
			)
		);
	}

	/**
	 * Create an "edit" ghost comment pointing at $replaces via liveblog_replaces meta.
	 *
	 * @param int    $replaces Comment ID this ghost claims to replace.
	 * @param string $content  Comment content.
	 * @return int Comment ID.
	 */
	private function create_edit_entry( int $replaces, string $content ): int {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'liveblog',
				'comment_type'     => 'liveblog',
				'comment_content'  => $content,
			)
		);
		add_comment_meta( $comment_id, WPCOM_Liveblog_Entry::REPLACES_META_KEY, $replaces );
		return $comment_id;
	}

	/**
	 * Build a liveblog with a correctly-pointed edit and a mis-pointed edit.
	 *
	 * Mirrors the archived-liveblog corruption fix-archive repairs: a second
	 * edit whose liveblog_replaces meta points at the first ghost instead of
	 * collapsing back to the root entry.
	 *
	 * @return array{root:int,ghost:int,broken:int} Comment IDs.
	 */
	private function create_liveblog_with_broken_replaces(): array {
		add_post_meta( $this->post_id, 'liveblog', 'enable' );

		$root   = $this->create_root_entry( 'original content' );
		$ghost  = $this->create_edit_entry( $root, 'first edit' );
		$broken = $this->create_edit_entry( $ghost, 'second edit' );

		return array(
			'root'   => $root,
			'ghost'  => $ghost,
			'broken' => $broken,
		);
	}

	/**
	 * Test that --dryrun reports intended changes without modifying data.
	 */
	public function test_fix_archive_dry_run_does_not_modify_data(): void {
		$ids = $this->create_liveblog_with_broken_replaces();

		$this->command->fix_archive( array(), array( 'dryrun' => true ) );

		$this->assertSame(
			(string) $ids['ghost'],
			get_comment_meta( $ids['broken'], WPCOM_Liveblog_Entry::REPLACES_META_KEY, true ),
			'Dry run must not touch liveblog_replaces meta.'
		);
	}

	/**
	 * Test that a real run corrects a mis-pointed liveblog_replaces value.
	 */
	public function test_fix_archive_corrects_incorrect_replaces_meta(): void {
		$ids = $this->create_liveblog_with_broken_replaces();

		$this->command->fix_archive( array(), array() );

		$this->assertSame(
			(string) $ids['root'],
			get_comment_meta( $ids['broken'], WPCOM_Liveblog_Entry::REPLACES_META_KEY, true ),
			'fix-archive should repoint the broken edit at the root entry.'
		);
		$this->assertTrue( \WP_CLI::$success_called );
	}

	/**
	 * Test that no liveblog posts at all completes without error.
	 */
	public function test_fix_archive_handles_no_liveblogs_found(): void {
		$this->command->fix_archive( array(), array() );

		$this->assertTrue( \WP_CLI::$success_called );
	}

	/**
	 * Test that a liveblog with no edited entries completes without error.
	 */
	public function test_fix_archive_handles_liveblog_with_no_edited_entries(): void {
		add_post_meta( $this->post_id, 'liveblog', 'enable' );
		$this->create_root_entry( 'only entry, never edited' );

		$this->command->fix_archive( array(), array() );

		$this->assertTrue( \WP_CLI::$success_called );
	}
}
