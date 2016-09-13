<?php

/**
 * Class WPCOM_Liveblog_Entry_Instagram_oEmbed
 *
 * This addresses the issues with Instagram oEmbeds in Liveblog
 */
class WPCOM_Liveblog_Entry_Instagram_oEmbed {

	/**
	 * Called by WPCOM_Liveblog::load(),
	 * it registers the initial filter
	 */
	public static function load() {

		add_filter( 'liveblog_entry_enable_embeds', array( __CLASS__, 'register_filters' ) );

	}

	/**
	 * Register the filters just in time - when we are about to return HTML in endpoint
	 */
	public static function register_filters( $return ) {
		
		add_filter( 'oembed_fetch_url', array( __CLASS__, 'add_omitscript_arg' ), 10, 3 );
		add_filter( 'embed_oembed_html', array( __CLASS__, 'add_custom_script' ), 10, 4 );
		add_filter( 'comment_text', array( __CLASS__, 'unregister_filters' ), 0, 1 );
		return $return;
	}

	/** 
	 * Deregister the filter we added
	 */
	public static function unregister_filters( $return ) {
		remove_filter( 'oembed_fetch_url', array( __CLASS__, 'add_omitscript_arg' ), 10 );
		remove_filter( 'embed_oembed_html', array( __CLASS__, 'add_custom_script' ), 10 );
		return $return;
	}

	public static function add_omitscript_arg( $provider, $url, $args ) {
		if ( true === self::is_instagram_provider( $url ) )  {
			$provider = add_query_arg( 'omitscript', rawurlencode( true ), $provider );
		}
		return $provider;
	}

	public static function add_custom_script( $html, $url, $attr, $post_ID ) {
		if ( true === self::is_instagram_provider( $url ) )	 {
			$instagram_script_uri = '//platform.instagram.com/en_US/embeds.js';
			$script = '<script type="text/javascript">';
			$script .= '(function( $ ){ if ( undefined === window.instgrm ) { $.getScript( '.wp_json_encode( $instagram_script_uri ).' ); } $(".instagram-media a").each( function(){ if ( -1 === $(this).attr("href").indexOf("instagr") ) { $(this).replaceWith( $(this).text() ) } } ); window.instgrm.Embeds.process(); })(jQuery);';
			$script .= '</script>';
			$html .= $script;
		}
		return $html;
	}

	private static function is_instagram_provider( $url ) {
		$wp_oembed = _wp_oembed_get_object();
		$provider = $wp_oembed->get_provider( $url );
		return ( 'https://api.instagram.com/oembed' === $provider );
	}
}
