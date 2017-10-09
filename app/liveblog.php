<?php

/**
 * Plugin Name: Liveblog
 * Plugin URI: http://wordpress.org/extend/plugins/liveblog/
 * Description: Blogging: at the speed of live.
 * Version:     1.6
 * Author:      WordPress.com VIP, Automattic
 * Author URI: http://vip.wordpress.com/
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
	const version          			= '1.6';
	const rewrites_version 			= 1;
	const min_wp_version   			= '3.5';
	const min_wp_rest_api_version 	= '4.4';
	const key              			= 'liveblog';
	const url_endpoint     			= 'liveblog';
	const edit_cap         			= 'publish_posts';
	const nonce_key               	= '_wpnonce'; // Using these strings since they're hard coded in the rest api. It'll still work fine for < 4.4
	const nonce_action            	= 'wp_rest';

	const refresh_interval        			= 10;   // how often should we refresh
	const debug_refresh_interval  			= 2;   // how often we refresh in development mode
	const focus_refresh_interval  			= 30;   // how often we refresh in when window not in focus
	const max_consecutive_retries 			= 100; // max number of failed tries before polling is disabled
	const human_time_diff_update_interval 	= 60; // how often we change the entry human timestamps: "a minute ago"
	const delay_threshold         			= 5;  // how many failed tries after which we should increase the refresh interval
	const delay_multiplier        			= 2; // by how much should we inscrease the refresh interval
	const fade_out_duration       			= 5; // how much time should take fading out the background of new entries
	const response_cache_max_age  			= DAY_IN_SECONDS; // `Cache-Control: max-age` value for cacheable JSON responses
	const use_rest_api            			= true; // Use the REST API if current version is at least min_wp_rest_api_version. Allows for easy disabling/enabling

	/** Variables *************************************************************/

	public static $post_id               	= null;
	private static $entry_query           	= null;
	private static $do_not_cache_response	= false;
	private static $custom_template_path  	= null;

	public static $is_rest_api_call       	= false;
	public static $auto_archive_days     	= null;
	public static $auto_archive_expiry_key  = 'liveblog_autoarchive_expiry_date';


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
		WPCOM_Liveblog_Entry_Instagram_oEmbed::load();

		if ( self::use_rest_api() ) {
			WPCOM_Liveblog_Rest_Api::load();
		}

		//Activate the WP CRON Hooks.
		WPCOM_Liveblog_Cron::load();
	}

	public static function add_custom_post_type_support( $query ) {
		if ( ! self::is_entries_ajax_request() )
			return;

		$post_types = array_filter( get_post_types(), array( __CLASS__, 'liveblog_post_type' ) );
		$query->set( 'post_type', $post_types );
	}

	public static function liveblog_post_type( $post_type ) {
		return post_type_supports( $post_type, self::key );
	}

	private static function add_old_wp_notice() {
		add_action( 'admin_notices', array( 'WPCOM_Liveblog', 'show_old_wp_notice' ) );
	}

	public static function show_old_wp_notice() {
		global $wp_version;
		$min_version = self::min_wp_version;
		echo self::get_template_part( 'old-wp-notice.php', compact( 'wp_version', 'min_version' ) );
	}

	/**
	 * Include the necessary files
	 */
	private static function includes() {
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-query.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-key-events.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-key-events-widget.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-hashtags.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-commands.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-emojis.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-extend-feature-authors.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-lazyloader.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-socketio-loader.php' );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-instagram-oembed.php' );

		if ( self::use_rest_api() ) {
			require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-rest-api.php' );
		}

		// Manually include ms.php theme-side in multisite environments because
		// we need its filesize and available space functions.
		if ( ! is_admin() && is_multisite() ) {
			require_once( ABSPATH . 'wp-admin/includes/ms.php' );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-wp-cli.php' );
		}

		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-cron.php' );
	}

	/**
	 * Hook actions in that run on every page-load
	 *
	 * @uses add_action()
	 */
	private static function add_actions() {
		add_action( 'init',                          array( __CLASS__, 'init'              ) );
		add_action( 'init',                          array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'permalink_structure_changed',   array( __CLASS__, 'add_rewrite_rules' ) );
		// flush the rewrite rules a lot later so that we don't interfere with other plugins using rewrite rules
		add_action( 'init',                          array( __CLASS__, 'flush_rewrite_rules' ), 1000 );
		add_action( 'wp_enqueue_scripts',            array( __CLASS__, 'enqueue_scripts'   ) );
		add_action( 'admin_enqueue_scripts',         array( __CLASS__, 'admin_enqueue_scripts'   ) );
		add_action( 'wp_ajax_set_liveblog_state_for_post', array( __CLASS__, 'admin_ajax_set_liveblog_state_for_post' ) );
		add_action( 'pre_get_posts',                 array( __CLASS__, 'add_custom_post_type_support' ) );
	}

	/**
	 * Hook filters in that run on every page-load
	 *
	 * @uses add_filter()
	 */
	private static function add_filters() {
		add_filter( 'template_redirect', array( __CLASS__, 'handle_request' ), 9 );
		add_filter( 'comment_class',     array( __CLASS__, 'add_comment_class' ), 10, 3 );
		add_filter( 'is_protected_meta', array( __CLASS__, 'protect_liveblog_meta_key'	 ), 10, 2 );

		// Add In the Filter hooks to Strip any Restricted Shortcodes before a new post or updating a post. Called from the WPCOM_Liveblog_Entry Class.
		add_filter( 'liveblog_before_insert_entry', array( 'WPCOM_Liveblog_Entry', 'handle_restricted_shortcodes' ), 10, 1 );
		add_filter( 'liveblog_before_update_entry', array( 'WPCOM_Liveblog_Entry', 'handle_restricted_shortcodes' ), 10, 1 );

		//We need to check the Liveblog autoarchive date on each time a new entry is added or updated to ensure we extend the date  out correctly to the next archive point based on the configured offset.
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
		if ( ! is_admin() )
			return;

		add_action( 'add_meta_boxes',        array( __CLASS__, 'add_meta_box'  ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'add_post_filtering_dropdown_to_manage_posts' ) );
		add_action( 'pre_get_posts',         array( __CLASS__, 'handle_query_vars_for_post_filtering' ) );
	}

	/**
	 * Hook filters in that run on every admin page-load
	 *
	 * @uses add_filter()
	 * @uses is_admin()
	 */
	private static function add_admin_filters() {

		// Bail if not in admin area
		if ( ! is_admin() )
			return;

		add_filter( 'display_post_states', array( __CLASS__, 'add_display_post_state' ), 10, 2 );
		add_filter( 'query_vars',          array( __CLASS__, 'add_query_var_for_post_filtering' ) );
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
		add_post_type_support( 'post', self::key );

		/**
		 * Apply a Filter to Setup our Auto Archive Days.
		 * NULL is classed as disabled.
		 */
		self::$auto_archive_days = apply_filters( 'liveblog_auto_archive_days', self::$auto_archive_days);

		do_action( 'after_liveblog_init' );
	}

	public static function add_rewrite_rules() {
		add_rewrite_endpoint( self::url_endpoint, EP_PERMALINK );
	}

	public static function flush_rewrite_rules() {
		if ( get_option( 'liveblog_rewrites_version' ) != self::rewrites_version ) {
			flush_rewrite_rules();
			update_option( 'liveblog_rewrites_version', self::rewrites_version );
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
	 * This is where a majority of the magic happens.
	 *
	 * Hooked to template_redirect, this method tries to add anything it can to
	 * the current post output. If nothing needs to be added, we redirect back
	 * to the permalink.
	 *
	 * @return If request has been handled
	 */
	public static function handle_request() {

		if ( ! self::is_viewing_liveblog_post() )
			return;

		self::$post_id     = get_the_ID();

		self::$custom_template_path = apply_filters( 'liveblog_template_path', self::$custom_template_path, self::$post_id );
		if( ! is_dir( self::$custom_template_path ) ) {
			self::$custom_template_path = null;
		} else {
			// realpath is used here to ensure we have an absolute path which is necessary to avoid APC related bugs
			self::$custom_template_path = untrailingslashit( realpath( self::$custom_template_path ) );
		}

		self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::key );

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

		$endpoint_suffix = get_query_var( self::url_endpoint );

		if ( !$endpoint_suffix ) {
			// we redirect, because if somebody accessed <permalink>/liveblog
			// they probably did that in the URL bar, not via AJAX
			wp_safe_redirect( get_permalink() );
			exit();
		}
		wp_cache_delete( self::key . '_entries_asc_' . self::$post_id, 'liveblog' );

		$suffix_to_method = array(
			'\d+/\d+' => 'ajax_entries_between',
			'crud' => 'ajax_crud_entry',
			'entry' => 'ajax_single_entry',
			'lazyload' => 'ajax_lazyload_entries',
			'preview' => 'ajax_preview_entry',
		);

		$response_method = 'ajax_unknown';

		foreach( $suffix_to_method as $suffix_re => $method ) {
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
	 * @param int $end_timestamp  	The end time boundary
	 *
	 * @return An array of Liveblog entries, possibly empty.
	 */
	public static function get_entries_by_time( $start_timestamp, $end_timestamp ) {

		// Set some defaults
		$latest_timestamp  = null;
		$entries_for_json  = array();

		// Do not cache if it's too soon
		if ( $end_timestamp > time() ) {
			self::$do_not_cache_response = true;
		}

		if ( empty( self::$entry_query ) ) {
			self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::key );
		}

		// Get liveblog entries within the start and end boundaries
		$entries = self::$entry_query->get_between_timestamps( $start_timestamp, $end_timestamp );

		if ( ! empty( $entries ) ) {
			/**
			 * Loop through each liveblog entry, set the most recent timestamp, and
			 * put the JSON data for each entry into a neat little array.
			 */
			foreach( $entries as $entry ) {
				$latest_timestamp   = max( $latest_timestamp, $entry->get_timestamp() );
				$entries_for_json[] = $entry->for_json();
			}
		}

		// Create the result array
		$result = array(
			'entries'           => $entries_for_json,
			'latest_timestamp'  => $latest_timestamp,
			'refresh_interval'  => self::get_refresh_interval(),
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
		return (bool)$state;
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
		if ( ! is_single() && ! is_admin() && ! self::$is_rest_api_call) {
			return false;
		}
		if ( empty( $post_id ) ) {
			if ( ! empty( self::$post_id ) ) {
				$post_id = self::$post_id;
			} else {
				global $post;
				if ( ! $post ){
					return false;
				}
				$post_id = $post->ID;
			}
		}
		$state = get_post_meta( $post_id, self::key, true );
		// backwards compatibility with older values
		if ( 1 == $state ) {
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

		return (bool) ! isset( $wp_query->query_vars[self::key] );
	}

	/**
	 * Is this an ajax request for the entries?
	 *
	 * @uses get_query_var() to check for the url_endpoint
	 * @return bool
	 */
	private static function is_entries_ajax_request() {
		return (bool) get_query_var( self::url_endpoint );
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
		$stamps = rtrim( get_query_var( self::url_endpoint ), '/' );
		if ( empty( $stamps ) )
			return array( false, false );

		// Get timestamps from the query variable
		$timestamps = explode( '/', $stamps );

		// Bail if there are not 2 timestamps
		if ( 2 !== count( $timestamps ) )
			return array( false, false );

		// Return integer timestamps in an array
		return array_map( 'intval', $timestamps );
	}

	//HANDLES THE CRUD ACTIONS FOR THE COMMENTS
	public static function ajax_crud_entry() {
		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		$args = array();

		$crud_action = isset( $_POST['crud_action'] ) ? $_POST['crud_action'] : 0;

		if ( ! self::is_valid_crud_action( $crud_action ) ) {
			self::send_user_error( sprintf( __( 'Invalid entry crud_action: %s', 'liveblog' ), $crud_action ) );
		}

		$args['post_id'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$args['content'] = isset( $_POST['content'] ) ? $_POST['content'] : '';
		$args['entry_id'] = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		$entry = self::do_crud_entry($crud_action, $args);

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
			self::json_return( array(
				'entries'          => array( $entry->for_json() ),
				'latest_timestamp' => null
			), array( 'cache' => false ) );
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
		$entry = call_user_func( array( 'WPCOM_Liveblog_Entry', $crud_action ), $args );
		if ( ! is_wp_error( $entry ) ) {
			// Do not send latest_timestamp. If we send it the client won't get
			// older entries. Since we send only the new one, we don't know if there
			// weren't any entries in between.
			$entry = array(
				'entries'           => array( $entry->for_json() ),
				'latest_timestamp'  => null
			);
		}

		return $entry;
	}

	/**
	 * Fetches the Liveblog entry with the ID given in the $_GET superglobal, and returns it via JSON.
	 */
	public static function ajax_single_entry() {

		// The URL is of the form "entry/entry_id".
		$fragments = explode( '/', get_query_var( self::url_endpoint ) );

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

		$entries = array();
		$previous_timestamp = 0;
		$next_timestamp = 0;

		if ( empty( self::$entry_query ) ) {
			self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::key );
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
				$next_entry = $all_entries[ $key + 1 ];
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
		$fragments = explode( '/', get_query_var( self::url_endpoint ) );

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
			self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::key );
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

		//self::json_return( $result_for_json );

		return $result;
	}

	public static function ajax_preview_entry() {
		$entry_content = isset( $_REQUEST['entry_content'] ) ? $_REQUEST['entry_content'] : '';
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
		if ( self::key == get_comment_type( $comment_id ) ) {
			$classes[] = 'liveblog-entry';
			$classes[] = 'liveblog-entry-class-' . $comment_id;
		}
		return $classes;
	}

	public static function admin_enqueue_scripts( $hook_suffix ) {
		global $post;

		// Enqueue admin scripts only if adding or editing a supported post type.
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) && post_type_supports( get_post_type(), self::key ) ) {

			$endpoint_url = '';
			$use_rest_api = 0;

			if ( self::use_rest_api() ) {
				$endpoint_url = WPCOM_Liveblog_Rest_Api::build_endpoint_base() . $post->ID . '/' . 'post_state';
				$use_rest_api = 1;
			}

			wp_enqueue_style( self::key, plugins_url( 'css/liveblog-admin.css', __FILE__ ) );
			wp_enqueue_script( 'liveblog-admin', plugins_url( 'js/liveblog-admin.js', __FILE__ ) );
			wp_localize_script( 'liveblog-admin', 'liveblog_admin_settings', array(
				'nonce_key'                    => self::nonce_key,
				'nonce'                        => wp_create_nonce( self::nonce_action ),
				'error_message_template'       => __( 'Error {error-code}: {error-message}', 'liveblog' ),
				'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
				'use_rest_api'                 => $use_rest_api,
				'endpoint_url'                 => $endpoint_url,
			) );
		}
	}

	/**
	 * Enqueue the necessary CSS and JS that liveblog needs to function.
	 *
	 * @return If not a liveblog post
	 */
	public static function enqueue_scripts() {

		if ( ! self::is_viewing_liveblog_post() )
			return;

		wp_enqueue_style( self::key,  plugins_url( 'css/liveblog.css', __FILE__ ) );
		wp_register_script( 'jquery-throttle',  plugins_url( 'js/jquery.ba-throttle-debounce.min.js', __FILE__ ) );
		wp_register_script( 'moment',  plugins_url( 'js/moment.min.js', __FILE__ ), array(), '1.7.2' );
		wp_localize_script( 'moment', 'momentLang', array(
			'locale' => get_locale(),
			'relativeTime' => array(
				'past' => __( '%s ago', 'liveblog' ),
				's' => __( 'a few seconds', 'liveblog' ),
				'm' => __( 'a minute', 'liveblog' ),
				'mm' => __( '%d minutes', 'liveblog' ),
				'h' => __( 'an hour', 'liveblog' ),
				'hh' => __( '%d hours', 'liveblog' ),
				'd' => __( 'a day', 'liveblog' ),
				'dd' => __( '%d days', 'liveblog' ),
				'M' => __( 'a month', 'liveblog' ),
				'MM' => __( '%d months', 'liveblog' ),
				'y' => __( 'a year', 'liveblog' ),
				'yy' => __( '%d years', 'liveblog' ),
			),
		));

		wp_enqueue_script( self::key, plugins_url( 'js/liveblog.js', __FILE__ ), array( 'jquery', 'jquery-color', 'backbone', 'jquery-throttle', 'moment' ), self::version, true );

		if ( self::is_liveblog_editable() )  {
			if ( apply_filters( 'liveblog_rich_text_editing_allowed', true ) ) {
				wp_enqueue_script( 'editor' );
			}
			wp_enqueue_script( 'liveblog-publisher', plugins_url( 'js/liveblog-publisher.js', __FILE__ ), array( self::key ), self::version, true );

			wp_register_script( 'liveblog-plupload', plugins_url( 'js/plupload.js', __FILE__ ), array( self::key, 'wp-plupload', 'jquery' ) );
			wp_localize_script( 'liveblog-plupload', 'liveblog_plupload', apply_filters( 'liveblog_plupload_localize', array(
				'browser' => '#liveblog-messages',
				'dropzone' => '#liveblog-container',
				'container' => false,
			) ) );
			wp_enqueue_script( 'liveblog-plupload' );
			self::add_default_plupload_settings();
		}

		if ( wp_script_is( 'jquery.spin', 'registered' ) ) {
			wp_enqueue_script( 'jquery.spin' );
		} else {
			wp_enqueue_script( 'spin',        plugins_url( 'js/spin.js',        __FILE__ ), false,                     '1.3' );
			wp_enqueue_script( 'jquery.spin', plugins_url( 'js/jquery.spin.js', __FILE__ ), array( 'jquery', 'spin' ), '1.3' );
		}

		if ( wp_script_is( 'jetpack-twitter-timeline', 'registered' ) ) {
			wp_enqueue_script( 'jetpack-twitter-timeline' );
		} else {
			wp_enqueue_script( 'liveblog-twitter-timeline', plugins_url( 'js/twitter-timeline.js', __FILE__ ), false, '1.5, true' );
		}

		wp_localize_script( self::key, 'liveblog_settings',
			apply_filters( 'liveblog_settings', array(
				'permalink'              => get_permalink(),
				'post_id'                => get_the_ID(),
				'state'                  => self::get_liveblog_state(),
				'is_liveblog_editable'   => self::is_liveblog_editable(),
				'socketio_enabled'       => WPCOM_Liveblog_Socketio_Loader::is_enabled(),

				'key'                    => self::key,
				'nonce_key'              => self::nonce_key,
				'nonce'                  => wp_create_nonce( self::nonce_action ),
				'latest_entry_timestamp' => self::$entry_query->get_latest_timestamp(),

				'refresh_interval'       => self::get_refresh_interval(),
				'focus_refresh_interval' => self::focus_refresh_interval,
				'max_consecutive_retries'=> self::max_consecutive_retries,
				'delay_threshold'        => self::delay_threshold,
				'delay_multiplier'       => self::delay_multiplier,
				'fade_out_duration'      => self::fade_out_duration,

				'use_rest_api'           => intval( self::use_rest_api() ),
				'endpoint_url'           => self::get_entries_endpoint_url(),

				'features'               => WPCOM_Liveblog_Entry_Extend::get_enabled_features(),
				'autocomplete'           => WPCOM_Liveblog_Entry_Extend::get_autocomplete(),
				'command_class'          => apply_filters( 'liveblog_command_class',   WPCOM_Liveblog_Entry_Extend_Feature_Commands::$class_prefix ),

				// i18n
				'delete_confirmation'    => __( 'Do you really want to delete this entry? There is no way back.', 'liveblog' ),
				'delete_key_confirm'     => __( 'Do you want to delete this key entry?', 'liveblog' ),
				'error_message_template' => __( 'Error {error-code}: {error-message}', 'liveblog' ),
				'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
				'new_update'             => __( 'Liveblog: {number} new update' , 'liveblog'),
				'new_updates'            => __( 'Liveblog: {number} new updates' , 'liveblog'),
				'create_link_prompt'     => __( 'Provide URL for link:', 'liveblog' ),

				// Classes
				'class_term_prefix'      => __( 'term-', 'liveblog' ),
				'class_alert'            => __( 'type-alert', 'liveblog' ),
				'class_key'              => __( 'type-key', 'liveblog' ),
			) )
		);
		wp_localize_script( 'liveblog-publisher', 'liveblog_publisher_settings', array(
			'loading_preview' => __( 'Loading preview…', 'liveblog' ),
			'new_entry_tab_label' => __( 'New Entry', 'liveblog' ),
			'new_entry_submit_label' => __( 'Publish Update', 'liveblog' ),
			'edit_entry_tab_label' => __( 'Edit Entry', 'liveblog' ),
			'edit_entry_submit_label' => __( 'Update', 'liveblog' ),
		) );
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
			'filters'             => array( array( 'title' => __( 'Allowed Files', 'liveblog' ), 'extensions' => '*') ),
			'multipart'           => true,
			'urlstream_upload'    => true,
			'multipart_params'    => array(
				'action'          => 'upload-attachment',
				'_wpnonce'        => wp_create_nonce( 'media-form' )
			)
		);

		$settings = array(
			'defaults' => $defaults,
			'browser'  => array(
				'mobile'    => wp_is_mobile(),
				'supported' => _device_can_upload(),
			)
		);

		$script = 'var _wpPluploadSettings = ' . json_encode( $settings ) . ';';
		$data   = $wp_scripts->get_data( 'wp-plupload', 'data' );

		if ( ! empty( $data ) )
			$script = "$data\n$script";

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
				$url = add_query_arg( self::url_endpoint, '', $post_permalink ) . '='; // returns something like ?p=1&liveblog=
			} else {
				$url = trailingslashit( trailingslashit( $post_permalink ) . self::url_endpoint ); // returns something like /2012/01/01/post/liveblog/
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

		$liveblog_output  = '<div id="liveblog-container" class="'. self::$post_id .'">';
		$liveblog_output .= self::get_editor_output();
		$liveblog_output .= '<div id="liveblog-update-spinner"></div>';
		$liveblog_output .= self::get_all_entry_output();
		$liveblog_output .= '</div>';

		$liveblog_output = apply_filters( 'liveblog_add_to_content', $liveblog_output, $content, self::$post_id );

		return $content . $liveblog_output;
	}

	/**
	 * Return the posting area for the end-user to liveblog from
	 *
	 * @return string
	 */
	private static function get_editor_output() {
		if ( !self::is_liveblog_editable() )
			return;

		return self::get_template_part( 'liveblog-form.php' );
	}

	/**
	 * Get all the liveblog entries for this post
	 */
	private static function get_all_entry_output() {

		// Get liveblog entries.
		$args = array();
		$state = self::get_liveblog_state();

		if ( 'archive' == $state ) {
			$args['order'] = 'ASC';
		}

		$args = apply_filters( 'liveblog_display_archive_query_args', $args, $state );
		$entries = (array) self::$entry_query->get_all( $args );
		$show_archived_message = 'archive' == $state && self::current_user_can_edit_liveblog();

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
		$theme_template = get_template_directory() . '/liveblog/' . ltrim( $template_name, '/' );
		$child_theme_template = get_stylesheet_directory() . '/liveblog/' . ltrim( $template_name, '/' );
		if ( file_exists( $child_theme_template ) ) {
			include( $child_theme_template );
		} else if ( file_exists( $theme_template ) ) {
			include( $theme_template );
		} else if( self::$custom_template_path && file_exists( self::$custom_template_path . '/' . $template_name ) ) {
			include( self::$custom_template_path . '/' . $template_name );
		} else {
			include( dirname( __FILE__ ) . '/templates/' . $template_name );
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
		if ( ! post_type_supports( $post_type, self::key ) )
			return;

		add_meta_box( self::key, __( 'Liveblog', 'liveblog' ), array( __CLASS__, 'display_meta_box' ) );
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
		echo self::get_meta_box( $post );

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
		$buttons = array(
			'enable' => array( 'value' => 'enable', 'text' => __( 'Enable', 'liveblog' ),
				'description' => __( 'Enables liveblog on this post. Posting tools are enabled for editors, visitors get the latest updates.' , 'liveblog'), 'active-text' => sprintf( __( 'There is an <strong>enabled</strong> liveblog on this post. <a href="%s">Visit the liveblog &rarr;</a>', 'liveblog' ), get_permalink( $post ) ), 'primary' => true, 'disabled' => false, ),
			'archive' => array( 'value' => 'archive', 'text' => __( 'Archive', 'liveblog' ),
				'description' => __( 'Archives the liveblog on this post. Visitors still see the liveblog entries, but posting tools are hidden.' , 'liveblog'), 'active-text' => sprintf( __( 'There is an <strong>archived</strong> liveblog on this post. <a href="%s">Visit the liveblog archive &rarr;</a>', 'liveblog' ), get_permalink( $post ) ), 'primary' => false, 'disabled' => false ),
		);
		if ( $current_state ) {
			$active_text = $buttons[$current_state]['active-text'];
			$buttons[$current_state]['disabled'] = true;
		} else {
			$active_text = __( 'This is a normal WordPress post, without a liveblog.', 'liveblog' );
			$buttons['archive']['disabled'] = true;
		}
		$update_text  = __( 'Settings have been successfully updated.', 'liveblog' );
		$extra_fields = array();
		$extra_fields = apply_filters( 'liveblog_admin_add_settings', $extra_fields, $post->ID );

		return self::get_template_part( 'meta-box.php', compact( 'active_text', 'buttons', 'update_text', 'extra_fields' ) );
	}

	public static function admin_ajax_set_liveblog_state_for_post() {
		$post_id = isset( $_REQUEST['post_id'] )? $_REQUEST['post_id'] : 0;
		$new_state = isset( $_REQUEST['state'] )? $_REQUEST['state'] : '';

		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		$meta_box = self::admin_set_liveblog_state_for_post( $post_id, $new_state, $_REQUEST );

		if ( ! $meta_box ) {

			if ( wp_is_post_revision( $post_id ) ) {
				self::send_user_error( __( "The post is a revision: $post_id" , 'liveblog') );
			}

			self::send_user_error( __( "Non-existing post ID: $post_id" , 'liveblog') );

		}

		self::json_return($meta_box);

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

		//if the auto_archive feature is not disabled
		if ( null !== self::$auto_archive_days ) {
			//Get the Current State
			$current_state 		= get_post_meta( $post_id, self::key );

			//Instantiate a entry query object
			$query = new WPCOM_Liveblog_Entry_Query( $post_id, self::key );
			$latest_timestamp = ( null !== $query->get_latest_timestamp() ) ? $query->get_latest_timestamp() : strtotime( date( 'Y-m-d H:i:s' ) );

			//set autoarchive date based on latest timestamp
			$autoarchive_date 	= strtotime(' + ' . self::$auto_archive_days . ' days', $latest_timestamp );

			//if the old state is archive and the new state is active or there is no current state and the new state is enable
			if( count( $current_state ) === 0 && $new_state === 'enable' || $current_state[0] === 'archive' && $new_state === 'enable' ) {

				//Then the live blog is being setup for the first time or is being reactivated.
				update_post_meta( $post_id, self::$auto_archive_expiry_key, $autoarchive_date );

			}
		}

		//Lets update the post_meta state as per usual.
		if ( in_array( $new_state, array( 'enable', 'archive' ) ) ) {
			update_post_meta( $post_id, self::key, $new_state );
			do_action( "liveblog_{$new_state}_post", $post_id );

		} elseif ( 'disable' == $new_state ) {
			delete_post_meta( $post_id, self::key );

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
		if( null !== self::$auto_archive_days ) {
			//Instantiate a entry query object
			$query = new WPCOM_Liveblog_Entry_Query( $args['post_id'], self::key );

			//set autoarchive date based on latest timestamp
			$autoarchive_date 	= strtotime(' + ' . self::$auto_archive_days . ' days', $query->get_latest_timestamp() );

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
			}
			else if ( 'archive' === $liveblog_state ) {
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
		if ( ! post_type_supports( $current_screen->post_type, self::key ) ) {
			return;
		}

		$options = array(
			'' => __( 'Filter liveblogs', 'liveblog' ),
			'any' => __( 'Any liveblogs', 'liveblog' ),
			'enable' => __( 'Enabled liveblogs', 'liveblog' ),
			'archive' => __( 'Archived liveblogs', 'liveblog' ),
			'none' => __( 'No liveblogs', 'liveblog' ),
		);
		echo self::get_template_part( 'restrict-manage-posts.php', compact( 'options' ) );
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
				'key' => self::key,
				'compare' => 'EXISTS',
			);
		}
		else if ( 'none' === $state ) {
			$new_meta_query_clause = array(
				'key' => self::key,
				'compare' => 'NOT EXISTS',
			);
		}
		else if ( in_array( $state, array( 'enable', 'archive' ) ) ) {
			$new_meta_query_clause = array(
				'key' => self::key,
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
		$retval = current_user_can( apply_filters( 'liveblog_edit_cap', self::edit_cap ) );
		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}

	public static function is_liveblog_editable() {
		return self::current_user_can_edit_liveblog() && 'enable' == self::get_liveblog_state();
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
	public static function ajax_check_nonce( $action = self::nonce_action ) {
		if ( ! isset( $_REQUEST[ self::nonce_key ] ) || ! wp_verify_nonce( $_REQUEST[ self::nonce_key ], $action ) ) {
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
		$args = wp_parse_args( $args, array(
			// Set false for nocache; set int for Cache-control+max-age
			'cache' => self::response_cache_max_age,
		) );
		$args = apply_filters( 'liveblog_json_return_args', $args, $data );

		$json_data = json_encode( $data );

		// Send cache headers, where appropriate
		if ( false === $args['cache'] ) {
			nocache_headers();
		} elseif ( is_numeric( $args['cache'] ) ) {
			header( sprintf( 'Cache-Control: max-age=%d', $args['cache'] ) );
		}

		header( 'Content-Type: application/json' );
		self::prevent_caching_if_needed();
		echo $json_data;
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

		$status                     = absint( $status );
		$official_message           = isset( $wp_header_to_desc[$status] ) ? $wp_header_to_desc[$status] : '';
		$wp_header_to_desc[$status] = self::sanitize_http_header( $message );

		status_header( $status );

		$wp_header_to_desc[$status] = $official_message;
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
		if ( self::key === $meta_key )
			return true;

		return $protected;
	}

	/**
	 * Tells browsers to not cache the response if $do_not_cache_response is true
	 */
	public static function prevent_caching_if_needed() {
		if ( self::$do_not_cache_response ) {
			nocache_headers();
		}
	}

	/**
	 * Checks to see if the current WordPress version has REST API support
	 *
	 * @return bool true if supported, false otherwise
	 */
	public static function can_use_rest_api() {
		global $wp_version;
		return version_compare( $wp_version, self::min_wp_rest_api_version, '>=' );
	}

	/**
	 * Checks if use_rest_api is on and the WordPress version supports it
	 */
	public static function use_rest_api() {
		return ( self::use_rest_api && self::can_use_rest_api() );
	}

	/**
	 * Check for allowed crud action
	 *
	 * @param String $action The CRUD action to check
	 * @return bool true if $action is one of insert|update|delete|delete_key. false otherwise
	 */
	public static function is_valid_crud_action( $action ) {
		return in_array( $action, array( 'insert', 'update', 'delete', 'delete_key' ) );
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

		if ( strpos( $size, 'k' ) !== false )
			$bytes = intval( $size ) * 1024;
		elseif ( strpos( $size, 'm' ) !== false )
			$bytes = intval( $size ) * 1024 * 1024;
		elseif ( strpos( $size, 'g' ) !== false )
			$bytes = intval( $size ) * 1024 * 1024 * 1024;

		return $bytes;
	}

	/**
	 * Convert bytes to hour
	 *
	 * @param string $bytes
	 * @return string
	 */
	private static function convert_bytes_to_hr( $bytes ) {
		$units = array( 0 => 'B', 1 => 'kB', 2 => 'MB', 3 => 'GB' );
		$log   = log( $bytes, 1024 );
		$power = (int) $log;
		$size  = pow( 1024, $log - $power );

		return $size . $units[$power];
	}

	/**
	 * Get the maximum upload file size
	 *
	 * @see wp_max_upload_size()
	 * @return string
	 */
	private static function max_upload_size() {
		$u_bytes = self::convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
		$p_bytes = self::convert_hr_to_bytes( ini_get( 'post_max_size'       ) );
		$bytes   = apply_filters( 'upload_size_limit', min( $u_bytes, $p_bytes ), $u_bytes, $p_bytes );

		return $bytes;
	}

	private static function is_wp_too_old() {
		global $wp_version;
		// if WordPress is loaded in a function the version variables aren't globalized
		// see: http://core.trac.wordpress.org/ticket/17749#comment:40
		if ( !isset( $wp_version ) || !$wp_version ) {
			return false;
		}
		return version_compare( $wp_version, self::min_wp_version, '<' );
	}

	/**
	 * Returns refresh interval after filters have been run
	 *
	 * @return int
	 */
	public static function get_refresh_interval() {
		$refresh_interval = WP_DEBUG ? self::debug_refresh_interval : self::refresh_interval;
		$refresh_interval = apply_filters( 'liveblog_refresh_interval', $refresh_interval  						      );
		$refresh_interval = apply_filters( 'liveblog_post_' . self::$post_id . '_refresh_interval', $refresh_interval );
		return $refresh_interval;
	}

}
WPCOM_Liveblog::load();

/** Plupload Helpers ******************************************************/
if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
	require_once( ABSPATH . 'wp-includes/load.php');
}

if ( ! function_exists( 'size_format' ) ) {
	require_once( ABSPATH . 'wp-includes/functions.php');
}

if ( ! function_exists( 'wp_max_upload_size' ) ) {
	require_once( ABSPATH . 'wp-includes/media.php');
}

endif;
