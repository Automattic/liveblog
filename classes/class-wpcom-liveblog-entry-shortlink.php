<?php

/**
 * Enables the use of /shortcode to turn all links into a short link via bitly.com
 *
 * Requires Bitly OAuth key to use
 *
 */
class WPCOM_Liveblog_Entry_Shortlink {

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 */
	public static function load() {

		if ( defined( 'LIVEBLOG_BITLY_OAUTH_KEY' ) ) {
			add_filter( 'liveblog_active_commands', array( __CLASS__, 'add_key_shortlink' ), 10 );
		}
	}

	/**
	 * Adds the /shortlink command and sets which function
	 * is to handle the action.
	 *
	 * @param $commands
	 * @return mixed
	 */
	public static function add_key_shortlink( $commands ) {
		$commands['shortlink'] = array(
			array( __CLASS__, 'add_shortlink_filter' ),
			false,
		);
		return $commands;
	}

	/**
	 * Generates a shortlink using bitly API
	 *
	 * @param  String $url URL to be shortened
	 * @return String      Shortlink or Long url if authentication fails
	 */
	private static function generate_bitly_link( $url ) {
		$ch            = curl_init();
		$domain        = defined( 'LIVEBLOG_BITLY_URL' ) ? LIVEBLOG_BITLY_URL : 'bit.ly';
		$bitly_request = 'https://api-ssl.bitly.com/v3/shorten?access_token=' . LIVEBLOG_BITLY_OAUTH_KEY . '&domain=' . $domain . '&longUrl=' . $url;

		curl_setopt( $ch, CURLOPT_URL, $bitly_request );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

		$response = json_decode( curl_exec( $ch ) );

		curl_close( $ch );

		if ( ! isset( $response->status_code ) || $response->status_code !== 200 ) {
			return $url;
		}

		return $response->data->url;
	}

	/**
	 * Filters the input.
	 *
	 * @param  String $content
	 * @return mixed
	 */
	public static function add_shortlink_filter( $content ) {
		$content = preg_replace_callback(
			"/(?<!\S)((http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?)/",
			array( __CLASS__, 'preg_replace_callback' ),
			$content
		);

		return $content;
	}

	/**
	 * The preg replace callback for the filter.
	 *
	 * @param array $match
	 * @return string
	 */
	public static function preg_replace_callback( $match ) {
		return self::generate_bitly_link( $match[1] );
	}
}
