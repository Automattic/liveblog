<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend
 *
 * This extends the entry box with an
 * autocomplete system.
 */
class WPCOM_Liveblog_Entry_Extend {

	/**
	 * Autocomplete settings
	 *
	 * @var array
	 */
	public static $autocomplete = array();

	/**
	 * Autocomplete features
	 *
	 * @var array
	 */
	protected static $features = 'commands, emojis, hashtags, authors';

	/**
	 * Called by WPCOM_Liveblog::load(),
	 * it attaches the new command.
	 */
	public static function load() {
		add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'strip_input' ), 1 );
		add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'strip_input' ), 1 );
		add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'fix_links_wrapped_in_div' ), 1 );
		add_filter( 'liveblog_before_update_entry', array( __CLASS__, 'fix_links_wrapped_in_div' ), 1 );
		add_filter( 'liveblog_before_preview_entry', array( __CLASS__, 'fix_links_wrapped_in_div' ), 1 );

		// Allow the features to be seperated in multiple ways: via spaces,
		// pipes or commas. This line explodes via spaces and pipes then
		// proceeds to explode it via commas. This allows for the tidy:
		// feature_one, feature_two, feature_three
		self::$features = explode( ',', preg_replace( '~[ |]+~', ',', self::$features ) );
		self::$features = array_filter( self::$features, 'strlen' );

		// We pass these features into a filter to allow other plugins,
		// themes, etc. to enable or disable any of the features.
		self::$features = apply_filters( 'liveblog_features', self::$features );

		// This is the autocomplete prefix regex.
		$regex_prefix = '~(?:(?<!\S)|>?)((?:';

		// This is the autocomplete postfix regex.
		$regex_postfix = '){1}([0-9_\-\p{L}]*[_\-\p{L}][0-9_\-\p{L}]*))(?:<)?~um';

		// We loop every feature and set them up individually.
		foreach ( self::$features as $name ) {

			// Grab the class from what we expect the classname to be.
			// WPCOM_Liveblog_Entry_Extend_Feature_{{ $name }}
			$class   = __CLASS__ . '_Feature_' . ucfirst( $name );
			$feature = new $class();

			// Add all the basic (common) feature filters.
			add_filter( 'liveblog_extend_autocomplete', array( $feature, 'get_config' ), 10 );
			add_filter( 'liveblog_before_insert_entry', array( $feature, 'filter' ), 10 );
			add_filter( 'liveblog_before_update_entry', array( $feature, 'filter' ), 10 );
			add_filter( 'liveblog_before_preview_entry', array( $feature, 'filter' ), 10 );
			add_filter( 'liveblog_before_edit_entry', array( $feature, 'revert' ), 10 );

			// Set the prefixes to the filtered prefixes.
			// This allows themes, plugins, etc. to change prefixes.
			$feature->set_prefixes( apply_filters( 'liveblog_' . $name . '_prefixes', $feature->get_prefixes() ) );

			// We apply the prefixes to the regex so we can match them
			// during the autocomplete and matching process.
			$regex = $regex_prefix . implode( '|', $feature->get_prefixes() ) . $regex_postfix;
			$feature->set_regex( apply_filters( 'liveblog_' . $name . '_regex', $regex ) );

			// Finally, simply load the feature as it may have it's
			// own setup that it is required to complete.
			$feature->load();
		}

		// Allow external sources to build the autocomplete config that is
		// used by the frontend javascript for autocomplete matching.
		self::$autocomplete = apply_filters( 'liveblog_extend_autocomplete', self::$autocomplete );
	}

	/**
	 * Returns the enabled features
	 *
	 * @return array
	 */
	public static function get_enabled_features() {
		return array_values( self::$features );
	}

	/**
	 * Returns the settings for autocomplete that are used by
	 * the frontend javascript for autocomplete matching.
	 *
	 * @return array
	 */
	public static function get_autocomplete() {
		return self::$autocomplete;
	}

	/**
	 * Strips out unneeded spans
	 *
	 * @param $entry
	 * @return mixed
	 */
	public static function strip_input( $entry ) {
		// Replace all escaped spaces with normal spaces to
		// allow matching to work as we expect it to.
		$entry['content'] = str_replace( '&nbsp;', ' ', $entry['content'] );

		// Strip at all the atwho classes that may have been
		// generated from the front end autocompletion.
		$entry['content'] = preg_replace( '~\\<span\\s+class\\=\\\\?"atwho\\-\\w+\\\\?"\\s*>([^<]*)\\</span\\>~', '$1', $entry['content'] );

		return $entry;
	}

	/**
	 * Replaces div wrapping oembedable links with p for core to pick those up
	 *
	 * The div wrapping links which would otherwise would be on their own line
	 * is coming from Webkit browser's contenteditable
	 *
	 * $param array Liveblog entry
	 * @return array
	*/
	public static function fix_links_wrapped_in_div( $entry ) {
		$entry['content'] = preg_replace( '|(<div(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/div>)|i', '<p>${2}</p>', $entry['content'] );
		return $entry;
	}

}
