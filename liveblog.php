<?php
/**
 * Plugin Name: Liveblog
 * Description: Blogging: at the speed of live.
 * Version: 0.1
 * Author: WordPress.com VIP, Automattic
 */

/*
TODO (0.1):
-- Loading icon
-- Prime caches on comment update (comment count)
-- Fix Batcache issues

TODO (future):
-- PHP and JS Actions/Filters/Triggers
-- Change "Read More" to "View Liveblog"
-- Manual refresh button
-- Allow marking of liveblog as ended
-- Allow comment modifications; need to store modified date as comment_meta
-- Drag-and-drop image uploading support

*/

require dirname( __FILE__ ) . '/class-wpcom-liveblog-entry.php';
require dirname( __FILE__ ) . '/class-wpcom-liveblog-entries.php';

if ( ! class_exists( 'WPCOM_Liveblog' ) ) :

class WPCOM_Liveblog {

	const version = 0.1;
	const key = 'liveblog';
	const url_endpoint = 'liveblog';
	const edit_cap = 'publish_posts';
	const nonce_key = 'liveblog_nonce';

	const refresh_interval = 3; // how often should we refresh
	const max_retries = 100; // max number of failed tries before polling is disabled
	const delay_threshold = 10; // how many failed tries after which we should increase the refresh interval
	const delay_multiplier = 1.5; // by how much should we inscrease the refresh interval


	static $post_id = null;
	static $entries = null;
	static $do_not_cache_response = false;

	function load() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_liveblog_insert_entry', array( __CLASS__, 'ajax_insert_entry' ) );

		add_filter( 'template_redirect', array( __CLASS__, 'handle_request' ) );

