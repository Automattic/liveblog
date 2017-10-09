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
	const meta_key_template = '_liveblog_key_entry_template';
	const meta_key_format   = '_liveblog_key_entry_format';
	const meta_key_limit    = '_liveblog_key_entry_limit';

	/**
	 * Template to render entries
	 */
	protected static $available_templates = array(
		'timeline' => array( 'liveblog-key-single-timeline.php', 'ul', 'liveblog-key-timeline' ),
		'list'     => array( 'liveblog-key-single-list.php', 'ul', 'liveblog-key-list' ),
	);

	/**
	 * Available content formats
	 */
	protected static $available_formats = array(
		'first-linebreak' => array( __CLASS__, 'format_content_first_linebreak' ),
		'first-sentence'  => array( __CLASS__, 'format_content_first_sentence' ),
		'full'            => false,
	);

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * new command and shortcode.
	 */
	public static function load() {

		// Hook into the WordPress init filter to make
		// sure the templates are registered.
		add_action( 'init',                           array( __CLASS__, 'add_templates' ), 11 );

		// Hook into the liveblog_active_commands
		// filter to append the /key command.
		add_filter( 'liveblog_active_commands',       array( __CLASS__, 'add_key_command' ), 10 );

		// Hook into the liveblog_entry_for_json filter
		// to inject the rendered key template.
		add_filter( 'liveblog_entry_for_json',        array( __CLASS__, 'render_key_template' ), 10, 2 );

		// Hook into the liveblog_admin_add_settings filter
		// to add the key event admin options.
		add_filter( 'liveblog_admin_add_settings',    array( __CLASS__, 'add_admin_options' ), 10, 2 );

		// Add the liveblog_key_events shortcode.
		add_shortcode( 'liveblog_key_events',         array( __CLASS__, 'shortcode' ) );

		// Hook into the after action for the key
		// command to run the key command.
		add_action( 'liveblog_command_key_after',     array( __CLASS__, 'add_key_action' ), 10, 3 );

		// Hook into the liveblog_admin_settings_update action
		// to save the key event template.
		add_action( 'liveblog_admin_settings_update', array( __CLASS__, 'save_template_option' ), 10, 3 );
	}

	/**
	 * Add templates for the key events on init
	 * so theme templates can add their own
     */
	public static function add_templates() {

		// Allow plugins, themes, etc. to modify
		// the available key templates.
		self::$available_templates = apply_filters( 'liveblog_key_templates', self::$available_templates );

		// Allow plugins, themes, etc. to modify
		// the available key formats.
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

		// Append the key command.
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
	 * Remove key event entry
	 *
	 * @param $id
	 */
	public static function remove_key_action( $id ) {
		return delete_comment_meta( $id, self::meta_key, self::meta_value );
	}

	/**
	 * Pass a separate template for key event shortcode
	 *
	 * @param $entry
	 * @param $object
	 * @return mixed
     */
	public static function render_key_template( $entry, $object ) {

		// We need the post_id to get it's template.
		$post_id      = $object->get_post_id();

		// Use the currently set template.
		$template     = self::get_current_template( $post_id );

		// Render that template and store it in the output.
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

		// Only save / update the template option if the response
		// state is `liveblog-key-template-save` and the
		// `liveblog-key-template-name` is not empty.
		if ( 'liveblog-key-template-save' == $response['state'] && ! empty( $response['liveblog-key-template-name'] ) ) {

			// The default template.
			$template = 'timeline';

			// Grab the template from the available templates if it exists.
			if ( isset( self::$available_templates[ $response['liveblog-key-template-name'] ] ) ) {
				$template = $response['liveblog-key-template-name'];
			}

			// Store this template on the post.
			update_post_meta( $post_id, self::meta_key_template, $template );

			// The default format.
			$format = 'first-linebreak';

			// Grab the format from the available formats if it exists.
			if ( isset( self::$available_formats[ $response['liveblog-key-template-format'] ] ) ) {
				$format = $response['liveblog-key-template-format'];
			}

			// Store this format on the post.
			update_post_meta( $post_id, self::meta_key_format, $format );

			// If isn't a valid number turns it into 0, which returns all key events
			$limit = absint( $response['liveblog-key-limit'] );
			if ( isset( $limit ) ) {
				update_post_meta( $post_id, self::meta_key_limit, $limit );
			}
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

		// Add the custom template fields to the editor.
		$extra_fields[] = WPCOM_Liveblog::get_template_part( 'liveblog-key-admin.php', array(
			'current_key_template' => get_post_meta( $post_id, self::meta_key_template, true ),
			'current_key_format'   => get_post_meta( $post_id, self::meta_key_format, true ),
			'current_key_limit'    => get_post_meta( $post_id, self::meta_key_limit, true ),
			'key_name'             => __( 'Template:', 'liveblog' ),
			'key_format_name'      => __( 'Format:', 'liveblog' ),
			'key_description'      => __( 'Set template for key events shortcode, select a format and restrict most recent shown.', 'liveblog' ),
			'key_limit'            => __( 'Limit', 'liveblog' ),
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

		// Get the template meta from the post.
		$type = get_post_meta( $post_id, self::meta_key_template, true );

		// If the post has a template set, return that.
		if ( ! empty( $type ) ) {
			return self::$available_templates[$type];
		}

		// If not, return the default 'timeline' template.
		return self::$available_templates['timeline'];
	}

	/**
	 * Returns the current format of content
	 *
	 * @param $post_id
	 * @return mixed
     */
	public static function get_current_format( $post_id ) {

		// Get the format meta from the post.
		$type = get_post_meta( $post_id, self::meta_key_format, true );

		// If the post has a format set, return that.
		if ( ! empty( $type ) ) {
			return self::$available_formats[$type];
		}

		// If not, return the default 'first-line' format.
		return self::$available_formats['first-linebreak'];
	}

	/**
	 * Calls the function to format the content.
	 *
	 * @param $content
	 * @param $post_id
	 * @return mixed
     */
	public static function get_formatted_content( $content, $post_id ) {

		// If there is a format currently set that isn't raw.
		if ( self::get_current_format( $post_id ) ) {

			// Then format the content with that formatter.
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

		// Grab the first sentence of the content.
		$content = preg_replace('/(.*?[?!.](?=\s|$)).*/', '\\1', $content);

		// Strip it of all non-accepted tags.
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

		// Standardise returns into <br /> for linebreaks.
		$content = str_replace( array( "\r", "\n" ), '<br />', $content);

		// Explode the content by the linebreaks.
		$content = explode('<br />', $content);

		// Strip it of all non-accepted tags.
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

		// Define the default shortcode attributes.
		$atts = shortcode_atts( array(
			'title' => 'Key Events',
		), $atts );

		// The args to pass into the entry query.
		$args = array(
			'meta_key'   => self::meta_key,
			'meta_value' => self::meta_value,
		);

		$limit = get_post_meta( $post->ID, self::meta_key_limit, true );
		if ( isset( $limit ) ) {
			$args['number'] = $limit;
		}

		// Build the entry query.
		$entry_query = new WPCOM_Liveblog_Entry_Query( $post->ID, WPCOM_Liveblog::key );

		// Execute the entry query with the previously defined args.
		$entries     = (array) $entry_query->get_all( $args );

		// Grab the template to use.
		$template    = self::get_current_template( $post->ID );

		// Only run the shortcode on an archived or enabled post.
		if ( WPCOM_Liveblog::get_liveblog_state( $post->ID ) ) {

			// Render the actual template.
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
