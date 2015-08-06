<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature
 *
 * The base class for autocomplete features.
 */
abstract class WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The regex.
	 *
	 * @var string
	 */
	protected $regex = null;

	/**
	 * The revert regex.
	 *
	 * @var string
	 */
	protected $revert_regex = null;

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array();

    /**
     * Called by WPCOM_Liveblog_Entry_Extend::load()
     *
     * @return void
     */
	public function load() {
		//
	}

	/**
	 * Sets the prefixes.
	 *
	 * @param array $prefixes
	 * @return void
	 */
	public function set_prefixes( $prefixes ) {
		$this->prefixes = $prefixes;
	}

	/**
	 * Gets the prefixes.
	 *
	 * @return array
	 */
	public function get_prefixes() {
		return $this->prefixes;
	}

	/**
	 * Sets the regex.
	 *
	 * @param string $regex
	 * @return void
	 */
	public function set_regex( $regex ) {
		$this->regex = $regex;
	}

	/**
	 * Gets the regex.
	 *
	 * @return string
	 */
	public function get_regex() {
		return $this->regex;
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 * @return array
	 */
	public abstract function get_config( $config );

	/**
	 * Filters the input.
	 *
     * @param mixed $entry
     * @return mixed
	 */
	public function filter( $entry ) {
		return $entry;
	}

	/**
	 * Reverts the input.
	 *
     * @param mixed $entry
     * @return mixed
	 */
	public function revert( $entry ) {
		return $entry;
	}

}
