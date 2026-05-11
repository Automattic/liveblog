<?php
/**
 * REST API controller for Liveblog.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Service\EntryOperations;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST API controller for Liveblog.
 *
 * This class registers and handles all REST API endpoints for the liveblog.
 * It replaces the legacy WPCOM_Liveblog_Rest_Api class with proper dependency injection.
 */
final class RestApiController {

	/**
	 * API version.
	 */
	private const API_VERSION = '1';

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private string $api_namespace;

	/**
	 * Endpoint base URL (instance).
	 *
	 * @var string
	 */
	private string $endpoint_base = '';

	/**
	 * Cached endpoint base URL (static for legacy compatibility).
	 *
	 * @var string
	 */
	private static string $static_endpoint_base = '';

	/**
	 * Entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $query_service;

	/**
	 * Entry operations service.
	 *
	 * @var EntryOperations
	 */
	private EntryOperations $entry_operations;

	/**
	 * Request router for formatting responses.
	 *
	 * @var RequestRouter
	 */
	private RequestRouter $request_router;

	/**
	 * Admin controller for state management.
	 *
	 * @var AdminController
	 */
	private AdminController $admin_controller;

	/**
	 * Current post ID for the request.
	 *
	 * @var int
	 */
	private int $current_post_id = 0;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService $query_service    Entry query service.
	 * @param EntryOperations   $entry_operations Entry operations service.
	 * @param RequestRouter     $request_router   Request router.
	 * @param AdminController   $admin_controller Admin controller.
	 */
	public function __construct(
		EntryQueryService $query_service,
		EntryOperations $entry_operations,
		RequestRouter $request_router,
		AdminController $admin_controller
	) {
		$this->api_namespace    = 'liveblog/v' . self::API_VERSION;
		$this->query_service    = $query_service;
		$this->entry_operations = $entry_operations;
		$this->request_router   = $request_router;
		$this->admin_controller = $admin_controller;
	}

	/**
	 * Check if the current user can edit the liveblog.
	 *
	 * Static method for REST API permission callbacks that have no post in the URL
	 * (e.g. authors and hashtags autocomplete). Gates on the configured global
	 * editor capability without any post context.
	 *
	 * @return bool True if the current user can edit.
	 */
	public static function current_user_can_edit_liveblog(): bool {
		$cap    = LiveblogConfiguration::get_edit_capability();
		$retval = current_user_can( $cap );

		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}

	/**
	 * Permission callback for REST write routes that target a specific post.
	 *
	 * The CRUD, preview and post_state routes all live under
	 * `/posts/(?P<post_id>\d+)/...`. Authorisation must be scoped to the post in
	 * the URL rather than to a global capability, otherwise any user holding
	 * `publish_posts` (every default Author and above) could mutate liveblog
	 * state on a post they have no right to edit.
	 *
	 * The check delegates to the `edit_post` meta capability, which WordPress
	 * maps to whatever primitive caps the current site honours. This keeps the
	 * check correct under role customisation rather than naming roles directly.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool True when the current user can edit the URL post.
	 */
	public static function current_user_can_edit_liveblog_for_request( WP_REST_Request $request ): bool {
		$url_params = $request->get_url_params();
		$post_id    = isset( $url_params['post_id'] ) ? (int) $url_params['post_id'] : 0;

		$retval = ( $post_id > 0 )
			&& ( get_post( $post_id ) instanceof \WP_Post )
			&& current_user_can( 'edit_post', $post_id );

		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}

