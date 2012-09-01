<?php

/**
 * Plugin Name: Liveblog
 * Description: Blogging: at the speed of live.
 * Version:     1.0-beta
 * Author:      WordPress.com VIP, Automattic
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
 * Things yet to be implimented:
 *
 * -- Change "Read More" to "View Liveblog"
 * -- Manual refresh button
 * -- Allow marking of liveblog as ended
 * -- Allow comment modifications; need to store modified date as comment_meta
 */
final class WPCOM_Liveblog {

	/** Constants *************************************************************/

	const version          = 0.1;
	const key              = 'liveblog';
	const url_endpoint     = 'liveblog';
	const edit_cap         = 'publish_posts';
	const nonce_key        = 'liveblog_nonce';

	const refresh_interval        = 3;   // how often should we refresh
	const max_consecutive_retries = 100; // max number of failed tries before polling is disabled
	const delay_threshold         = 5;  // how many failed tries after which we should increase the refresh interval
	const delay_multiplier        = 2; // by how much should we inscrease the refresh interval

	/** Variables *************************************************************/

	private static $post_id               = null;
	private static $entry_query           = null;
	private static $do_not_cache_response = false;

	/** Load Methods **********************************************************/

	/**
	 * @uses add_action() to hook methods into WordPress actions
	 * @uses add_filter() to hook methods into WordPress filters
	 */
	public static function load() {
		self::includes();
		self::add_actions();
		self::add_filters();
		self::add_admin_actions();
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
	}

	/**
	 * Hook actions in that run on every page-load
	 *
	 * @uses add_action()
	 */
	private static function add_actions() {
		add_action( 'init',                          array( __CLASS__, 'init'              ) );
		//add_action( 'wp_head',                       array( __CLASS__, 'wp_head'           ) );
		add_action( 'wp_enqueue_scripts',            array( __CLASS__, 'enqueue_scripts'   ) );
		add_action( 'wp_ajax_liveblog_insert_entry', array( __CLASS__, 'ajax_insert_entry' ) );
		add_action( 'wp_ajax_liveblog_preview_entry', array( __CLASS__, 'ajax_preview_entry' ) );
	}

	/**
	 * Hook filters in that run on every page-load
	 *
	 * @uses add_filter()
	 */
	private static function add_filters() {
		add_filter( 'template_redirect', array( __CLASS__, 'handle_request'    ) );
		add_filter( 'comment_class',     array( __CLASS__, 'add_comment_class' ) );
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

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box'  ) );
		add_action( 'save_post',      array( __CLASS__, 'save_meta_box' ) );
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
		 * Add a WordPress rewrite-rule enpoint.
		 *
		 * Looks like: /2012/01/01/post-name/liveblog/123456/
		 *
		 * where 123456 is a timestamp
		 */
		add_rewrite_endpoint( self::url_endpoint, EP_PERMALINK );

		/**
		 * Add liveblog support to the 'post' post type. This is done here so
		 * we can possibly introduce this to other post types later.
		 */
		add_post_type_support( 'post', self::key );
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
		self::$entry_query = new WPCOM_Liveblog_Entry_Query( self::$post_id, self::key );

		if ( self::is_initial_page_request() )
			add_filter( 'the_content', array( __CLASS__, 'add_liveblog_to_content' ) );
		else
			self::handle_ajax_request();
	}

