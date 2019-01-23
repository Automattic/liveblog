<?php

/**
 * Plugin Name: Liveblog
 * Plugin URI: http://wordpress.org/extend/plugins/liveblog/
 * Description: Empowers website owners to provide rich and engaging live event coverage to a large, distributed audience.
 * Version:     1.9.5
 * Author:      WordPress.com VIP, Big Bite Creative and contributors
 * Author URI: https://github.com/Automattic/liveblog/graphs/contributors
 * Text Domain: liveblog
 */

if ( ! class_exists( 'WPCOM_Liveblog' ) ) :

	/**
	 * The main Liveblog class used to setup everything this plugin needs.
	 *
	 * Liveblog currently uses a custom comment-type to circumvent post cache
	 * issues frequently experienced by other live-blog implimentations. It comes
	 * with a simple and effective templating mechanism, complete with all of the
	 * CSS, JS, and AJAX needed to make this a turn-key installation.
	 *
	 * This class is a big container for a bunch of static methods, similar to a
	 * factory but without object inheritance or instantiation.
	 */
	final class WPCOM_Liveblog {

		/** Constants *************************************************************/
		const VERSION                 = '1.9.5';
		const REWRITES_VERSION        = 1;
		const MIN_WP_VERSION          = '4.4';
		const MIN_WP_REST_API_VERSION = '4.4';
		const KEY                     = 'liveblog';
		const URL_ENDPOINT            = 'liveblog';
		const EDIT_CAP                = 'publish_posts';
		const NONCE_KEY               = '_wpnonce'; // Using these strings since they're hard coded in the rest api. It'll still work fine for < 4.4
		const NONCE_ACTION            = 'wp_rest';

		const REFRESH_INTERVAL                = 10;   // how often should we refresh
		const DEBUG_REFRESH_INTERVAL          = 2;   // how often we refresh in development mode
		const FOCUS_REFRESH_INTERVAL          = 30;   // how often we refresh in when window not in focus
		const MAX_CONSECUTIVE_RETRIES         = 100; // max number of failed tries before polling is disabled
		const HUMAN_TIME_DIFF_UPDATE_INTERVAL = 60; // how often we change the entry human timestamps: "a minute ago"
		const DELAY_THRESHOLD                 = 5;  // how many failed tries after which we should increase the refresh interval
		const DELAY_MULTIPLIER                = 2; // by how much should we inscrease the refresh interval
		const FADE_OUT_DURATION               = 5; // how much time should take fading out the background of new entries
		const RESPONSE_CACHE_MAX_AGE          = DAY_IN_SECONDS; // `Cache-Control: max-age` value for cacheable JSON responses
		const USE_REST_API                    = true; // Use the REST API if current version is at least MIN_WP_REST_API_VERSION. Allows for easy disabling/enabling
		const DEFAULT_IMAGE_SIZE              = 'full'; // The default image size to use when inserting media frm the media library.
		const AUTHOR_LIST_DEBOUNCE_TIME       = 500; // This is the time ms to debounce the async author list.

		/** Variables *************************************************************/

		public static $post_id                = null;
		private static $entry_query           = null;
		private static $do_not_cache_response = false;
		private static $cache_control_max_age = null;
		private static $custom_template_path  = null;

		public static $is_rest_api_call        = false;
		public static $auto_archive_days       = null;
		public static $auto_archive_expiry_key = 'liveblog_autoarchive_expiry_date';
		public static $latest_timestamp        = false;


		/** Load Methods **********************************************************/

		/**
		 * @uses add_action() to hook methods into WordPress actions
		 * @uses add_filter() to hook methods into WordPress filters
		 */
		public static function load() {
			load_plugin_textdomain( 'liveblog', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			if ( self::is_wp_too_old() ) {
				self::add_old_wp_notice();
				return;
			}
			self::includes();
			self::add_actions();
			self::add_filters();
			self::add_admin_actions();
			self::add_admin_filters();
			self::register_embed_handlers();

			WPCOM_Liveblog_Entry_Key_Events::load();
			WPCOM_Liveblog_Entry_Key_Events_Widget::load();
			WPCOM_Liveblog_Entry_Extend::load();
			WPCOM_Liveblog_Lazyloader::load();
			WPCOM_Liveblog_Socketio_Loader::load();
			WPCOM_Liveblog_Entry_Embed_SDKs::load();
			WPCOM_Liveblog_AMP::load();

			if ( self::use_rest_api() ) {
				WPCOM_Liveblog_Rest_Api::load();
			}

			// Activate the WP CRON Hooks.
			WPCOM_Liveblog_Cron::load();
		}

		public static function add_custom_post_type_support( $query ) {
			if ( ! self::is_entries_ajax_request() ) {
				return;
			}

			$post_types = array_filter( get_post_types(), array( __CLASS__, 'liveblog_post_type' ) );
			$query->set( 'post_type', $post_types );
		}

		public static function liveblog_post_type( $post_type ) {
			return post_type_supports( $post_type, self::KEY );
		}

		private static function add_old_wp_notice() {
			add_action( 'admin_notices', array( 'WPCOM_Liveblog', 'show_old_wp_notice' ) );
		}

		public static function show_old_wp_notice() {
			global $wp_version;
			$min_version = self::MIN_WP_VERSION;
			echo self::get_template_part( 'old-wp-notice.php', compact( 'wp_version', 'min_version' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Include the necessary files
		 */
		private static function includes() {
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-query.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-key-events.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-key-events-widget.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-hashtags.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-commands.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-emojis.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-authors.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-lazyloader.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-socketio-loader.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-embed.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-embed-sdks.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-amp.php';
			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-amp-template.php';

			if ( self::use_rest_api() ) {
				require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-rest-api.php';
			}

			// Manually include ms.php theme-side in multisite environments because
			// we need its filesize and available space functions.
			if ( ! is_admin() && is_multisite() ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
			}

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-wp-cli.php';
			}

			require dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-cron.php';
		}

		/**
		 * Hook actions in that run on every page-load
		 *
		 * @uses add_action()
		 */
		private static function add_actions() {
			add_action( 'init', array( __CLASS__, 'init' ) );
			add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
			add_action( 'permalink_structure_changed', array( __CLASS__, 'add_rewrite_rules' ) );
			// flush the rewrite rules a lot later so that we don't interfere with other plugins using rewrite rules
			add_action( 'init', array( __CLASS__, 'flush_rewrite_rules' ), 1000 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_set_liveblog_state_for_post', array( __CLASS__, 'admin_ajax_set_liveblog_state_for_post' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'add_custom_post_type_support' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_liveblog_metadata' ) );
		}

		/**
		 * Hook filters in that run on every page-load
		 *
		 * @uses add_filter()
		 */
		private static function add_filters() {
			add_filter( 'template_redirect', array( __CLASS__, 'handle_request' ), 9 );
			add_filter( 'comment_class', array( __CLASS__, 'add_comment_class' ), 10, 3 );
			add_filter( 'is_protected_meta', array( __CLASS__, 'protect_liveblog_meta_key' ), 10, 2 );

			// Add In the Filter hooks to Strip any Restricted Shortcodes before a new post or updating a post.
			// Called from the WPCOM_Liveblog_Entry Class.
			add_filter( 'liveblog_before_insert_entry', array( 'WPCOM_Liveblog_Entry', 'handle_restricted_shortcodes' ), 10, 1 );
			add_filter( 'liveblog_before_update_entry', array( 'WPCOM_Liveblog_Entry', 'handle_restricted_shortcodes' ), 10, 1 );

			// We need to check the Liveblog autoarchive date on each time a new entry is added or updated to
			// ensure we extend the date out correctly to the next archive point based on the configured offset.
			add_filter( 'liveblog_before_insert_entry', array( __CLASS__, 'update_autoarchive_expiry' ), 10, 1 );
		}

		/**
		 * Hook actions in that run on every admin page-load
		 *
		 * @uses add_action()
		 * @uses is_admin()
		 */
		private static function add_admin_actions() {

			// Bail if not in admin area
			if ( ! is_admin() ) {
				return;
			}

			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
			add_action( 'restrict_manage_posts', array( __CLASS__, 'add_post_filtering_dropdown_to_manage_posts' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'handle_query_vars_for_post_filtering' ) );
		}

		/**
		 * Hook filters in that run on every admin page-load
		 *
		 * @uses add_filter()
		 * @uses is_admin()
		 */
		private static function add_admin_filters() {

			// Bail if not in admin area
			if ( ! is_admin() ) {
				return;
			}

			add_filter( 'display_post_states', array( __CLASS__, 'add_display_post_state' ), 10, 2 );
			add_filter( 'query_vars', array( __CLASS__, 'add_query_var_for_post_filtering' ) );
		}

		private static function register_embed_handlers() {
			// register it to run later, because the regex is pretty general and we don't want it to prevent more specific handlers from running
			wp_embed_register_handler( 'liveblog_image', '/\.(png|jpe?g|gif)(\?.*)?$/', array( 'WPCOM_Liveblog', 'image_embed_handler' ), 99 );
		}

		/** Public Methods ********************************************************/

		/**
		 * Liveblog initialization functions.
		 *
		 * This is where Liveblog sets up any additional things it needs to run
		 * inside of WordPress. Where some plugins would register post types or
		 * taxonomies, we modify endpoints and add post type support for Liveblog.
		 */
		public static function init() {
			/**
			 * Add liveblog support to the 'post' post type. This is done here so
			 * we can possibly introduce this to other post types later.
			 */
			add_post_type_support( 'post', self::KEY );

			/**
			 * Apply a Filter to Setup our Auto Archive Days.
			 * NULL is classed as disabled.
			 */
			self::$auto_archive_days = apply_filters( 'liveblog_auto_archive_days', self::$auto_archive_days );

			do_action( 'after_liveblog_init' );
		}

		public static function add_rewrite_rules() {
			add_rewrite_endpoint( self::URL_ENDPOINT, EP_PERMALINK );
		}

		public static function flush_rewrite_rules() {
			$rewrites_version = (int) get_option( 'liveblog_rewrites_version' );
			if ( self::REWRITES_VERSION !== $rewrites_version ) {
				flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.VIP.RestrictedFunctions.rewrite_rules_flush_rewrite_rules
				update_option( 'liveblog_rewrites_version', self::REWRITES_VERSION );
			}
		}

		/**
		 * Returns the ID of the Liveblog post.
		 *
		 * @throws Exception when called before post ID is set
		 * @return int Liveblog post ID
		 */
		public static function get_post_id() {
			if ( is_null( self::$post_id ) ) {
				throw new Exception( __( 'No Liveblog post ID is set yet', 'liveblog' ) );
			}

			return self::$post_id;
		}

		/**
		 * Returns the avatar for a user
		 *
		 * @param $user_id author ID
		 * @param $size size, in pixels (or named size)
		 * @return HTML for avatar
		 */
		public static function get_avatar( $user_id, $size ) {
			return apply_filters( 'liveblog_author_avatar', get_avatar( $user_id, $size ), $user_id, $size );
		}

		/**
		 * Get current user
		 */
		public static function get_current_user() {
			if ( ! self::is_liveblog_editable() ) {
				return false;
			}

			$user = wp_get_current_user();

			return array(
				'id'     => $user->ID,
				'key'    => strtolower( $user->user_nicename ),
				'name'   => $user->display_name,
				'avatar' => self::get_avatar( $user->ID, 20 ),
			);
		}

		/**
		 * This is where a majority of the magic happens.
		 *
		 * Hooked to template_redirect, this method tries to add anything it can to
		 * the current post output. If nothing needs to be added, we redirect back
		 * to the permalink.
		 *
		 * return if request has been handled
		 */
		public static function handle_request() {

			if ( ! self::is_viewing_liveblog_post() ) {
				return;
			}

			self::$post_id = get_the_ID();

			self::$custom_template_path = apply_filters( 'liveblog_template_path', self::$custom_template_path, self::$post_id );
			if ( ! is_dir( self::$custom_template_path ) ) {
				self::$custom_template_path = null;
			} else {
				// realpath is used here to ensure we have an absolute path which is necessary to avoid APC related bugs
				self::$custom_template_path = untrailingslashit( realpath( self::$custom_template_path ) );
			}

			self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );

			if ( self::is_initial_page_request() ) {
				// we need to add the liveblog after the shortcodes are run, because we already
				// process shortcodes in the comment contents and if we left any (like in the original content)
				// we wouldn't like them to be processed
				add_filter( 'the_content', array( __CLASS__, 'add_liveblog_to_content' ), 20 );
			} else {
				self::handle_ajax_request();
			}
		}

		private static function handle_ajax_request() {

			$endpoint_suffix = get_query_var( self::URL_ENDPOINT );

			if ( ! $endpoint_suffix ) {
				// we redirect, because if somebody accessed <permalink>/liveblog
				// they probably did that in the URL bar, not via AJAX
				wp_safe_redirect( get_permalink() );
				exit();
			}
			wp_cache_delete( self::KEY . '_entries_asc_' . self::$post_id, 'liveblog' );

			$suffix_to_method = array(
				'\d+/\d+'  => 'ajax_entries_between',
				'crud'     => 'ajax_crud_entry',
				'entry'    => 'ajax_single_entry',
				'lazyload' => 'ajax_lazyload_entries',
				'preview'  => 'ajax_preview_entry',
			);

			$response_method = 'ajax_unknown';

			foreach ( $suffix_to_method as $suffix_re => $method ) {
				if ( preg_match( "%^$suffix_re/?%", $endpoint_suffix ) ) {
					$response_method = $method;
					break;
				}
			}

			/**
			 * Fires just before the Liveblog's ajax request is handled by one of the methods
			 *
			 * @param string $response_method The name of the method used for handling the request.
			 */
			do_action( 'liveblog_ajax_request', $response_method );

			self::$response_method();

		}

		/**
		 * Look for any new Liveblog entries, and return them via JSON
		 * Legacy endpoint for pre 4.4 installs
		 */
		public static function ajax_entries_between() {
			$response_args = array();

			// Look for entry boundaries
			list( $start_timestamp, $end_timestamp ) = self::get_timestamps_from_query();

			// Bail if there is no end timestamp
			if ( empty( $end_timestamp ) ) {
				self::send_user_error( __( 'A timestamp is missing. Correct URL: <permalink>/liveblog/<from>/</to>/', 'liveblog' ) );
			}

			// Get liveblog entries within the start and end boundaries
			$result_for_json = self::get_entries_by_time( $start_timestamp, $end_timestamp );

			self::json_return( $result_for_json );
		}

		/**
		 * Get Liveblog entries between a start and end time for a post
		 *
		 * @param int $start_timestamp  The start time boundary
		 * @param int $end_timestamp    The end time boundary
		 *
		 * @return An array of Liveblog entries, possibly empty.
		 */
		public static function get_entries_by_time( $start_timestamp, $end_timestamp ) {

			// Set some defaults
			$latest_timestamp = null;
			$entries_for_json = array();

			$now = time();

			// If end timestamp is in future, set a cache TTL until it's not
			if ( $end_timestamp > $now ) {
				self::$cache_control_max_age = $end_timestamp - $now;
			}

			if ( empty( self::$entry_query ) ) {
				self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );
			}

			// Get liveblog entries within the start and end boundaries
			$all_entries = self::$entry_query->get_all_entries_asc();
			$entries     = self::$entry_query->find_between_timestamps( $all_entries, $start_timestamp, $end_timestamp );
			$pages       = false;
			$per_page    = WPCOM_Liveblog_Lazyloader::get_number_of_entries();

			if ( ! empty( $entries ) ) {
				/**
				 * Loop through each liveblog entry, set the most recent timestamp, and
				 * put the JSON data for each entry into a neat little array.
				 */
				foreach ( $entries as $entry ) {
					$latest_timestamp   = max( $latest_timestamp, $entry->get_timestamp() );
					$entries_for_json[] = $entry->for_json();
				}

				$pages = ceil( count( self::flatten_entries( $all_entries ) ) / $per_page );
			}

			// Create the result array
			$result = array(
				'entries'          => $entries_for_json,
				'latest_timestamp' => $latest_timestamp,
				'refresh_interval' => self::get_refresh_interval(),
				'pages'            => $pages,
			);

			if ( ! empty( $entries_for_json ) ) {
				do_action( 'liveblog_entry_request', $result );
			} else {
				do_action( 'liveblog_entry_request_empty' );
			}

			return $result;
		}

		/**
		 * Is a given post_id a liveblog enabled post?
		 *
		 * @global WP_Post $post
		 * @param int $post_id
		 * @return bool
		 */
		public static function is_liveblog_post( $post_id = null ) {
			$state = self::get_liveblog_state( $post_id );
			return (bool) $state;
		}

		/**
		 * Are we viewing a liveblog post?
		 *
		 * @uses is_single()
		 * @uses is_liveblog_post()
		 * @return bool
		 */
		public static function is_viewing_liveblog_post() {
			return (bool) ( is_single() && self::is_liveblog_post() );
		}

		/**
		 * One of: 'enable', 'archive', false.
		 */
		public static function get_liveblog_state( $post_id = null ) {
			if ( ! is_single() && ! is_admin() && ! self::$is_rest_api_call ) {
				return false;
			}
			if ( empty( $post_id ) ) {
				if ( ! empty( self::$post_id ) ) {
					$post_id = self::$post_id;
				} else {
					global $post;
					if ( ! $post ) {
						return false;
					}
					$post_id = $post->ID;
				}
			}
			$state = get_post_meta( $post_id, self::KEY, true );
			// backwards compatibility with older values
			if ( 1 === $state ) {
				$state = 'enable';
			}

			return $state;
		}

		/** Private _is_ Methods **************************************************/

		/**
		 * Is this the initial page request?
		 *
		 * Note that we do not use get_query_var() - it returns '' for all requests,
		 * which is valid for /post-name/liveblog/
		 *
		 * @global WP_Query $wp_query
		 * @return bool
		 */
		private static function is_initial_page_request() {
			global $wp_query;

			return (bool) ! isset( $wp_query->query_vars[ self::KEY ] );
		}

		/**
		 * Is this an ajax request for the entries?
		 *
		 * @uses get_query_var() to check for the URL_ENDPOINT
		 * @return bool
		 */
		private static function is_entries_ajax_request() {
			return (bool) get_query_var( self::URL_ENDPOINT );
		}

		/**
		 * Get timestamps from the current WP_Query
		 *
		 * Ensures that two timestamps exist, and returns a properly formatted empty
		 * array if not.
		 *
		 * @return array
		 */
		private static function get_timestamps_from_query() {

			// Look for timestamps and bail if none
			$stamps = rtrim( get_query_var( self::URL_ENDPOINT ), '/' );
			if ( empty( $stamps ) ) {
				return array( false, false );
			}

			// Get timestamps from the query variable
			$timestamps = explode( '/', $stamps );

			// Bail if there are not 2 timestamps
			if ( 2 !== count( $timestamps ) ) {
				return array( false, false );
			}

			// Return integer timestamps in an array
			return array_map( 'intval', $timestamps );
		}

		// HANDLES THE CRUD ACTIONS FOR THE COMMENTS
		public static function ajax_crud_entry() {
			self::ajax_current_user_can_edit_liveblog();
			self::ajax_check_nonce();

			$args = array();

			$crud_action = isset( $_POST['crud_action'] ) ? sanitize_text_field( wp_unslash( $_POST['crud_action'] ) ) : 0; // input var ok

			if ( ! self::is_valid_crud_action( $crud_action ) ) {
				// translators: 1: crud action
				self::send_user_error( sprintf( __( 'Invalid entry crud_action: %s', 'liveblog' ), $crud_action ) );
			}

			$args['post_id']         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0; // input var ok
			$args['content']         = isset( $_POST['content'] ) ? sanitize_text_field( wp_unslash( $_POST['content'] ) ) : ''; // input var ok
			$args['entry_id']        = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0; // input var ok
			$args['author_id']       = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : false; // input var ok
			$args['contributor_ids'] = isset( $_POST['contributor_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['contributor_ids'] ) ) : false; // input var ok

			$entry = self::do_crud_entry( $crud_action, $args );

			if ( is_wp_error( $entry ) ) {
				self::send_server_error( $entry->get_error_message() );
			}

			if ( WPCOM_Liveblog_Socketio_Loader::is_enabled() ) {
				WPCOM_Liveblog_Socketio::emit(
					'liveblog entry',
					$entry->for_json()
				);
			} else {
				// Do not send latest_timestamp. If we send it the client won't get
				// older entries. Since we send only the new one, we don't know if there
				// weren't any entries in between.
				self::json_return(
					array(
						'entries'          => array( $entry->for_json() ),
						'latest_timestamp' => null,
					),
					array( 'cache' => false )
				);
			}
		}

		/**
		 * Perform a specific CRUD action on an entry for a post
		 *
		 * @param string $crud_action Allowed actions are insert|update|delete|delete_key
		 * @param array $args An array of data to be passed to the crud method
		 *
		 * @return mixed The result of the crud method
		 */
		public static function do_crud_entry( $crud_action, $args ) {

			$args['user'] = wp_get_current_user();
			$entry        = call_user_func( array( 'WPCOM_Liveblog_Entry', $crud_action ), $args );
			if ( ! is_wp_error( $entry ) ) {
				// Do not send latest_timestamp. If we send it the client won't get
				// older entries. Since we send only the new one, we don't know if there
				// weren't any entries in between.
				$entry = array(
					'entries'          => array( $entry->for_json() ),
					'latest_timestamp' => null,
				);
			}

			return $entry;
		}

		/**
		 * Fetches the Liveblog entry with the ID given in the $_GET superglobal, and returns it via JSON.
		 */
		public static function ajax_single_entry() {

			// The URL is of the form "entry/entry_id".
			$fragments = explode( '/', get_query_var( self::URL_ENDPOINT ) );

			$entry_id = isset( $fragments[1] ) ? $fragments[1] : '';

			$result_for_json = self::get_single_entry( $entry_id );

			self::json_return( $result_for_json );
		}

		/**
		 * Get a single Liveblog entry for a post by entry ID
		 *
		 * @param int $entry_id The ID of the entry
		 *
		 * @return array An array of entry data
		 */
		public static function get_single_entry( $entry_id ) {

			$entries            = array();
			$previous_timestamp = 0;
			$next_timestamp     = 0;

			if ( empty( self::$entry_query ) ) {
				self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );
			}

			// Why not just get the single entry rather than all?
			$all_entries = array_values( self::$entry_query->get_all() );

			foreach ( $all_entries as $key => $entry ) {
				if ( $entry_id !== $entry->get_id() ) {
					continue;
				}

				$entries = array( $entry );

				if ( isset( $all_entries[ $key - 1 ] ) ) {
					$previous_entry = $all_entries[ $key - 1 ];
					$next_timestamp = $previous_entry->get_timestamp();
				}

				if ( isset( $all_entries[ $key + 1 ] ) ) {
					$next_entry         = $all_entries[ $key + 1 ];
					$previous_timestamp = $next_entry->get_timestamp();
				}

				break;
			}

			$entries_for_json = array();

			// Set up an array containing the JSON data for Liveblog entry.
			foreach ( $entries as $entry ) {
				$entries_for_json[] = $entry->for_json();
			}

			// Set up the data to be returned via JSON.
			$result_for_json = array(
				'entries' => $entries_for_json,
			);

			if ( ! empty( $entries_for_json ) ) {
				// Entries found
				$result_for_json['index']             = (int) filter_input( INPUT_GET, 'index' );
				$result_for_json['nextTimestamp']     = $next_timestamp;
				$result_for_json['previousTimestamp'] = $previous_timestamp;

				do_action( 'liveblog_entry_request', $result_for_json );
				self::$do_not_cache_response = true;
			} else {
				// No entries
				do_action( 'liveblog_entry_request_empty' );
			}

			return $result_for_json;
		}

		/**
		 * Fetches all Liveblog entries that are to be lazyloaded, and returns them via JSON.
		 */
		public static function ajax_lazyload_entries() {

			// The URL is of the form "lazyload/optional_max_timestamp/optional_min_timestamp".
			$fragments = explode( '/', get_query_var( self::URL_ENDPOINT ) );

			// Get all Liveblog entries that are to be lazyloaded.
			$result_for_json = self::get_lazyload_entries(
				isset( $fragments[1] ) ? (int) $fragments[1] : 0,
				isset( $fragments[2] ) ? (int) $fragments[2] : 0
			);

			self::json_return( $result_for_json );
		}

		/**
		 * Get all Liveblog entries that are to be lazyloaded.
		 *
		 * @param int $max_timestamp Maximum timestamp for the Liveblog entries.
		 * @param int $min_timestamp Minimum timestamp for the Liveblog entries.
		 *
		 * @return array An array of json encoded results
		 */
		public static function get_lazyload_entries( $max_timestamp, $min_timestamp ) {

			if ( empty( self::$entry_query ) ) {
				self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );
			}

			// Get all Liveblog entries that are to be lazyloaded.
			$entries = self::$entry_query->get_for_lazyloading( $max_timestamp, $min_timestamp );

			$entries_for_json = array();

			if ( ! empty( $entries ) ) {
				$entries = array_slice( $entries, 0, WPCOM_Liveblog_Lazyloader::get_number_of_entries() );

				// Populate an array containing the JSON data for all Liveblog entries.
				foreach ( $entries as $entry ) {
					$entries_for_json[] = $entry->for_json();
				}
			}

			$result = array(
				'entries' => $entries_for_json,
				'index'   => (int) filter_input( INPUT_GET, 'index' ),
			);

			if ( ! empty( $entries_for_json ) ) {
				do_action( 'liveblog_entry_request', $result );
				self::$do_not_cache_response = true;
			} else {
				do_action( 'liveblog_entry_request_empty' );
			}

			return $result;
		}

		/**
		 * Get single entry
		 *
		 * @param int $id entry id
		 * @return array An array of json encoded results
		 */
		public static function get_single_liveblog_entry( $id = false ) {
			if ( empty( self::$entry_query ) ) {
				self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );
			}

			return self::$entry_query->get_by_id( $id );
		}

		/**
		 * Get all entries for specific page
		 *
		 * @param int $page Requested Page.
		 * @param string $last_know_entry id-timestamp of the last rendered entry.
		 * @param int $id entry id
		 * @return array An array of json encoded results
		 */
		public static function get_entries_paged( $page, $last_known_entry = false, $id = false ) {

			if ( empty( self::$entry_query ) ) {
				self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::KEY );
			}

			$per_page = WPCOM_Liveblog_Lazyloader::get_number_of_entries();

			$entries = self::$entry_query->get_all_entries_asc();
			$entries = self::flatten_entries( $entries );

			if ( $last_known_entry ) {
				$last_known_entry = explode( '-', $last_known_entry );
				if ( isset( $last_known_entry[0], $last_known_entry[1] ) ) {
					$last_entry_id = (int) $last_known_entry[0];
					$index         = array_search( $last_entry_id, array_keys( $entries ), true );
					$entries       = array_slice( $entries, $index, null, true );
				}
			}

			$pages = ceil( count( $entries ) / $per_page );

			//If no page is passed but entry id is, we search for the correct page.
			if ( false === $page && false !== $id ) {
				$index = array_search( (int) $id, array_keys( $entries ), true );
				$index++;
				$page = ceil( $index / $per_page );
			}

			$offset  = $per_page * ( $page - 1 );
			$entries = array_slice( $entries, $offset, $per_page );
			$entries = self::entries_for_json( $entries );

			$result = array(
				'entries' => $entries,
				'page'    => (int) $page,
				'pages'   => (int) $pages,
			);

			if ( ! empty( $entries ) ) {
				do_action( 'liveblog_entry_request', $result );
				self::$do_not_cache_response = true;
			} else {
				do_action( 'liveblog_entry_request_empty' );
			}

			return $result;
		}

		/**
		 * Convert array of entries to their json response
		 * @param type $entries
		 * @return array
		 */
		public static function entries_for_json( $entries ) {
			$entries_for_json = array();
			foreach ( $entries as $entry ) {
				$entries_for_json[] = $entry->for_json();
			}
			return $entries_for_json;
		}


		/**
		 * Flattens Entries by running updates and deletes to get actual
		 * list of entries
		 *
		 * @param array $entires
		 * @return array
		 */
		public static function flatten_entries( $entries ) {
			if ( empty( $entries ) || ! is_array( $entries ) ) {
				return array();
			}
			$flatten = array();
			foreach ( $entries as $entry ) {
				$type = $entry->get_type();
				$id   = $entry->get_id();

				if ( ! empty( $entry->replaces ) ) {
					$id = $entry->replaces;
				}

				switch ( $type ) {
					case 'new':
						$flatten[ $id ] = $entry;
						break;
					case 'update':
						$flatten[ $id ] = $entry;
						break;
					case 'delete':
						unset( $flatten[ $id ] );
						break;
				}
			}

			return array_reverse( $flatten, true );
		}

		public static function ajax_preview_entry() {
			$entry_content = isset( $_REQUEST['entry_content'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['entry_content'] ) ) : ''; // input var ok
			$entry_content = self::format_preview_entry( $entry_content );

			self::json_return( $entry_content );
		}

		/**
		 * Format the passed in content and give it back in an array
		 *
		 * @param string $entry_content The entry content to be previewed
		 *
		 * @return array The entry content wrapped in HTML elements
		 */
		public static function format_preview_entry( $entry_content ) {
			$entry_content = stripslashes( wp_filter_post_kses( $entry_content ) );
			$entry_content = apply_filters( 'liveblog_before_preview_entry', array( 'content' => $entry_content ) );
			$entry_content = $entry_content['content'];
			$entry_content = WPCOM_Liveblog_Entry::render_content( $entry_content );

			do_action( 'liveblog_preview_entry', $entry_content );

			return array( 'html' => $entry_content );
		}

		public static function ajax_unknown() {
			self::send_user_error( __( 'Unknown liveblog action', 'liveblog' ) );
		}


		/** Comment Methods *******************************************************/

		/**
		 * Add a liveblog class to each comment, so they can be styled
		 *
		 * @param array $classes
		 * @return string
		 */
		public static function add_comment_class( $classes, $class, $comment_id ) {
			if ( self::KEY === get_comment_type( $comment_id ) ) {
				$classes[] = 'liveblog-entry';
				$classes[] = 'liveblog-entry-class-' . $comment_id;
			}
			return $classes;
		}

		public static function admin_enqueue_scripts( $hook_suffix ) {
			global $post;

			// Enqueue admin scripts only if adding or editing a supported post type.
			if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && post_type_supports( get_post_type(), self::KEY ) ) {

				$endpoint_url = '';
				$use_rest_api = 0;

				if ( self::use_rest_api() ) {
					$endpoint_url = WPCOM_Liveblog_Rest_Api::build_endpoint_base() . $post->ID . '/post_state';
					$use_rest_api = 1;
				}

				wp_enqueue_style( self::KEY, plugins_url( 'assets/dashboard/app.css', __FILE__ ), array(), self::VERSION );
				wp_enqueue_script( 'liveblog-admin', plugins_url( 'assets/dashboard/app.js', __FILE__ ), array(), self::VERSION, false );
				wp_localize_script(
					'liveblog-admin',
					'liveblog_admin_settings',
					array(
						'nonce_key'                    => self::NONCE_KEY,
						'nonce'                        => wp_create_nonce( self::NONCE_ACTION ),
						'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
						'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
						'use_rest_api'                 => $use_rest_api,
						'endpoint_url'                 => $endpoint_url,
					)
				);
			}
		}

		/**
		 * Enqueue the necessary CSS and JS that liveblog needs to function.
		 *
		 * @return If not a liveblog post
		 */
		public static function enqueue_scripts() {

			if ( ! self::is_viewing_liveblog_post() ) {
				return;
			}

			wp_enqueue_style( self::KEY, plugins_url( 'assets/app.css', __FILE__ ) );
			wp_enqueue_style( self::KEY . '_theme', plugins_url( 'assets/theme.css', __FILE__ ) );

			// Load Client Scripts
			wp_enqueue_script( self::KEY, plugins_url( 'assets/app.js', __FILE__ ), array(), self::VERSION, true );

			if ( self::is_liveblog_editable() ) {
				self::add_default_plupload_settings();
			}

			self::$latest_timestamp = self::$entry_query->get_latest_timestamp();

			wp_localize_script(
				self::KEY,
				'liveblog_settings',
				apply_filters(
					'liveblog_settings',
					array(
						'permalink'                    => get_permalink(),
						'plugin_dir'                   => plugin_dir_url( __FILE__ ),
						'post_id'                      => get_the_ID(),
						'state'                        => self::get_liveblog_state(),
						'is_liveblog_editable'         => self::is_liveblog_editable(),
						'current_user'                 => self::get_current_user(),
						'socketio_enabled'             => WPCOM_Liveblog_Socketio_Loader::is_enabled(),

						'key'                          => self::KEY,
						'nonce_key'                    => self::NONCE_KEY,
						'nonce'                        => wp_create_nonce( self::NONCE_ACTION ),

						'image_nonce'                  => wp_create_nonce( 'media-form' ),
						'default_image_size'           => apply_filters( 'liveblog_default_image_size', self::DEFAULT_IMAGE_SIZE ),

						'latest_entry_timestamp'       => self::$latest_timestamp,
						'latest_entry_id'              => self::$entry_query->get_latest_id(),
						'timestamp'                    => time(),
						'utc_offset'                   => get_option( 'gmt_offset' ) * 60, // in minutes
						'date_format'                  => get_option( 'date_format' ),
						'time_format'                  => get_option( 'time_format' ),
						'entries_per_page'             => WPCOM_Liveblog_Lazyloader::get_number_of_entries(),

						'refresh_interval'             => self::get_refresh_interval(),
						'focus_refresh_interval'       => self::FOCUS_REFRESH_INTERVAL,
						'max_consecutive_retries'      => self::MAX_CONSECUTIVE_RETRIES,
						'delay_threshold'              => self::DELAY_THRESHOLD,
						'delay_multiplier'             => self::DELAY_MULTIPLIER,
						'fade_out_duration'            => self::FADE_OUT_DURATION,

						'use_rest_api'                 => intval( self::use_rest_api() ),
						'endpoint_url'                 => self::get_entries_endpoint_url(),
						'cross_domain'                 => false,

						'features'                     => WPCOM_Liveblog_Entry_Extend::get_enabled_features(),
						'autocomplete'                 => WPCOM_Liveblog_Entry_Extend::get_autocomplete(),
						'command_class'                => apply_filters( 'liveblog_command_class', WPCOM_Liveblog_Entry_Extend_Feature_Commands::$class_prefix ),

						// i18n
						'delete_confirmation'          => __( 'Do you really want to delete this entry? There is no way back.', 'liveblog' ),
						'delete_key_confirm'           => __( 'Do you want to delete this key entry?', 'liveblog' ),
						'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
						'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
						'new_update'                   => __( 'Liveblog: {number} new update', 'liveblog' ),
						'new_updates'                  => __( 'Liveblog: {number} new updates', 'liveblog' ),
						'create_link_prompt'           => __( 'Provide URL for link:', 'liveblog' ),

						// Classes
						'class_term_prefix'            => __( 'term-', 'liveblog' ),
						'class_alert'                  => __( 'type-alert', 'liveblog' ),
						'class_key'                    => __( 'type-key', 'liveblog' ),

						/**
						 * Filters the Author list debounce time, defaults to 500ms.
						 *
						 * @since 1.9.2
						 *
						 * @param int $time Author list debounce time.
						 */
						'author_list_debounce_time'    => apply_filters( 'liveblog_author_list_debounce_time', self::AUTHOR_LIST_DEBOUNCE_TIME ),
					)
				)
			);
			wp_localize_script(
				'liveblog-publisher',
				'liveblog_publisher_settings',
				array(
					'loading_preview'         => __( 'Loading preview…', 'liveblog' ),
					'new_entry_tab_label'     => __( 'New Entry', 'liveblog' ),
					'new_entry_submit_label'  => __( 'Publish Update', 'liveblog' ),
					'edit_entry_tab_label'    => __( 'Edit Entry', 'liveblog' ),
					'edit_entry_submit_label' => __( 'Update', 'liveblog' ),
				)
			);
		}

		/**
		 * Sets up some default Plupload settings so we can upload meda theme-side
		 *
		 * @global type $wp_scripts
		 */
		private static function add_default_plupload_settings() {
			global $wp_scripts;

			$defaults = array(
				'runtimes'            => 'html5,silverlight,flash,html4',
				'file_data_name'      => 'async-upload',
				'multiple_queues'     => true,
				'max_file_size'       => wp_max_upload_size() . 'b',
				'url'                 => admin_url( 'admin-ajax.php', 'relative' ),
				'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
				'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
				'filters'             => array(
					array(
						'title'      => __( 'Allowed Files', 'liveblog' ),
						'extensions' => '*',
					),
				),
				'multipart'           => true,
				'urlstream_upload'    => true,
				'multipart_params'    => array(
					'action'   => 'upload-attachment',
					'_wpnonce' => wp_create_nonce( 'media-form' ),
				),
			);

			$settings = array(
				'defaults' => $defaults,
				'browser'  => array(
					'mobile'    => ( function_exists( 'jetpack_is_mobile' ) ? jetpack_is_mobile() : wp_is_mobile() ), // phpcs:ignore WordPressVIPMinimum.VIP.RestrictedFunctions.wp_is_mobile_wp_is_mobile
					'supported' => _device_can_upload(),
				),
			);

			$script = 'var _wpPluploadSettings = ' . wp_json_encode( $settings ) . ';';
			$data   = $wp_scripts->get_data( 'wp-plupload', 'data' );

			if ( ! empty( $data ) ) {
				$script = "$data\n$script";
			}

			$wp_scripts->add_data( 'wp-plupload', 'data', $script );
		}

		/**
		 * Get the URL of a specific liveblog entry.
		 *
		 * @return string
		 */
		private static function get_entries_endpoint_url() {
			if ( self::use_rest_api() ) {
				$url = WPCOM_Liveblog_Rest_Api::build_endpoint_base() . self::$post_id . '/';
			} else {
				$post_permalink = get_permalink( self::$post_id );
				if ( false !== strpos( $post_permalink, '?p=' ) ) {
					$url = add_query_arg( self::URL_ENDPOINT, '', $post_permalink ) . '='; // returns something like ?p=1&liveblog=
				} else {
					$url = trailingslashit( trailingslashit( $post_permalink ) . self::URL_ENDPOINT ); // returns something like /2012/01/01/post/liveblog/
				}
			}

			return apply_filters( 'liveblog_endpoint_url', $url, self::$post_id );

		}

		/** Display Methods *******************************************************/

		/**
		 * Filter the_content and add the liveblog theme-side UI above the normal
		 * content area.
		 *
		 * @param string $content
		 * @return string
		 */
		public static function add_liveblog_to_content( $content ) {

			// We don't want to add the liveblog to other loops
			// on the same page
			if ( ! self::is_viewing_liveblog_post() ) {
				return $content;
			}

			$liveblog_output = '<div id="wpcom-liveblog-container" class="' . self::$post_id . '"></div>';

			$liveblog_output = apply_filters( 'liveblog_add_to_content', $liveblog_output, $content, self::$post_id );

			return $content . wp_kses_post( $liveblog_output );
		}

		/**
		 * Return the posting area for the end-user to liveblog from
		 *
		 * @return string
		 */
		private static function get_editor_output() {
			if ( ! self::is_liveblog_editable() ) {
				return;
			}

			return self::get_template_part( 'liveblog-form.php' );
		}

		/**
		 * Get all the liveblog entries for this post
		 */
		private static function get_all_entry_output() {

			// Get liveblog entries.
			$args  = array();
			$state = self::get_liveblog_state();

			if ( 'archive' === $state ) {
				$args['order'] = 'ASC';
			}

			$args                  = apply_filters( 'liveblog_display_archive_query_args', $args, $state );
			$entries               = (array) self::$entry_query->get_all( $args );
			$show_archived_message = 'archive' === $state && self::current_user_can_edit_liveblog();

			// Get the template part
			return self::get_template_part( 'liveblog-loop.php', compact( 'entries', 'show_archived_message' ) );
		}

		/**
		 * Get the template part in an output buffer and return it
		 *
		 * @param string $template_name
		 * @param array $template_variables
		 */
		public static function get_template_part( $template_name, $template_variables = array() ) {
			ob_start();
			extract( $template_variables );
			$theme_template       = get_template_directory() . '/liveblog/' . ltrim( $template_name, '/' );
			$child_theme_template = get_stylesheet_directory() . '/liveblog/' . ltrim( $template_name, '/' );
			if ( file_exists( $child_theme_template ) ) {
				include $child_theme_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
			} elseif ( file_exists( $theme_template ) ) {
				include $theme_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
			} elseif ( self::$custom_template_path && file_exists( self::$custom_template_path . '/' . $template_name ) ) {
				include self::$custom_template_path . '/' . $template_name; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
			} else {
				include dirname( __FILE__ ) . '/templates/' . $template_name;
			}
			return ob_get_clean();
		}

		/** Admin Methods *********************************************************/

		/**
		 * Register the metabox with the supporting post-type
		 *
		 * @param string $post_type
		 */
		public static function add_meta_box( $post_type ) {

			// Bail if not supported
			if ( ! post_type_supports( $post_type, self::KEY ) ) {
				return;
			}

			add_meta_box( self::KEY, __( 'Liveblog', 'liveblog' ), array( __CLASS__, 'display_meta_box' ) );
		}

		public static function image_embed_handler( $matches, $attr, $url, $rawattr ) {
			$embed = sprintf( '<img src="%s" alt="" />', esc_url( $url ) );
			return apply_filters( 'embed_liveblog_image', $embed, $matches, $attr, $url, $rawattr );
		}

		/**
		 * Output the metabox
		 *
		 * @param WP_Post $post
		 */
		public static function display_meta_box( $post ) {

			// Get and display the metabox content
			echo self::get_meta_box( $post ); // @codingStandardsIgnoreLine

		}

		/**
		 * Get the metabox for outputting
		 *
		 * @param WP_Post $post
		 *
		 * @return string The metabox markup
		 */
		public static function get_meta_box( $post ) {
			$current_state = self::get_liveblog_state( $post->ID );
			$buttons       = array(
				'enable'  => array(
					'value'       => 'enable',
					'text'        => __( 'Enable', 'liveblog' ),
					'description' => __( 'Enables liveblog on this post. Posting tools are enabled for editors, visitors get the latest updates.', 'liveblog' ),
					// translators: 1: post url
					'active-text' => sprintf( __( 'There is an <strong>enabled</strong> liveblog on this post. <a href="%s">Visit the liveblog &rarr;</a>', 'liveblog' ), get_permalink( $post ) ),
					'primary'     => true,
					'disabled'    => false,
				),
				'archive' => array(
					'value'       => 'archive',
					'text'        => __( 'Archive', 'liveblog' ),
					'description' => __( 'Archives the liveblog on this post. Visitors still see the liveblog entries, but posting tools are hidden.', 'liveblog' ),
					// translators: 1: archive url
					'active-text' => sprintf( __( 'There is an <strong>archived</strong> liveblog on this post. <a href="%s">Visit the liveblog archive &rarr;</a>', 'liveblog' ), get_permalink( $post ) ),
					'primary'     => false,
					'disabled'    => false,
				),
			);
			if ( $current_state ) {
				$active_text                           = $buttons[ $current_state ]['active-text'];
				$buttons[ $current_state ]['disabled'] = true;
			} else {
				$active_text                    = __( 'This is a normal WordPress post, without a liveblog.', 'liveblog' );
				$buttons['archive']['disabled'] = true;
			}
			$update_text  = __( 'Settings have been successfully updated.', 'liveblog' );
			$extra_fields = array();
			$extra_fields = apply_filters( 'liveblog_admin_add_settings', $extra_fields, $post->ID );

			return self::get_template_part( 'meta-box.php', compact( 'active_text', 'buttons', 'update_text', 'extra_fields' ) );
		}

		public static function admin_ajax_set_liveblog_state_for_post() {
			$post_id   = isset( $_REQUEST['post_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_id'] ) ) : 0; // input var ok
			$new_state = isset( $_REQUEST['state'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['state'] ) ) : ''; // input var ok

			self::ajax_current_user_can_edit_liveblog();
			self::ajax_check_nonce();

			$meta_box = self::admin_set_liveblog_state_for_post( $post_id, $new_state, $_REQUEST ); //input var ok

			if ( ! $meta_box ) {

				if ( wp_is_post_revision( $post_id ) ) {
					// translators: 1: post ID
					self::send_user_error( sprintf( __( 'The post is a revision: %s', 'liveblog' ), $post_id ) );
				}

				// translators: 1: post ID
				self::send_user_error( sprintf( __( 'Non-existing post ID: %s', 'liveblog' ), $post_id ) );

			}

			self::json_return( $meta_box );

		}

		/**
		 * Update the Liveblog state and return the metabox to be displayed
		 *
		 * @param int $post_id Post ID
		 * @param string $new_state The new state to give the Liveblog post. One of enable|archive|disable
		 *
		 * @return string The metabox markup
		 */
		public static function admin_set_liveblog_state_for_post( $post_id, $new_state, $request_vars ) {

			$post = get_post( $post_id );

			if ( empty( $post ) || wp_is_post_revision( $post_id ) ) {
				return false;
			}

			do_action( 'liveblog_admin_settings_update', $request_vars, $post_id );

			self::set_liveblog_state( $post_id, $new_state );

			return self::get_meta_box( $post );

		}

		/**
		 * set_liveblog_state
		 *
		 * Sets the status of the Liveblog.
		 * Integrates with the Auto Archive feature to check
		 * archive date and update where required. Means a liveblog
		 * can be auto archived and re-enabled extending the auto archive
		 * forward by the pre-determined amount of days from the re-enable
		 * date.
		 *
		 * @param $post_id
		 * @param $new_state
		 *
		 * @return bool
		 */
		public static function set_liveblog_state( $post_id, $new_state ) {

			// if the auto_archive feature is not disabled
			if ( null !== self::$auto_archive_days ) {
				//Get the Current State
				$current_state = get_post_meta( $post_id, self::KEY );

				//Instantiate a entry query object
				$query            = new WPCOM_Liveblog_Entry_Query( $post_id, self::KEY );
				$latest_timestamp = ( null !== $query->get_latest_timestamp() ) ? $query->get_latest_timestamp() : strtotime( date( 'Y-m-d H:i:s' ) );

				//set autoarchive date based on latest timestamp
				$autoarchive_date = strtotime( ' + ' . self::$auto_archive_days . ' days', $latest_timestamp );

				// if the old state is archive and the new state is active or there is no current state and the new state is enable
				if ( 0 === count( $current_state ) && 'enable' === $new_state || 'archive' === $current_state[0] && 'enable' === $new_state ) {

					// Then the live blog is being setup for the first time or is being reactivated.
					update_post_meta( $post_id, self::$auto_archive_expiry_key, $autoarchive_date );

				}
			}

			// Let's update the post_meta state as per usual.
			if ( in_array( $new_state, array( 'enable', 'archive' ), true ) ) {
				update_post_meta( $post_id, self::KEY, $new_state );
				do_action( "liveblog_{$new_state}_post", $post_id );

			} elseif ( 'disable' === $new_state ) {
				delete_post_meta( $post_id, self::KEY );

				//Lets remove the autoarchive meta data too.
				delete_post_meta( $post_id, self::$auto_archive_expiry_key );

				do_action( 'liveblog_disable_post', $post_id );
			} else {
				return false;
			}
		}

		/**
		 * Hooks in and updates the autoarchive date if not disabled.
		 * Means that any update moving forward pushes the auto archive date
		 * in turn.
		 *
		 * @param  array $args Passed in arguments
		 * @return array $args Post Filtered Arguments.
		 */
		public static function update_autoarchive_expiry( $args ) {
			if ( null !== self::$auto_archive_days ) {
				//Instantiate a entry query object
				$query = new WPCOM_Liveblog_Entry_Query( $args['post_id'], self::KEY );

				//set autoarchive date based on latest timestamp
				$autoarchive_date = strtotime( ' + ' . self::$auto_archive_days . ' days', $query->get_latest_timestamp() );

				//Update the Post Meta the extend the AutoArchive Date.
				update_post_meta( $args['post_id'], self::$auto_archive_expiry_key, $autoarchive_date );
			}

			return $args;
		}

		/**
		 * Indicate in the post list that a post is a liveblog
		 *
		 * @param array $post_states
		 * @param mixed $post
		 * @return array
		 * @filter display_post_states
		 */
		public static function add_display_post_state( $post_states, $post = null ) {
			if ( is_null( $post ) ) {
				$post = get_post();
			}
			if ( self::is_liveblog_post( $post->ID ) ) {
				$liveblog_state = self::get_liveblog_state( $post->ID );
				if ( 'enable' === $liveblog_state ) {
					$post_states[] = __( 'Liveblog', 'liveblog' );
				} elseif ( 'archive' === $liveblog_state ) {
					$post_states[] = __( 'Liveblog (archived)', 'liveblog' );
				}
			}
			return $post_states;
		}

		/**
		 * Register the query_var for filtering posts by liveblog state
		 *
		 * @param array $query_vars
		 * @return array
		 * @filter query_vars
		 */
		public static function add_query_var_for_post_filtering( $query_vars ) {
			$query_vars[] = 'liveblog_state';
			return $query_vars;
		}

		/**
		 * Render the liveblog state select to filter posts in the post table
		 *
		 * @action restrict_manage_posts
		 */
		public static function add_post_filtering_dropdown_to_manage_posts() {
			$current_screen = get_current_screen();
			if ( ! post_type_supports( $current_screen->post_type, self::KEY ) ) {
				return;
			}

			$options = array(
				''        => __( 'Filter liveblogs', 'liveblog' ),
				'any'     => __( 'Any liveblogs', 'liveblog' ),
				'enable'  => __( 'Enabled liveblogs', 'liveblog' ),
				'archive' => __( 'Archived liveblogs', 'liveblog' ),
				'none'    => __( 'No liveblogs', 'liveblog' ),
			);
			echo self::get_template_part( 'restrict-manage-posts.php', compact( 'options' ) ); // @codingStandardsIgnoreLine
		}

		/**
		 * Translate the liveblog_state query_var into a meta_query
		 *
		 * @param WP_Query $query
		 */
		public static function handle_query_vars_for_post_filtering( $query ) {
			if ( ! $query->is_main_query() ) {
				return;
			}
			$state = $query->get( 'liveblog_state' );
			if ( 'any' === $state ) {
				$new_meta_query_clause = array(
					'key'     => self::KEY,
					'compare' => 'EXISTS',
				);
			} elseif ( 'none' === $state ) {
				$new_meta_query_clause = array(
					'key'     => self::KEY,
					'compare' => 'NOT EXISTS',
				);
			} elseif ( in_array( $state, array( 'enable', 'archive' ), true ) ) {
				$new_meta_query_clause = array(
					'key'   => self::KEY,
					'value' => $state,
				);
			}

			if ( isset( $new_meta_query_clause ) ) {
				$meta_query = $query->get( 'meta_query' );
				if ( empty( $meta_query ) ) {
					$meta_query = array();
				}
				array_push( $meta_query, $new_meta_query_clause );
				$query->set( 'meta_query', $meta_query );
			}
		}

		/** Error Methods *********************************************************/

		/**
		 * Can the current user edit liveblog data (non-ajax)
		 *
		 * @return bool
		 */
		public static function current_user_can_edit_liveblog() {
			$retval = current_user_can( apply_filters( 'liveblog_edit_cap', self::EDIT_CAP ) );
			return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
		}

		public static function is_liveblog_editable() {
			return self::current_user_can_edit_liveblog() && 'enable' === self::get_liveblog_state();
		}

		/**
		 * Can the current user edit liveblog data (ajax)
		 *
		 * Sends an error if not
		 */
		public static function ajax_current_user_can_edit_liveblog() {
			if ( ! self::current_user_can_edit_liveblog() ) {
				self::send_forbidden_error( __( "Cheatin', uh?", 'liveblog' ) );
			}
		}

		/**
		 * Check for valid intention, and send an error if there is none
		 *
		 * @param string $action
		 */
		public static function ajax_check_nonce( $action = self::NONCE_ACTION ) {
			if ( ! isset( $_REQUEST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_KEY ] ) ), $action ) ) { // input var ok
				self::send_forbidden_error( __( 'Sorry, we could not authenticate you.', 'liveblog' ) );
			}
		}

		/** Feedback **************************************************************/

		/**
		 * Send an error message
		 * @param type $message
		 */
		private static function send_server_error( $message ) {
			self::status_header_with_message( 500, $message );
			exit();
		}

		private static function send_user_error( $message ) {
			self::status_header_with_message( 406, $message );
			exit();
		}

		private static function send_forbidden_error( $message ) {
			self::status_header_with_message( 403, $message );
			exit();
		}

		/**
		 * Encode some data and echo it (possibly without cached headers)
		 *
		 * @param array $data
		 */
		private static function json_return( $data, $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					// Set false for nocache; set int for Cache-control+max-age
					'cache' => self::RESPONSE_CACHE_MAX_AGE,
				)
			);
			$args = apply_filters( 'liveblog_json_return_args', $args, $data );

			// Send cache headers, where appropriate
			if ( false === $args['cache'] ) {
				nocache_headers();
			} elseif ( is_numeric( $args['cache'] ) ) {
				header( sprintf( 'Cache-Control: max-age=%d', $args['cache'] ) );
			}

			header( 'Content-Type: application/json' );
			self::prevent_caching_if_needed();
			echo wp_json_encode( $data );
			exit();
		}

		/**
		 * Modify the header and description in the global array
		 *
		 * @global array $wp_header_to_desc
		 * @param int $status
		 * @param string $message
		 */
		private static function status_header_with_message( $status, $message ) {
			global $wp_header_to_desc;

			$status                       = absint( $status );
			$official_message             = isset( $wp_header_to_desc[ $status ] ) ? $wp_header_to_desc[ $status ] : '';
			$wp_header_to_desc[ $status ] = self::sanitize_http_header( $message ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited

			status_header( $status );

			$wp_header_to_desc[ $status ] = $official_message; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		}

		/**
		 * Removes newlines from headers
		 *
		 * The only forbidden value in a header is a newline. PHP has a safe
		 * guard against header splitting, but it doesn't set the header at all.
		 */
		public static function sanitize_http_header( $text ) {
			return str_replace( array( "\n", "\r", chr( 0 ) ), '', $text );
		}

		/**
		 * Hide meta key from being edited from users
		 * @param  Boolean $protected
		 * @param  String $meta_key
		 * @return Boolean
		 */
		public static function protect_liveblog_meta_key( $protected, $meta_key ) {
			if ( self::KEY === $meta_key ) {
				return true;
			}

			return $protected;
		}

		/**
		 * Tells browsers to not cache the response if $do_not_cache_response is true
		 */
		public static function prevent_caching_if_needed() {
			if ( self::$do_not_cache_response ) {
				nocache_headers();
			} else if ( self::$cache_control_max_age ) {
				header( 'Cache-control: max-age=' . self::$cache_control_max_age );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + self::$cache_control_max_age ) );
			}
		}

		/**
		 * Checks to see if the current WordPress version has REST API support
		 *
		 * @return bool true if supported, false otherwise
		 */
		public static function can_use_rest_api() {
			global $wp_version;
			return version_compare( $wp_version, self::MIN_WP_REST_API_VERSION, '>=' );
		}

		/**
		 * Checks if use_rest_api is on and the WordPress version supports it
		 */
		public static function use_rest_api() {
			return ( self::USE_REST_API && self::can_use_rest_api() );
		}

		/**
		 * Check for allowed crud action
		 *
		 * @param String $action The CRUD action to check
		 * @return bool true if $action is one of insert|update|delete|delete_key. false otherwise
		 */
		public static function is_valid_crud_action( $action ) {
			return in_array( $action, array( 'insert', 'update', 'delete', 'delete_key' ), true );
		}


		/** Plupload Helpers ******************************************************/

		/**
		 * Convert hours to bytes
		 *
		 * @param unknown_type $size
		 * @return unknown
		 */
		private static function convert_hr_to_bytes( $size ) {
			$size  = strtolower( $size );
			$bytes = (int) $size;

			if ( strpos( $size, 'k' ) !== false ) {
				$bytes = intval( $size ) * 1024;
			} elseif ( strpos( $size, 'm' ) !== false ) {
				$bytes = intval( $size ) * 1024 * 1024;
			} elseif ( strpos( $size, 'g' ) !== false ) {
				$bytes = intval( $size ) * 1024 * 1024 * 1024;
			}

			return $bytes;
		}

		/**
		 * Convert bytes to hour
		 *
		 * @param string $bytes
		 * @return string
		 */
		private static function convert_bytes_to_hr( $bytes ) {
			$units = array(
				0 => 'B',
				1 => 'kB',
				2 => 'MB',
				3 => 'GB',
			);
			$log   = log( $bytes, 1024 );
			$power = (int) $log;
			$size  = pow( 1024, $log - $power );

			return $size . $units[ $power ];
		}

		/**
		 * Get the maximum upload file size
		 *
		 * @see wp_max_upload_size()
		 * @return string
		 */
		private static function max_upload_size() {
			$u_bytes = self::convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
			$p_bytes = self::convert_hr_to_bytes( ini_get( 'post_max_size' ) );
			$bytes   = apply_filters( 'upload_size_limit', min( $u_bytes, $p_bytes ), $u_bytes, $p_bytes );

			return $bytes;
		}

		private static function is_wp_too_old() {
			global $wp_version;
			// if WordPress is loaded in a function the version variables aren't globalized
			// see: http://core.trac.wordpress.org/ticket/17749#comment:40
			if ( ! isset( $wp_version ) || ! $wp_version ) {
				return false;
			}
			return version_compare( $wp_version, self::MIN_WP_VERSION, '<' );
		}

		/**
		 * Returns refresh interval after filters have been run
		 *
		 * @return int
		 */
		public static function get_refresh_interval() {
			$refresh_interval = WP_DEBUG ? self::DEBUG_REFRESH_INTERVAL : self::REFRESH_INTERVAL;
			$refresh_interval = apply_filters( 'liveblog_refresh_interval', $refresh_interval );
			$refresh_interval = apply_filters( 'liveblog_post_' . self::$post_id . '_refresh_interval', $refresh_interval );
			return $refresh_interval;
		}

		/**
		 * Generates metadata for a single liveblog
		 *
		 * @param  array   $metadata Metadata.
		 * @param  WP_Post $post     Current Post.
		 * @return array             Updated Meta
		 */
		public static function get_liveblog_metadata( $metadata, $post ) {

			// If we are not viewing a liveblog post then exit the filter.
			if ( self::is_liveblog_post( $post->ID ) === false ) {
				return $metadata;
			}

			$request = self::get_request_data();

			$entries = self::get_entries_paged( $request->page, $request->last );

			$blog_updates = [];

			if ( ! isset( $entries['entries'] ) || ! is_array( $entries['entries'] ) ) {
				return $metadata;
			}

			foreach ( $entries['entries'] as $entry ) {
				$blog_item = [
					'@type'            => 'BlogPosting',
					'headline'         => WPCOM_Liveblog_Entry::get_entry_title( $entry ),
					'url'              => $entry->share_link,
					'mainEntityOfPage' => $entry->share_link,
					'datePublished'    => date( 'c', $entry->entry_time ),
					'dateModified'     => date( 'c', $entry->timestamp ),
					'author'           => [
						'@type' => 'Person',
						'name'  => $entry->authors[0]['name'],
					],
					'articleBody'      => [
						'@type' => 'Text',
					],
				];

				if ( isset( $metadata['publisher'] ) ) {
					$blog_item['publisher'] = $metadata['publisher'];
				}

				$blog_updates[] = json_decode( wp_json_encode( $blog_item ) );
			}

			$metadata['@type']          = 'LiveBlogPosting';
			$metadata['liveBlogUpdate'] = $blog_updates;

			/**
			 * Filters the Liveblog metadata.
			 *
			 * Allows plugins and themes to adapt the metadata printed by the
			 * liveblog into the head, describing the liveblog and it's entries.
			 *
			 * @since 1.9
			 *
			 * @param array $metadata An array of metadata.
			 */
			$metadata = apply_filters( 'liveblog_metadata', $metadata, $post );

			return $metadata;
		}

		public static function print_liveblog_metadata() {

			// Bail if we are not viewing a liveblog.
			if ( self::is_liveblog_post( get_the_ID() ) === false ) {
				return;
			}

			$metadata = self::get_liveblog_metadata( array(), get_post() );
			if ( empty( $metadata ) ) {
				return;
			}

			?>
			<script type="application/ld+json"><?php echo wp_json_encode( $metadata ); ?></script>
			<?php

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

	}
	WPCOM_Liveblog::load();

	/** Plupload Helpers ******************************************************/
	if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
		require_once ABSPATH . 'wp-includes/load.php';
	}

	if ( ! function_exists( 'size_format' ) ) {
		require_once ABSPATH . 'wp-includes/functions.php';
	}

	if ( ! function_exists( 'wp_max_upload_size' ) ) {
		require_once ABSPATH . 'wp-includes/media.php';
	}

endif;