	/**
	 * Permission callback for the public read REST endpoints.
	 *
	 * Public read endpoints (entries, lazyload, single entry, paged entries) accept
	 * gateway for CWE-639 (IDOR). Without this check, an unauthenticated caller
	 * could retrieve entries from a draft, private, future or trashed parent
	 * post simply by enumerating post IDs.
	 *
	 * Access is permitted when all of the following are true:
	 *
	 * - The post exists and its post type supports liveblog.
	 * - Liveblog is enabled or archived on the post.
	 * - The post is published, or the current user has `read_post` for it.
	 *
	 * All failure paths return a 404 so the endpoint cannot be used as an oracle
	 * for post existence, status or liveblog-ness.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return true|WP_Error True when the request is permitted, otherwise a 404 error.
	 */
	public static function can_read_liveblog( WP_REST_Request $request ) {
		$post_id       = (int) $request->get_param( 'post_id' );
		$liveblog_post = $post_id > 0 ? LiveblogPost::from_id( $post_id ) : null;

		$allowed = (
			$liveblog_post instanceof LiveblogPost
			&& $liveblog_post->is_liveblog()
			&& ( $liveblog_post->is_published() || current_user_can( 'read_post', $post_id ) )
		);

		/**
		 * Filters whether the current request may read liveblog entries for a given post.
		 *
		 * Default behaviour requires that the post exists, supports liveblog,
		 * has liveblog enabled or archived, and is either published or readable
		 * by the current user. Filter to loosen (e.g. for a headless front end
		 * serving drafts) or tighten.
		 *
		 * @param bool            $allowed Whether the request is allowed.
		 * @param int             $post_id The post ID being queried (0 when not supplied).
		 * @param WP_REST_Request $request The current REST request.
		 */
		$allowed = (bool) apply_filters( 'liveblog_rest_read_permission', $allowed, $post_id, $request );

		if ( $allowed ) {
			return true;
		}

		return new WP_Error(
			'rest_liveblog_not_found',
			__( 'Liveblog not found.', 'liveblog' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Initialize the REST API controller.
	 *
	 * Called from PluginBootstrapper to wire up the controller.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Get the endpoint base URL.
	 *
	 * @return string
	 */
	public function get_endpoint_base(): string {
		if ( ! empty( $this->endpoint_base ) && apply_filters( 'liveblog_cache_endpoint_base', true ) ) {
			return $this->endpoint_base;
		}

		$this->endpoint_base = self::build_endpoint_base();

		return $this->endpoint_base;
	}

	/**
	 * Build the REST API endpoint base URL.
	 *
	 * Static method for use before instance is available (e.g. AssetManager).
	 *
	 * @return string The endpoint base URL.
	 */
	public static function build_endpoint_base(): string {
		/**
		 * Filters whether to use the static cache for the REST API endpoint base URL.
		 *
		 * @param bool $cache_enabled Whether to enable static caching. Default true.
		 */
		if ( ! empty( self::$static_endpoint_base ) && apply_filters( 'liveblog_cache_endpoint_base', true ) ) {
			return self::$static_endpoint_base;
		}

		$api_namespace = 'liveblog/v' . self::API_VERSION;

		if ( get_option( 'permalink_structure' ) ) {
			$base = '/' . rest_get_url_prefix() . '/' . $api_namespace . '/';
		} else {
			$base = '/?rest_route=/' . $api_namespace . '/';
		}

		self::$static_endpoint_base = home_url( $base );

		return self::$static_endpoint_base;
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get entries between timestamps.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/entries/(?P<start_time>\d+)/(?P<end_time>\d+)([/]*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entries' ),
				'args'                => array(
					'post_id'    => array( 'required' => true ),
					'start_time' => array( 'required' => true ),
					'end_time'   => array( 'required' => true ),
				),
				'permission_callback' => array( self::class, 'can_read_liveblog' ),
			)
		);

		// CRUD operations.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/crud([/]*)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'crud_entry' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog_for_request' ),
				'args'                => array(
					'crud_action' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_crud_action' ),
					),
					'post_id'     => array(
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_numeric' ),
					),
					'content'     => array( 'required' => false ),
					'entry_id'    => array(
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_numeric' ),
					),
				),
			)
		);

