<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature_Emojis
 *
 * The base class for autocomplete features.
 */
class WPCOM_Liveblog_Entry_Extend_Feature_Emojis extends WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The class prefix.
	 *
	 * @var string
	 */
	protected $class_prefix = 'emoji-';

	/**
	 * The emojis.
	 *
	 * @var string
	 */
	protected $emojis = array(
		'smile' => 'Smile',
		'grin' => 'Grin',
	);

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array( ':', '\x{003a}' );

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load()
	 *
	 * @return void
	 */
	public function load() {
		$this->emojis       = apply_filters( 'liveblog_active_emojis', $this->emojis );
		$this->class_prefix = apply_filters( 'liveblog_emoji_class', $this->class_prefix );

		add_filter( 'comment_class',          array( $this, 'add_emoji_class_to_entry' ), 10, 3 );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public function get_config( $config ) {
		$emojis = array();
		foreach ($this->get_emojis() as $key => $val) {
			$emojis[] = array( 'key' => $key, 'name' => $val );
		}

		$config[] = array(
			'at'         => $this->get_prefixes()[0],
			'data'       => $emojis,
		    'displayTpl' => '<li>${name} <img src="'.plugins_url('../images/emojis', __FILE__).'/${key}.png"  height="20" width="20" /></li>',
			'insertTpl'  => '<img src="'.plugins_url('../images/emojis', __FILE__).'/${key}.png" class="liveblog-emoji '.$this->class_prefix.'${key}" data-emoji="${key}">',
		);

		return $config;
	}

	/**
	 * Maps an emoji for at.js.
	 *
	 * @param  string $val
	 * @param  string $key
	 * @return array
	 */
	public function map_emoji($val) {
		return array( 'key' => $key, 'name' => $val );
	}

	/**
	 * Get all the available emojis.
	 *
	 * @return array
	 */
	public function get_emojis() {
		return $this->emojis;
	}

	/**
	 * Sets the regex.
	 *
	 * @param string $regex
	 * @return void
	 */
	public function set_regex( $regex ) {
		$regex_prefix = substr($regex, 0, strlen($regex) - 3);
		$regex_postfix = substr($regex, strlen($regex) - 3);
		$this->regex = $regex_prefix.'(?:'.implode( '|', $this->get_prefixes() ).')'.$regex_postfix;
	}

	/**
	 * Adds emoji-{emoji} class to entry
	 *
	 * @param $classes
	 * @param $class
	 * @param $comment_id
	 * @return array
	 */
	public function add_emoji_class_to_entry( $classes, $class, $comment_id ) {
		$emojis   = array();
		$comment = get_comment( $comment_id );

		if ( 'liveblog' == $comment->comment_type ) {
			preg_match_all( '/(?<!\w)'.preg_quote( $this->class_prefix ).'\w+/', $comment->comment_content, $emojis );
			$classes = array_merge( $classes, $emojis[0] );
		}

		return $classes;
	}

}
