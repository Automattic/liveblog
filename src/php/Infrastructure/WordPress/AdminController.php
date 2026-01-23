<?php
/**
 * Admin controller for liveblog admin functionality.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_Post;
use WP_Query;

/**
 * Handles admin-side functionality for the liveblog plugin.
 *
 * Includes metabox registration, post state display, and post filtering.
 */
final class AdminController {

	/**
	 * Template renderer instance.
	 *
	 * @var TemplateRenderer
	 */
	private TemplateRenderer $template_renderer;

	/**
	 * Constructor.
	 *
	 * @param TemplateRenderer $template_renderer The template renderer.
	 */
	public function __construct( TemplateRenderer $template_renderer ) {
		$this->template_renderer = $template_renderer;
	}

	/**
	 * Register the metabox for supported post types.
	 *
	 * @param string $post_type The post type being edited.
	 * @return void
	 */
	public function add_meta_box( string $post_type ): void {
		if ( ! post_type_supports( $post_type, LiveblogConfiguration::KEY ) ) {
			return;
		}

		add_meta_box(
			LiveblogConfiguration::KEY,
			__( 'Liveblog', 'liveblog' ),
			array( $this, 'display_meta_box' )
		);
	}

	/**
	 * Display the metabox content.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public function display_meta_box( WP_Post $post ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in template.
		echo $this->get_meta_box_content( $post );
	}

	/**
	 * Get the metabox HTML content.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The metabox HTML.
	 */
	public function get_meta_box_content( WP_Post $post ): string {
		$liveblog_post = LiveblogPost::from_post( $post );
		$current_state = $liveblog_post->state();
		$is_published  = $liveblog_post->is_published();

		$permalink_link = $is_published
			? sprintf(
				' <a href="%s">%s</a>',
				esc_url( $liveblog_post->permalink() ),
				__( 'Visit the liveblog &rarr;', 'liveblog' )
			)
			: '';

		$buttons = $this->get_metabox_buttons( $current_state, $permalink_link );

		if ( $current_state ) {
			$active_text                           = $buttons[ $current_state ]['active-text'];
			$buttons[ $current_state ]['disabled'] = true;
		} else {
			$active_text                    = __( 'This is a normal WordPress post, without a liveblog.', 'liveblog' );
			$buttons['archive']['disabled'] = true;
			$buttons['disable']['disabled'] = true;
		}

		$update_text  = __( 'Settings have been successfully updated.', 'liveblog' );
		$extra_fields = apply_filters( 'liveblog_admin_add_settings', array(), $post->ID );

		return $this->template_renderer->render(
			'meta-box.php',
			compact( 'active_text', 'buttons', 'update_text', 'extra_fields' )
		);
	}

	/**
	 * Get the metabox button configurations.
	 *
	 * @param string $current_state  Current liveblog state.
	 * @param string $permalink_link Link to the liveblog (if published).
	 * @return array Button configuration array.
	 */
	private function get_metabox_buttons( string $current_state, string $permalink_link ): array {
		return array(
			'enable'  => array(
				'value'       => 'enable',
				'text'        => __( 'Enable', 'liveblog' ),
				'description' => __( 'Enables liveblog on this post. Posting tools are enabled for editors, visitors get the latest updates.', 'liveblog' ),
				'active-text' => sprintf(
					/* translators: %s: optional link to view the liveblog */
					__( 'There is an <strong>enabled</strong> liveblog on this post.%s', 'liveblog' ),
					$permalink_link
				),
				'primary'     => true,
				'disabled'    => false,
			),
			'archive' => array(
				'value'       => 'archive',
				'text'        => __( 'Archive', 'liveblog' ),
				'description' => __( 'Archives the liveblog on this post. Visitors still see the liveblog entries, but posting tools are hidden.', 'liveblog' ),
				'active-text' => sprintf(
					/* translators: %s: optional link to view the liveblog archive */
					__( 'There is an <strong>archived</strong> liveblog on this post.%s', 'liveblog' ),
					$permalink_link
				),
				'primary'     => false,
				'disabled'    => false,
			),
			'disable' => array(
				'value'       => 'disable',
				'text'        => __( 'Disable', 'liveblog' ),
				'description' => __( 'Removes liveblog from this post. Existing entries are kept as comments.', 'liveblog' ),
				'active-text' => '',
				'primary'     => false,
				'disabled'    => false,
			),
		);
	}