		// Lazyload entries.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/lazyload/(?P<max_time>\d+)/(?P<min_time>\d+)([/]*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_lazyload_entries' ),
				'args'                => array(
					'post_id'  => array( 'required' => true ),
					'max_time' => array( 'required' => true ),
					'min_time' => array( 'required' => true ),
				),
				'permission_callback' => array( self::class, 'can_read_liveblog' ),
			)
		);

		// Single entry.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/entry/(?P<entry_id>\d+)([/]*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_single_entry' ),
				'args'                => array(
					'post_id'  => array( 'required' => true ),
					'entry_id' => array( 'required' => true ),
				),
				'permission_callback' => array( self::class, 'can_read_liveblog' ),
			)
		);

		// Preview entry.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/preview([/]*)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'format_preview_entry' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog_for_request' ),
				'args'                => array(
					'entry_content' => array( 'required' => true ),
				),
			)
		);

		// Authors autocomplete.
		register_rest_route(
			$this->api_namespace,
			'/authors([/]*)(?P<term>.*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_authors' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'term' => array( 'required' => false ),
				),
			)
		);

		// Hashtags autocomplete.
		register_rest_route(
			$this->api_namespace,
			'/hashtags([/]*)(?P<term>.*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_hashtag_terms' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog' ),
				'args'                => array(
					'term' => array( 'required' => false ),
				),
			)
		);

		// Post state.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/post_state([/]*)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_post_state' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog_for_request' ),
				'args'                => array(
					'post_id' => array( 'required' => true ),
					'state'   => array( 'required' => true ),
				),
			)
		);

		// Paged entries.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/get-entries/(?P<page>\d+)/(?P<last_known_entry>[^\/]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entries_paged' ),
				'args'                => array(
					'post_id'          => array( 'required' => true ),
					'page'             => array( 'required' => true ),
					'last_known_entry' => array( 'required' => true ),
				),
				'permission_callback' => array( self::class, 'can_read_liveblog' ),
			)
		);

		// Get entries as HTML (for vanilla JS polling).
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/entries-html/(?P<timestamp>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_entries_html' ),
				'args'                => array(
					'post_id'   => array( 'required' => true ),
					'timestamp' => array( 'required' => true ),
				),
				'permission_callback' => array( self::class, 'can_read_liveblog' ),
			)
		);

		// Breakout entry into standalone post.
		register_rest_route(
			$this->api_namespace,
			'/(?P<post_id>\d+)/breakout/(?P<entry_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'breakout_entry' ),
				'permission_callback' => array( self::class, 'current_user_can_edit_liveblog_for_request' ),
				'args'                => array(
					'post_id'  => array( 'required' => true ),
					'entry_id' => array( 'required' => true ),
				),
			)
		);
	}

	/**
	 * Get entries between timestamps.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_entries( WP_REST_Request $request ): array {
		$post_id         = (int) $request->get_param( 'post_id' );
		$start_timestamp = (int) $request->get_param( 'start_time' );
		$end_timestamp   = (int) $request->get_param( 'end_time' );

		$this->set_liveblog_vars( $post_id );

		$entries          = $this->query_service->get_between_timestamps( $post_id, $start_timestamp, $end_timestamp );
		$entries_for_json = $this->entries_for_json( $entries );

		$latest_timestamp = 0;
		if ( ! empty( $entries ) ) {
			$latest_timestamp = $this->query_service->get_latest_timestamp( $post_id );
		}

		HttpResponseHelper::prevent_caching_if_needed();

		return array(
			'entries'          => $entries_for_json,
			'latest_timestamp' => $latest_timestamp,
		);
	}

	/**
	 * Perform CRUD operation on entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return mixed
	 */
	public function crud_entry( WP_REST_Request $request ) {
		$crud_action = $request->get_param( 'crud_action' );
		$json        = $request->get_json_params();

		// Use the URL post_id as the authoritative target. The permission
		// callback authorises against the URL post_id, so the JSON body must
		// not be allowed to redirect the action at a different post.
		$url_params = $request->get_url_params();
		$post_id    = isset( $url_params['post_id'] ) ? (int) $url_params['post_id'] : 0;

		$entry_id_value = $this->get_json_param( 'entry_id', $json );

		// For update and delete actions, verify that the entry belongs to
		// the URL's post_id to prevent cross-parent IDOR.
		if ( false !== $entry_id_value && 'insert' !== $crud_action ) {
			$entry_post = get_post( (int) $entry_id_value );
			if ( ! $entry_post || (int) $entry_post->post_parent !== $post_id ) {
				return new WP_Error(
					'rest_liveblog_entry_not_found',
					__( 'Entry not found.', 'liveblog' ),
					array( 'status' => 404 )
				);
			}
		}

		$args = array(
			'post_id'         => $post_id,
			'content'         => $this->get_json_param( 'content', $json ),
			'entry_id'        => $entry_id_value,
			'author_id'       => $this->get_json_param( 'author_id', $json ),
			'contributor_ids' => $this->get_json_param( 'contributor_ids', $json ),
		);

		$this->set_liveblog_vars( $post_id );

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return new WP_Error(
				'rest_liveblog_invalid_user',
				__( 'Invalid user.', 'liveblog' ),
				array( 'status' => 401 )
			);
		}

		$result = $this->entry_operations->do_crud( $crud_action, $args, $user );

		HttpResponseHelper::prevent_caching_if_needed();

		return $result;
	}

	/**
	 * Get entries for lazyloading.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_lazyload_entries( WP_REST_Request $request ): array {
		$post_id       = (int) $request->get_param( 'post_id' );
		$max_timestamp = (int) $request->get_param( 'max_time' );
		$min_timestamp = (int) $request->get_param( 'min_time' );

		$this->set_liveblog_vars( $post_id );

		$result = $this->request_router->get_lazyload_entries( $post_id, $max_timestamp, $min_timestamp );

		HttpResponseHelper::prevent_caching_if_needed();

		return $result;
	}

	/**
	 * Get a single entry.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_single_entry( WP_REST_Request $request ): array {
		$post_id  = (int) $request->get_param( 'post_id' );
		$entry_id = (int) $request->get_param( 'entry_id' );

		$this->set_liveblog_vars( $post_id );

		$result = $this->request_router->get_single_entry( $post_id, $entry_id );

		HttpResponseHelper::prevent_caching_if_needed();

		return $result;
	}

	/**
	 * Format entry content for preview.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function format_preview_entry( WP_REST_Request $request ): array {
		$post_id       = (int) $request->get_param( 'post_id' );
		$json          = $request->get_json_params();
		$entry_content = $this->get_json_param( 'entry_content', $json );

		$this->set_liveblog_vars( $post_id );

		$preview = $this->entry_operations->format_preview( is_string( $entry_content ) ? $entry_content : '' );

		HttpResponseHelper::prevent_caching_if_needed();

		return $preview;
	}

	/**
	 * Get authors matching search term.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_authors( WP_REST_Request $request ): array {
		$term = $request->get_param( 'term' );
		$term = is_string( $term ) ? $term : '';

		$query_args = array(
			'search'         => '*' . $term . '*',
			'search_columns' => array( 'display_name', 'user_nicename' ),
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		);

		/**
		 * Filters the user query arguments for author autocomplete.
		 *
		 * @param array  $query_args The WP_User_Query arguments.
		 * @param string $term       The search term.
		 */
		$query_args = apply_filters( 'liveblog_author_autocomplete_query_args', $query_args, $term );

		$users  = get_users( $query_args );
		$result = array();

		foreach ( $users as $user ) {
			$result[] = array(
				'value'      => $user->ID,
				'label'      => $user->display_name,
				'label_html' => sprintf(
					'<span class="liveblog-author-suggestion">%1$s <span class="liveblog-meta">(%2$s)</span></span>',
					esc_html( $user->display_name ),
					esc_html( $user->user_nicename )
				),
			);
		}

		return $result;
	}

	/**
	 * Get hashtags matching search term.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_hashtag_terms( WP_REST_Request $request ): array {
		$term = $request->get_param( 'term' );
		$term = is_string( $term ) ? $term : '';

		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'search'     => $term,
				'hide_empty' => false,
				'number'     => 20,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term_obj ) {
			$result[] = array(
				'value'      => '#' . $term_obj->name,
				'label'      => '#' . $term_obj->name,
				'label_html' => sprintf(
					'<span class="liveblog-hashtag-suggestion">#%1$s <span class="liveblog-meta">(%2$s)</span></span>',
					esc_html( $term_obj->name ),
					esc_html( (string) $term_obj->count )
				),
			);
		}

		return $result;
	}

	/**
	 * Update post state.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string
	 */
	public function update_post_state( WP_REST_Request $request ): array {
		$post_id = (int) $request->get_param( 'post_id' );
		$state   = $request->get_param( 'state' );

		$request_vars = array(
			'state'                        => $state,
			'liveblog-key-template-name'   => $request->get_param( 'template_name' ),
			'liveblog-key-template-format' => $request->get_param( 'template_format' ),
			'liveblog-key-limit'           => $request->get_param( 'limit' ),
		);

		$this->set_liveblog_vars( $post_id );

		$result = $this->admin_controller->set_liveblog_state( $post_id, $state, $request_vars );

		HttpResponseHelper::prevent_caching_if_needed();

		return $result;
	}

	/**
	 * Get paged entries.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function get_entries_paged( WP_REST_Request $request ): array {
		$post_id          = (int) $request->get_param( 'post_id' );
		$page             = (int) $request->get_param( 'page' );
		$last_known_entry = $request->get_param( 'last_known_entry' );

		$this->set_liveblog_vars( $post_id );

		$entries = $this->request_router->get_entries_paged(
			$post_id,
			$page,
			is_string( $last_known_entry ) ? $last_known_entry : null
		);

		HttpResponseHelper::prevent_caching_if_needed();

		return $entries;
	}

	/**
	 * Set liveblog variables for the current request.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function set_liveblog_vars( int $post_id ): void {
		$this->current_post_id = $post_id;
	}

	/**
	 * Convert entries to JSON format.
	 *
	 * @param Entry[] $entries Array of Entry domain objects.
	 * @return array
	 */
	private function entries_for_json( array $entries ): array {
		$entries_for_json = array();

		foreach ( $entries as $entry ) {
			if ( $entry instanceof Entry ) {
				$presenter          = EntryPresenter::from_entry( $entry );
				$entries_for_json[] = $presenter->for_json();
			}
		}

		return $entries_for_json;
	}

	/**
	 * Validate CRUD action.
	 *
	 * @param string          $param   Parameter value.
	 * @param WP_REST_Request $request REST request.
	 * @param string          $key     Parameter key.
	 * @return bool
	 */
	public function validate_crud_action( $param, $request, $key ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by REST API validate_callback signature.
		return is_string( $param ) && $this->entry_operations->is_valid_action( $param );
	}

	/**
	 * Sanitize numeric value.
	 *
	 * @param mixed           $param   Parameter value.
	 * @param WP_REST_Request $request REST request.
	 * @param string          $key     Parameter key.
	 * @return int
	 */
	public function sanitize_numeric( $param, $request, $key ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by REST API sanitize_callback signature.
		return ( ! empty( $param ) && is_numeric( $param ) ? (int) $param : 0 );
	}

	/**
	 * Get parameter from JSON.
	 *
	 * @param string     $param Parameter name.
	 * @param array|null $json  JSON data.
	 * @return mixed
	 */
	private function get_json_param( string $param, ?array $json ) {
		if ( null === $json || ! isset( $json[ $param ] ) ) {
			return false;
		}

		$value = $json[ $param ];

		if ( is_array( $value ) ) {
			return array_map(
				function ( $item ) {
					return is_string( $item ) ? html_entity_decode( $item ) : $item;
				},
				$value
			);
		}

		return is_string( $value ) ? html_entity_decode( $value ) : $value;
	}
	/**
	 * Get entries as rendered HTML after a timestamp.
	 *
	 * Used for polling in vanilla JS.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function get_entries_html( WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post_id' );
		$timestamp = (int) $request->get_param( 'timestamp' );

		$container  = \Automattic\Liveblog\Infrastructure\DI\Container::instance();
		$repository = $container->entry_repository();
		$renderer   = $container->template_renderer();

		$liveblog_post = LiveblogPost::from_id( $post_id );

		// Convert timestamp to UTC date for post query.
		$date = gmdate( 'Y-m-d H:i:s', $timestamp );

		$args = array(
			'post_parent'    => $post_id,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => $date,
				),
			),
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'posts_per_page' => 50,
		);

		$posts = get_posts( $args );

		$html      = '';
		$newest_ts = 0;

		foreach ( $posts as $post ) {
			$entry_id = \Automattic\Liveblog\Domain\ValueObject\EntryId::from_int( $post->ID );
			$entry    = $repository->get_entry( $entry_id );

			if ( ! $entry instanceof Entry ) {
				continue;
			}

			$entry_html = $renderer->render(
				'entry.php',
				array(
					'entry'         => $entry,
					'liveblog_post' => $liveblog_post,
				)
			);

			$html .= $entry_html;

			$modified_ts = (int) strtotime( $post->post_modified_gmt );
			if ( $modified_ts > $newest_ts ) {
				$newest_ts = $modified_ts;
			}
		}

		return new \WP_REST_Response(
			array(
				'html'      => $html,
				'count'     => count( $posts ),
				'timestamp' => $newest_ts,
			),
			200
		);
	}

	/**
	 * Break out a liveblog entry into a standalone draft post.
	 *
	 * Copies entry content, inherits parent taxonomies and featured image,
	 * creates a new draft post, and links back via liveblog_breakout_post_id meta.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function breakout_entry( WP_REST_Request $request ) {
		$post_id  = (int) $request->get_param( 'post_id' );
		$entry_id = (int) $request->get_param( 'entry_id' );

		$entry_post = get_post( $entry_id );
		if ( ! $entry_post || $entry_post->post_parent !== $post_id ) {
			return new WP_Error(
				'rest_liveblog_entry_not_found',
				__( 'Entry not found.', 'liveblog' ),
				array( 'status' => 404 )
			);
		}

		$existing_breakout = get_post_meta( $entry_id, 'liveblog_breakout_post_id', true );
		if ( $existing_breakout ) {
			return new WP_Error(
				'rest_liveblog_already_broken_out',
				__( 'Entry is already broken out.', 'liveblog' ),
				array( 'status' => 400 )
			);
		}

		$parent_post = get_post( $post_id );
		if ( ! $parent_post ) {
			return new WP_Error(
				'rest_liveblog_parent_not_found',
				__( 'Parent post not found.', 'liveblog' ),
				array( 'status' => 404 )
			);
		}

		// Build new post data from the entry.
		$new_post = array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => wp_trim_words( wp_strip_all_tags( $entry_post->post_content ), 10, '…' ),
			'post_content' => $entry_post->post_content,
			'post_author'  => get_current_user_id(),
			'post_parent'  => 0,
		);

		$new_post_id = wp_insert_post( $new_post, true );
		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		// Inherit categories from parent liveblog post.
		$categories = wp_get_post_categories( $post_id );
		if ( ! empty( $categories ) ) {
			wp_set_post_categories( $new_post_id, $categories );
		}

		// Inherit tags from parent.
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $new_post_id, $tags );
		}

		// Inherit featured image from parent.
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $new_post_id, $thumbnail_id );
		}

		// Store breakout link on original entry.
		update_post_meta( $entry_id, 'liveblog_breakout_post_id', $new_post_id );

		// Store reverse link on breakout post so we can find the source entry
		// when the breakout gets published (for polling re-delivery).
		update_post_meta( $new_post_id, '_liveblog_breakout_source_entry', $entry_id );

		return new \WP_REST_Response(
			array(
				'breakout_post_id' => $new_post_id,
				'edit_link'        => get_edit_post_link( $new_post_id, 'raw' ),
			),
			200
		);
	}
}
