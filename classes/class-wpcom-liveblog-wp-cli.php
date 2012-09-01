<?php
WP_CLI::add_command( 'liveblog', 'WPCOM_Liveblog_WP_CLI' );

class WPCOM_Liveblog_WP_CLI extends WP_CLI_Command {
	public function readme_for_github() {
		$readme_path = dirname( __FILE__ ) . '/../readme.txt';
		$readme = file_get_contents( $readme_path );
		$readme = $this->listify_meta( $readme );
		$readme = $this->add_contributors_wp_org_profile_links( $readme );
		$readme = $this->add_screenshot_links( $readme );
		$readme = $this->markdownify_headings( $readme );
		echo $readme;
	}

	private function markdownify_headings( $readme ) {
		return preg_replace_callback( '/^\s*(=+)\s*(.*?)\s*=+\s*$/m',
			function( $matches ) {
				return "\n" . str_repeat( '#', 4 - strlen( $matches[1] ) ) . ' ' . $matches[2] . "\n";
			},
			$readme );
	}

	private function listify_meta( $readme ) {
		return preg_replace_callback( '/===\s*\n+(.*?)\n\n/s',
			function ( $matches ) {
				$meta = $matches[1];
				if ( !$meta ) return $matches[0];
				return "===\n" . preg_replace( '/^/m', "* ", $meta ) . "\n\n";
			},
			$readme );
	}

	private function add_contributors_wp_org_profile_links( $readme ) {
		return preg_replace_callback( '/Contributors: (.*)/',
			function( $matches ) {
				$links = array_filter( array_map(
					function( $username ) {
						return "[$username](http://profiles.wordpress.org/$username)";
					}, preg_split( '/\s*,\s*/', $matches[1] ) ) );
				return "Contributors: " . implode( ', ', $links );
			},
		   	$readme );
	}

	private function add_screenshot_links( $readme ) {
		return preg_replace_callback( '/==\s*Screenshots\s*==\n(.*?)==/ms',
			function ( $matches ) {
				return "== Screenshots ==\n" . preg_replace( '/^\s*(\d+)\.\s*(.*?)$/m', '![\2](https://raw.github.com/Automattic/liveblog/master/screenshot-\1.png)', $matches[1] ) . "\n==";
			},
			$readme );
	}

	static function help() {
		WP_CLI::line( <<<HELP
usage: wp liveblog readme_for_github
	Converts the readme.txt to real markdown to be used as a README.md
HELP
		);
	}
}
