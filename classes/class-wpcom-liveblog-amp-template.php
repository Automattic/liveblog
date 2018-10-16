<?php

/**
 * Class WPCOM_Liveblog_AMP_Template
 *
 * Simple Class for working with Templates.
 */
class WPCOM_Liveblog_AMP_Template {

	/**
	 * Theme Template Path
	 *
	 * @var string
	 */
	public $theme_template_path = '/liveblog/amp/';

	/**
	 * Theme Template Path
	 *
	 * @var string
	 */
	public $plugin_template_path = '/templates/amp/';

	/**
	 * Template Data
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Contrustor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->theme_template_path  = apply_filters( 'liveblog_amp_theme_template_path', $this->theme_template_path );
		$this->plugin_template_path = apply_filters( 'liveblog_amp_plugin_template_path', $this->plugin_template_path );
	}

	/**
	 * Get a template variable.
	 *
	 * @param  string $name    Name if variable.
	 * @param  mixed  $default  Default value.
	 * @return mixed          value
	 */
	public function get( $name, $default = false ) {
		if ( isset( $this->data[ $name ] ) ) {
			return $this->data[ $name ];
		}
		return $default;
	}

	/**
	 * Render template.
	 *
	 * @param  string $name      Name of Template.
	 * @param  array  $variables Variables to be passed to Template.
	 * @return string            Rendered Template
	 */
	public function render( $name, $variables = array() ) {

		$name        = ltrim( esc_attr( $name ), '/' ) . '.php';
		$theme       = get_template_directory() . $this->theme_template_path . $name;
		$child_theme = get_stylesheet_directory() . $this->theme_template_path . $name;
		$plugin      = dirname( __DIR__ ) . $this->plugin_template_path . $name;
		$path        = false;

		if ( file_exists( $child_theme ) ) {
			$path = $child_theme;
		} elseif ( file_exists( $theme ) ) {
			$path = $theme;
		} elseif ( file_exists( $plugin ) ) {
			$path = $plugin;
		}

		if ( false === $path ) {
			return 'Template Not Found: ' . $name;
		}

		$this->data = $variables;

		ob_start();
		include $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
		return ob_get_clean();
	}

	/**
	 * Load partial
	 *
	 * @param  string $name      Name of Template.
	 * @param  array  $variables Variables to be passed to Template.
	 * @return void
	 */
	public function load_part( $name, $variables = array() ) {
		echo WPCOM_Liveblog_AMP::get_template( $name, $variables ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
