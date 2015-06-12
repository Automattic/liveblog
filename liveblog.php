<?php

/**
 * Plugin Name: Liveblog
 * Plugin URI: http://wordpress.org/extend/plugins/liveblog/
 * Description: Blogging: at the speed of live.
 * Version:     1.4.1
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
 *
 * Things yet to be implemented:
 *
 * -- Change "Read More" to "View Liveblog"
 * -- Manual refresh button
 * -- Allow marking of liveblog as ended
 * -- Allow comment modifications; need to store modified date as comment_meta
 */
final class WPCOM_Liveblog {

	/** Constants *************************************************************/

	const version          = '1.4';
	const rewrites_version = 1;
	const min_wp_version   = '3.5';
	const key              = 'liveblog';
	const url_endpoint     = 'liveblog';
	const edit_cap         = 'publish_posts';
	const nonce_key        = 'liveblog_nonce';

	const refresh_interval        = 10;   // how often should we refresh
	const debug_refresh_interval  = 2;   // how often we refresh in development mode
	const max_consecutive_retries = 100; // max number of failed tries before polling is disabled
	const human_time_diff_update_interval = 60; // how often we change the entry human timestamps: "a minute ago"
	const delay_threshold         = 5;  // how many failed tries after which we should increase the refresh interval
	const delay_multiplier        = 2; // by how much should we inscrease the refresh interval
	const fade_out_duration       = 5; // how much time should take fading out the background of new entries

	/** Variables *************************************************************/

	private static $post_id               = null;
	private static $entry_query           = null;
	private static $do_not_cache_response = false;
	private static $custom_template_path  = null;

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
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry.php'       );
		require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-entry-query.php' );

