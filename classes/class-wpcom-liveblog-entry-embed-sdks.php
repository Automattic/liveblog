<?php
/**
 * SDK loader for embedded content in liveblog entries.
 *
 * @package Liveblog
 */

/**
 * Class WPCOM_Liveblog_Entry_Embed_SDKs
 *
 * Provider SDKs (Facebook, Twitter/X, Instagram, Reddit) are no longer enqueued
 * unconditionally on every Liveblog post. Instead, the list of provider SDK URLs
 * is passed to the front-end app, which lazy-loads each SDK on demand the first
 * time an entry containing that provider's embed markup is rendered. This avoids
 * contacting third-party domains when no matching embed is present, and correctly
 * handles embeds arriving in entries polled after the initial page load.
 *
 * @see triggerOembedLoad() in src/react/utils/utils.js for the client-side loader.
 */
class WPCOM_Liveblog_Entry_Embed_SDKs {

	/**
	 * A list of provider SDKs.
	 *
	 * @var array
	 */
	protected static $sdks = array(
		'facebook'  => 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.5',
		'twitter'   => 'https://platform.twitter.com/widgets.js',
		'instagram' => 'https://www.instagram.com/embed.js',
		'reddit'    => 'https://embed.reddit.com/widgets.js',
	);

	/**
	 * Called by WPCOM_Liveblog::load(),
	 * acts as a constructor
	 */
	public static function load() {
		/**
		 * Filters the list of provider embed SDKs made available to the front-end.
		 *
		 * Return an empty array to disable all third-party SDK loading, or remove
		 * individual providers (by key) to prevent their SDK from ever being loaded.
		 *
		 * @param array $sdks Map of provider name => SDK script URL.
		 */
		self::$sdks = apply_filters( 'liveblog_embed_sdks', self::$sdks );

		add_filter( 'liveblog_settings', array( __CLASS__, 'add_sdks_to_settings' ) );
	}

	/**
	 * Expose the provider SDK URLs to the front-end app so it can lazy-load
	 * each SDK only when a matching embed is actually rendered.
	 *
	 * @param array $settings The liveblog front-end settings array.
	 * @return array Settings with the `embed_sdks` map added.
	 */
	public static function add_sdks_to_settings( $settings ) {
		$settings['embed_sdks'] = self::get_sdks();
		return $settings;
	}

	/**
	 * Get the (filtered) list of provider SDK URLs.
	 *
	 * @return array Map of provider name => SDK script URL.
	 */
	public static function get_sdks() {
		return self::$sdks;
	}
}
