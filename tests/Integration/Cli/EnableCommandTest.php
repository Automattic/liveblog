<?php
/**
 * Integration tests for EnableCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\CLI\EnableCommand;

/**
 * EnableCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\EnableCommand
 */
final class EnableCommandTest extends CliTestCase {

	/**
	 * Test enabling liveblog on a regular post.
	 */
	public function test_enable_on_regular_post(): void {
		$post_id = self::factory()->post->create();
		$command = new EnableCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_success( 'Liveblog enabled' );
		$this->assertSame( 'enable', $this->get_liveblog_meta( $post_id ) );
	}

	/**
	 * Test enabling liveblog verifies meta is set correctly.
	 */
	public function test_enable_sets_liveblog_meta(): void {
		$post_id = self::factory()->post->create();
		$command = new EnableCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertNotNull( $liveblog );
		$this->assertTrue( $liveblog->is_enabled() );
		$this->assertFalse( $liveblog->is_archived() );
	}

	/**
	 * Test enabling liveblog with invalid post ID.
	 */
	public function test_enable_with_invalid_id(): void {
		$command = new EnableCommand();

		$this->invoke_expecting_error( $command, [ '0' ] );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test enabling liveblog with non-existent post.
	 */
	public function test_enable_with_non_existent_post(): void {
		$command = new EnableCommand();

		$this->invoke_expecting_error( $command, [ '999999' ] );

		$this->assert_error_contains( 'not found' );
	}

	/**
	 * Test enabling liveblog on already enabled liveblog.
	 */
	public function test_enable_on_already_enabled_liveblog(): void {
		$post_id = $this->create_liveblog();
		$command = new EnableCommand();

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_warning( 'already an enabled liveblog' );
	}

	/**
	 * Test enabling liveblog on archived liveblog re-enables it.
	 */
	public function test_enable_on_archived_liveblog(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );

		// Verify it's archived.
		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_archived() );

		$command = new EnableCommand();
		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_success( 'Liveblog enabled' );

		// Verify it's now enabled.
		$liveblog = LiveblogPost::from_id( $post_id );
		$this->assertTrue( $liveblog->is_enabled() );
		$this->assertFalse( $liveblog->is_archived() );
	}

	/**
	 * Test enabling liveblog with non-numeric post ID.
	 */
	public function test_enable_with_non_numeric_post_id(): void {
		$command = new EnableCommand();

		// 'abc' converts to 0 via absint, which triggers the invalid ID error.
		$this->invoke_expecting_error( $command, [ 'abc' ] );

		$this->assert_error_contains( 'valid post ID' );
	}
}
