<?php
/**
 * Admin controller for liveblog settings and meta boxes.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;
use WP_Post;

/**
 * Admin controller for handling meta boxes and admin settings.
 */
final class AdminController {

	/**
	 * Template renderer instance.
	 *
	 * @var TemplateRenderer
	 */
	private TemplateRenderer $template_renderer;

	/**
	 * Entry query service instance.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $entry_query_service;

	/**
	 * Constructor.
	 *
	 * @param TemplateRenderer  $template_renderer   The template renderer.
	 * @param EntryQueryService $entry_query_service Entry query service.
	 */
	public function __construct( TemplateRenderer $template_renderer, EntryQueryService $entry_query_service ) {
		$this->template_renderer   = $template_renderer;
		$this->entry_query_service = $entry_query_service;
	}

	/**
	 * Add the liveblog meta box to supported post types.
	 *
	 * Loads at the top of all metaboxes via 'high' priority on 'normal'
	 * context. Contains both the enable/disable toggle and the entries
	 * DataViews table.
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
			array( $this, 'display_meta_box' ),
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Display the combined metabox content — toggle button + entries DataViews.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public function display_meta_box( WP_Post $post ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in template.
		echo $this->get_meta_box_content( $post );
	}

	/**
	 * Get the combined metabox HTML content — toggle button + entries DataViews.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The metabox HTML.
	 */
	public function get_meta_box_content( WP_Post $post ): string {
		$liveblog_post = LiveblogPost::from_post( $post );
		$current_state = $liveblog_post->state();
		$is_published  = $liveblog_post->is_published();

		$is_archived = LiveblogPost::STATE_ARCHIVED === $current_state;

		if ( LiveblogPost::STATE_ENABLED === $current_state ) {
			$active_text = sprintf(
				/* translators: %d: post ID */
				__( 'Liveblog is active on post #%d.', 'liveblog' ),
				$post->ID
			);
		} elseif ( $is_archived ) {
			$active_text = __( 'This liveblog is archived.', 'liveblog' );
		} else {
			$active_text = __( 'Liveblog is not active on this post.', 'liveblog' );
		}

		$buttons = array(
			array(
				'text'        => $this->get_button_text( $current_state ),
				'value'       => $is_archived ? LiveblogPost::STATE_ENABLED : (
					LiveblogPost::STATE_ENABLED === $current_state
						? LiveblogPost::STATE_DISABLED
						: LiveblogPost::STATE_ENABLED
				),
				'primary'     => true,
				'disabled'    => false,
				'description' => $is_archived
					? __( 'This liveblog is archived. Re-enable to make changes.', 'liveblog' )
					: __( 'Toggle liveblog state.', 'liveblog' ),
			),
		);

		// Add Archive button when liveblog is currently enabled.
		if ( LiveblogPost::STATE_ENABLED === $current_state ) {
			$buttons[] = array(
				'text'        => __( 'Archive', 'liveblog' ),
				'value'       => LiveblogPost::STATE_ARCHIVED,
				'primary'     => false,
				'disabled'    => false,
				'description' => __( 'Archive the liveblog. Visitors can still read entries but no new entries can be added.', 'liveblog' ),
			);
		}

		$extra_fields   = apply_filters( 'liveblog_admin_add_settings', array(), $post->ID );
		$permalink_link = $is_published
			? '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'View liveblog', 'liveblog' ) . '</a>'
			: '';

		ob_start();
		include __DIR__ . '/../../../../templates/meta-box.php';

		if ( LiveblogPost::STATE_ENABLED === $current_state || $is_archived ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_entries_metabox_html( $post );
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ob_get_clean();
	}

	/**
	 * Get button text for state transition.
	 *
	 * @param string $current_state Current liveblog state.
	 * @return string Button text.
	 */
	private function get_button_text( string $current_state ): string {
		switch ( $current_state ) {
			case LiveblogPost::STATE_ENABLED:
				return __( 'Disable Liveblog', 'liveblog' );
			case LiveblogPost::STATE_DISABLED:
				return __( 'Enable Liveblog', 'liveblog' );
			case LiveblogPost::STATE_ARCHIVED:
				return __( 'Re-enable Liveblog', 'liveblog' );
			default:
				return __( 'Enable Liveblog', 'liveblog' );
		}
	}

	/**
	 * Get button URL for state transition.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $current_state Current liveblog state.
	 * @return string Button URL.
	 */
	private function get_button_url( int $post_id, string $current_state ): string {
		$new_state = LiveblogPost::STATE_ENABLED === $current_state
			? LiveblogPost::STATE_DISABLED
			: LiveblogPost::STATE_ENABLED;

		return wp_nonce_url(
			add_query_arg(
				array(
					'post_id' => $post_id,
					'state'   => $new_state,
				),
				admin_url( 'admin-ajax.php?action=set_liveblog_state_for_post' )
			),
			LiveblogConfiguration::NONCE_ACTION,
			LiveblogConfiguration::NONCE_KEY
		);
	}

	/**
	 * Handle liveblog state change via AJAX/REST API.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $new_state New liveblog state.
	 * @param array  $request   Request data.
	 * @return string Metabox HTML.
	 */
	public function set_liveblog_state( int $post_id, string $new_state, array $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $request kept for future extensibility.
		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( ! $liveblog_post instanceof LiveblogPost ) {
			return array(
				'metabox_html' => '',
			);
		}

		if ( ! $liveblog_post->current_user_can_edit() ) {
			return array(
				'metabox_html' => '',
			);
		}

		$liveblog_post->set_state( $new_state );

		$post = $liveblog_post->post();

		return array(
			'metabox_html' => $this->get_meta_box_content( $post ),
		);
	}

	/**
	 * Check if current user can edit a liveblog post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether user can edit.
	 */
	public static function current_user_can_edit_for_post( int $post_id ): bool {
		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( ! $liveblog_post instanceof LiveblogPost ) {
			return false;
		}

		return $liveblog_post->current_user_can_edit();
	}

	/**
	 * Get the entries DataViews table HTML content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string DataViews HTML.
	 */
	public function get_entries_metabox_html( \WP_Post $post ): string {
		$liveblog_post = LiveblogPost::from_post( $post );

		if ( ! $liveblog_post->is_liveblog() ) {
			return '<p>' . esc_html__( 'Enable liveblog to manage entries.', 'liveblog' ) . '</p>';
		}

		$container_instance = Container::instance();
		$query_service      = $container_instance->entry_query_service();

		$entries = $query_service->get_all( $post->ID, 0, array( 'order' => 'ASC' ) );

		$entries_data = array();
		foreach ( $entries as $entry ) {
			$authors     = $entry->authors();
			$author_name = $authors->is_empty() ? '—' : $authors->primary()->name();

			$breakout_post_id = (int) get_post_meta( $entry->id()->to_int(), 'liveblog_breakout_post_id', true );
			$breakout_status  = $breakout_post_id ? get_post_status( $breakout_post_id ) : null;

			$entries_data[] = array(
				'id'               => $entry->id()->to_int(),
				'title'            => EntryPresenter::get_entry_title( $entry ),
				'content'          => EntryPresenter::get_entry_content( $entry ),
				'author'           => $author_name,
				'date'             => $entry->created_at()->format( 'Y-m-d H:i:s' ),
				'timestamp'        => $entry->timestamp(),
				'permalink'        => get_permalink( $entry->id()->to_int() ),
				'breakout_post_id' => $breakout_post_id,
				'breakout_status'  => $breakout_status,
				'thumbnail'        => get_the_post_thumbnail_url( $entry->id()->to_int(), 'thumbnail' ),
			);
		}

		$config = array(
			'entries'    => $entries_data,
			'postId'     => $post->ID,
			'nonce'      => wp_create_nonce( LiveblogConfiguration::NONCE_ACTION ),
			'isArchived' => LiveblogPost::STATE_ARCHIVED === $liveblog_post->state(),
		);

		ob_start();
		?>
		<div id="liveblog-entries-dataview" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
		<?php if ( ! $config['isArchived'] ) : ?>
		<p class="liveblog-add-entry">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_parent=' . $post->ID ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add New Entry', 'liveblog' ); ?>
			</a>
			<span class="description"><?php esc_html_e( 'Opens the WordPress editor with this post set as parent.', 'liveblog' ); ?></span>
		</p>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Hide liveblog metabox on child posts (entries).
	 */
	public function hide_metaboxes_on_child_posts(): void {
		global $post;

		if ( ! $post || 'post' !== $post->post_type ) {
			return;
		}

		// Check post_parent from URL for new posts.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url_parent = isset( $_GET['post_parent'] ) ? (int) $_GET['post_parent'] : 0;

		// Hide metabox if this is a child post OR creating child from URL param.
		if ( $post->post_parent > 0 || $url_parent > 0 ) {
			remove_meta_box( 'liveblog', 'post', 'normal' );
		}
	}

	/**
	 * Store post_parent from URL in user meta for later use.
	 *
	 * Called on admin_init to persist across AJAX/REST API calls.
	 */
	public function store_parent_from_url(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['post_parent'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$parent_id = (int) $_GET['post_parent'];
		if ( $parent_id <= 0 ) {
			return;
		}

		// Verify parent exists.
		$parent = get_post( $parent_id );
		if ( ! $parent instanceof WP_Post ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $parent_id ) ) {
			return;
		}

		// Store in user meta for retrieval during auto-draft creation.
		update_user_meta( get_current_user_id(), 'liveblog_creating_child_of', $parent_id );
	}

	/**
	 * Add breakout settings metabox for child posts.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_breakout_metabox( string $post_type ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by add_meta_box callback signature.
		global $post;

		if ( ! $post || $post->post_parent <= 0 ) {
			return;
		}

		add_meta_box(
			'liveblog-breakout-settings',
			__( 'Breakout Settings', 'liveblog' ),
			array( $this, 'render_breakout_metabox' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render breakout settings metabox.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_breakout_metabox( \WP_Post $post ): void {
		// Only show on child posts.
		if ( $post->post_parent <= 0 ) {
			echo '<p>' . esc_html__( 'Not a liveblog entry.', 'liveblog' ) . '</p>';
			return;
		}

		$read_more_text = get_post_meta( $post->ID, 'liveblog_breakout_read_more_text', true );
		$breakout_id    = get_post_meta( $post->ID, 'liveblog_breakout_post_id', true );

		wp_nonce_field( 'liveblog_breakout_settings', 'liveblog_breakout_nonce' );
		?>
		<p>
			<label for="liveblog_breakout_read_more">
				<?php esc_html_e( 'Read More Link Text', 'liveblog' ); ?>
			</label>
			<input type="text"
					id="liveblog_breakout_read_more"
					name="liveblog_breakout_read_more_text"
					value="<?php echo esc_attr( $read_more_text ); ?>"
					placeholder="<?php esc_attr_e( 'Read more', 'liveblog' ); ?>"
					class="widefat"
					style="margin-top:4px" />
		</p>
		<?php if ( $breakout_id ) : ?>
		<p>
			<strong><?php esc_html_e( 'Breakout Post:', 'liveblog' ); ?></strong>
			<a href="<?php echo esc_url( get_edit_post_link( (int) $breakout_id ) ); ?>">
				#<?php echo esc_html( (string) $breakout_id ); ?>
			</a>
		</p>
			<?php
		endif;
	}

	/**
	 * Save breakout settings.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_breakout_settings( int $post_id ): void {
		if ( ! isset( $_POST['liveblog_breakout_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['liveblog_breakout_nonce'] ) ), 'liveblog_breakout_settings' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$read_more = isset( $_POST['liveblog_breakout_read_more_text'] )
			? sanitize_text_field( wp_unslash( $_POST['liveblog_breakout_read_more_text'] ) )
			: '';

		update_post_meta( $post_id, 'liveblog_breakout_read_more_text', $read_more );
	}

	/**
	 * Set parent post from URL parameter on auto-draft creation.
	 *
	 * @param array $post_data Post data array.
	 * @param array $postarr   Post array.
	 * @return array Modified post data.
	 */
	public function set_parent_on_auto_draft( array $post_data, array $postarr ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by wp_insert_post_data filter signature.
		// Only for new posts (auto-draft).
		if ( 'auto-draft' !== ( $post_data['post_status'] ?? '' ) ) {
			return $post_data;
		}

		// Only for 'post' post type.
		if ( 'post' !== ( $post_data['post_type'] ?? 'post' ) ) {
			return $post_data;
		}

		// Try URL parameter first (traditional admin path).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post_parent'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$parent_id = (int) $_GET['post_parent'];
			if ( $parent_id > 0 ) {
				$parent = get_post( $parent_id );
				if ( $parent instanceof WP_Post ) {
					$post_data['post_parent'] = $parent_id;
				}
			}
		}

		return $post_data;
	}

