<?php
/**
 * AMP integration for liveblog.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Presenter\MetadataPresenter;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_Post;

/**
 * Provides AMP support for liveblog posts.
 *
 * This integration is only loaded when the AMP plugin is active,
 * using the amp_init hook to conditionally initialise.
 */
final class AmpIntegration {

	/**
	 * AMP adds this query string when polling for updates.
	 */
	public const AMP_UPDATE_QUERY_VAR = 'amp_latest_update_time';

	/**
	 * Minimum refresh interval for AMP live-list component (milliseconds).
	 *
	 * @see https://amp.dev/documentation/components/amp-live-list/
	 */
	private const AMP_MIN_REFRESH_INTERVAL = 15000;

	/**
	 * Template renderer.
	 *
	 * @var TemplateRenderer
	 */
	private TemplateRenderer $template_renderer;

	/**
	 * Asset manager.
	 *
	 * @var AssetManager
	 */
	private AssetManager $asset_manager;

	/**
	 * Request router.
	 *
	 * @var RequestRouter
	 */
	private RequestRouter $request_router;

	/**
	 * Metadata presenter.
	 *
	 * @var MetadataPresenter
	 */
	private MetadataPresenter $metadata_presenter;

	/**
	 * AMP template renderer.
	 *
	 * @var AmpTemplate
	 */
	private AmpTemplate $amp_template;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Constructor.
	 *
	 * @param TemplateRenderer  $template_renderer  Template renderer.
	 * @param AssetManager      $asset_manager      Asset manager.
	 * @param RequestRouter     $request_router     Request router.
	 * @param MetadataPresenter $metadata_presenter Metadata presenter.
	 * @param string            $plugin_dir         Plugin directory path.
	 */
	public function __construct(
		TemplateRenderer $template_renderer,
		AssetManager $asset_manager,
		RequestRouter $request_router,
		MetadataPresenter $metadata_presenter,
		string $plugin_dir
	) {
		$this->template_renderer  = $template_renderer;
		$this->asset_manager      = $asset_manager;
		$this->request_router     = $request_router;
		$this->metadata_presenter = $metadata_presenter;
		$this->plugin_dir         = $plugin_dir;
		$this->amp_template       = new AmpTemplate( $plugin_dir );
	}

	/**
	 * Initialise AMP integration.
	 *
	 * Called from PluginBootstrapper via amp_init hook.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook at template_redirect as some liveblog hooks require it.
		add_action( 'template_redirect', array( $this, 'setup' ), 10 );

		// Add query vars to support pagination and single entries.
		add_filter( 'query_vars', array( $this, 'add_custom_query_vars' ), 10 );
	}

	/**
	 * Set up AMP-specific hooks.
	 *
	 * Removes standard liveblog hooks and adds AMP-compatible replacements.
	 *
	 * @return void
	 */
	public function setup(): void {
		// Bail if not on an AMP endpoint.
		if ( ! function_exists( 'is_amp_endpoint' ) || ! is_amp_endpoint() ) {
			return;
		}

		// Bail if not on a liveblog post.
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );
		if ( null === $liveblog_post || ! $liveblog_post->is_liveblog() ) {
			return;
		}

		// Remove standard liveblog React markup.
		remove_filter( 'the_content', array( $this->template_renderer, 'filter_the_content' ) );

		// Remove standard liveblog scripts (not needed for AMP).
		remove_action( 'wp_enqueue_scripts', array( $this->asset_manager, 'maybe_enqueue_frontend_scripts' ) );

		// Add liveblog metadata to AMP schema.
		add_filter( 'amp_post_template_metadata', array( $this, 'append_liveblog_to_metadata' ), 10, 2 );

		// Remove wpautop as it affects AMP layout when the_content filter is at priority 7.
		remove_filter( 'the_content', 'wpautop' );

		// Add AMP-ready liveblog markup.
		add_filter( 'the_content', array( $this, 'append_liveblog_to_content' ), 7 );

