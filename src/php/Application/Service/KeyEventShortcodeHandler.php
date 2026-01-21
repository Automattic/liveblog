<?php
/**
 * Key event shortcode handler.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Application\Config\KeyEventConfiguration;
use WPCOM_Liveblog;

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
		$state = WPCOM_Liveblog::get_liveblog_state( $post->ID );
		if ( ! $state ) {
			return null;
		}

		// Get the limit from post meta.
		$limit = $this->configuration->get_current_limit( $post->ID );

		// Get key events using the service.
		$entries = $this->key_event_service->get_key_events( $post->ID, $limit );

		// Convert domain entries to legacy format for template compatibility.
		$legacy_entries = array();
		foreach ( $entries as $entry ) {
			$legacy_entries[] = \WPCOM_Liveblog_Entry::from_comment(
				get_comment( $entry->id()->to_int() )
			);
		}

		// Get template configuration.
		$template = $this->configuration->get_current_template( $post->ID );

		// Render using the existing template system.
		return WPCOM_Liveblog::get_template_part(
			'liveblog-key-events.php',
			array(
				'entries'  => $legacy_entries,
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
		return WPCOM_Liveblog::get_template_part(
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
