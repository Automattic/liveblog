<?php

/**
 * Class WPCOM_Liveblog_Entry_Key_Events
 *
 * Adds the /key command which attaches meta data to that
 * liveblog entry. This meta data is then used by the
 * shortcode [liveblog_key_events] to build a list of
 * key events.
 */
class WPCOM_Liveblog_Entry_Key_Events {

	/**
	 * Set the meta_key and meta_value
	 */
	const meta_key          = 'liveblog_key_entry';
	const meta_value        = 'true';
	const meta_key_template = 'liveblog_key_entry_template';
	const meta_key_format   = 'liveblog_key_entry_format';

	/**
	 * Template to render entries
	 */
	protected static $available_templates = array(
		'list'     => array( 'liveblog-key-single-list.php', 'ul', 'liveblog-key-list' ),
		'timeline' => array( 'liveblog-key-single-timeline.php', 'ul', 'liveblog-key-timeline' ),
	);
	protected static $available_formats = array(
		'first-sentence'  => array( __CLASS__, 'format_content_first_sentence' ),
		'first-linebreak' => array( __CLASS__, 'format_content_first_linebreak' ),
		'full'            => false,
	);

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * new command and shortcode.
	 */
	public static function load() {
		add_action( 'init',                           array( __CLASS__, 'add_templates' ), 11 );
		add_filter( 'liveblog_active_commands',       array( __CLASS__, 'add_key_command' ), 10 );
		add_filter( 'liveblog_entry_for_json',        array( __CLASS__, 'render_key_template' ), 10, 2 );
		add_filter( 'liveblog_admin_add_settings',    array( __CLASS__, 'add_admin_options' ), 10, 2 );
		add_shortcode( 'liveblog_key_events',         array( __CLASS__, 'shortcode' ) );
		add_action( 'liveblog_command_key_after',     array( __CLASS__, 'add_key_action' ), 10, 3 );
		add_action( 'liveblog_admin_settings_update', array( __CLASS__, 'save_template_option' ), 10, 3 );
	}

	/**
	 * Add templates for the key events on init
	 * so theme templates can add their own
     */
	public static function add_templates() {
		self::$available_templates = apply_filters( 'liveblog_key_templates', self::$available_templates );
		self::$available_formats   = apply_filters( 'liveblog_key_formats', self::$available_formats );
	}

	/**
	 * Adds the /key command and sets which function
	 * is to handle the action.
	 *
	 * @param $commands
	 * @return mixed
	 */
	public static function add_key_command( $commands ) {
		$commands[] = 'key';

		return $commands;
	}

	/**
	 * Called when the /key command is used in an entry,
	 * it attaches meta to set it as a key entry.
	 *
	 * @param $content
	 * @param $id
	 * @param $post_id
	 */
	public static function add_key_action( $content, $id, $post_id ) {
		add_comment_meta( $id, self::meta_key, self::meta_value );
	}

	/**
	 * Pass a separate template for key event shortcode
	 *
	 * @param $entry
	 * @param $object
	 * @return mixed
     */
	public static function render_key_template( $entry, $object ) {
		$post_id      = $object->get_post_id();
		$template     = self::get_current_template( $post_id );
		$entry['key'] = $object->render( $template[0] );
		return $entry;
	}

	/**
	 * Handle the save of the admin options related
	 * to the key events template box
	 *
	 * @param $response
	 * @param $post_id
     */
	public static function save_template_option( $response, $post_id ) {
		if ( 'liveblog-key-template-save' == $response['state'] && ! empty( $response['liveblog-key-template-name'] ) ) {

			$template = 'list';
			if ( isset( self::$available_templates[ $response['liveblog-key-template-name'] ] ) ) {
				$template = $response['liveblog-key-template-name'];
			}
			update_post_meta( $post_id, self::meta_key_template, $template );

			$format = 'first-sentence';
			if ( isset( self::$available_formats[ $response['liveblog-key-template-format'] ] ) ) {
				$format = $response['liveblog-key-template-format'];
			}
			update_post_meta( $post_id, self::meta_key_format, $format );
		}
	}

