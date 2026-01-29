<?php
/**
 * Integration tests for AddCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Infrastructure\CLI\AddCommand;

/**
 * AddCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\AddCommand
 */
final class AddCommandTest extends CliTestCase {

	/**
	 * Set up test fixtures.
	 *
	 * Remove filters that expect array args (registered by PluginBootstrapper)
	 * since AddCommand applies the filter with a string content value directly.
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'liveblog_before_insert_entry' );
	}

	/**
	 * Test adding a basic entry.
	 */
	public function test_add_basic_entry(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id, 'Test entry content' ] );

		$this->assert_command_success( 'Entry' );
		$this->assert_success_contains( 'added to liveblog' );
	}

	/**
	 * Test add creates comment in database.
	 */
	public function test_add_creates_comment(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id, 'Test entry content' ] );

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'liveblog',
			]
		);

		$this->assertCount( 1, $comments );
		$this->assertSame( 'Test entry content', $comments[0]->comment_content );
	}

	/**
	 * Test adding entry with specific author.
	 */
	public function test_add_with_author(): void {
		$post_id = $this->create_liveblog();
		$user    = $this->create_user( [ 'display_name' => 'Test Author' ] );
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'author' => (string) $user->ID ]
		);

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'liveblog',
			]
		);

		$this->assertSame( (int) $user->ID, (int) $comments[0]->user_id );
	}

	/**
	 * Test adding entry with contributors.
	 */
	public function test_add_with_contributors(): void {
		$post_id = $this->create_liveblog();
		$user1   = $this->create_user();
		$user2   = $this->create_user();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'contributors' => sprintf( '%d,%d', $user1->ID, $user2->ID ) ]
		);

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'liveblog',
			]
		);

		$contributors = get_comment_meta( $comments[0]->comment_ID, 'liveblog_contributors', true );
		$this->assertContains( (int) $user1->ID, $contributors );
		$this->assertContains( (int) $user2->ID, $contributors );
	}

	/**
	 * Test adding entry with --hide-authors flag.
	 */
	public function test_add_with_hide_authors(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'hide-authors' => true ]
		);

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'liveblog',
			]
		);

		$hide_authors = get_comment_meta( $comments[0]->comment_ID, 'liveblog_hide_authors', true );
		$this->assertSame( '1', $hide_authors );
	}

	/**
	 * Test adding entry with --key-event flag.
	 */
	public function test_add_with_key_event(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'key-event' => true ]
		);

		$this->assert_success_contains( 'Key event' );

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'liveblog',
			]
		);

		$key_event = get_comment_meta( $comments[0]->comment_ID, 'liveblog_key_entry', true );
		$this->assertSame( '1', $key_event );
	}

	/**
	 * Test adding entry with --porcelain flag.
	 */
	public function test_add_with_porcelain(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'porcelain' => true ]
		);

		// Should output just the ID via log, not success message.
		$this->assertFalse( $this->output->has_success() );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		$this->assertIsNumeric( trim( $logs[0] ) );
	}

	/**
	 * Test adding entry with invalid post ID.
	 */
	public function test_add_with_invalid_post_id(): void {
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_error( $command, [ '0', 'Test entry' ] );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test adding entry to non-liveblog post.
	 */
	public function test_add_to_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_error( $command, [ (string) $post_id, 'Test entry' ] );

		$this->assert_error_contains( 'not an enabled liveblog' );
	}

	/**
	 * Test adding entry to archived liveblog.
	 */
	public function test_add_to_archived_liveblog(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_error( $command, [ (string) $post_id, 'Test entry' ] );

		$this->assert_error_contains( 'archived' );
		$this->assert_error_contains( 'Unarchive it first' );
	}

	/**
	 * Test adding entry with empty content.
	 */
	public function test_add_with_empty_content(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_error( $command, [ (string) $post_id, '' ] );

		$this->assert_error_contains( 'provide entry content' );
	}

	/**
	 * Test adding entry with invalid author shows warning.
	 */
	public function test_add_with_invalid_author_shows_warning(): void {
		$post_id = $this->create_liveblog();
		// Create a user so there's a fallback admin.
		$admin = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin->ID );

		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success(
			$command,
			[ (string) $post_id, 'Test entry' ],
			[ 'author' => '999999' ]
		);

		$this->assert_warning_contains( 'not found' );
		$this->assert_command_success();
	}

	/**
	 * Test adding entry includes post ID in success message.
	 */
	public function test_add_success_message_includes_post_id(): void {
		$post_id = $this->create_liveblog();
		$command = new AddCommand( $this->container()->entry_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id, 'Test entry' ] );

		$this->assert_success_contains( (string) $post_id );
	}
}
