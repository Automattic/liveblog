<?php
/**
 * Integration tests for ArchiveOldCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Application\Aggregate\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\ArchiveOldCommand;

/**
 * ArchiveOldCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\ArchiveOldCommand
 */
final class ArchiveOldCommandTest extends CliTestCase {

	/**
	 * Test archive-old requires --days argument.
	 */
	public function test_archive_old_requires_days(): void {
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_error( $command );

		$this->assert_error_contains( '--days' );
	}

	/**
	 * Test archive-old with dry-run.
	 */
	public function test_archive_old_dry_run(): void {
		$post_id = $this->create_liveblog();
		// Create old post and entry.
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days'    => '30',
				'dry-run' => true,
			) 
		);

		$this->assert_success_contains( 'Dry run complete' );
		$this->assert_success_contains( 'No changes made' );

		// Post should still be enabled.
		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_enabled() );
	}

	/**
	 * Test archive-old with --yes flag.
	 */
	public function test_archive_old_with_yes_flag(): void {
		$post_id = $this->create_liveblog();
		// Create old post.
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days' => '30',
				'yes'  => true,
			) 
		);

		$this->assert_command_success( 'Archived' );
		$this->assert_confirm_not_called();

		// Post should be archived.
		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_archived() );
	}

	/**
	 * Test archive-old without --yes flag calls confirm.
	 */
	public function test_archive_old_without_yes_calls_confirm(): void {
		$post_id = $this->create_liveblog();
		// Create old post.
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success( $command, array(), array( 'days' => '30' ) );

		$this->assert_confirm_called();
	}

	/**
	 * Test archive-old with invalid days value.
	 */
	public function test_archive_old_invalid_days(): void {
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_error( $command, array(), array( 'days' => '0' ) );

		$this->assert_error_contains( 'at least 1' );
	}

	/**
	 * Test archive-old finds inactive liveblogs.
	 */
	public function test_archive_old_finds_inactive_liveblogs(): void {
		// Create an old inactive liveblog.
		$old_id = $this->create_liveblog( array( 'post_title' => 'Old Liveblog' ) );
		wp_update_post(
			array(
				'ID'        => $old_id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);

		// Create a recent liveblog.
		$recent_id = $this->create_liveblog( array( 'post_title' => 'Recent Liveblog' ) );
		$this->add_entry( $recent_id, 'Recent entry' );

		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days'    => '30',
				'dry-run' => true,
			) 
		);

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		// Should only include the old liveblog.
		$this->assertCount( 1, $format_calls[0]['items'] );
		$this->assertSame( $old_id, $format_calls[0]['items'][0]['ID'] );
	}

	/**
	 * Test archive-old respects cutoff date.
	 */
	public function test_archive_old_respects_cutoff(): void {
		// Create liveblog with entry 15 days ago (should not be archived with 30 day cutoff).
		$post_id = $this->create_liveblog();

		// Add entry dated 15 days ago.
		global $wpdb;
		$entry_date = gmdate( 'Y-m-d H:i:s', strtotime( '-15 days' ) );
		$this->add_entry( $post_id, 'Entry from 15 days ago' );
		// Update the comment date directly.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->comments,
			array(
				'comment_date'     => $entry_date,
				'comment_date_gmt' => $entry_date,
			),
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 'liveblog',
			)
		);
		clean_comment_cache( $post_id );

		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days'    => '30',
				'dry-run' => true,
			) 
		);

		$this->assert_success_contains( 'No inactive liveblogs found' );
	}

	/**
	 * Test archive-old handles no liveblogs gracefully.
	 */
	public function test_archive_old_no_liveblogs(): void {
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success( $command, array(), array( 'days' => '30' ) );

		$this->assert_success_contains( 'No inactive liveblogs found' );
	}

	/**
	 * Test archive-old shows preview table.
	 */
	public function test_archive_old_shows_preview(): void {
		$post_id = $this->create_liveblog( array( 'post_title' => 'Old Liveblog' ) );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days'    => '30',
				'dry-run' => true,
			) 
		);

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertContains( 'ID', $format_calls[0]['columns'] );
		$this->assertContains( 'title', $format_calls[0]['columns'] );
		$this->assertContains( 'last_entry', $format_calls[0]['columns'] );
	}

	/**
	 * Test archive-old archives multiple liveblogs.
	 */
	public function test_archive_old_archives_multiple(): void {
		$post_id1 = $this->create_liveblog();
		$post_id2 = $this->create_liveblog();
		wp_update_post(
			array(
				'ID'        => $post_id1,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		wp_update_post(
			array(
				'ID'        => $post_id2,
				'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
			)
		);
		$command = new ArchiveOldCommand();

		$this->invoke_expecting_success(
			$command,
			array(),
			array(
				'days' => '30',
				'yes'  => true,
			) 
		);

		$this->assert_success_contains( '2 liveblog' );

		$liveblog1 = LiveblogPost::from_id( $post_id1 );
		$liveblog2 = LiveblogPost::from_id( $post_id2 );
		$this->assertTrue( $liveblog1->is_archived() );
		$this->assertTrue( $liveblog2->is_archived() );
	}
}
