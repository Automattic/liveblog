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
-- Added caching for get_recent_entries?
-- Fix Batcache issues
-- Show updates server-side on initial load? (problem is that batcache would show an old set; although, timestamp would also be old so we'd still fetch the necessary entries)

TODO (future):
-- PHP and JS Actions/Filters/Triggers
-- Change "Read More" to "View Liveblog"
-- Manual refresh button
-- Allow marking of liveblog as ended
-- Allow comment modifications; need to store modified date as comment_meta
-- Drag-and-drop image uploading support

*/

if ( ! class_exists( 'WPCOM_Liveblog' ) ) :

class WPCOM_Liveblog {

	const version = 0.1;
	const key = 'liveblog';
	const url_endpoint = 'liveblog';
	const edit_cap = 'publish_posts';
	const nonce_key = 'liveblog_nonce';

	const refresh_interval = 15; // how often should we refresh
	const max_retries = 100; // max number of failed tries before polling is disabled
	const delay_threshold = 10; // how many failed tries after which we should increase the refresh interval
	const delay_multiplier = 1.5; // by how much should we inscrease the refresh interval

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
		// Check that current template is a liveblog
		if ( ! self::is_viewing_liveblog_post() )
			return;

		// Check that this isn't a liveblog AJAX request
		if ( self::is_liveblog_request() ) {
			add_filter( 'the_content', array( __CLASS__, 'add_liveblog_to_content' ) );
			return;
		}

		$timestamp = get_query_var( 'liveblog' );
		$entries = self::ajax_get_recent_entries( $timestamp );
	}
	function is_liveblog_request() {
		global $wp_query;
		// Not using get_query_var since it returns '' for all requests, which is a valid for /post-name/liveblog/
		return ! isset( $wp_query->query_vars['liveblog'] );
	}
	function ajax_insert_entry() {
		self::_ajax_current_user_can_edit_liveblog();
		self::_ajax_check_nonce();

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id )
			self::json_return( false, __( 'Sorry, that\'s an invalid post_id', 'liveblog' ) );

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

		wp_insert_comment( $entry );

		self::refresh_last_entry_timestamp();

		self::json_return( true, '' );
	}

	function ajax_insert_image() {
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		//
	}

	function ajax_get_recent_entries( $timestamp = 0 ) {
		// When a new update is added, a cache key should be set/updated to the current GMT timestamp.
		// The AJAX call can then just check that value and immediately abort if it knows that there is no
		// new data to get yet without having to actually ask WordPress if there are new posts.

		$last_timestamp = self::get_last_entry_timestamp();
		if ( $last_timestamp && $timestamp >= $last_timestamp ) {
			self::json_return( true, '', array( 'timestamp' => $last_timestamp ) );
		}

		$entries_output = array();
		$entries = self::get_entries_since( get_the_ID(), $timestamp );
		$filtered_entries = array();

		foreach( $entries as $entry ) {
			$filtered_entry = new stdClass;
			$filtered_entry->ID = $entry->comment_ID;
			$filtered_entry->content = self::entry_output( $entry, false );
			array_push( $filtered_entries, $filtered_entry );
		}

		if ( ! $last_timestamp )
			self::refresh_last_entry_timestamp();

		self::json_return( true, '', array( 'entries' => $filtered_entries, 'timestamp' => $last_timestamp ) );
	}

	function entry_output( $entry, $echo = true ) {
		$entry_id = $entry->comment_ID;
		$post_id = $entry->comment_post_ID;
		$output = '';

		// Allow plugins to override the output
		$output = apply_filters( 'liveblog_pre_entry_output', $output, $entry );
		if ( $output )
			return $output;

		$args = apply_filters( 'liveblog_entry_output_args', array(
			'avatar_size' => 30,
		) );

		$output .= '<div id="liveblog-entry-'. $entry_id .'" '. comment_class( '', $entry_id, $post_id, false ) . '>';
			$output .= '<div class="liveblog-entry-text">';
				$output .= get_comment_text( $entry_id );
			$output .= '</div>';

			$output .= '<header class="liveblog-meta">';
				$output .= '<span class="liveblog-author-avatar">';
					$output .= get_avatar( $entry->comment_author_email, $args['avatar_size'] );
				$output .= '</span>';

				$output .= '<span class="liveblog-author-name">'. get_comment_author_link( $entry_id ) .'</span>';
				$output .= '<span class="liveblog-meta-time">';
					$output .= '<a href="#liveblog-entry-'. $entry_id .'">';
					$output .= sprintf( __('%1$s at %2$s'), get_comment_date( '', $entry_id ), get_comment_date( 'g:i a', $entry_id ) );
					$output .= '</a>';
				$output .= '</time>';
			$output .= '</header>';
		$output .= '</div>';

		$output = apply_filters( 'liveblog_entry_output', $output, $entry );
		if ( ! $echo )
			return $output;
		echo $output;
	}

	function add_comment_class( $classes ) {
		$classes[] = 'liveblog-entry';
		return $classes;
	}

	function get_entries_since( $post_id, $timestamp = 0 ) {
		add_filter( 'comments_clauses', array( __CLASS__, 'comments_where_include_liveblog_status' ), false, 2 );
		$entries = get_comments( array(
			'post_id' => $post_id,
			'orderby' => 'comment_date_gmt',
			'order' => 'ASC',
			'type' => self::key,
		) );
		remove_filter( 'comments_clauses', array( __CLASS__, 'comments_where_include_liveblog_status' ), false );

		$filtered_entries = array();

		foreach( $entries as $entry ) {
			if ( $timestamp && strtotime( $entry->comment_date_gmt ) <= $timestamp )
				continue;

			$filtered_entries[ $entry->comment_ID ] = $entry;
		}
		$entries = $filtered_entries;

		return $entries;
	}

	function comments_where_include_liveblog_status( $clauses, $query ) {
		global $wpdb;
		// TODO: should we only fetch the posts we want based on the timestamp in the current context?
		$clauses[ 'where' ] = $wpdb->prepare( 'comment_post_ID = %d AND comment_type = %s AND comment_approved = %s', $query->query_vars['post_id'], self::key, self::key );
		return $clauses;
	}

	function enqueue_scripts() {
		if ( ! self::is_viewing_liveblog_post() )
			return;

		wp_enqueue_script( 'liveblog', plugins_url( 'js/liveblog.js', __FILE__ ), array( 'jquery' ), self::version, true );
		wp_enqueue_style( 'liveblog', plugins_url( 'css/liveblog.css', __FILE__ ) );

		if ( self::_current_user_can_edit_liveblog() )
			wp_enqueue_script( 'liveblog-publisher', plugins_url( 'js/liveblog-publisher.js', __FILE__ ), array( 'liveblog' ), self::version, true );

		$liveblog_settings = apply_filters( 'liveblog_settings', array(
			'key' => self::key,
			'nonce_key' => self::nonce_key,
			'permalink' => get_permalink(),
			'post_id' => get_the_ID(),
			'last_timestamp' => self::get_last_entry_timestamp(),

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
		$post_id = get_the_ID();
		$entries = self::get_entries_since( $post_id );
		$entries = array_reverse( $entries );

		$liveblog_output = '';
		$liveblog_output .= '<div id="liveblog-'. $post_id .'" class="liveblog-container">';
		$liveblog_output .= '<div class="liveblog-actions">';
		$liveblog_output .= self::get_entry_editor_output();
		$liveblog_output .= '</div>';
		$liveblog_output .= '<div class="liveblog-entries">';
		foreach ( (array) $entries as $entry ) {
			$liveblog_output .= self::entry_output( $entry, false );
		}

		$liveblog_output .= '</div>';
		$liveblog_output .= '</div>';

		return $content . $liveblog_output;
	}

	function get_entry_editor_output() {
		if ( ! self::_current_user_can_edit_liveblog() )
			return;

		$editor_output = '';
		$editor_output .= '<textarea id="liveblog-form-entry" name="liveblog-form-entry"></textarea>';
		//$editor_output .= '<a href="#" id="liveblog-form-entry-submit" class="button">Submit</a>';
		$editor_output .= '<input type="button" id="liveblog-form-entry-submit" class="button" value="'. __( 'Post Update' ) . '" />';
		$editor_output .= wp_nonce_field( self::nonce_key, self::nonce_key, false, false );

		return $editor_output;
	}

	function is_viewing_liveblog_post() {
		return is_single() && self::is_liveblog_post();
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


	function refresh_last_entry_timestamp( $timestamp = null ) {
		if ( ! $timestamp )
			$timestamp = current_time( 'timestamp', 1 ); // always work against gmt

		set_transient( 'liveblog-last-entry-timestamp', $timestamp );

		return $timestamp;
	}
	function get_last_entry_timestamp() {
		return get_transient( 'liveblog-last-entry-timestamp' );
	}

	function get_entries_endpoint_url( $post_id = 0, $timestamp = '' ) {
		$url = trailingslashit( get_permalink( $post_id ) . self::url_endpoint );
		if ( $timestamp )
			$url .= trailingslashit( $timestamp );
		return $url;
	}

	function _ajax_current_user_can_edit_liveblog() {
		if ( ! self::_current_user_can_edit_liveblog() ) {
			self::json_return( false, __( 'Cheatin\', uh?', 'liveblog' ) );
		}
	}
	function _current_user_can_edit_liveblog() {
		return current_user_can( apply_filters( 'liveblog_edit_cap', self::edit_cap ) );
	}

	function _ajax_check_nonce( $action = 'liveblog_nonce' ) {
		if ( ! isset( $_REQUEST[ self::nonce_key ] ) || ! wp_verify_nonce( $_REQUEST[ self::nonce_key ], $action ) ) {
			self::json_return( false, __( 'Sorry, we could not authenticate you.', 'liveblog' ) );
		}
	}

	function json_return( $success, $message, $data = array(), $echo_and_exit = true ) {
		$return = json_encode( array(
			'status' => intval( $success ),
			'message' => $message,
			'data' => $data,
		) );

		if ( $echo_and_exit ) {
			header( 'Content-Type: application/json' );
			echo $return;
			exit;
		} else {
			return $return;
		}
	}

}

WPCOM_Liveblog::load();
endif;
