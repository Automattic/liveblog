<?php
/**
 * Integration tests for FixArchiveCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Application\Service\ArchiveRepairService;
use Automattic\Liveblog\Infrastructure\CLI\FixArchiveCommand;

/**
 * FixArchiveCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\FixArchiveCommand
 */
final class FixArchiveCommandTest extends CliTestCase {

	/**
	 * Test fix-archive with dry-run.
	 */
	public function test_fix_archive_dry_run(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command, array(), array( 'dry-run' => true ) );

		$this->assert_success_contains( 'Dry run completed' );
		$this->assert_line_contains( 'dry-run mode' );
	}

	/**
	 * Test fix-archive without dry-run.
	 */
	public function test_fix_archive_without_dry_run(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_success_contains( 'Fixed all entries' );
	}

	/**
	 * Test fix-archive handles no liveblogs.
	 */
	public function test_fix_archive_no_liveblogs(): void {
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_command_warning( 'No liveblog posts found' );
	}

	/**
	 * Test fix-archive shows summary.
	 */
	public function test_fix_archive_shows_summary(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_line_contains( 'Entries corrected' );
		$this->assert_line_contains( 'Content items replaced' );
	}

	/**
	 * Test fix-archive shows progress bar message.
	 */
	public function test_fix_archive_shows_progress(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_line_contains( 'progress_bar' );
	}

	/**
	 * Test fix-archive processes multiple liveblogs.
	 */
	public function test_fix_archive_processes_multiple(): void {
		$post_id1 = $this->create_liveblog();
		$post_id2 = $this->create_liveblog();
		$this->add_entry( $post_id1, 'Entry 1' );
		$this->add_entry( $post_id2, 'Entry 2' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_line_contains( '2 liveblog post(s)' );
	}

	/**
	 * Test fix-archive dry-run suggests re-run.
	 */
	public function test_fix_archive_dry_run_suggests_rerun(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command, array(), array( 'dry-run' => true ) );

		$this->assert_success_contains( 'Re-run without --dry-run' );
	}

	/**
	 * Test fix-archive finds liveblog posts.
	 */
	public function test_fix_archive_finds_posts(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_line_contains( 'Finding all liveblog entries' );
		$this->assert_line_contains( 'Found' );
	}

	/**
	 * Test fix-archive works with archived liveblogs.
	 */
	public function test_fix_archive_works_with_archived(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$this->archive_liveblog( $post_id );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_success_contains( 'Fixed all entries' );
	}

	/**
	 * Test fix-archive with entry that has replaces meta.
	 */
	public function test_fix_archive_with_edited_entry(): void {
		$post_id  = $this->create_liveblog();
		$user     = $this->create_user();
		$entry_id = $this->add_entry( $post_id, 'Original content', array( 'user' => $user ) );

		// Simulate an edit by creating an update entry via the service.
		$entry_service = $this->container()->entry_service();
		$entry_id_vo   = \Automattic\Liveblog\Domain\ValueObject\EntryId::from_int( $entry_id );
		$entry_service->update( $post_id, $entry_id_vo, 'Updated content', $user );

		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_success_contains( 'Fixed all entries' );
	}

	/**
	 * Test fix-archive shows zero stats when nothing to fix.
	 */
	public function test_fix_archive_zero_stats(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Simple entry' );
		$service = new ArchiveRepairService();
		$command = new FixArchiveCommand( $service );

		$this->invoke_expecting_success( $command );

		$this->assert_line_contains( 'Entries corrected: 0' );
		$this->assert_line_contains( 'Content items replaced: 0' );
	}
}
