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
		
		add_filter( 'embed_oembed_html', array( __CLASS__, 'filter_oembed_html' ), 10, 4 );
		//Deregister the filter as soon as it is no longer needed
		add_filter( 'comment_text', array( __CLASS__, 'unregister_filters' ), 0, 1 );
		return $return;
	}

	/** 
	 * Deregister the filter we added
	 */
	public static function unregister_filters( $return ) {
		remove_filter( 'embed_oembed_html', array( __CLASS__, 'filter_oembed_html' ) );
		return $return;
	}

	/**
	 * Filter the oEmbed HTML only if instagram is the provider
	 */
	public static function filter_oembed_html( $html, $url, $attr, $post_ID ) {

		$oembed = _wp_oembed_get_object();
		$provider = $oembed->get_provider( $url );
		if ( false !== strpos( 'instagr.am' ) || false !== strpos( 'instagram.com' ) ) {
			$html = self::instagram_handler( $html, $url, $attr, $post_ID );
		}

		return $html;

	}

	/**
	 * The custom modifications themselves
	 *
	 * This method is removing the default script and attaches it's own
	 */
	public static function instagram_handler( $html, $url, $attr, $post_ID ) {
		$instagram_script_uri = '//platform.instagram.com/en_US/embeds.js';
		$dom = new DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$script = $dom->getElementsByTagName( 'script' );

		$new_script = $dom->createElement( 'script', '(function( $ ){ if ( undefined === window.instgrm ) { $.getScript( '.wp_json_encode( $instagram_script_uri ).' ); } $(".instagram-media a").each( function(){ if ( -1 === $(this).attr("href").indexOf("instagr") ) { $(this).replaceWith( $(this).text() ) } } ); window.instgrm.Embeds.process(); })(jQuery);' );
		$new_script->setAttribute( 'type', 'text/javascript' );

		if ( false === empty( $script ) ) {
			foreach( $script as $item ) {
				if ( $instagram_script_uri === $item->getAttribute('src') ) {
					$parent = $item->parentNode;
					$item->parentNode->removeChild($item);
					$parent->appendChild( $new_script );
					break;
				}
			}
		}
		$html = $dom->saveHTML();
		return $html;
	}
}