	/**
	 * Update the liveblog state for a post.
	 *
	 * @param int    $post_id      The post ID.
	 * @param string $new_state    The new state (enable, archive, disable).
	 * @param array  $request_vars Additional request variables.
	 * @return string|false The updated metabox HTML, or false on failure.
	 */
	public function set_liveblog_state( int $post_id, string $new_state, array $request_vars = array() ) {
		$post = get_post( $post_id );

		if ( empty( $post ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		do_action( 'liveblog_admin_settings_update', $request_vars, $post_id );

		$liveblog_post = LiveblogPost::from_post( $post );

		switch ( $new_state ) {
			case LiveblogPost::STATE_ENABLED:
				$liveblog_post->enable();
				break;
			case LiveblogPost::STATE_ARCHIVED:
				$liveblog_post->archive();
				break;
			default:
				$liveblog_post->disable();
				break;
		}

		return $this->get_meta_box_content( $post );
	}

	/**
	 * Add liveblog state indicator to post states in admin list.
	 *
	 * @param array        $post_states Current post states.
	 * @param WP_Post|null $post        The post object.
	 * @return array Modified post states.
	 */
	public function add_display_post_state( array $post_states, ?WP_Post $post = null ): array {
		if ( null === $post ) {
			$post = get_post();
		}

		if ( ! $post instanceof WP_Post ) {
			return $post_states;
		}

		$liveblog_post = LiveblogPost::from_post( $post );

		if ( $liveblog_post->is_enabled() ) {
			$post_states[] = __( 'Liveblog', 'liveblog' );
		} elseif ( $liveblog_post->is_archived() ) {
			$post_states[] = __( 'Liveblog (archived)', 'liveblog' );
		}

		return $post_states;
	}

	/**
	 * Register the liveblog_state query variable.
	 *
	 * @param array $query_vars Current query variables.
	 * @return array Modified query variables.
	 */
	public function add_query_var( array $query_vars ): array {
		$query_vars[] = 'liveblog_state';
		return $query_vars;
	}

	/**
	 * Render the liveblog filter dropdown in post list.
	 *
	 * @return void
	 */
	public function render_filter_dropdown(): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen || ! post_type_supports( $current_screen->post_type, LiveblogConfiguration::KEY ) ) {
			return;
		}

		$options = array(
			''        => __( 'Filter liveblogs', 'liveblog' ),
			'any'     => __( 'Any liveblogs', 'liveblog' ),
			'enable'  => __( 'Enabled liveblogs', 'liveblog' ),
			'archive' => __( 'Archived liveblogs', 'liveblog' ),
			'none'    => __( 'No liveblogs', 'liveblog' ),
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in template.
		echo $this->template_renderer->render( 'restrict-manage-posts.php', compact( 'options' ) );
	}

	/**
	 * Handle the liveblog_state query variable for post filtering.
	 *
	 * @param WP_Query $query The query object.
	 * @return void
	 */
	public function handle_filter_query( WP_Query $query ): void {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$state = $query->get( 'liveblog_state' );

		if ( empty( $state ) ) {
			return;
		}

		$meta_query_clause = $this->get_meta_query_clause( $state );

		if ( null === $meta_query_clause ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		if ( empty( $meta_query ) ) {
			$meta_query = array();
		}

		$meta_query[] = $meta_query_clause;
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Get the meta query clause for a given state filter.
	 *
	 * @param string $state The state to filter by.
	 * @return array|null The meta query clause, or null if invalid.
	 */
	private function get_meta_query_clause( string $state ): ?array {
		switch ( $state ) {
			case 'any':
				return array(
					'key'     => LiveblogConfiguration::KEY,
					'compare' => 'EXISTS',
				);

			case 'none':
				return array(
					'key'     => LiveblogConfiguration::KEY,
					'compare' => 'NOT EXISTS',
				);

			case 'enable':
			case 'archive':
				return array(
					'key'   => LiveblogConfiguration::KEY,
					'value' => $state,
				);

			default:
				return null;
		}
	}

	/**
	 * Check if the current user can edit liveblog.
	 *
	 * @return bool True if user can edit.
	 */
	public static function current_user_can_edit(): bool {
		$cap    = LiveblogConfiguration::get_edit_capability();
		$retval = current_user_can( $cap );

		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}
}