	/**
	 * Add a "Liveblog" column to the admin post list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_liveblog_column( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['liveblog_status'] = __( 'Liveblog', 'liveblog' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render the "Liveblog" column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 * @return void
	 */
	public function render_liveblog_column( string $column_name, int $post_id ): void {
		if ( 'liveblog_status' !== $column_name ) {
			return;
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );
		if ( null === $liveblog_post ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		switch ( $liveblog_post->state() ) {
			case LiveblogPost::STATE_ENABLED:
				printf(
					'<span class="liveblog-status-badge liveblog-status-enabled" style="display:inline-block;padding:2px 8px;border-radius:3px;background:#0073aa;color:#fff;font-size:11px;line-height:1.4;white-space:nowrap;">%s</span>',
					esc_html__( 'Enabled', 'liveblog' )
				);
				break;
			case LiveblogPost::STATE_ARCHIVED:
				printf(
					'<span class="liveblog-status-badge liveblog-status-archived" style="display:inline-block;padding:2px 8px;border-radius:3px;background:#999;color:#fff;font-size:11px;line-height:1.4;white-space:nowrap;">%s</span>',
					esc_html__( 'Archived', 'liveblog' )
				);
				break;
			default:
				echo '<span aria-hidden="true">—</span>';
				break;
		}
	}

	/**
	 * Bump the source entry's post_modified timestamp when a breakout post
	 * gets published. This triggers the poller to re-deliver the entry HTML,
	 * which now includes breakout badge, footer, and CSS classes.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function bump_entry_on_breakout_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status || ! $post instanceof \WP_Post ) {
			return;
		}

		$source_entry_id = (int) get_post_meta( $post->ID, '_liveblog_breakout_source_entry', true );
		if ( $source_entry_id <= 0 ) {
			return;
		}

		$source_entry = get_post( $source_entry_id );
		if ( ! $source_entry instanceof \WP_Post ) {
			return;
		}

		wp_update_post(
			array(
				'ID'                => $source_entry_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			)
		);

		update_post_meta( $source_entry_id, 'liveblog_breakout_post_id', $post->ID );
	}

	/**
	 * Add a "State" filter dropdown to the admin post list.
	 *
	 * @param string $post_type The current post type.
	 * @return void
	 */
	public function add_liveblog_state_filter( string $post_type ): void {
		if ( 'post' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading filter value, not modifying state.
		$current = isset( $_GET['liveblog_state'] ) ? sanitize_key( $_GET['liveblog_state'] ) : '';

		$options = array(
			''        => __( 'All states', 'liveblog' ),
			'enable'  => __( 'Liveblog: Enabled', 'liveblog' ),
			'archive' => __( 'Liveblog: Archived', 'liveblog' ),
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<select name="liveblog_state">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply the liveblog state filter to the admin post list query.
	 *
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public function apply_liveblog_state_filter( \WP_Query $query ): void {
		if ( ! is_admin() || 'post' !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading filter value, not modifying state.
		if ( empty( $_GET['liveblog_state'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading filter value for query, not modifying state.
		$state = sanitize_key( $_GET['liveblog_state'] );

		$term_slug = 'enable' === $state
			? LiveblogConfiguration::TERM_ENABLED
			: LiveblogConfiguration::TERM_ARCHIVED;

		$tax_query   = $query->get( 'tax_query', array() );
		$tax_query[] = array(
			'taxonomy' => LiveblogConfiguration::TAXONOMY,
			'field'    => 'slug',
			'terms'    => $term_slug,
		);
		$query->set( 'tax_query', $tax_query );
	}
}
