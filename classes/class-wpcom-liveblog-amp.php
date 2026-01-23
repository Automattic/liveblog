<?php
/**
 * Adds AMP support for Liveblog.
 *
 * @package Liveblog
 */

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Class WPCOM_Liveblog_AMP
 *
 * Adds AMP support for Liveblog.
 */
class WPCOM_Liveblog_AMP {

	/**
	 * AMP adds the following query string to requests when polling.
	 */
	const AMP_UPDATE_QUERY_VAR = 'amp_latest_update_time';

	/**
	 * Called by PluginBootstrapper::init_legacy_classes() to initialize AMP support.
	 *
	 * @return void
	 */
	public static function load() {

		// Make sure AMP plugin is installed, if not exit.
		if ( ! function_exists( 'amp_activate' ) ) {
			return;
		}

		// Hook at template_redirect level as some Liveblog hooks require it.
		add_action( 'template_redirect', array( __CLASS__, 'setup' ), 10 );

		// Add query vars to support pagination and single entries.
		add_filter( 'query_vars', array( __CLASS__, 'add_custom_query_vars' ), 10 );
	}

	/**
	 * AMP Setup by removing and adding new hooks.
	 */
	public static function setup() {
		// If we're not on an AMP page then bail.
		if ( ! is_amp_endpoint() ) {
			return;
		}

		// If we're not on a liveblog, then bail.
		$liveblog_post = LiveblogPost::from_id( get_the_ID() );
		if ( null === $liveblog_post || ! $liveblog_post->is_liveblog() ) {
			return;
		}

		// Remove the standard Liveblog markup which just a <div> for React to render.
		remove_filter( 'the_content', array( Container::instance()->template_renderer(), 'filter_the_content' ) );

		// Remove standard Liveblog scripts as custom JS is not required for AMP.
		remove_action( 'wp_enqueue_scripts', array( Container::instance()->asset_manager(), 'maybe_enqueue_frontend_scripts' ) );

		// Add Liveblog to Schema.
		add_filter( 'amp_post_template_metadata', array( __CLASS__, 'append_liveblog_to_metadata' ), 10, 2 );

		/**
		 * If the_content filter is set higher than 7, embeds (for example Twitter) don't
		 * render on a AMP only theme. Having it set at 7 means we must remove wpauto
		 * as this affects layout.
		 */
		remove_filter( 'the_content', 'wpautop' );

		// Add AMP ready markup to post.
		add_filter( 'the_content', array( __CLASS__, 'append_liveblog_to_content' ), 7 );

		// Add AMP CSS for Liveblog and social meta tags.
		// If this an AMP Theme then use enqueue for styles.
		if ( current_theme_supports( 'amp' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
			add_action( 'wp_head', array( __CLASS__, 'social_meta_tags' ) );
		} else {
			add_action( 'amp_post_template_css', array( __CLASS__, 'print_styles' ) );
			add_action( 'amp_post_template_head', array( __CLASS__, 'social_meta_tags' ) );
		}
	}

	/**
	 * Add query vars to support pagination and single entries.
	 *
	 * @param array $query_vars Allowed Query Variables.
	 * @return array Updated query vars.
	 */
	public static function add_custom_query_vars( $query_vars ) {
		$query_vars[] = 'liveblog_page';
		$query_vars[] = 'liveblog_id';
		$query_vars[] = 'liveblog_last';

		return $query_vars;
	}

	/**
	 * Add default social share options.
	 *
	 * @return array Array of social platform identifiers for AMP sharing.
	 */
	public static function add_social_share_options() {
		$social_array = array( 'twitter', 'pinterest', 'email' );

		/**
		 * Filters the Facebook App ID for AMP social sharing.
		 *
		 * Facebook sharing requires an App ID. Return your Facebook App ID
		 * from this filter to enable Facebook sharing on AMP liveblog entries.
		 *
		 * Example usage:
		 *     add_filter( 'liveblog_amp_facebook_share_app_id', function() {
		 *         return '123456789012345';
		 *     } );
		 *
		 * @since 1.9.7
		 *
		 * @param string $app_id Facebook App ID. Default empty string (disabled).
		 */
		$facebook_app_id = apply_filters( 'liveblog_amp_facebook_share_app_id', '' );

		if ( ! empty( $facebook_app_id ) ) {
			$social_array[] = 'facebook';
		}

		return apply_filters( 'liveblog_amp_social_share_platforms', $social_array );
	}

	/**
	 * Print styles out by including file.
	 *
	 * @return void
	 */
	public static function print_styles() {
		$css      = file_get_contents( dirname( __DIR__ ) . '/build/amp.css' );
		$safe_css = wp_check_invalid_utf8( $css );
		$safe_css = _wp_specialchars( $safe_css );

		echo $safe_css;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enqueue Styles
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		/** This filter is documented in liveblog.php */
		if ( apply_filters( 'liveblog_load_default_styles', true ) ) {
			wp_enqueue_style( 'liveblog', plugin_dir_url( __DIR__ ) . 'build/amp.css', array(), LiveblogConfiguration::VERSION );
		}
	}


	/**
	 * Print soical meta tags
	 *
	 * @return void
	 */
	public static function social_meta_tags() {
		global $post;

		// If we are not viewing a liveblog post then exist the filter.
		$liveblog_post = LiveblogPost::from_post( $post );
		if ( ! $liveblog_post->is_liveblog() ) {
			return;
		}

		$request = Container::instance()->request_router()->get_request_data();

		// If no entry id set then not on single entry.
		if ( false === $request->id ) {
			return;
		}

		$entry       = self::get_entry( $request->id, $post->ID );
		$title       = \Automattic\Liveblog\Application\Presenter\EntryPresenter::get_entry_title( $entry );
		$description = wp_strip_all_tags( $entry->content );
		$url         = self::build_single_entry_permalink( amp_get_permalink( $post->ID ), $entry->id );
		$image       = self::get_entry_image( $entry );

		// If the entry doesn't contain images, lets see if the post has featured image.
		if ( false === $image ) {
			$image = get_the_post_thumbnail_url( $post->ID );
		}

		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">';
		echo '<meta property="og:url" content="' . esc_attr( $url ) . '">';
		echo '<meta name="twitter:card" content="' . esc_attr( $description ) . '">';

		// If we have an image, lets use it.
		if ( $image ) {
				echo '<meta property="og:image" content="' . esc_attr( $image ) . '">';
		}
	}

	/**
	 * Gets the first image within the entry content HTML.
	 *
	 * @param  object $entry Entry.
	 * @return string        Image URL
	 */
	public static function get_entry_image( $entry ) {
		$doc = new DOMDocument();
		$doc->loadHTML( $entry->content );

		$tags = $doc->getElementsByTagName( 'img' );

		foreach ( $tags as $img ) {
			return $img->getAttribute( 'src' );
		}

		return false;
	}

	/**
	 * Adds Liveblog information to Schema metadata.
	 *
	 * @param  array   $metadata  Metadata.
	 * @param  WP_Post $post    Current Post.
	 * @return array           Updated Meta
	 */
	public static function append_liveblog_to_metadata( $metadata, $post ) {
		// Only append metadata to Liveblogs.
		$liveblog_post = LiveblogPost::from_post( $post );
		if ( $liveblog_post->is_liveblog() ) {
			$metadata = Container::instance()->metadata_presenter()->generate( $post, $metadata );
		}

		return $metadata;
	}

	/**
	 * Append Liveblog to Content
	 *
	 * @param  string $content WordPress Post Content.
	 * @return string          Updated Content
	 */
	public static function append_liveblog_to_content( $content ) {
		global $post;

		$liveblog_post = LiveblogPost::from_post( $post );
		if ( ! $liveblog_post->is_liveblog() ) {
			return $content;
		}

		$router  = Container::instance()->request_router();
		$request = $router->get_request_data();

		// If AMP Polling request don't restrict content so it knows there is updates are available.
		if ( self::is_amp_polling() ) {
			$request->last = false;
		}

		if ( $request->id ) {
			$entries  = $router->get_entries_paged( $post->ID, 1, null, (int) $request->id );
			$request  = self::set_request_last_from_entries( $entries, $request );
			$content .= self::build_single_entry( $entries, $request, $post->ID );
		} else {
			$last_entry = $request->last ? (string) $request->last : null;
			$entries    = $router->get_entries_paged( $post->ID, (int) $request->page, $last_entry );
			$request    = self::set_request_last_from_entries( $entries, $request );
			$content   .= self::build_entries_feed( $entries, $request, $post->ID );
		}

		return $content;
	}


	/**
	 * Set the last known entry for users who don't have one yet.
	 *
	 * @param  array  $entries Liveblog entries.
	 * @param  object $request Request Object.
	 * @return object Updated request object.
	 */
	public static function set_request_last_from_entries( $entries, $request ) {
		if ( false === $request->last && ! empty( $entries['entries'] ) ) {
			$request->last = $entries['entries'][0]->id . '-' . $entries['entries'][0]->timestamp;
		}

		return $request;
	}

	/**
	 * Builds entry data for single liveblog entry template on AMP
	 *
	 * @param  array  $entries liveblog entries.
	 * @param  object $request Request Object.
	 * @param  string $post_id post id.
	 * @return object          template
	 */
	public static function build_single_entry( $entries, $request, $post_id ) {

		$entry = self::get_entry( $request->id, $post_id, $entries );

		if ( false === $entry ) {
			return '';
		}

		$rendered = self::get_template(
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

		return $rendered;
	}



	/**
	 * Get a single entry.
	 *
	 * @param  int   $id      Entry ID.
	 * @param  int   $post_id Post ID.
	 * @param  mixed $entries Entries.
	 * @return object         Entry
	 */
	public static function get_entry( $id, $post_id, $entries = false ) {
		if ( false === $entries ) {
			$entries = Container::instance()->request_router()->get_entries_paged( $post_id, 1, null, (int) $id );
		}

		$entries['entries'] = self::filter_entries( $entries['entries'], $post_id );

		foreach ( $entries['entries'] as $entry ) {
			if ( (int) $entry->id === (int) $id ) {
				return $entry;
			}
		}

		return false;
	}

	/**
	 * Minimum refresh interval for AMP live-list component (in milliseconds).
	 *
	 * @see https://amp.dev/documentation/components/amp-live-list/
	 */
	const AMP_MIN_REFRESH_INTERVAL = 15000;

	/**
	 * Builds entry data for liveblog entries feed template on AMP.
	 *
	 * @param  array  $entries liveblog entries.
	 * @param  object $request Request Object.
	 * @param  string $post_id post id.
	 * @return string          Rendered template.
	 */
	public static function build_entries_feed( $entries, $request, $post_id ) {
		// AMP live-list requires a minimum poll interval of 15 seconds.
		$refresh_interval = max( self::AMP_MIN_REFRESH_INTERVAL, LiveblogConfiguration::get_refresh_interval() * 1000 );

		$rendered = self::get_template(
			'feed',
			array(
				'entries'  => self::filter_entries( $entries['entries'], $post_id ),
				'post_id'  => $post_id,
				'page'     => $entries['page'],
				'pages'    => $entries['pages'],
				'links'    => self::get_pagination_links( $request, $entries['pages'], $post_id ),
				'last'     => get_query_var( 'liveblog_last', false ),
				'settings' => array(
					'entries_per_page' => ( new \Automattic\Liveblog\Application\Config\LazyloadConfiguration() )->get_entries_per_page(),
					'refresh_interval' => $refresh_interval,
					'social'           => self::add_social_share_options(),
				),
			)
		);

		return $rendered;
	}

	/**
	 * Filter Entries, adding Time Ago, and Entry Date.
	 *
	 * @param  array  $entries Entries.
	 * @param  string $post_id post id.
	 * @return array         Updates Entries
	 */
	public static function filter_entries( $entries, $post_id ) {
		$permalink = amp_get_permalink( $post_id );

		foreach ( $entries as $key => $entry ) {
			$entries[ $key ]->time_ago       = self::get_entry_time_ago( $entry );
			$entries[ $key ]->date           = self::get_entry_date( $entry );
			$entries[ $key ]->update_time    = $entry->timestamp;
			$entries[ $key ]->share_link_amp = self::build_single_entry_permalink( $permalink, $entry->id );
		}

		return $entries;
	}

	/**
	 * Work out Entry time ago.
	 *
	 * @param  object $entry Entry.
	 * @return string        Time Ago
	 */
	public static function get_entry_time_ago( $entry ) {
		return human_time_diff( $entry->entry_time, time() ) . ' ago';
	}

	/**
	 * Work out Entry date.
	 *
	 * @param  object $entry Entry.
	 * @return string        Date
	 */
	public static function get_entry_date( $entry ) {
		$utc_offset  = get_option( 'gmt_offset' ) . 'hours';
		$date_format = get_option( 'date_format' );

		return date_i18n( $date_format, strtotime( $utc_offset, $entry->entry_time ) );
	}

	/**
	 * Gets Pagination Links (First, Last, Next, Previous)
	 *
	 * @param  object $request Request Object.
	 * @param  int    $pages   Number of pages.
	 * @param  int    $post_id Post ID.
	 * @return object         Pagination Links
	 */
	public static function get_pagination_links( $request, $pages, $post_id ) {
		$links = array();

		$permalink = amp_get_permalink( $post_id );

		$links['base']  = self::build_paged_permalink( $permalink, 1, false );
		$links['first'] = self::build_paged_permalink( $permalink, 1, false );
		$links['last']  = self::build_paged_permalink( $permalink, $pages, $request->last );

		$links['prev'] = false;
		if ( $request->page > 1 ) {
			$keep_postion  = ( 2 === (int) $request->page ) ? false : $request->last;
			$links['prev'] = self::build_paged_permalink( $permalink, $request->page - 1, $keep_postion );
		}

		$links['next'] = false;
		if ( $request->page < $pages ) {
			$links['next'] = self::build_paged_permalink( $permalink, $request->page + 1, $request->last );
		}

		return (object) $links;
	}

	/**
	 * Builds up a pagination link.
	 *
	 * @param  string $permalink        Permalink.
	 * @param  int    $page             Page Number.
	 * @param  string $last             Last Know Entry.
	 * @return string                   Pagination Link
	 */
	public static function build_paged_permalink( $permalink, $page, $last ) {
		return add_query_arg(
			array(
				'liveblog_page' => $page,
				'liveblog_last' => $last,
			),
			$permalink
		);
	}

	/**
	 * Builds up a pagination link.
	 *
	 * @param  string $permalink        Permalink.
	 * @param  int    $id               Entry Id.
	 * @return string                   Entry Link
	 */
	public static function build_single_entry_permalink( $permalink, $id ) {
		return add_query_arg(
			array(
				'liveblog_id' => $id,
			),
			$permalink
		);
	}

	/**
	 * Get template.
	 *
	 * @param  string $name      Name of Template.
	 * @param  array  $variables Variables to be passed to Template.
	 * @return string            Rendered Template
	 */
	public static function get_template( $name, $variables = array() ) {
		$template = new WPCOM_Liveblog_AMP_Template();
		return $template->render( $name, $variables );
	}

	/**
	 * Is this an AMP polling request.
	 *
	 * @return bool AMP polling request.
	 */
	public static function is_amp_polling() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only check for AMP polling status.
		return isset( $_GET[ self::AMP_UPDATE_QUERY_VAR ] );
	}
}