	/**
	 * Add a input for the user to pick a template.
	 *
	 * @param $extra_fields
	 * @param $post_id
	 * @return array
     */
	public static function add_admin_options( $extra_fields, $post_id ) {
		$extra_fields[] = WPCOM_Liveblog::get_template_part( 'liveblog-key-admin.php', array(
			'current_key_template' => get_post_meta( $post_id, self::meta_key_template, true ),
			'current_key_format'   => get_post_meta( $post_id, self::meta_key_format, true ),
			'key_name'             => __( 'Template:', 'liveblog' ),
			'key_format_name'      => __( 'Format:', 'liveblog' ),
			'key_description'      => __( 'Set template for key events shortcode. And select a format.', 'liveblog' ),
			'key_button'           => __( 'Save', 'liveblog' ),
			'templates'			   => array_keys( self::$available_templates ),
			'formats'              => array_keys( self::$available_formats ),
		) );
		return $extra_fields;
	}

	/**
	 * Returns the current for template for that post
	 *
	 * @param $post_id
	 * @return mixed
     */
	public static function get_current_template( $post_id ) {
		$type = get_post_meta( $post_id, self::meta_key_template, true );
		if ( ! empty( $type ) ) {
			return self::$available_templates[$type];
		}
		return self::$available_templates['list'];
	}

	/**
	 * Returns the current format of content
	 *
	 * @param $post_id
	 * @return mixed
     */
	public static function get_current_format( $post_id ) {
		$type = get_post_meta( $post_id, self::meta_key_format, true );
		if ( ! empty( $type ) ) {
			return self::$available_formats[$type];
		}
		return self::$available_formats['first-sentence'];
	}

	/**
	 * Calls the function to format the content.
	 *
	 * @param $content
	 * @param $post_id
	 * @return mixed
     */
	public static function get_formatted_content( $content, $post_id ) {
		if ( self::get_current_format( $post_id ) ) {
			$content = call_user_func( self::get_current_format( $post_id ), $content );
		}
		return $content;
	}

	/**
	 * Grab first sentence
	 *
	 * @param $content
	 * @return string
     */
	public static function format_content_first_sentence( $content ) {
		$content = preg_replace('/(.*?[?!.](?=\s|$)).*/', '\\1', $content);
		$content = strip_tags( $content , '<strong></strong><em></em><span></span><img>' );
		return $content;
	}


	/**
	 * Grab first paragraph/linebreak
	 *
	 * @param $content
	 * @return string
     */
	public static function format_content_first_linebreak( $content ) {
		$content = str_replace( array( "\r", "\n" ), '<br />', $content);
		$content = explode('<br />', $content);
		$content = strip_tags( $content[0] , '<strong></strong><em></em><span></span><img>' );
		return $content;
	}

	/**
	 * Builds the box to display key entries
	 *
	 * @param $atts
	 * @return mixed
	 */
	public static function shortcode( $atts ) {
		global $post;

		if ( ! is_single() ) {
			return;
		}

		$atts = shortcode_atts( array(
			'title' => 'Key Events',
		), $atts );

		$args = array(
			'meta_key'   => self::meta_key,
			'meta_value' => self::meta_value,
		);
		$entry_query = new WPCOM_Liveblog_Entry_Query( $post->ID, WPCOM_Liveblog::key );
		$entries     = (array) $entry_query->get_all( $args );
		$template    = self::get_current_template( $post->ID );

		if ( WPCOM_Liveblog::get_liveblog_state( $post->ID ) ) {
			return WPCOM_Liveblog::get_template_part( 'liveblog-key-events.php', array(
				'entries'  => $entries,
				'title'    => $atts['title'],
				'template' => $template[0],
				'wrap'     => $template[1],
				'class'    => $template[2],
			) );
		}
	}
}
