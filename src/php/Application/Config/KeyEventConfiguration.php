<?php
/**
 * Key event configuration for templates and formats.
 *
 * @package Automattic\Liveblog\Application\Config
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Config;

/**
 * Configuration for key event templates and formats.
 *
 * Manages available templates, formats, and their settings
 * for displaying key events in liveblogs.
 */
final class KeyEventConfiguration {

	/**
	 * Meta key for template.
	 *
	 * @var string
	 */
	public const META_KEY_TEMPLATE = '_liveblog_key_entry_template';

	/**
	 * Meta key for format.
	 *
	 * @var string
	 */
	public const META_KEY_FORMAT = '_liveblog_key_entry_format';

	/**
	 * Meta key for limit.
	 *
	 * @var string
	 */
	public const META_KEY_LIMIT = '_liveblog_key_entry_limit';

	/**
	 * Default template name.
	 *
	 * @var string
	 */
	private const DEFAULT_TEMPLATE = 'timeline';

	/**
	 * Default format name.
	 *
	 * @var string
	 */
	private const DEFAULT_FORMAT = 'first-linebreak';

	/**
	 * Available templates to render entries.
	 *
	 * Format: name => [template_file, wrapper_element, css_class]
	 *
	 * @var array<string, array{0: string, 1: string, 2: string}>
	 */
	private array $templates = array(
		'timeline' => array( 'liveblog-key-single-timeline.php', 'ul', 'liveblog-key-timeline' ),
		'list'     => array( 'liveblog-key-single-list.php', 'ul', 'liveblog-key-list' ),
	);

	/**
	 * Available content formats.
	 *
	 * Format: name => callback|false
	 *
	 * @var array<string, callable|false>
	 */
	private array $formats = array();

	/**
	 * Whether configuration has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Initialize configuration (call after init hook).
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->formats = array(
			'first-linebreak' => array( $this, 'format_first_linebreak' ),
			'first-sentence'  => array( $this, 'format_first_sentence' ),
			'full'            => false,
		);

		/**
		 * Filter available key event templates.
		 *
		 * @param array $templates Available templates.
		 */
		$this->templates = apply_filters( 'liveblog_key_templates', $this->templates );

		/**
		 * Filter available key event formats.
		 *
		 * @param array $formats Available formats.
		 */
		$this->formats = apply_filters( 'liveblog_key_formats', $this->formats );

		$this->initialized = true;
	}

	/**
	 * Get available template names.
	 *
	 * @return array<string> Template names.
	 */
	public function get_template_names(): array {
		return array_keys( $this->templates );
	}

	/**
	 * Get available format names.
	 *
	 * @return array<string> Format names.
	 */
	public function get_format_names(): array {
		return array_keys( $this->formats );
	}

	/**
	 * Get template configuration by name.
	 *
	 * @param string $name The template name.
	 * @return array{0: string, 1: string, 2: string}|null Template config or null if not found.
	 */
	public function get_template( string $name ): ?array {
		return $this->templates[ $name ] ?? null;
	}

	/**
	 * Get the current template for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array{0: string, 1: string, 2: string} Template configuration.
	 */
	public function get_current_template( int $post_id ): array {
		$type = get_post_meta( $post_id, self::META_KEY_TEMPLATE, true );

		if ( ! empty( $type ) && isset( $this->templates[ $type ] ) ) {
			return $this->templates[ $type ];
		}

		return $this->templates[ self::DEFAULT_TEMPLATE ];
	}

	/**
	 * Get the current format callback for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return callable|false Format callback or false for full content.
	 */
	public function get_current_format( int $post_id ) {
		$type = get_post_meta( $post_id, self::META_KEY_FORMAT, true );

		if ( ! empty( $type ) && isset( $this->formats[ $type ] ) ) {
			return $this->formats[ $type ];
		}

		return $this->formats[ self::DEFAULT_FORMAT ];
	}

	/**
	 * Get the current limit for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return int The limit (0 for unlimited).
	 */
	public function get_current_limit( int $post_id ): int {
		$limit = get_post_meta( $post_id, self::META_KEY_LIMIT, true );
		return absint( $limit );
	}

	/**
	 * Format content using the current format for a post.
	 *
	 * @param string $content The content to format.
	 * @param int    $post_id The post ID.
	 * @return string Formatted content.
	 */
	public function format_content( string $content, int $post_id ): string {
		$format = $this->get_current_format( $post_id );

		if ( false !== $format && is_callable( $format ) ) {
			return call_user_func( $format, $content );
		}

		return $content;
	}

	/**
	 * Save template settings for a post.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $template The template name.
	 * @param string $format   The format name.
	 * @param int    $limit    The entry limit.
	 * @return void
	 */
	public function save_settings( int $post_id, string $template, string $format, int $limit ): void {
		// Validate and save template.
		$valid_template = isset( $this->templates[ $template ] ) ? $template : self::DEFAULT_TEMPLATE;
		update_post_meta( $post_id, self::META_KEY_TEMPLATE, $valid_template );

		// Validate and save format.
		$valid_format = isset( $this->formats[ $format ] ) ? $format : self::DEFAULT_FORMAT;
		update_post_meta( $post_id, self::META_KEY_FORMAT, $valid_format );

		// Save limit.
		update_post_meta( $post_id, self::META_KEY_LIMIT, $limit );
	}

	/**
	 * Format content to first linebreak/paragraph.
	 *
	 * @param string $content The content to format.
	 * @return string Formatted content.
	 */
	public function format_first_linebreak( string $content ): string {
		// Standardise returns into <br /> for linebreaks.
		$content = str_replace( array( "\r", "\n" ), '<br />', $content );

		// Explode the content by the linebreaks.
		$parts = explode( '<br />', $content );

		// Strip all non-accepted tags.
		return wp_strip_all_tags( $parts[0], '<strong></strong><em></em><span></span><img>' );
	}

	/**
	 * Format content to first sentence.
	 *
	 * @param string $content The content to format.
	 * @return string Formatted content.
	 */
	public function format_first_sentence( string $content ): string {
		// Grab the first sentence of the content.
		$content = preg_replace( '/(.*?[?!.](?=\s|$)).*/', '\\1', $content ) ?? $content;

		// Strip all non-accepted tags.
		return wp_strip_all_tags( $content, '<strong></strong><em></em><span></span><img>' );
	}

	/**
	 * Check if a template name is valid.
	 *
	 * @param string $name The template name.
	 * @return bool True if valid.
	 */
	public function is_valid_template( string $name ): bool {
		return isset( $this->templates[ $name ] );
	}

	/**
	 * Check if a format name is valid.
	 *
	 * @param string $name The format name.
	 * @return bool True if valid.
	 */
	public function is_valid_format( string $name ): bool {
		return isset( $this->formats[ $name ] );
	}
}
