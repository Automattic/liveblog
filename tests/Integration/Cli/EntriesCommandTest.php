<?php
/**
 * Integration tests for EntriesCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Infrastructure\CLI\EntriesCommand;

/**
 * EntriesCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\EntriesCommand
 */
final class EntriesCommandTest extends CliTestCase {

	/**
	 * Test listing entries with default format.
	 */
	public function test_entries_default_format(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'First entry' );
		$this->add_entry( $post_id, 'Second entry' );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'table', $format_calls[0]['format'] );
		$this->assertCount( 2, $format_calls[0]['items'] );
	}

	/**
	 * Test listing entries with JSON format.
	 */
	public function test_entries_json_format(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'format' => 'json' ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'json', $format_calls[0]['format'] );
	}

	/**
	 * Test listing entries with CSV format.
	 */
	public function test_entries_csv_format(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'format' => 'csv' ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'csv', $format_calls[0]['format'] );
	}

	/**
	 * Test listing entries with IDs format.
	 */
	public function test_entries_ids_format(): void {
		$post_id  = $this->create_liveblog();
		$entry_id = $this->add_entry( $post_id, 'Test entry' );
		$command  = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'format' => 'ids' ] );

		// IDs format outputs via WP_CLI::log() and format_items is not called.
		$this->assertTrue( \WP_CLI::was_called( 'log' ) );
		$format_calls = $this->output->get_format_items_calls();
		$this->assertEmpty( $format_calls, 'format_items should not be called for ids format.' );
	}

	/**
	 * Test listing entries with --limit.
	 */
	public function test_entries_with_limit(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Entry 1' );
		$this->add_entry( $post_id, 'Entry 2' );
		$this->add_entry( $post_id, 'Entry 3' );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'limit' => '2' ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 2, $format_calls[0]['items'] );
	}

	/**
	 * Test listing entries with --key-events filter.
	 */
	public function test_entries_key_events_filter(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Regular entry' );
		$this->add_entry( $post_id, 'Key event entry', [ 'key_event' => true ] );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'key-events' => true ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 1, $format_calls[0]['items'] );
	}

	/**
	 * Test listing entries from non-liveblog post.
	 */
	public function test_entries_from_non_liveblog(): void {
		$post_id = self::factory()->post->create();
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_error( $command, [ (string) $post_id ] );

		$this->assert_error_contains( 'not a liveblog' );
	}

	/**
	 * Test listing entries from empty liveblog.
	 */
	public function test_entries_from_empty_liveblog(): void {
		$post_id = $this->create_liveblog();
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$this->assert_command_warning( 'No entries found' );
	}

	/**
	 * Test entries include expected columns.
	 */
	public function test_entries_include_expected_columns(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Test entry' );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );

		$columns = $format_calls[0]['columns'];
		$this->assertContains( 'ID', $columns );
		$this->assertContains( 'author', $columns );
		$this->assertContains( 'date', $columns );
		$this->assertContains( 'content', $columns );
		$this->assertContains( 'key_event', $columns );
	}

	/**
	 * Test key-events filter excludes key_event column.
	 */
	public function test_key_events_filter_excludes_key_event_column(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Key event', [ 'key_event' => true ] );
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'key-events' => true ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );

		$columns = $format_calls[0]['columns'];
		$this->assertNotContains( 'key_event', $columns );
	}

	/**
	 * Test entries with invalid post ID.
	 */
	public function test_entries_with_invalid_post_id(): void {
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_error( $command, [ '0' ] );

		$this->assert_error_contains( 'valid post ID' );
	}

	/**
	 * Test empty key events returns appropriate warning.
	 */
	public function test_empty_key_events_returns_warning(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Regular entry' ); // Not a key event.
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'key-events' => true ] );

		$this->assert_command_warning( 'No key events found' );
	}

	/**
	 * Test entries with limit of 0 returns all entries.
	 */
	public function test_entries_limit_zero_returns_all(): void {
		$post_id = $this->create_liveblog();
		for ( $i = 1; $i <= 25; $i++ ) {
			$this->add_entry( $post_id, "Entry $i" );
		}
		$command = new EntriesCommand( $this->container()->entry_query_service() );

		$this->invoke_expecting_success( $command, [ (string) $post_id ], [ 'limit' => '0' ] );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 25, $format_calls[0]['items'] );
	}
}
