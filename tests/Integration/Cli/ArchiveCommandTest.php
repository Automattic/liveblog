<?php
/**
 * Integration tests for ArchiveCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\ArchiveCommand;

/**
 * ArchiveCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\ArchiveCommand
 */
final class ArchiveCommandTest extends CliTestCase {

	/**
	 * Test archiving an enabled liveblog.
	 */
	public function test_archive_enabled_liveblog(): void {
		$post_id = $this->create_liveblog();
		$command = new ArchiveCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_success( 'archived' );

		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_archived() );
	}

	/**
	 * Test archive verifies meta is set correctly.
	 */
	public function test_archive_sets_liveblog_meta(): void {
		$post_id = $this->create_liveblog();
		$command = new ArchiveCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assertSame( 'archive', $this->get_liveblog_meta( $post_id ) );
	}

	/**
	 * Test archiving with invalid post ID.
	 */
	public function test_archive_with_invalid_id(): void {
		$command = new ArchiveCommand();

		$this->invoke_expecting_error( $command, [ '0' ] );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test archiving non-existent post.
	 */
	public function test_archive_non_existent_post(): void {
		$command = new ArchiveCommand();

		$this->invoke_expecting_error( $command, [ '999999' ] );

		$this->assert_error_contains( 'not found' );
	}

	/**
	 * Test archiving a non-liveblog post.
	 */
	public function test_archive_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		$command = new ArchiveCommand();

		$this->invoke_expecting_error( $command, [ (string) $post_id ] );

		$this->assert_error_contains( 'not a liveblog' );
	}

	/**
	 * Test archiving an already archived liveblog.
	 */
	public function test_archive_already_archived_liveblog(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new ArchiveCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_warning( 'already archived' );
	}

	/**
	 * Test archive success message includes post ID.
	 */
	public function test_archive_success_message_includes_post_id(): void {
		$post_id = $this->create_liveblog();
		$command = new ArchiveCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_success_contains( (string) $post_id );
	}
}
