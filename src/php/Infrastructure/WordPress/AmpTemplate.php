<?php
/**
 * AMP template renderer for liveblog.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

/**
 * Handles AMP template rendering with theme override support.
 *
 * Templates can be overridden by placing them in:
 * - Child theme: /liveblog/amp/{template}.php
 * - Parent theme: /liveblog/amp/{template}.php
 * - Plugin default: /templates/amp/{template}.php
 */
final class AmpTemplate {

	/**
	 * Theme template path (relative to theme root).
	 *
	 * @var string
	 */
	private string $theme_template_path;

	/**
	 * Plugin template path (relative to plugin root).
	 *
	 * @var string
	 */
	private string $plugin_template_path;

	/**
	 * Template data available to templates.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Plugin directory path.
	 */
	public function __construct( string $plugin_dir ) {
		$this->plugin_dir = $plugin_dir;

		/**
		 * Filters the theme template path for AMP liveblog templates.
		 *
		 * @param string $path Template path relative to theme root.
		 */
		$this->theme_template_path = apply_filters( 'liveblog_amp_theme_template_path', '/liveblog/amp/' );

		/**
		 * Filters the plugin template path for AMP liveblog templates.
		 *
		 * @param string $path Template path relative to plugin root.
		 */
		$this->plugin_template_path = apply_filters( 'liveblog_amp_plugin_template_path', '/templates/amp/' );
	}

	/**
	 * Get a template variable.
	 *
	 * @param string $name          Variable name.
	 * @param mixed  $default_value Default value if not set.
	 * @return mixed Variable value or default.
	 */
	public function get( string $name, $default_value = false ) {
		return $this->data[ $name ] ?? $default_value;
	}

	/**
	 * Render a template.
	 *
	 * Looks for template in child theme, parent theme, then plugin.
	 *
	 * @param string              $name      Template name (without .php extension).
	 * @param array<string,mixed> $variables Variables to pass to template.
	 * @return string Rendered template HTML.
	 */
	public function render( string $name, array $variables = array() ): string {
		$name = ltrim( esc_attr( $name ), '/' ) . '.php';

		$child_theme = get_stylesheet_directory() . $this->theme_template_path . $name;
		$theme       = get_template_directory() . $this->theme_template_path . $name;
		$plugin      = $this->plugin_dir . $this->plugin_template_path . $name;

		$path = false;

		if ( file_exists( $child_theme ) ) {
			$path = $child_theme;
		} elseif ( file_exists( $theme ) ) {
			$path = $theme;
		} elseif ( file_exists( $plugin ) ) {
			$path = $plugin;
		}

		if ( false === $path ) {
			return 'Template Not Found: ' . esc_html( $name );
		}

		$this->data = $variables;

		ob_start();
		include $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile -- Template loading with validated path.
		return (string) ob_get_clean();
	}

	/**
	 * Load and output a partial template.
	 *
	 * @param string              $name      Template name.
	 * @param array<string,mixed> $variables Variables to pass to template.
	 * @return void
	 */
	public function load_part( string $name, array $variables = array() ): void {
		echo $this->render( $name, $variables ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output.
	}
}
