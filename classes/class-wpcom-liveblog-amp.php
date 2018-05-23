<?php

/**
 * Class WPCOM_Liveblog_AMP
 *
 * Adds AMP support for Liveblog
 */
class WPCOM_Liveblog_AMP {

	/**
	 * Called by WPCOM_Liveblog::load(),
	 */
	public static function load() {

		// Make sure AMP plugin is installed, if not exit.
		if ( ! function_exists( 'amp_activate' ) ) {
			return;
		}

		// Hook at template_redirect level as some Liveblog hooks require it.
		add_filter( 'template_redirect', array( __CLASS__, 'setup' ), 10 );

		// Add query vars to support pagination and single entries.
		add_filter( 'query_vars', array( __CLASS__, 'add_customn_query_vars' ), 10 );
	}

	/**
	 * AMP Setup by removing and adding new hooks.
	 *
	 * @return void
	 */
	public static function setup() {
		// If we're on an AMP page then bail.
		if ( ! is_amp_endpoint() ) {
			return;
		}

		// Remove the standard Liveblog markup which just a <div> for React to render.
		remove_filter( 'the_content', array( 'WPCOM_Liveblog', 'add_liveblog_to_content' ), 20 );

		// Remove standard Liveblog scripts as custom JS is not required for AMP.
		remove_action( 'wp_enqueue_scripts', array( 'WPCOM_Liveblog', 'enqueue_scripts' ) );

		// Add Liveblog to Schema
		add_filter( 'amp_post_template_metadata', array( __CLASS__, 'append_liveblog_to_metadata' ), 10, 2 );

		// Add AMP ready markup to post.
		add_filter( 'the_content', array( __CLASS__, 'append_liveblog_to_content' ), 10 );

		// Add AMP CSS for Liveblog.
		// If this an AMP Theme then use enqueue for styles.
		if ( current_theme_supports( 'amp' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		} else {
			add_action( 'amp_post_template_css', array( __CLASS__, 'print_styles' ) );
		}

	}

	/**
	 * Add query vars to support pagination and single entries.
	 *
	 * @param array $query_vars Allowed Query Variables.
	 */
	public static function add_customn_query_vars( $query_vars ) {
		$query_vars[] = 'liveblog_page';
		$query_vars[] = 'liveblog_id';
		$query_vars[] = 'liveblog_last';

		return $query_vars;
	}

	/**
	 * Add default social share options
	 */
	public static function add_social_share_options() {
		if ( defined( 'LIVEBLOG_AMP_SOCIAL_SHARE' ) && false === LIVEBLOG_AMP_SOCIAL_SHARE ) {
			return array();
		}

		$social_array = array( 'twitter', 'pinterest', 'email', 'gplus' );

		if ( defined( 'LIVEBLOG_AMP_FACEBOOK_SHARE' ) ) {
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
		include dirname( __DIR__ ) . '/assets/amp.css';
	}

	/**
	 * Enqueue Styles
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		wp_enqueue_style( 'liveblog', plugin_dir_url( __DIR__ ) . 'assets/amp.css' );
	}

	/**
	 * Adds Liveblog information to Schema metadata.
	 *
	 * @param  array   $metadata  Metadata.
	 * @param  WP_Post $post    Current Post.
	 * @return array           Updated Meta
	 */
	public static function append_liveblog_to_metadata( $metadata, $post ) {

		// If we are not viewing a liveblog post then exist the filter.
		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		$publisher_organization = '';
		$publisher_name         = '';

		$entries = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last );

		$blog_updates = [];

		foreach ( $entries['entries'] as $key => $entry ) {

			if ( isset( $metadata['publisher']['name'] ) ) {
				$publisher_name = $metadata['publisher']['name'];
			}

			if ( isset( $metadata['publisher']['type'] ) ) {
				$publisher_organization = $metadata['publisher']['type'];
			}

			$blog_item = (object) array(
				'@type'         => 'BlogPosting',
				'headline'      => 'headline',
				'url'           => $entry->share_link,
				'datePublished' => date( 'c', $entry->entry_time ),
				'dateModified'  => date( 'c', $entry->timestamp ),
				'author'        => (object) array(
					'@type' => 'Person',
					'name'  => $entry->authors[0]['name'],
				),
				'articleBody'   => (object) array(
					'@type' => 'Text',
				),
				'publisher'     => (object) array(
					'@type' => $publisher_organization,
					'name'  => $publisher_name,
				),
			);

			array_push( $blog_updates, $blog_item );
		}

		$metadata['@type']          = 'LiveBlogPosting';
		$metadata['liveBlogUpdate'] = $blog_updates;

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

		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		if ( $request->id ) {
			$entries  = WPCOM_Liveblog::get_entries_paged( false, false, $request->id );
			$request  = self::set_request_last_from_entries( $entries, $request );
			$content .= self::build_single_entry( $entries, $request, $post->post_id );
		} else {
			$entries  = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last );
			$request  = self::set_request_last_from_entries( $entries, $request );
			$content .= self::build_entries_feed( $entries, $request, $post->post_id );
		}

		return $content;
	}


