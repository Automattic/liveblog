<?php
/**
 * Base test case for CLI integration tests.
 *
 * @package Automattic\Liveblog\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration\Cli;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Tests\Integration\IntegrationTestCase;
use WP_CLI;
use WP_CLI_ExitException;

/**
 * Base test case for CLI integration tests.
 *
 * Provides helper methods for testing WP-CLI commands.
 */
abstract class CliTestCase extends IntegrationTestCase {

	/**
	 * Output capture helper.
	 *
	 * @var WpCliOutputCapture
	 */
	protected WpCliOutputCapture $output;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->output = new WpCliOutputCapture();
		$this->output->reset();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->output->reset();
		parent::tear_down();
	}

	/**
	 * Invoke a CLI command.
	 *
	 * @param object $command    Command instance.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 * @throws WP_CLI_ExitException When command calls WP_CLI::error().
	 */
	protected function invoke_command( object $command, array $args = array(), array $assoc_args = array() ): void {
		$command( $args, $assoc_args );
	}

	/**
	 * Invoke a command expecting it to succeed.
	 *
	 * @param object $command    Command instance.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function invoke_expecting_success( object $command, array $args = array(), array $assoc_args = array() ): void {
		try {
			$this->invoke_command( $command, $args, $assoc_args );
		} catch ( WP_CLI_ExitException $e ) {
			$this->fail( sprintf( 'Command failed unexpectedly with error: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Invoke a command expecting it to fail.
	 *
	 * @param object $command    Command instance.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return WP_CLI_ExitException The caught exception.
	 */
	protected function invoke_expecting_error( object $command, array $args = array(), array $assoc_args = array() ): WP_CLI_ExitException {
		try {
			$this->invoke_command( $command, $args, $assoc_args );
			$this->fail( 'Command should have failed with an error.' );
		} catch ( WP_CLI_ExitException $e ) {
			return $e;
		}
	}

	/**
	 * Assert command succeeded with a success message.
	 *
	 * @param string $message Expected message fragment (optional).
	 * @return void
	 */
	protected function assert_command_success( string $message = '' ): void {
		$this->assertTrue( $this->output->has_success(), 'Expected success message but none was output.' );

		if ( '' !== $message ) {
			$this->assert_success_contains( $message );
		}
	}

	/**
	 * Assert command output a warning.
	 *
	 * @param string $message Expected message fragment (optional).
	 * @return void
	 */
	protected function assert_command_warning( string $message = '' ): void {
		$this->assertTrue( $this->output->has_warning(), 'Expected warning message but none was output.' );

		if ( '' !== $message ) {
			$this->assert_warning_contains( $message );
		}
	}

	/**
	 * Assert success message contains text.
	 *
	 * @param string $expected Expected text.
	 * @return void
	 */
	protected function assert_success_contains( string $expected ): void {
		$messages = $this->output->get_success_messages();
		$found    = false;

		foreach ( $messages as $message ) {
			if ( str_contains( $message, $expected ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf(
				'Expected success message containing "%s". Got: %s',
				$expected,
				implode( ', ', $messages )
			)
		);
	}

	/**
	 * Assert error message contains text.
	 *
	 * @param string $expected Expected text.
	 * @return void
	 */
	protected function assert_error_contains( string $expected ): void {
		$messages = $this->output->get_error_messages();
		$found    = false;

		foreach ( $messages as $message ) {
			if ( str_contains( $message, $expected ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf(
				'Expected error message containing "%s". Got: %s',
				$expected,
				implode( ', ', $messages )
			)
		);
	}

	/**
	 * Assert warning message contains text.
	 *
	 * @param string $expected Expected text.
	 * @return void
	 */
	protected function assert_warning_contains( string $expected ): void {
		$messages = $this->output->get_warning_messages();
		$found    = false;

		foreach ( $messages as $message ) {
			if ( str_contains( $message, $expected ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf(
				'Expected warning message containing "%s". Got: %s',
				$expected,
				implode( ', ', $messages )
			)
		);
	}

	/**
	 * Assert log output contains text.
	 *
	 * @param string $expected Expected text.
	 * @return void
	 */
	protected function assert_log_contains( string $expected ): void {
		$logs  = $this->output->get_logs();
		$found = false;

		foreach ( $logs as $log ) {
			if ( str_contains( $log, $expected ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf(
				'Expected log containing "%s". Got: %s',
				$expected,
				implode( ', ', $logs )
			)
		);
	}

	/**
	 * Assert line output contains text.
	 *
	 * @param string $expected Expected text.
	 * @return void
	 */
	protected function assert_line_contains( string $expected ): void {
		$lines = $this->output->get_lines();
		$found = false;

		foreach ( $lines as $line ) {
			if ( str_contains( $line, $expected ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf(
				'Expected line output containing "%s". Got: %s',
				$expected,
				implode( ', ', $lines )
			)
		);
	}

	/**
	 * Assert confirm was called.
	 *
	 * @return void
	 */
	protected function assert_confirm_called(): void {
		$this->assertTrue( $this->output->was_confirm_called(), 'Expected confirm() to be called.' );
	}

	/**
	 * Assert confirm was not called.
	 *
	 * @return void
	 */
	protected function assert_confirm_not_called(): void {
		$this->assertFalse( $this->output->was_confirm_called(), 'Did not expect confirm() to be called.' );
	}

	/**
	 * Create a post and enable liveblog on it.
	 *
	 * @param array $args Post arguments.
	 * @return int Post ID.
	 */
	protected function create_liveblog( array $args = array() ): int {
		$defaults = array(
			'post_title'  => 'Test Liveblog',
			'post_status' => 'publish',
		);

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );
		$this->enable_liveblog( $post_id );

		return $post_id;
	}

	/**
	 * Enable liveblog on a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function enable_liveblog( int $post_id ): void {
		$liveblog = LiveblogPost::from_id( $post_id );
		if ( $liveblog ) {
			$liveblog->enable();
		}
	}

	/**
	 * Archive a liveblog.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function archive_liveblog( int $post_id ): void {
		$liveblog = LiveblogPost::from_id( $post_id );
		if ( $liveblog ) {
			$liveblog->archive();
		}
	}

	/**
	 * Add an entry to a liveblog.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Entry content.
	 * @param array  $args    Additional arguments.
	 * @return int Entry ID.
	 */
	protected function add_entry( int $post_id, string $content, array $args = array() ): int {
		$user = $args['user'] ?? self::factory()->user->create_and_get();

		$entry_service = $this->container()->entry_service();
		$entry_id      = $entry_service->create( $post_id, $content, $user );

		if ( ! empty( $args['key_event'] ) ) {
			update_comment_meta( $entry_id->to_int(), 'liveblog_key_entry', '1' );
		}

		return $entry_id->to_int();
	}

	/**
	 * Create a test user.
	 *
	 * @param array $args User arguments.
	 * @return \WP_User
	 */
	protected function create_user( array $args = array() ): \WP_User {
		return self::factory()->user->create_and_get( $args );
	}

	/**
	 * Get liveblog meta value.
	 *
	 * @param int $post_id Post ID.
	 * @return string|false Meta value or false if not set.
	 */
	protected function get_liveblog_meta( int $post_id ) {
		return get_post_meta( $post_id, 'liveblog', true );
	}
}