		add_filter( 'comment_class', array( __CLASS__, 'add_comment_class' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
	}

	function init() {
		// TODO filters for time and interval overrides

		add_rewrite_endpoint( self::url_endpoint, EP_PERMALINK ); // /2012/01/01/post-name/liveblog/123456/ where 123456 is a timestamp

		add_post_type_support( 'post', self::key );
	}

	function handle_request( $query ) {
		if ( ! self::is_viewing_liveblog_post() )
			return;

		self::$post_id = get_the_ID();
		self::$entries = new WPCOM_Liveblog_Entries( self::$post_id, self::key );

		if ( self::is_initial_page_request() ) {
			add_filter( 'the_content', array( __CLASS__, 'add_liveblog_to_content' ) );
			return;
		}

		if ( self::is_entries_ajax_request() ) {
			self::ajax_entries_between();
			return;
		}

		wp_safe_redirect( get_permalink() );
		exit;
	}

	function ajax_entries_between() {
		$current_timestamp = time();

		list( $start_timestamp, $end_timestamp ) = self::get_timestamps_from_query();
		if ( !$end_timestamp ) {
			wp_safe_redirect( get_permalink() );
		}

		if ( $end_timestamp > $current_timestamp ) {
			self::$do_not_cache_response = true;
		}

		$entries = self::$entries->get_between_timestamps( $start_timestamp, $end_timestamp );
		if ( !$entries ) {
			self::json_return( true, '', array( 'entries' => array(), 'current_timestamp' => $current_timestamp, 'latest_timestamp' => null ) );
		}

		$latest_timestamp = 0;

		$entries_for_json = array();
		foreach( $entries as $entry ) {
			$latest_timestamp = max( $latest_timestamp, $entry->get_timestamp() );
			$entries_for_json[] = $entry->for_json();
		}

		$result_for_json = array(
			'entries' => $entries_for_json,
			'current_timestamp' => $current_timestamp,
			'latest_timestamp' => $latest_timestamp,
		);

		self::json_return( true, '', $result_for_json );
	}

	function is_viewing_liveblog_post() {
		return is_single() && self::is_liveblog_post();
	}

	function is_initial_page_request() {
		global $wp_query;
		// Not using get_query_var since it returns '' for all requests, which is a valid for /post-name/liveblog/
		return ! isset( $wp_query->query_vars['liveblog'] );
	}


	function is_entries_ajax_request() {
		return (bool)get_query_var( self::url_endpoint );
	}

	function get_timestamps_from_query() {
		$timestamps = explode( '/', get_query_var( self::url_endpoint) );
		if ( 2 != count( $timestamps ) ) {
			return array( false, false );
		}
		$timestamps = array_map( 'intval', $timestamps );
		return $timestamps;
	}

	function ajax_insert_entry() {
		self::ajax_current_user_can_edit_liveblog();
		self::ajax_check_nonce();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id )
			self::json_return( false, __( "Sorry, that's an invalid post_id", 'liveblog' ) );

		$user = wp_get_current_user();

		$entry_content = wp_filter_post_kses( $_POST['entry_content'] ); // these should have the same kses rules as posts

		$entry = array(
			'comment_post_ID' => $post_id,
			'comment_content' => $entry_content,

			'comment_approved' => self::key,
			'comment_type' => self::key,

			'user_id' => $user->ID,
			// TODO: Should we be adding this or generating dynamically?
			'comment_author' => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url' => $user->user_url,
			// Borrowed from core as wp_insert_comment does not generate them
			'comment_author_IP' => preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] ),
			'comment_agent' => substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ),
		);

		$new_comment_id = wp_insert_comment( $entry );

		if ( !$new_comment_id ) {
			self::json_return( false, __( 'Error posting entry!' ) );
		}
		self::json_return( true, '' );
	}

	function entry_output( $entry ) {
	}

	function add_comment_class( $classes ) {
		$classes[] = 'liveblog-entry';
		return $classes;
	}

	function enqueue_scripts() {
		if ( ! self::is_viewing_liveblog_post() )
			return;

		wp_enqueue_script( 'liveblog', plugins_url( 'js/liveblog.js', __FILE__ ), array( 'jquery' ), self::version, true );
		wp_enqueue_style( 'liveblog', plugins_url( 'css/liveblog.css', __FILE__ ) );

		if ( self::current_user_can_edit_liveblog() )
			wp_enqueue_script( 'liveblog-publisher', plugins_url( 'js/liveblog-publisher.js', __FILE__ ), array( 'liveblog' ), self::version, true );

		if ( wp_script_is( 'jquery.spin', 'registered' ) ) {
			wp_enqueue_script( 'jquery.spin' );
		} else {
			wp_enqueue_script( 'spin', plugins_url( 'js/spin.js', __FILE__ ), false, '1.2.4' );
			wp_enqueue_script( 'jquery.spin', plugins_url( 'js/jquery.spin.js', __FILE__ ), array( 'jquery', 'spin' ) );
		}

		$liveblog_settings = apply_filters( 'liveblog_settings', array(
			'key' => self::key,
			'nonce_key' => self::nonce_key,
			'permalink' => get_permalink(),
			'post_id' => get_the_ID(),
			'latest_entry_timestamp' => self::$entries->get_latest_timestamp(),

			'refresh_interval' => self::refresh_interval,
			'max_retries' => self::max_retries,
			'delay_threshold' => self::delay_threshold,
			'delay_multiplier' => self::delay_multiplier,

			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'entriesurl' => self::get_entries_endpoint_url(),

			// i18n
			'update_nag_singular' => __( '%d new update', 'liveblog' ), // TODO: is there a better way to do _n via js?
			'update_nag_plural' => __( '%d new updates', 'liveblog' ),
		) );
		wp_localize_script( 'liveblog', 'liveblog_settings', $liveblog_settings );
	}

	function add_liveblog_to_content( $content ) {
		$entries = self::$entries->get( array( 'order' => 'ASC' ) );
		$entries = array_reverse( $entries );

		$liveblog_output = '';
		$liveblog_output .= '<div id="liveblog-'. self::$post_id .'" class="liveblog-container">';
		$liveblog_output .= '<div class="liveblog-actions">';
		$liveblog_output .= self::get_entry_editor_output();
		$liveblog_output .= '</div>';
		$liveblog_output .= '<div class="liveblog-entries">';
		foreach ( (array) $entries as $entry ) {
			$liveblog_output .= $entry->render();
		}

		$liveblog_output .= '</div>';
		$liveblog_output .= '</div>';

		return $content . $liveblog_output;
	}

	function get_entry_editor_output() {
		if ( ! self::current_user_can_edit_liveblog() )
			return;

		$editor_output = '';
		$editor_output .= '<textarea id="liveblog-form-entry" name="liveblog-form-entry"></textarea>';
		$editor_output .= '<input type="button" id="liveblog-form-entry-submit" class="button" value="'. esc_attr__( 'Post Update' ) . '" />';
		$editor_output .= '<span id="liveblog-submit-spinner"></span>';
		$editor_output .= wp_nonce_field( self::nonce_key, self::nonce_key, false, false );

		return $editor_output;
	}


	function is_liveblog_post( $post_id = null ) {
		if ( empty( $post_id ) ) {
			global $post;
			$post_id = $post->ID;
		}
		return get_post_meta( $post_id, self::key, true );
	}

	function add_meta_box( $post_type ) {
		if ( post_type_supports( $post_type, self::key ) )
			add_meta_box( self::key, __( 'Liveblog', 'liveblog' ), array( __CLASS__, 'display_meta_box' ) );
	}
	function display_meta_box( $post ) {
		?>
		<label>
			<input type="checkbox" name="is-liveblog" id="is-liveblog" value="1" <?php checked( self::is_liveblog_post( $post->ID ) ); ?> />
			<?php esc_html_e( 'This is a liveblog', 'liveblog' ); ?>
		</label>
		<?php
		wp_nonce_field( 'liveblog_nonce', 'liveblog_nonce', false );
	}
	function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['liveblog_nonce'] ) || ! wp_verify_nonce( $_POST['liveblog_nonce'], 'liveblog_nonce' ) )
			return;

		if ( isset( $_POST['is-liveblog'] ) )
			update_post_meta( $post_id, self::key, 1 );
		else
			delete_post_meta( $post_id, self::key );
	}

	function get_entries_endpoint_url() {
		return trailingslashit( get_permalink( self::$post_id ) . self::url_endpoint );
	}

	function ajax_current_user_can_edit_liveblog() {
		if ( ! self::current_user_can_edit_liveblog() ) {
			self::json_return( false, __( 'Cheatin\', uh?', 'liveblog' ) );
		}
	}
	function current_user_can_edit_liveblog() {
		return current_user_can( apply_filters( 'liveblog_edit_cap', self::edit_cap ) );
	}

	function ajax_check_nonce( $action = 'liveblog_nonce' ) {
		if ( ! isset( $_REQUEST[ self::nonce_key ] ) || ! wp_verify_nonce( $_REQUEST[ self::nonce_key ], $action ) ) {
			self::json_return( false, __( 'Sorry, we could not authenticate you.', 'liveblog' ) );
		}
	}

	function json_return( $success, $message, $data = array() ) {
		$return = json_encode( array(
			'status' => intval( $success ),
			'message' => $message,
			'data' => $data,
		) );

		header( 'Content-Type: application/json' );
		if ( self::$do_not_cache_response ) {
			nocache_headers();
		}
		echo $return;
		exit;
	}

}

WPCOM_Liveblog::load();
endif;
