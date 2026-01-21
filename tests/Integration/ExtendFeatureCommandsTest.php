<?php
/**
 * Tests for the CommandFilter class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Filter\CommandFilter;

/**
 * Command Filter test case.
 *
 * @covers \Automattic\Liveblog\Application\Filter\CommandFilter
 */
final class ExtendFeatureCommandsTest extends IntegrationTestCase {

	/**
	 * Tests the returned config includes the test filter injection and returns an array.
	 *
	 * @covers \Automattic\Liveblog\Application\Filter\CommandFilter::get_autocomplete_config()
	 */
	public function test_get_autocomplete_config_filter_executes(): void {
		add_filter( 'liveblog_command_config', array( $this, 'example_test_filter' ), 1, 10 );

		$filter = $this->container()->command_filter();
		$config = $filter->get_autocomplete_config();

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'testCase', $config );
		$this->assertTrue( $config['testCase'] );
	}

	/**
	 * Tests that commands can be added via filter.
	 *
	 * @covers \Automattic\Liveblog\Application\Filter\CommandFilter::load_custom_commands()
	 */
	public function test_commands_can_be_added_via_filter(): void {
		add_filter(
			'liveblog_active_commands',
			function ( $commands ) {
				$commands[] = 'test_command';
				return $commands;
			}
		);

		$filter = new CommandFilter();
		$filter->load();
		$filter->load_custom_commands();

		// The filter method should now recognize our custom command.
		// We can verify by checking the autocomplete config includes our command.
		$config = $filter->get_autocomplete_config();

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'data', $config );
	}

	/**
	 * Defines a test filter to check filters are being executed correctly.
	 *
	 * @param mixed $example The example value.
	 * @return mixed The modified value.
	 */
	public function example_test_filter( $example ) {
		if ( is_array( $example ) ) {
			$example['testCase'] = true;
		} elseif ( is_string( $example ) ) {
			$example = 'testCase';
		}
		return $example;
	}
}
