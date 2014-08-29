<?php

class Test_Liveblog extends WP_UnitTestCase {
	function test_headers_should_skip_newlines() {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\nba" ) );
	}

	function test_headers_should_skip_crs() {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( "ba\rba" ) );
	}

	function test_headers_should_skip_null_bytes() {
		$this->assertEquals( 'baba', WPCOM_Liveblog::sanitize_http_header( 'ba' . chr(0) . 'ba' ) );
	}

	function test_protected_liveblog_meta_should_return_true() {
		$this->assertEquals( true, is_protected_meta( WPCOM_Liveblog::key ) );
	}	
}
