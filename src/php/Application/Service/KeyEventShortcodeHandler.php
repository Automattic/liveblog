<?php
/**
 * Key event shortcode handler.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Application\Config\KeyEventConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Handles the [liveblog_key_events] shortcode.
 *
 * Renders a list of key events for a liveblog post.
 */
final class KeyEventShortcodeHandler {

	/**
	 * The key event service.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $key_event_service;

	/**
	 * The key event configuration.
	 *
	 * @var KeyEventConfiguration
	 */
	private KeyEventConfiguration $configuration;

	/**
	 * Constructor.
	 *
	 * @param KeyEventService       $key_event_service The key event service.
	 * @param KeyEventConfiguration $configuration     The configuration.
	 */
	public function __construct(
		KeyEventService $key_event_service,
		KeyEventConfiguration $configuration
	) {
		$this->key_event_service = $key_event_service;
		$this->configuration     = $configuration;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'liveblog_key_events', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, string>|string $atts The shortcode attributes.
	 * @return string|null The shortcode output.
	 */
	public function render( $atts ): ?string {
		global $post;

		if ( ! is_single() || ! $post ) {
			return null;
		}

		// Define the default shortcode attributes.
		$atts = shortcode_atts(
			array(
				'title' => 'Key Events',
			),
			$atts,
			'liveblog_key_events'
		);

		// Only run the shortcode on an archived or enabled post.
		$liveblog_post = LiveblogPost::from_post( $post );
		if ( ! $liveblog_post->is_liveblog() ) {
			return null;
		}

		// Get template configuration.
		$template = $this->configuration->get_current_template( $post->ID );

		// Render using the existing template system.
		// Note: The template renders a container div; key events are loaded via JS.
		return Container::instance()->template_renderer()->render(
			'liveblog-key-events.php',
			array(
				'title'    => $atts['title'],
				'template' => $template[0],
				'wrap'     => $template[1],
				'class'    => $template[2],
			)
		);
	}

	/**
	 * Get admin options HTML for key event settings.
	 *
	 * @param int $post_id The post ID.
	 * @return string The admin options HTML.
	 */
	public function get_admin_options( int $post_id ): string {
		return Container::instance()->template_renderer()->render(
			'liveblog-key-admin.php',
			array(
				'current_key_template' => get_post_meta( $post_id, KeyEventConfiguration::META_KEY_TEMPLATE, true ),
				'current_key_format'   => get_post_meta( $post_id, KeyEventConfiguration::META_KEY_FORMAT, true ),
				'current_key_limit'    => get_post_meta( $post_id, KeyEventConfiguration::META_KEY_LIMIT, true ),
				'key_name'             => __( 'Template:', 'liveblog' ),
				'key_format_name'      => __( 'Format:', 'liveblog' ),
				'key_description'      => __( 'Set template for key events shortcode, select a format and restrict most recent shown.', 'liveblog' ),
				'key_limit'            => __( 'Limit', 'liveblog' ),
				'key_button'           => __( 'Save', 'liveblog' ),
				'templates'            => $this->configuration->get_template_names(),
				'formats'              => $this->configuration->get_format_names(),
			)
		);
	}

	/**
	 * Handle saving template options from admin.
	 *
	 * @param array<string, mixed> $response The response data.
	 * @param int                  $post_id  The post ID.
	 * @return void
	 */
	public function save_admin_options( array $response, int $post_id ): void {
		if ( 'liveblog-key-template-save' !== ( $response['state'] ?? '' ) ) {
			return;
		}

		if ( empty( $response['liveblog-key-template-name'] ) ) {
			return;
		}

		$template = $response['liveblog-key-template-name'];
		$format   = $response['liveblog-key-template-format'] ?? 'first-linebreak';
		$limit    = absint( $response['liveblog-key-limit'] ?? 0 );

		$this->configuration->save_settings( $post_id, $template, $format, $limit );
	}
}