	private static function handle_ajax_request() {

		$endpoint_suffix = get_query_var( self::url_endpoint );

		if ( !$endpoint_suffix ) {
			// we redirect, because if somebody accessed <permalink>/liveblog
			// they probably did that in the URL bar, not via AJAX
			wp_safe_redirect( get_permalink() );
			exit();
		}

		$suffix_to_method = array(
			'\d+/\d+' => 'ajax_entries_between',
			'insert' => 'ajax_insert_entry',
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
			self::send_user_error( __( 'A timestamp is missing. Correct URL: <permalink>/liveblog/<from>/</to>/' ) );
		}

		// Do not cache if it's too soon
		if ( $end_timestamp > time() )
			self::$do_not_cache_response = true;

		// Get liveblog entries within the start and end boundaries
		$entries = self::$entry_query->get_between_timestamps( $start_timestamp, $end_timestamp );
		if ( empty( $entries ) ) {
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

		self::json_return( $result_for_json );
	}

	/** Private _is_ Methods **************************************************/

	/**
	 * Are we viewing a liveblog post?
	 *
	 * @uses is_single()
	 * @uses is_liveblog_post()
	 * @return bool
	 */
	private static function is_viewing_liveblog_post() {
		return (bool) ( is_single() && self::is_liveblog_post() );
	}

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
	 * Is a given post_id a liveblog enabled post?
	 *
	 * @global WP_Post $post
	 * @param int $post_id
	 * @return bool
	 */
	private static function is_liveblog_post( $post_id = null ) {
		if ( empty( $post_id ) ) {
			global $post;
			$post_id = $post->ID;
		}

		return (bool) get_post_meta( $post_id, self::key, true );
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
		$stamps = get_query_var( self::url_endpoint );
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

	/**
	 * Inserts, updates, or deletes a liveblog entry
	 */
	public static function ajax_insert_entry() {

		// Capability and Intention Checks
		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		// Check POST data
		$post_id             = isset( $_POST['post_id']       ) ? intval( $_POST['post_id']  ) : 0;
		$replaces_comment_id = isset( $_POST['replaces']      ) ? intval( $_POST['replaces'] ) : 0;
		$entry_content       = isset( $_POST['entry_content'] ) ? $_POST['entry_content']      : '';

		if ( empty( $post_id ) )
			self::send_user_error( __( 'Sorry, that post is not accepting Liveblog entries.', 'liveblog' ) );

		// Get the current user
		$user = wp_get_current_user();

		// Insert new comment
		$new_comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => wp_filter_post_kses( $entry_content ),
			'comment_approved'     => self::key,
			'comment_type'         => self::key,
			'user_id'              => $user->ID,

			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => $user->user_url,

			// Borrowed from core as wp_insert_comment does not generate them
			'comment_author_IP'    => preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] ),
			'comment_agent'        => substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ),
		) );

		// Bail if comment could not be saved
		if ( empty( $new_comment_id ) || is_wp_error( $new_comment_id ) )
			self::send_server_error( __( 'Error posting entry', 'liveblog' ) );

		// Are we replacing an existing comment?
		if ( !empty( $replaces_comment_id ) ) {

			//
			add_comment_meta( $new_comment_id, WPCOM_Liveblog_Entry::replaces_meta_key, $replaces_comment_id );

			// Update an existing comment
			if ( !empty( $entry_content ) ) {
				wp_update_comment( array(
					'comment_ID'      => $replaces_comment_id,
					'comment_content' => wp_filter_post_kses( $entry_content ),
				) );

			// Delete this comment
			} else {
				wp_delete_comment( $replaces_comment_id );
			}
		}

		$entry = WPCOM_Liveblog_Entry::from_comment( get_comment( $new_comment_id ) );

		// Do not send latest_timestamp. If we send it the client won't get
		// older entries. Since we send only the new one, we don't know if there
		// weren't any entries in between.
		self::json_return( array(
			'entries'           => array( $entry->for_json() ),
			'latest_timestamp'  => null
		) );
	}

	function ajax_preview_entry() {
		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();
		$entry_content = isset( $_REQUEST['entry_content'] ) ? $_REQUEST['entry_content'] : '';
		$entry_content = wp_filter_post_kses( $entry_content );
		$entry_content = WPCOM_Liveblog_Entry::render_content( $entry_content );
		self::json_return( array( 'html' => $entry_content ) );
	}

	public function ajax_unknown() {
		self::send_user_error( __( 'Unknown liveblog action', 'liveblog' ) );
	}


	/** Comment Methods *******************************************************/

	/**
	 * Add a liveblog class to each comment, so they can be styled
	 *
	 * @param array $classes
	 * @return string
	 */
	public static function add_comment_class( $classes ) {
		$classes[] = 'liveblog-entry';
		return $classes;
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
		wp_enqueue_script( self::key, plugins_url( 'js/liveblog.js', __FILE__ ), array( 'jquery', 'jquery-color' ), self::version, true );

		if ( self::current_user_can_edit_liveblog() )  {
			wp_enqueue_script( 'liveblog-publisher', plugins_url( 'js/liveblog-publisher.js', __FILE__ ), array( self::key, 'jquery-ui-tabs' ), self::version, true );
			wp_enqueue_script( 'liveblog-plupload', plugins_url( 'js/plupload.js', __FILE__ ), array( self::key, 'wp-plupload', 'jquery' ) );
			self::add_default_plupload_settings();
		}

		if ( wp_script_is( 'jquery.spin', 'registered' ) ) {
			wp_enqueue_script( 'jquery.spin' );
		} else {
			wp_enqueue_script( 'spin',        plugins_url( 'js/spin.js',        __FILE__ ), false,                    '1.2.4' );
			wp_enqueue_script( 'jquery.spin', plugins_url( 'js/jquery.spin.js', __FILE__ ), array( 'jquery', 'spin' )         );
		}

		wp_localize_script( self::key, 'liveblog_settings',
			apply_filters( 'liveblog_settings', array(
				'permalink'              => get_permalink(),
				'post_id'                => get_the_ID(),

				'key'                    => self::key,
				'nonce_key'              => self::nonce_key,
				'latest_entry_timestamp' => self::$entry_query->get_latest_timestamp(),

				'refresh_interval'       => self::refresh_interval,
				'max_consecutive_retries'=> self::max_consecutive_retries,
				'delay_threshold'        => self::delay_threshold,
				'delay_multiplier'       => self::delay_multiplier,

				'endpoint_url'             => self::get_entries_endpoint_url(),

				// i18n
				'update_nag_singular'    => __( '%d new update',  'liveblog' ),
				'update_nag_plural'      => __( '%d new updates', 'liveblog' ),
				'delete_confirmation'    => __( 'Do you really want do delete this entry? There is no way back.', 'liveblog' ),
			) )
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
			'max_file_size'       => self::max_upload_size() . 'b',
			'url'                 => admin_url( 'admin-ajax.php', 'relative' ),
			'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'             => array( array( 'title' => __( 'Allowed Files' ), 'extensions' => '*') ),
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
		return get_permalink( self::$post_id ) . self::url_endpoint;
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

		$liveblog_output  = '<div id="liveblog-container" class="'. self::$post_id .'">';
		$liveblog_output .= self::get_editor_output();
		$liveblog_output .= '<div id="liveblog-update-spinner"></div>';
		$liveblog_output .= self::get_all_entry_output();
		$liveblog_output .= '</div>';

		return $content . $liveblog_output;
	}

	/**
	 * Return the posting area for the end-user to liveblog from
	 *
	 * @return string
	 */
	private static function get_editor_output() {
		if ( ! self::current_user_can_edit_liveblog() )
			return;

		// Get the template part
		return self::get_template_part( 'liveblog-form.php' );
	}

	/**
	 * Get all the liveblog entries for this post
	 */
	private static function get_all_entry_output() {

		// Get liveblog entries
		$entries = (array) self::$entry_query->get_all();

		// Get the template part
		return self::get_template_part( 'liveblog-loop.php', compact( 'entries' ) );
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
		include( dirname( __FILE__ ) . '/templates/' . $template_name );
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

	/**
	 * Output the metabox
	 *
	 * @param WP_Post $post
	 */
	public static function display_meta_box( $post ) {
	?>

		<label>
			<input type="checkbox" name="is-liveblog" id="is-liveblog" value="1" <?php checked( self::is_liveblog_post( $post->ID ) ); ?> />
			<?php esc_html_e( 'This is a liveblog', 'liveblog' ); ?>
		</label>

		<?php
		wp_nonce_field( self::nonce_key, self::nonce_key, false );
	}

	/**
	 * Save the metabox when the post is saved
	 *
	 * @param type $post_id
	 * @return type
	 */
	public function save_meta_box( $post_id ) {

		// Bail if no liveblog nonce
		if ( empty( $_POST[self::nonce_key] ) || ! wp_verify_nonce( $_POST[self::nonce_key], self::nonce_key ) )
			return;

		// Update liveblog beta
		if ( ! empty( $_POST['is-liveblog'] ) )
			update_post_meta( $post_id, self::key, 1 );

		// Delete liveblog meta
		else
			delete_post_meta( $post_id, self::key );
	}

	/** Error Methods *********************************************************/

	/**
	 * Can the current user edit liveblog data (non-ajax)
	 *
	 * @return bool
	 */
	public static function current_user_can_edit_liveblog() {
		return (bool) current_user_can( apply_filters( 'liveblog_edit_cap', self::edit_cap ) );
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
		$wp_header_to_desc[$status] = $message;

		status_header( $status );

		$wp_header_to_desc[$status] = $official_message;
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
}

/**
 * Load the one true WPCOM_Liveblog instance
 *
 * Loaded late on the 'plugins_loaded' hook to allow any other plugin to sneak
 * in ahead of it, to add actions, filters, etc...
 *
 * @uses WPCOM_Liveblog::load()
 */
function wpcom_liveblog_load() {
	WPCOM_Liveblog::load();
}
add_action( 'plugins_loaded', 'wpcom_liveblog_load', 999 );

endif;
