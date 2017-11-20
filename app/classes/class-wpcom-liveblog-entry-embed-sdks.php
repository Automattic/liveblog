<?php

/**
 * Class WPCOM_Liveblog_Entry_Embed_SDKs
 *
 * As we're render posts in React it requires some SDKs to pulled in on page load
 */
class WPCOM_Liveblog_Entry_Embed_SDKs {

	/**
	 * @var A list of provider SDKs
	 */
	protected static $sdks = array(
		'facebook'  => 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v2.5',
		'twitter'   => 'https://platform.twitter.com/widgets.js',
		'instagram' => 'https://platform.instagram.com/en_US/embeds.js',
		'reddit' 	=> 'https://embed.redditmedia.com/widgets/platform.js'
	);

	/**
	 * Called by WPCOM_Liveblog::load(),
	 * acts as a constructor
	 */
	public static function load() {
		self::$sdks = apply_filters( 'liveblog_embed_sdks', self::$sdks );

      	add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Loop through provider SDKs and enqueue them
	 * 
	 * @return void
	 */
	public static function enqueue() {	
		foreach ( self::$sdks as $name => $url ) {
			wp_enqueue_script( $name, $url, false );
		}
	}
}