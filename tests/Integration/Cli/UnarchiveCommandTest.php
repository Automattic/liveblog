<?php
/**
 * Integration tests for UnarchiveCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\UnarchiveCommand;

/**
 * UnarchiveCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\UnarchiveCommand
 */
final class UnarchiveCommandTest extends CliTestCase {

	/**
	 * Test unarchiving an archived liveblog.
	 */
	public function test_unarchive_archived_liveblog(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new UnarchiveCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assert_command_success( 'unarchived and re-enabled' );

		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_enabled() );
		$this->assertFalse( $liveblog->is_archived() );
	}

	/**
	 * Test unarchive sets correct meta.
	 */
	public function test_unarchive_sets_liveblog_meta(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new UnarchiveCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assertSame( 'enable', $this->get_liveblog_meta( $post_id ) );
	}

	/**
	 * Test unarchiving with invalid post ID.
	 */
	public function test_unarchive_with_invalid_id(): void {
		$command = new UnarchiveCommand();

		$this->invoke_expecting_error( $command, array( '0' ) );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test unarchiving non-existent post.
	 */
	public function test_unarchive_non_existent_post(): void {
		$command = new UnarchiveCommand();

		$this->invoke_expecting_error( $command, array( '999999' ) );

		$this->assert_error_contains( 'not found' );
	}

	/**
	 * Test unarchiving an already enabled liveblog.
	 */
	public function test_unarchive_already_enabled_liveblog(): void {
		$post_id = $this->create_liveblog();
		$command = new UnarchiveCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assert_command_warning( 'already enabled' );
	}

	/**
	 * Test unarchiving a non-liveblog post.
	 */
	public function test_unarchive_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		$command = new UnarchiveCommand();

		$this->invoke_expecting_error( $command, array( (string) $post_id ) );

		$this->assert_error_contains( 'not an archived liveblog' );
	}

	/**
	 * Test unarchive success message includes post ID.
	 */
	public function test_unarchive_success_message_includes_post_id(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new UnarchiveCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assert_success_contains( (string) $post_id );
	}
}