		// Manually include ms.php theme-side in multisite environments because
		// we need its filesize and available space functions.
		if ( ! is_admin() && is_multisite() ) {
			require_once( ABSPATH . 'wp-admin/includes/ms.php' );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require( dirname( __FILE__ ) . '/classes/class-wpcom-liveblog-wp-cli.php' );
		}
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
		add_filter( 'template_redirect', array( __CLASS__, 'handle_request'    ) );
		add_filter( 'comment_class',     array( __CLASS__, 'add_comment_class' ), 10, 3 );
		add_filter( 'is_protected_meta', array( __CLASS__, 'protect_liveblog_meta_key'	 ), 10, 2 );		
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
			'preview' => 'ajax_preview_entry',
		);

		$response_method = 'ajax_unknown';

		foreach( $suffix_to_method as $suffix_re => $method ) {
			if ( preg_match( "%^$suffix_re/?%", $endpoint_suffix ) ) {
				$response_method = $method;
				break;
			}
		}

		self::$response_method();

	}

	/**
	 * Look for any new Liveblog entries, and return them via JSON
	 */
	public static function ajax_entries_between() {

		// Set some defaults
		$latest_timestamp  = 0;
		$entries_for_json  = array();

		// Look for entry boundaries
		list( $start_timestamp, $end_timestamp ) = self::get_timestamps_from_query();

		// Bail if there is no end timestamp
		if ( empty( $end_timestamp ) ) {
			self::send_user_error( __( 'A timestamp is missing. Correct URL: <permalink>/liveblog/<from>/</to>/', 'liveblog' ) );
		}

		// Do not cache if it's too soon
		if ( $end_timestamp > time() )
			self::$do_not_cache_response = true;

		// Get liveblog entries within the start and end boundaries
		$entries = self::$entry_query->get_between_timestamps( $start_timestamp, $end_timestamp );
		if ( empty( $entries ) ) {
			do_action( 'liveblog_entry_request_empty' );

			self::json_return( array(
				'entries'           => array(),
				'latest_timestamp'  => null
			) );
		}

		/**
		 * Loop through each liveblog entry, set the most recent timestamp, and
		 * put the JSON data for each entry into a neat little array.
		 */
		foreach( $entries as $entry ) {
			$latest_timestamp   = max( $latest_timestamp, $entry->get_timestamp() );
			$entries_for_json[] = $entry->for_json();
		}

		// Setup our data to return via JSON
		$result_for_json = array(
			'entries'           => $entries_for_json,
			'latest_timestamp'  => $latest_timestamp,
		);

		do_action( 'liveblog_entry_request', $result_for_json );

		self::json_return( $result_for_json );
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
		if ( empty( $post_id ) ) {
			global $post;
			$post_id = $post->ID;
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

	public static function ajax_crud_entry() {
		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		$args = array();

		$crud_action = isset( $_POST['crud_action'] ) ? $_POST['crud_action'] : 0;

		if ( !in_array( $crud_action, array( 'insert', 'update', 'delete' ) ) ) {
			self::send_user_error( sprintf( __( 'Invalid entry crud_action: %s', 'liveblog' ), $crud_action ) );
		}

		$args['post_id'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$args['content'] = isset( $_POST['content'] ) ? $_POST['content'] : '';
		$args['entry_id'] = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		$args['user'] = wp_get_current_user();

		$entry = call_user_func( array( 'WPCOM_Liveblog_Entry', $crud_action ), $args );

		if ( is_wp_error( $entry ) ) {
			self::send_server_error( $entry->get_error_message() );
		}

		// Do not send latest_timestamp. If we send it the client won't get
		// older entries. Since we send only the new one, we don't know if there
		// weren't any entries in between.
		self::json_return( array(
			'entries'           => array( $entry->for_json() ),
			'latest_timestamp'  => null
		) );
	}

	public static function ajax_preview_entry() {
		$entry_content = isset( $_REQUEST['entry_content'] ) ? $_REQUEST['entry_content'] : '';
		$entry_content = stripslashes( wp_filter_post_kses( $entry_content ) );
		$entry_content = WPCOM_Liveblog_Entry::render_content( $entry_content );

		do_action( 'liveblog_preview_entry', $entry_content );

		self::json_return( array( 'html' => $entry_content ) );
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
		if ( self::key == get_comment_type( $comment_id ) )
			$classes[] = 'liveblog-entry';
		return $classes;
	}

	public static function admin_enqueue_scripts() {
		wp_enqueue_style( self::key,  plugins_url( 'css/liveblog-admin.css', __FILE__ ) );
		wp_enqueue_script( 'liveblog-admin',  plugins_url( 'js/liveblog-admin.js', __FILE__ ) );
		wp_localize_script( 'liveblog-admin', 'liveblog_admin_settings', array(
			'nonce_key' => self::nonce_key,
			'nonce' => wp_create_nonce( self::nonce_key ),
			'error_message_template' => __( 'Error {error-code}: {error-message}', 'liveblog' ),
			'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
		) );
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
			wp_enqueue_script( 'liveblog-plupload', plugins_url( 'js/plupload.js', __FILE__ ), array( self::key, 'wp-plupload', 'jquery' ) );
			self::add_default_plupload_settings();
		}

		if ( wp_script_is( 'jquery.spin', 'registered' ) ) {
			wp_enqueue_script( 'jquery.spin' );
		} else {
			wp_enqueue_script( 'spin',        plugins_url( 'js/spin.js',        __FILE__ ), false,                     '1.3' );
			wp_enqueue_script( 'jquery.spin', plugins_url( 'js/jquery.spin.js', __FILE__ ), array( 'jquery', 'spin' ), '1.3' );
		}

		wp_localize_script( self::key, 'liveblog_settings',
			apply_filters( 'liveblog_settings', array(
				'permalink'              => get_permalink(),
				'post_id'                => get_the_ID(),
				'state'                  => self::get_liveblog_state(),

				'key'                    => self::key,
				'nonce_key'              => self::nonce_key,
				'nonce'                  => wp_create_nonce( self::nonce_key ),
				'latest_entry_timestamp' => self::$entry_query->get_latest_timestamp(),

				'refresh_interval'       => WP_DEBUG? self::debug_refresh_interval : self::refresh_interval,
				'max_consecutive_retries'=> self::max_consecutive_retries,
				'delay_threshold'        => self::delay_threshold,
				'delay_multiplier'       => self::delay_multiplier,
				'fade_out_duration'      => self::fade_out_duration,

				'endpoint_url'           => self::get_entries_endpoint_url(),

				// i18n
				'delete_confirmation'    => __( 'Do you really want to delete this entry? There is no way back.', 'liveblog' ),
				'error_message_template' => __( 'Error {error-code}: {error-message}', 'liveblog' ),
				'short_error_message_template' => __( 'Error: {error-message}', 'liveblog' ),
				'new_update'             => __( 'Liveblog: {number} new update' , 'liveblog'),
				'new_updates'            => __( 'Liveblog: {number} new updates' , 'liveblog'),
				'create_link_prompt'     => __( 'Provide URL for link:', 'liveblog' )
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
			'max_file_size'       => self::max_upload_size() . 'b',
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
		$post_permalink = get_permalink( self::$post_id );
		if ( false !== strpos( $post_permalink, '?p=' ) )
			$url = add_query_arg( self::url_endpoint, '', $post_permalink ) . '='; // returns something like ?p=1&liveblog=
		else
			$url = trailingslashit( trailingslashit( $post_permalink ) . self::url_endpoint ); // returns something like /2012/01/01/post/liveblog/
		$url = apply_filters( 'liveblog_endpoint_url', $url, self::$post_id );
		return $url;
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
		if( self::$custom_template_path && file_exists( self::$custom_template_path . '/' . $template_name ) ) {
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
		echo self::get_template_part( 'meta-box.php', compact( 'active_text', 'buttons' ) );
	}

	public static function admin_ajax_set_liveblog_state_for_post() {
		$post_id = isset( $_REQUEST['post_id'] )? $_REQUEST['post_id'] : 0;
		$new_state = isset( $_REQUEST['state'] )? $_REQUEST['state'] : '';

		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		if ( !$REQUEST = get_post( $post_id ) ) {
			self::send_user_error( __( "Non-existing post ID: $post_id" , 'liveblog') );
		}

		if ( wp_is_post_revision( $post_id ) ) {
			self::send_user_error( __( "The post is a revision: $post_id" , 'liveblog') );
		}

		self::set_liveblog_state( $post_id, $_REQUEST['state'] );
		self::display_meta_box( $REQUEST );
		exit;
	}

	private static function set_liveblog_state( $post_id, $state ) {
		if ( in_array( $state, array( 'enable', 'archive' ) ) ) {
			update_post_meta( $post_id, self::key, $state );
			do_action( "liveblog_{$state}_post", $post_id );
		} elseif ( 'disable' == $state ) {
			delete_post_meta( $post_id, self::key );
			do_action( 'liveblog_disable_post', $post_id );
		} else {
			return false;
		}
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
	public static function ajax_check_nonce( $action = self::nonce_key ) {
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
	private static function json_return( $data ) {
		$json_data = json_encode( $data );

		header( 'Content-Type: application/json' );
		if ( self::$do_not_cache_response )
			nocache_headers();

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
}

function wpcom_liveblog_load() {
	WPCOM_Liveblog::load();
}
add_action( 'plugins_loaded', 'wpcom_liveblog_load', 999 );

endif;
