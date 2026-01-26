<?php
/**
 * Template renderer for liveblog templates.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Handles loading and rendering of liveblog templates.
 *
 * Supports theme overrides in child-theme/liveblog/ and theme/liveblog/ directories.
 */
final class TemplateRenderer {

	/**
	 * Plugin templates directory path.
	 *
	 * @var string
	 */
	private string $plugin_templates_path;

	/**
	 * Custom template path (for external plugins/themes).
	 *
	 * @var string|null
	 */
	private ?string $custom_template_path = null;

	/**
	 * Allowed template variables (whitelist for security).
	 *
	 * @var string[]
	 */
	private const ALLOWED_VARIABLES = array(
		'wp_version',
		'min_version',
		'entries',
		'show_archived_message',
		'active_text',
		'buttons',
		'update_text',
		'extra_fields',
		'options',
		'message',
		'plugin',
		'current_key_template',
		'current_key_format',
		'current_key_limit',
		'key_name',
		'key_format_name',
		'key_description',
		'key_limit',
		'key_button',
		'templates',
		'formats',
		'title',
		'template',
		'wrap',
		'class',
		'new_label',
		'new_button',
		// Entry template variables.
		'entry_id',
		'post_id',
		'css_classes',
		'content',
		'original_content',
		'avatar_size',
		'avatar_img',
		'author_link',
		'authors',
		'entry_date',
		'entry_time',
		'entry_timestamp',
		'timestamp',
		'share_link',
		'key_event',
		'is_liveblog_editable',
		'allowed_tags_for_entry',
		// DDD services.
		'key_event_service',
		// Feature flags.
		'socketio_enabled',
	);

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file The main plugin file path.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_templates_path = dirname( $plugin_file ) . '/templates/';
	}

	/**
	 * Set a custom template path for external overrides.
	 *
	 * @param string|null $path Custom template path.
	 * @return void
	 */
	public function set_custom_path( ?string $path ): void {
		$this->custom_template_path = $path;
	}

	/**
	 * Render a template with variables.
	 *
	 * Looks for template in order:
	 * 1. Child theme: get_stylesheet_directory()/liveblog/{template}
	 * 2. Parent theme: get_template_directory()/liveblog/{template}
	 * 3. Custom path (if set)
	 * 4. Plugin templates directory
	 *
	 * @param string $template_name      Template file name.
	 * @param array  $template_variables Variables to pass to the template.
	 * @return string Rendered template output.
	 */
	public function render( string $template_name, array $template_variables = array() ): string {
		ob_start();

		// Extract only allowed variables for security.
		foreach ( $template_variables as $key => $value ) {
			if ( in_array( $key, self::ALLOWED_VARIABLES, true ) ) {
				${$key} = $value;
			}
		}

		$template_path = $this->locate_template( $template_name );

		if ( $template_path ) {
			include $template_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile -- Template file.
		}

		return ob_get_clean();
	}

	/**
	 * Locate a template file.
	 *
	 * @param string $template_name Template file name.
	 * @return string|null Full path to template file, or null if not found.
	 */
	public function locate_template( string $template_name ): ?string {
		$template_name = ltrim( $template_name, '/' );

		// Check child theme first.
		$child_theme_path = get_stylesheet_directory() . '/liveblog/' . $template_name;
		if ( file_exists( $child_theme_path ) ) {
			return $child_theme_path;
		}

		// Check parent theme.
		$theme_path = get_template_directory() . '/liveblog/' . $template_name;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		// Check custom path.
		if ( $this->custom_template_path ) {
			$custom_path = $this->custom_template_path . '/' . $template_name;
			if ( file_exists( $custom_path ) ) {
				return $custom_path;
			}
		}

		// Fall back to plugin templates.
		$plugin_path = $this->plugin_templates_path . $template_name;
		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return null;
	}

	/**
	 * Check if a template exists.
	 *
	 * @param string $template_name Template file name.
	 * @return bool True if template exists.
	 */
	public function template_exists( string $template_name ): bool {
		return null !== $this->locate_template( $template_name );
	}

	/**
	 * Filter the_content to add liveblog output.
	 *
	 * @param string $content The post content.
	 * @return string Modified content with liveblog container.
	 */
	public function filter_the_content( string $content ): string {
		if ( ! LiveblogPost::is_viewing_liveblog_post() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		$liveblog_output = '<div id="wpcom-liveblog-container" class="' . esc_attr( (string) $post->ID ) . '"></div>';
		$liveblog_output = apply_filters( 'liveblog_add_to_content', $liveblog_output, $content, $post->ID );

		/**
		 * Filters whether the liveblog output should appear at the top of the post content.
		 *
		 * @param bool $at_top Whether to display the liveblog at the top. Default false.
		 */
		if ( true === apply_filters( 'liveblog_output_at_top', false ) ) {
			return wp_kses_post( $liveblog_output ) . $content;
		}

		return $content . wp_kses_post( $liveblog_output );
	}

	/**
	 * Handle image embeds for liveblog entries.
	 *
	 * @param array  $matches The regex matches.
	 * @param array  $attr    The embed attributes.
	 * @param string $url     The URL being embedded.
	 * @param array  $rawattr The raw attributes.
	 * @return string The embedded image HTML.
	 */
	public function image_embed_handler( array $matches, array $attr, string $url, array $rawattr ): string {
		$embed = sprintf( '<img src="%s" alt="" />', esc_url( $url ) );

		return apply_filters( 'embed_liveblog_image', $embed, $matches, $attr, $url, $rawattr ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name.
	}

	/**
	 * Get all liveblog entry output for initial render.
	 *
	 * @param int $post_id The post ID.
	 * @return string The rendered entries output.
	 */
	public function get_all_entry_output( int $post_id ): string {
		$container         = Container::instance();
		$query_service     = $container->entry_query_service();
		$key_event_service = $container->key_event_service();

		$liveblog_post = LiveblogPost::from_id( $post_id );
		$state         = $liveblog_post ? $liveblog_post->state() : '';

		$args = array();
		if ( LiveblogPost::STATE_ARCHIVED === $state ) {
			$args['order'] = 'ASC';
		}

		$args                  = apply_filters( 'liveblog_display_archive_query_args', $args, $state );
		$entries               = (array) $query_service->get_all( $post_id, 0, $args );
		$show_archived_message = LiveblogPost::STATE_ARCHIVED === $state
			&& $liveblog_post
			&& $liveblog_post->current_user_can_edit();
		$socketio_enabled      = $container->socketio_manager()->is_enabled();

		return $this->render(
			'liveblog-loop.php',
			compact( 'entries', 'show_archived_message', 'key_event_service', 'socketio_enabled' )
		);
	}
}
