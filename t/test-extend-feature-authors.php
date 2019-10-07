<?php

class Test_Extend_Feature_Authors extends WP_UnitTestCase {

	/**
	 * Tests the returned config includes the test filter injection and returns an array
	 * @return mixed
	 * @covers Liveblog_Entry_Extend_Feature_Authors::get_config()
	 */
	public function test_get_config_filter_executes() {
		add_filter( 'liveblog_author_config', [ $this, 'example_test_filter' ], 1, 10 );
		$class  = new Liveblog_Entry_Extend_Feature_Authors();
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
