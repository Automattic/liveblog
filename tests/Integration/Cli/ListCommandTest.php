<?php
/**
 * Integration tests for ListCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Infrastructure\CLI\ListCommand;

/**
 * ListCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\ListCommand
 */
final class ListCommandTest extends CliTestCase {

	/**
	 * Test listing all liveblogs.
	 */
	public function test_list_all_liveblogs(): void {
		$this->create_liveblog( array( 'post_title' => 'Enabled Liveblog' ) );
		$archived_id = $this->create_liveblog( array( 'post_title' => 'Archived Liveblog' ) );
		$this->archive_liveblog( $archived_id );
		$command = new ListCommand();

		$this->invoke_expecting_success( $command );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 2, $format_calls[0]['items'] );
	}

	/**
	 * Test listing with --state=enabled.
	 */
	public function test_list_state_enabled(): void {
		$this->create_liveblog( array( 'post_title' => 'Enabled Liveblog' ) );
		$archived_id = $this->create_liveblog( array( 'post_title' => 'Archived Liveblog' ) );
		$this->archive_liveblog( $archived_id );
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'state' => 'enabled' ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 1, $format_calls[0]['items'] );
		$this->assertSame( 'enable', $format_calls[0]['items'][0]['state'] );
	}

	/**
	 * Test listing with --state=archived.
	 */
	public function test_list_state_archived(): void {
		$this->create_liveblog( array( 'post_title' => 'Enabled Liveblog' ) );
		$archived_id = $this->create_liveblog( array( 'post_title' => 'Archived Liveblog' ) );
		$this->archive_liveblog( $archived_id );
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'state' => 'archived' ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertCount( 1, $format_calls[0]['items'] );
		$this->assertSame( 'archive', $format_calls[0]['items'][0]['state'] );
	}

	/**
	 * Test listing with table format.
	 */
	public function test_list_table_format(): void {
		$this->create_liveblog();
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'table' ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'table', $format_calls[0]['format'] );
	}

	/**
	 * Test listing with JSON format.
	 */
	public function test_list_json_format(): void {
		$this->create_liveblog();
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'json', $format_calls[0]['format'] );
	}

	/**
	 * Test listing with CSV format.
	 */
	public function test_list_csv_format(): void {
		$this->create_liveblog();
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'csv' ) );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'csv', $format_calls[0]['format'] );
	}

	/**
	 * Test listing with IDs format.
	 */
	public function test_list_ids_format(): void {
		$post_id = $this->create_liveblog();
		$command = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'ids' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		$this->assertStringContainsString( (string) $post_id, $logs[0] );
	}

	/**
	 * Test listing empty site shows warning.
	 */
	public function test_list_empty_site(): void {
		$command = new ListCommand();

		$this->invoke_expecting_success( $command );

		$this->assert_command_warning( 'No liveblogs found' );
	}

	/**
	 * Test list includes entry count.
	 */
	public function test_list_includes_entry_count(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Entry 1' );
		$this->add_entry( $post_id, 'Entry 2' );
		$command = new ListCommand();

		$this->invoke_expecting_success( $command );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 2, $format_calls[0]['items'][0]['entries'] );
	}

	/**
	 * Test list includes expected columns.
	 */
	public function test_list_includes_expected_columns(): void {
		$this->create_liveblog();
		$command = new ListCommand();

		$this->invoke_expecting_success( $command );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );

		$columns = $format_calls[0]['columns'];
		$this->assertContains( 'ID', $columns );
		$this->assertContains( 'title', $columns );
		$this->assertContains( 'state', $columns );
		$this->assertContains( 'entries', $columns );
		$this->assertContains( 'last_updated', $columns );
	}

	/**
	 * Test list item includes title.
	 */
	public function test_list_item_includes_title(): void {
		$this->create_liveblog( array( 'post_title' => 'My Test Liveblog' ) );
		$command = new ListCommand();

		$this->invoke_expecting_success( $command );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'My Test Liveblog', $format_calls[0]['items'][0]['title'] );
	}

	/**
	 * Test list shows multiple IDs in ids format.
	 */
	public function test_list_ids_format_multiple(): void {
		$post_id1 = $this->create_liveblog();
		$post_id2 = $this->create_liveblog();
		$command  = new ListCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'ids' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		$this->assertStringContainsString( (string) $post_id1, $logs[0] );
		$this->assertStringContainsString( (string) $post_id2, $logs[0] );
	}
}
