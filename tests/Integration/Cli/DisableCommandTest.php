<?php
/**
 * Integration tests for DisableCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\DisableCommand;
use WP_CLI;

/**
 * DisableCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\DisableCommand
 */
final class DisableCommandTest extends CliTestCase {

	/**
	 * Test disabling liveblog with --yes flag.
	 */
	public function test_disable_with_yes_flag(): void {
		$post_id = $this->create_liveblog();
		$command = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'yes' => true ) );

		$this->assert_command_success( 'Liveblog disabled' );
		$this->assert_confirm_not_called();

		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertFalse( $liveblog->is_liveblog() );
	}

	/**
	 * Test disabling liveblog without --yes flag calls confirm.
	 */
	public function test_disable_without_yes_flag_calls_confirm(): void {
		$post_id = $this->create_liveblog();
		$command = new DisableCommand();

		// Confirm will auto-return true by default.
		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assert_confirm_called();
		$this->assert_command_success( 'Liveblog disabled' );
	}

	/**
	 * Test disabling liveblog removes meta.
	 */
	public function test_disable_removes_liveblog_meta(): void {
		$post_id = $this->create_liveblog();
		$command = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'yes' => true ) );

		// Meta should be removed after disable.
		$meta = $this->get_liveblog_meta( $post_id );
		$this->assertEmpty( $meta );
	}

	/**
	 * Test disabling with invalid post ID.
	 */
	public function test_disable_with_invalid_id(): void {
		$command = new DisableCommand();

		$this->invoke_expecting_error( $command, array( '0' ) );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test disabling non-existent post.
	 */
	public function test_disable_non_existent_post(): void {
		$command = new DisableCommand();

		$this->invoke_expecting_error( $command, array( '999999' ) );

		$this->assert_error_contains( 'not found' );
	}

	/**
	 * Test disabling a non-liveblog post.
	 */
	public function test_disable_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		$command = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$this->assert_command_warning( 'not a liveblog' );
	}

	/**
	 * Test disabling preserves entries.
	 */
	public function test_disable_preserves_entries(): void {
		$post_id  = $this->create_liveblog();
		$entry_id = $this->add_entry( $post_id, 'Test entry content' );
		$command  = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'yes' => true ) );

		// Entry should still exist.
		$comment = get_comment( $entry_id );
		$this->assertNotNull( $comment );
		$this->assertSame( 'Test entry content', $comment->comment_content );
	}

	/**
	 * Test success message mentions entries are preserved.
	 */
	public function test_success_message_mentions_entries_preserved(): void {
		$post_id = $this->create_liveblog();
		$command = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'yes' => true ) );

		$this->assert_success_contains( 'preserved' );
	}

	/**
	 * Test disabling an archived liveblog.
	 */
	public function test_disable_archived_liveblog(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new DisableCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'yes' => true ) );

		$this->assert_command_success( 'Liveblog disabled' );

		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertFalse( $liveblog->is_liveblog() );
	}
}
