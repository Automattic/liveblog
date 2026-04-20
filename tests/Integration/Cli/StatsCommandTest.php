<?php
/**
 * Integration tests for StatsCommand.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Infrastructure\CLI\StatsCommand;

/**
 * StatsCommand integration test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\StatsCommand
 */
final class StatsCommandTest extends CliTestCase {

	/**
	 * Test stats with table format.
	 */
	public function test_stats_table_format(): void {
		$this->create_liveblog();
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command );

		$format_calls = $this->output->get_format_items_calls();
		$this->assertNotEmpty( $format_calls );
		$this->assertSame( 'table', $format_calls[0]['format'] );
	}

	/**
	 * Test stats with JSON format.
	 */
	public function test_stats_json_format(): void {
		$this->create_liveblog();
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		$json = json_decode( implode( '', $logs ), true );
		$this->assertIsArray( $json );
		$this->assertArrayHasKey( 'Total Liveblogs', $json );
	}

	/**
	 * Test stats with YAML format.
	 */
	public function test_stats_yaml_format(): void {
		$this->create_liveblog();
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'yaml' ) );

		$logs = $this->output->get_logs();
		$this->assertNotEmpty( $logs );
		$this->assertStringContainsString( 'Total Liveblogs:', $logs[0] );
	}

	/**
	 * Test stats shows liveblog counts.
	 */
	public function test_stats_shows_liveblog_counts(): void {
		$this->create_liveblog();
		$archived_id = $this->create_liveblog();
		$this->archive_liveblog( $archived_id );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '2', $json['Total Liveblogs'] );
		$this->assertSame( '1', $json['Enabled'] );
		$this->assertSame( '1', $json['Archived'] );
	}

	/**
	 * Test stats shows entry counts.
	 */
	public function test_stats_shows_entry_counts(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Entry 1' );
		$this->add_entry( $post_id, 'Entry 2' );
		$this->add_entry( $post_id, 'Entry 3' );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '3', $json['Total Entries'] );
	}

	/**
	 * Test stats shows key events count.
	 */
	public function test_stats_shows_key_events(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Regular entry' );
		$this->add_entry( $post_id, 'Key event 1', array( 'key_event' => true ) );
		$this->add_entry( $post_id, 'Key event 2', array( 'key_event' => true ) );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '2', $json['Key Events'] );
	}

	/**
	 * Test stats shows unique authors.
	 */
	public function test_stats_shows_unique_authors(): void {
		$post_id = $this->create_liveblog();
		$user1   = $this->create_user();
		$user2   = $this->create_user();
		$user3   = $this->create_user();
		$this->add_entry( $post_id, 'Entry 1', array( 'user' => $user1 ) );
		$this->add_entry( $post_id, 'Entry 2', array( 'user' => $user2 ) );
		$this->add_entry( $post_id, 'Entry 3', array( 'user' => $user3 ) );
		$this->add_entry( $post_id, 'Entry 4', array( 'user' => $user1 ) );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '3', $json['Unique Authors'] );
	}

	/**
	 * Test stats on empty site.
	 */
	public function test_stats_empty_site(): void {
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '0', $json['Total Liveblogs'] );
		$this->assertSame( '0', $json['Total Entries'] );
	}

	/**
	 * Test stats shows most active liveblog.
	 */
	public function test_stats_shows_most_active_liveblog(): void {
		$post_id1 = $this->create_liveblog( array( 'post_title' => 'Less Active' ) );
		$post_id2 = $this->create_liveblog( array( 'post_title' => 'Most Active' ) );
		$this->add_entry( $post_id1, 'Entry 1' );
		$this->add_entry( $post_id2, 'Entry 1' );
		$this->add_entry( $post_id2, 'Entry 2' );
		$this->add_entry( $post_id2, 'Entry 3' );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertStringContainsString( 'Most Active', $json['Most Active Liveblog'] );
		$this->assertStringContainsString( '3 entries', $json['Most Active Liveblog'] );
	}

	/**
	 * Test stats shows most recent entry.
	 */
	public function test_stats_shows_most_recent_entry(): void {
		$post_id = $this->create_liveblog();
		$this->add_entry( $post_id, 'Entry' );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertNotSame( 'None', $json['Most Recent Entry'] );
	}

	/**
	 * Test stats shows average entries per liveblog.
	 */
	public function test_stats_shows_average_entries(): void {
		$post_id1 = $this->create_liveblog();
		$post_id2 = $this->create_liveblog();
		$this->add_entry( $post_id1, 'Entry 1' );
		$this->add_entry( $post_id1, 'Entry 2' );
		$this->add_entry( $post_id2, 'Entry 1' );
		$this->add_entry( $post_id2, 'Entry 2' );
		$command = new StatsCommand();

		$this->invoke_expecting_success( $command, array(), array( 'format' => 'json' ) );

		$logs = $this->output->get_logs();
		$json = json_decode( implode( '', $logs ), true );
		$this->assertSame( '2.0', $json['Avg Entries/Liveblog'] );
	}
}
