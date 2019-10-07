<?php

class Test_Extend_Feature_Commands extends WP_UnitTestCase {

	/**
	 * checks the get_commands returns an array
	 * @return bool
	 * @covers Liveblog_Entry_Extend_Feature_Commands::get_commands()
	 */
	public function test_get_commands_returns_array() {
		$class = new Liveblog_Entry_Extend_Feature_Commands();
		$array = is_array( $class->get_commands() );
		$this->assertTrue( $array );
	}

	/**
	 * Tests the returned config includes the test filter injection and returns an array
	 * @return mixed
	 * @covers Liveblog_Entry_Extend_Feature_Commands::get_config()
	 */
	public function test_get_config_filter_executes() {
		add_filter( 'liveblog_command_config', [ $this, 'example_test_filter' ], 1, 10 );
		$class  = new Liveblog_Entry_Extend_Feature_Commands();
		$config = [];
		$test   = $class->get_config( $config );

		$this->assertTrue( is_array( $test ) );
		$this->assertArrayHasKey( 'testCase', $test[0] );
		$this->assertTrue( true === $test[0]['testCase'] );
	}

	/**
	 * Defines a test filter to check filters are being executed correctly
	 * @param  mixed $example
	 * @return mixed
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
