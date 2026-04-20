<?php
/**
 * Integration tests for StatusCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Infrastructure\CLI\StatusCommand;

/**
 * StatusCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\StatusCommand
 */
final class StatusCommandTest extends CliTestCase {

	/**
	 * Test status with table format.
	 */
	public function test_status_table_format(): void {
		$post_id = $this->create_liveblog();
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'table', $format_calls[0]['format'] );
	}

	/**
	 * Test status with JSON format.
	 */
	public function test_status_json_format(): void {
		$post_id = $this->create_liveblog();
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		// JSON output should be valid JSON.
		$json = json_decode( implode( '', $logs ), true );
		$this->assertIsArray( $json );
		$this->assertArrayHasKey( 'Post ID', $json );
	}

	/**
	 * Test status with YAML format.
	 */
	public function test_status_yaml_format(): void {
		$post_id = $this->create_liveblog();
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'yaml' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		// YAML format outputs "Key: Value" lines.
		$this->assertStringContainsString( 'Post ID:', $logs[0] );
	}

	/**
	 * Test status with invalid post ID.
	 */
	public function test_status_with_invalid_id(): void {
		$command = new StatusCommand();

		$this->invoke_expecting_error( $command, array( '0' ) );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test status with non-existent post.
	 */
	public function test_status_non_existent_post(): void {
		$command = new StatusCommand();

		$this->invoke_expecting_error( $command, array( '999999' ) );

		$this->assert_error_contains( 'not found' );
	}

	/**
	 * Test status with non-liveblog post.
	 */
	public function test_status_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		$command = new StatusCommand();

		$this->invoke_expecting_error( $command, array( (string) $post_id ) );

		$this->assert_error_contains( 'not a liveblog' );
	}

	/**
	 * Test status includes entry stats.
	 */
	public function test_status_includes_entry_stats(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Entry 1' );
		$this->add_entry( $post_id, 'Entry 2' );
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '2', $json['Total Entries'] );
	}

	/**
	 * Test status includes key events count.
	 */
	public function test_status_includes_key_events(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Regular entry' );
		$this->add_entry( $post_id, 'Key event', array( 'key_event' => true ) );
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '1', $json['Key Events'] );
	}

	/**
	 * Test status includes auto-archive info.
	 */
	public function test_status_includes_auto_archive(): void {
		$post_id = $this->create_liveblog();
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertArrayHasKey( 'Auto-archive Expiry', $json );
	}

	/**
	 * Test status shows enabled state.
	 */
	public function test_status_shows_enabled_state(): void {
		$post_id = $this->create_liveblog();
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( 'enable', $json['State'] );
	}

	/**
	 * Test status shows archived state.
	 */
	public function test_status_shows_archived_state(): void {
		$post_id = $this->create_liveblog();
		$this->archive_liveblog( $post_id );
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( 'archive', $json['State'] );
	}

	/**
	 * Test status includes post title.
	 */
	public function test_status_includes_title(): void {
		$post_id = $this->create_liveblog( array( 'post_title' => 'My Liveblog Title' ) );
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( 'My Liveblog Title', $json['Title'] );
	}

	/**
	 * Test status includes unique authors count.
	 */
	public function test_status_includes_unique_authors(): void {
		$post_id = $this->create_liveblog();
		$user1   = $this->create_user();
		$user2   = $this->create_user();
		$this->add_entry( $post_id, 'Entry 1', array( 'user' => $user1 ) );
		$this->add_entry( $post_id, 'Entry 2', array( 'user' => $user2 ) );
		$this->add_entry( $post_id, 'Entry 3', array( 'user' => $user1 ) );
		$command = new StatusCommand();

		$this->invoke_expecting_success( $command, array( (string) $post_id ), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '2', $json['Unique Authors'] );
	}
}