		// Add AMP styles and social meta tags.
		if ( current_theme_supports( 'amp' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'wp_head', array( $this, 'social_meta_tags' ) );
		} else {
			add_action( 'amp_post_template_css', array( $this, 'print_styles' ) );
			add_action( 'amp_post_template_head', array( $this, 'social_meta_tags' ) );
		}
	}

	/**
	 * Add query vars for pagination and single entries.
	 *
	 * @param string[] $query_vars Allowed query variables.
	 * @return string[] Updated query vars.
	 */
	public function add_custom_query_vars( array $query_vars ): array {
		$query_vars[] = 'liveblog_page';
		$query_vars[] = 'liveblog_id';
		$query_vars[] = 'liveblog_last';

		return $query_vars;
	}

	/**
	 * Get social share platforms for AMP.
	 *
	 * @return string[] Array of platform identifiers.
	 */
	public function get_social_share_platforms(): array {
		$platforms = array( 'twitter', 'pinterest', 'email' );

		/**
		 * Filters the Facebook App ID for AMP social sharing.
		 *
		 * Facebook sharing requires an App ID. Return your Facebook App ID
		 * from this filter to enable Facebook sharing on AMP liveblog entries.
		 *
		 * @since 1.9.7
		 *
		 * @param string $app_id Facebook App ID. Default empty string (disabled).
		 */
		$facebook_app_id = apply_filters( 'liveblog_amp_facebook_share_app_id', '' );

		if ( ! empty( $facebook_app_id ) ) {
			$platforms[] = 'facebook';
		}

		/**
		 * Filters the social sharing platforms for AMP liveblog entries.
		 *
		 * @param string[] $platforms Array of platform identifiers.
		 */
		return apply_filters( 'liveblog_amp_social_share_platforms', $platforms );
	}

	/**
	 * Print AMP styles inline.
	 *
	 * @return void
	 */
	public function print_styles(): void {
		$css_path = $this->plugin_dir . '/build/amp.css';

		if ( ! file_exists( $css_path ) ) {
			return;
		}

		$css = file_get_contents( $css_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.

		if ( false === $css ) {
			return;
		}

		$safe_css = wp_check_invalid_utf8( $css );
		$safe_css = _wp_specialchars( $safe_css );

		echo $safe_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS already sanitised.
	}

	/**
	 * Enqueue AMP styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		/** This filter is documented in src/php/Infrastructure/WordPress/AssetManager.php */
		if ( apply_filters( 'liveblog_load_default_styles', true ) ) {
			wp_enqueue_style(
				'liveblog',
				plugins_url( 'build/amp.css', $this->plugin_dir . '/liveblog.php' ),
				array(),
				LiveblogConfiguration::VERSION
			);
		}
	}

	/**
	 * Print social meta tags for single entry pages.
	 *
	 * @return void
	 */
	public function social_meta_tags(): void {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$liveblog_post = LiveblogPost::from_post( $post );
		if ( ! $liveblog_post->is_liveblog() ) {
			return;
		}

		$request = $this->request_router->get_request_data();

		// Only show meta tags on single entry pages.
		if ( false === $request->id ) {
			return;
		}

		$entry = $this->get_entry( (int) $request->id, $post->ID );
		if ( false === $entry ) {
			return;
		}

		$title       = EntryPresenter::get_entry_title( $entry );
		$description = wp_strip_all_tags( $entry->content );
		$url         = $this->build_single_entry_permalink( amp_get_permalink( $post->ID ), (int) $entry->id );
		$image       = $this->get_entry_image( $entry );

		// Fall back to post featured image.
		if ( false === $image ) {
			$image = get_the_post_thumbnail_url( $post->ID );
		}

		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">';
		echo '<meta property="og:url" content="' . esc_attr( $url ) . '">';
		echo '<meta name="twitter:card" content="' . esc_attr( $description ) . '">';

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_attr( $image ) . '">';
		}
	}

	/**
	 * Get the first image from entry content.
	 *
	 * @param object $entry Entry object.
	 * @return string|false Image URL or false if not found.
	 */
	private function get_entry_image( object $entry ) {
		$doc = new \DOMDocument();

		// Suppress warnings for malformed HTML.
		libxml_use_internal_errors( true );
		$doc->loadHTML( $entry->content );
		libxml_clear_errors();

		$images = $doc->getElementsByTagName( 'img' );

		foreach ( $images as $img ) {
			$src = $img->getAttribute( 'src' );
			if ( ! empty( $src ) ) {
				return $src;
			}
		}

		return false;
	}

	/**
	 * Append liveblog data to AMP schema metadata.
	 *
	 * @param array<string,mixed> $metadata Schema metadata.
	 * @param WP_Post             $post     Current post.
	 * @return array<string,mixed> Updated metadata.
	 */
	public function append_liveblog_to_metadata( array $metadata, WP_Post $post ): array {
		$liveblog_post = LiveblogPost::from_post( $post );

		if ( $liveblog_post->is_liveblog() ) {
			$metadata = $this->metadata_presenter->generate( $post, $metadata );
		}

		return $metadata;
	}

	/**
	 * Append liveblog content for AMP.
	 *
	 * @param string $content Post content.
	 * @return string Updated content with liveblog markup.
	 */
	public function append_liveblog_to_content( string $content ): string {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		$liveblog_post = LiveblogPost::from_post( $post );
		if ( ! $liveblog_post->is_liveblog() ) {
			return $content;
		}

		$request = $this->request_router->get_request_data();

		// For AMP polling requests, don't restrict content.
		if ( $this->is_amp_polling() ) {
			$request->last = false;
		}

		if ( $request->id ) {
			$entries  = $this->request_router->get_entries_paged( $post->ID, 1, null, (int) $request->id );
			$request  = $this->set_request_last_from_entries( $entries, $request );
			$content .= $this->build_single_entry( $entries, $request, $post->ID );
		} else {
			$last_entry = $request->last ? (string) $request->last : null;
			$entries    = $this->request_router->get_entries_paged( $post->ID, (int) $request->page, $last_entry );
			$request    = $this->set_request_last_from_entries( $entries, $request );
			$content   .= $this->build_entries_feed( $entries, $request, $post->ID );
		}

		return $content;
	}

	/**
	 * Set last known entry for users without one.
	 *
	 * @param array<string,mixed> $entries Liveblog entries.
	 * @param object              $request Request object.
	 * @return object Updated request object.
	 */
	private function set_request_last_from_entries( array $entries, object $request ): object {
		if ( false === $request->last && ! empty( $entries['entries'] ) ) {
			$first_entry   = $entries['entries'][0];
			$request->last = $first_entry->id . '-' . $first_entry->timestamp;
		}

		return $request;
	}

	/**
	 * Build single entry template.
	 *
	 * @param array<string,mixed> $entries Entries data.
	 * @param object              $request Request object.
	 * @param int                 $post_id Post ID.
	 * @return string Rendered template.
	 */
	private function build_single_entry( array $entries, object $request, int $post_id ): string {
		$entry = $this->get_entry( (int) $request->id, $post_id, $entries );

		if ( false === $entry ) {
			return '';
		}

		return $this->amp_template->render(
			'entry',
			array(
				'single'         => true,
				'id'             => $entry->id,
				'content'        => $entry->content,
				'authors'        => $entry->authors,
				'time'           => $entry->time,
				'date'           => $entry->date,
				'time_ago'       => $entry->time_ago,
				'share_link'     => $entry->share_link,
				'update_time'    => $entry->timestamp,
				'share_link_amp' => $entry->share_link_amp,
			)
		);
	}

	/**
	 * Get a single entry.
	 *
	 * @param int                      $id      Entry ID.
	 * @param int                      $post_id Post ID.
	 * @param array<string,mixed>|null $entries Pre-fetched entries.
	 * @return object|false Entry object or false if not found.
	 */
	private function get_entry( int $id, int $post_id, ?array $entries = null ) {
		if ( null === $entries ) {
			$entries = $this->request_router->get_entries_paged( $post_id, 1, null, $id );
		}

		$entries['entries'] = $this->filter_entries( $entries['entries'], $post_id );

		foreach ( $entries['entries'] as $entry ) {
			if ( (int) $entry->id === $id ) {
				return $entry;
			}
		}

		return false;
	}

	/**
	 * Build entries feed template.
	 *
	 * @param array<string,mixed> $entries Entries data.
	 * @param object              $request Request object.
	 * @param int                 $post_id Post ID.
	 * @return string Rendered template.
	 */
	private function build_entries_feed( array $entries, object $request, int $post_id ): string {
		// AMP live-list requires minimum 15 second poll interval.
		$refresh_interval = max(
			self::AMP_MIN_REFRESH_INTERVAL,
			LiveblogConfiguration::get_refresh_interval() * 1000
		);

		$lazyload = new LazyloadConfiguration();

		return $this->amp_template->render(
			'feed',
			array(
				'entries'  => $this->filter_entries( $entries['entries'], $post_id ),
				'post_id'  => $post_id,
				'page'     => $entries['page'],
				'pages'    => $entries['pages'],
				'links'    => $this->get_pagination_links( $request, (int) $entries['pages'], $post_id ),
				'last'     => get_query_var( 'liveblog_last', false ),
				'settings' => array(
					'entries_per_page' => $lazyload->get_entries_per_page(),
					'refresh_interval' => $refresh_interval,
					'social'           => $this->get_social_share_platforms(),
				),
			)
		);
	}

	/**
	 * Filter entries to add time ago, date, and AMP links.
	 *
	 * @param object[] $entries Entry objects.
	 * @param int      $post_id Post ID.
	 * @return object[] Updated entries.
	 */
	private function filter_entries( array $entries, int $post_id ): array {
		$permalink = amp_get_permalink( $post_id );

		foreach ( $entries as $key => $entry ) {
			$entries[ $key ]->time_ago       = $this->get_entry_time_ago( $entry );
			$entries[ $key ]->date           = $this->get_entry_date( $entry );
			$entries[ $key ]->update_time    = $entry->timestamp;
			$entries[ $key ]->share_link_amp = $this->build_single_entry_permalink( $permalink, (int) $entry->id );
		}

		return $entries;
	}

	/**
	 * Get human-readable time ago for entry.
	 *
	 * @param object $entry Entry object.
	 * @return string Time ago string.
	 */
	private function get_entry_time_ago( object $entry ): string {
		return human_time_diff( (int) $entry->entry_time, time() ) . ' ago';
	}

	/**
	 * Get formatted date for entry.
	 *
	 * @param object $entry Entry object.
	 * @return string Formatted date.
	 */
	private function get_entry_date( object $entry ): string {
		$utc_offset  = get_option( 'gmt_offset' ) . 'hours';
		$date_format = get_option( 'date_format' );

		return date_i18n( $date_format, strtotime( $utc_offset, (int) $entry->entry_time ) );
	}

	/**
	 * Get pagination links.
	 *
	 * @param object $request Request object.
	 * @param int    $pages   Total pages.
	 * @param int    $post_id Post ID.
	 * @return object Pagination links.
	 */
	private function get_pagination_links( object $request, int $pages, int $post_id ): object {
		$permalink = amp_get_permalink( $post_id );

		$links = array(
			'base'  => $this->build_paged_permalink( $permalink, 1, false ),
			'first' => $this->build_paged_permalink( $permalink, 1, false ),
			'last'  => $this->build_paged_permalink( $permalink, $pages, $request->last ),
			'prev'  => false,
			'next'  => false,
		);

		if ( $request->page > 1 ) {
			$keep_position = ( 2 === (int) $request->page ) ? false : $request->last;
			$links['prev'] = $this->build_paged_permalink( $permalink, $request->page - 1, $keep_position );
		}

		if ( $request->page < $pages ) {
			$links['next'] = $this->build_paged_permalink( $permalink, $request->page + 1, $request->last );
		}

		return (object) $links;
	}

	/**
	 * Build paginated permalink.
	 *
	 * @param string       $permalink Base permalink.
	 * @param int          $page      Page number.
	 * @param string|false $last      Last known entry.
	 * @return string Paginated URL.
	 */
	private function build_paged_permalink( string $permalink, int $page, $last ): string {
		return add_query_arg(
			array(
				'liveblog_page' => $page,
				'liveblog_last' => $last,
			),
			$permalink
		);
	}

	/**
	 * Build single entry permalink.
	 *
	 * @param string $permalink Base permalink.
	 * @param int    $id        Entry ID.
	 * @return string Entry URL.
	 */
	private function build_single_entry_permalink( string $permalink, int $id ): string {
		return add_query_arg( array( 'liveblog_id' => $id ), $permalink );
	}

	/**
	 * Check if this is an AMP polling request.
	 *
	 * @return bool True if AMP polling.
	 */
	private function is_amp_polling(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only check.
		return isset( $_GET[ self::AMP_UPDATE_QUERY_VAR ] );
	}

	/**
	 * Get the AMP template renderer.
	 *
	 * Used by AMP templates to load partials.
	 *
	 * @return AmpTemplate Template renderer.
	 */
	public function get_template(): AmpTemplate {
		return $this->amp_template;
	}
}