	/**
	 * Set the last known entry for users who don't have one yet.
	 *
	 * @param  array  $entries liveblog entries.
	 * @param  object $request Request Object.
	 */
	public function set_request_last_from_entries( $entries, $request ) {
		if ( false === $request->last ) {
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

		$entries['entries'] = self::filter_entries( $entries['entries'], $post_id );

		$match = false;

		foreach ( $entries['entries'] as $entry ) {
			if ( (int) $entry->id === (int) $request->id ) {
				$match = $entry;
			}
		}

		if ( false === $match ) {
			return '';
		}

		$rendered = self::get_template(
			'entry', array(
				'single'         => true,
				'id'             => $request->id,
				'content'        => $match->content,
				'authors'        => $match->authors,
				'time'           => $match->time,
				'date'           => $match->date,
				'time_ago'       => $match->time_ago,
				'share_link'     => $match->share_link,
				'update_time'    => $match->timestamp,
				'share_link_amp' => $match->share_link_amp,
			)
		);

		return $rendered;
	}

	/**
	 * Builds entry data for single liveblog entry template on AMP
	 *
	 * @param  array  $entries liveblog entries.
	 * @param  object $request Request Object.
	 * @param  string $post_id post id.
	 * @return object          template
	 */
	public static function build_entries_feed( $entries, $request, $post_id ) {
		$rendered = self::get_template(
			'feed', array(
				'entries'  => self::filter_entries( $entries['entries'], $post_id ),
				'post_id'  => $post_id,
				'page'     => $entries['page'],
				'pages'    => $entries['pages'],
				'links'    => self::get_pagination_links( $request, $entries['pages'], $post_id ),
				'settings' => array(
					'entries_per_page' => WPCOM_Liveblog_Lazyloader::get_number_of_entries(),
					'refresh_interval' => WPCOM_Liveblog::get_refresh_interval(),
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
		return human_time_diff( $entry->entry_time, current_time( 'timestamp', true ) ) . ' ago';
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

		$links['first'] = self::build_paged_permalink( $permalink, 1, $request->last );
		$links['last']  = self::build_paged_permalink( $permalink, $pages, $request->last );

		$links['prev'] = false;
		if ( $request->page > 1 ) {
			$links['prev'] = self::build_paged_permalink( $permalink, $request->page - 1, $request->last );
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
			), $permalink
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
			), $permalink
		);
	}

	/**
	 * Get Page and Last known entry from the request.
	 *
	 * @return object Request Data.
	 */
	public static function get_request_data() {
		return (object) array(
			'page' => get_query_var( 'liveblog_page', 1 ),
			'last' => get_query_var( 'liveblog_last', false ),
			'id'   => get_query_var( 'liveblog_id', false ),
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
}
