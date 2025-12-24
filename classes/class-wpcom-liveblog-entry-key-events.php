<?php
/**
 * Key events functionality for liveblog entries.
 *
 * @package Liveblog
 */

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
	 * Meta key for key entries.
	 *
	 * @var string
	 */
	const META_KEY = 'liveblog_key_entry';

	/**
	 * Meta value for key entries.
	 *
	 * @var string
	 */
	const META_VALUE = 'true';

	/**
	 * Meta key for template.
	 *
	 * @var string
	 */
	const META_KEY_TEMPLATE = '_liveblog_key_entry_template';

	/**
	 * Meta key for format.
	 *
	 * @var string
	 */
	const META_KEY_FORMAT = '_liveblog_key_entry_format';

	/**
	 * Meta key for limit.
	 *
	 * @var string
	 */
	const META_KEY_LIMIT = '_liveblog_key_entry_limit';

	/**
	 * Available templates to render entries.
	 *
	 * @var array
	 */
	protected static $available_templates = array(
		'timeline' => array( 'liveblog-key-single-timeline.php', 'ul', 'liveblog-key-timeline' ),
		'list'     => array( 'liveblog-key-single-list.php', 'ul', 'liveblog-key-list' ),
	);

	/**
	 * Available content formats.
	 *
	 * @var array
	 */
	protected static $available_formats = array(
		'first-linebreak' => array( __CLASS__, 'format_content_first_linebreak' ),
		'first-sentence'  => array( __CLASS__, 'format_content_first_sentence' ),
		'full'            => false,
	);

	/**
	 * Called by WPCOM_Liveblog::load(), it attaches the
	 * new command and shortcode.
	 *
	 * @return void
	 */
	public static function load() {

		// Hook into the WordPress init filter to make
		// sure the templates are registered.
		add_action( 'init', array( __CLASS__, 'add_templates' ), 11 );

		// Hook into the liveblog_active_commands
		// filter to append the /key command.
		add_filter( 'liveblog_active_commands', array( __CLASS__, 'add_key_command' ), 10 );

		// Hook into the liveblog_entry_for_json filter
		// to inject the rendered key template.
		add_filter( 'liveblog_entry_for_json', array( __CLASS__, 'render_key_template' ), 10, 2 );

		// Hook into the liveblog_admin_add_settings filter
		// to add the key event admin options.
		add_filter( 'liveblog_admin_add_settings', array( __CLASS__, 'add_admin_options' ), 10, 2 );

		// Add the liveblog_key_events shortcode.
		add_shortcode( 'liveblog_key_events', array( __CLASS__, 'shortcode' ) );

		// Hook into the after action for the key
		// command to run the key command.
		add_action( 'liveblog_command_key_after', array( __CLASS__, 'add_key_action' ), 10, 3 );

		// Hook into the liveblog_admin_settings_update action
		// to save the key event template.
		add_action( 'liveblog_admin_settings_update', array( __CLASS__, 'save_template_option' ), 10, 3 );
	}

	/**
	 * Add templates for the key events on init
	 * so theme templates can add their own.
	 *
	 * @return void
	 */
	public static function add_templates() {

		// Allow plugins, themes, etc. to modify
		// the available key templates.
		self::$available_templates = apply_filters( 'liveblog_key_templates', self::$available_templates );

		// Allow plugins, themes, etc. to modify
		// the available key formats.
		self::$available_formats = apply_filters( 'liveblog_key_formats', self::$available_formats );
	}

	/**
	 * Adds the /key command and sets which function
	 * is to handle the action.
	 *
	 * @param array $commands The existing commands.
	 * @return array Modified commands.
	 */
	public static function add_key_command( $commands ) {

		// Append the key command.
		$commands[] = 'key';

		return $commands;
	}

	/**
	 * Check if entry is key event by checking its meta.
	 *
	 * @param int $id The entry ID.
	 * @return bool True if key event.
	 */
	public static function is_key_event( $id ) {
		if ( self::META_VALUE === get_comment_meta( $id, self::META_KEY, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Called when the /key command is used in an entry,
	 * it attaches meta to set it as a key entry.
	 *
	 * @param string $content The entry content.
	 * @param int    $id      The entry ID.
	 * @param int    $post_id The post ID.
	 * @return void
	 */
	public static function add_key_action( $content, $id, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by liveblog_command_key_after action signature.
		add_comment_meta( $id, self::META_KEY, self::META_VALUE );
	}

	/**
	 * Remove key event entry.
	 *
	 * @param string $content The entry content.
	 * @param int    $id      The entry ID.
	 * @return string Modified content.
	 */
	public static function remove_key_action( $content, $id ) {
		delete_comment_meta( $id, self::META_KEY, self::META_VALUE );
		return str_replace( '/key', '', $content );
	}

	/**
	 * Pass a separate template for key event shortcode.
	 *
	 * @param array                $entry        The entry data.
	 * @param WPCOM_Liveblog_Entry $entry_object The entry object.
	 * @return array Modified entry data.
	 */
	public static function render_key_template( $entry, $entry_object ) {

		// We need the post_id to get its template.
		$post_id = $entry_object->get_post_id();

		// Get the entry content.
		$content = $entry_object->get_content();

		// Set if key event.
		$entry['key_event'] = self::is_key_event( $entry['id'] );

		// If key event add content.
		if ( $entry['key_event'] ) {
			$entry['key_event_content'] = self::get_formatted_content( $content, $post_id );
		}

		return $entry;
	}

	/**
	 * Handle the save of the admin options related
	 * to the key events template box.
	 *
	 * @param array $response The response data.
	 * @param int   $post_id  The post ID.
	 * @return void
	 */
	public static function save_template_option( $response, $post_id ) {

		// Only save / update the template option if the response
		// state is `liveblog-key-template-save` and the
		// `liveblog-key-template-name` is not empty.
		if ( 'liveblog-key-template-save' === $response['state'] && ! empty( $response['liveblog-key-template-name'] ) ) {

			// The default template.
			$template = 'timeline';

			// Grab the template from the available templates if it exists.
			if ( isset( self::$available_templates[ $response['liveblog-key-template-name'] ] ) ) {
				$template = $response['liveblog-key-template-name'];
			}

			// Store this template on the post.
			update_post_meta( $post_id, self::META_KEY_TEMPLATE, $template );

			// The default format.
			$format = 'first-linebreak';

			// Grab the format from the available formats if it exists.
			if ( isset( self::$available_formats[ $response['liveblog-key-template-format'] ] ) ) {
				$format = $response['liveblog-key-template-format'];
			}

			// Store this format on the post.
			update_post_meta( $post_id, self::META_KEY_FORMAT, $format );

			// If isn't a valid number turns it into 0, which returns all key events.
			$limit = absint( $response['liveblog-key-limit'] );
			if ( isset( $limit ) ) {
				update_post_meta( $post_id, self::META_KEY_LIMIT, $limit );
			}
		}
	}

	/**
	 * Add an input for the user to pick a template.
	 *
	 * @param array $extra_fields The existing extra fields.
	 * @param int   $post_id      The post ID.
	 * @return array Modified extra fields.
	 */
	public static function add_admin_options( $extra_fields, $post_id ) {

		// Add the custom template fields to the editor.
		$extra_fields[] = WPCOM_Liveblog::get_template_part(
			'liveblog-key-admin.php',
			array(
				'current_key_template' => get_post_meta( $post_id, self::META_KEY_TEMPLATE, true ),
				'current_key_format'   => get_post_meta( $post_id, self::META_KEY_FORMAT, true ),
				'current_key_limit'    => get_post_meta( $post_id, self::META_KEY_LIMIT, true ),
				'key_name'             => __( 'Template:', 'liveblog' ),
				'key_format_name'      => __( 'Format:', 'liveblog' ),
				'key_description'      => __( 'Set template for key events shortcode, select a format and restrict most recent shown.', 'liveblog' ),
				'key_limit'            => __( 'Limit', 'liveblog' ),
				'key_button'           => __( 'Save', 'liveblog' ),
				'templates'            => array_keys( self::$available_templates ),
				'formats'              => array_keys( self::$available_formats ),
			)
		);

		return $extra_fields;
	}

	/**
	 * Returns the current template for that post.
	 *
	 * @param int $post_id The post ID.
	 * @return array The template configuration.
	 */
	public static function get_current_template( $post_id ) {

		// Get the template meta from the post.
		$type = get_post_meta( $post_id, self::META_KEY_TEMPLATE, true );

		// If the post has a template set, return that.
		if ( ! empty( $type ) ) {
			return self::$available_templates[ $type ];
		}

		// If not, return the default 'timeline' template.
		return self::$available_templates['timeline'];
	}

	/**
	 * Returns the current format of content.
	 *
	 * @param int $post_id The post ID.
	 * @return callable|false The format callback or false.
	 */
	public static function get_current_format( $post_id ) {

		// Get the format meta from the post.
		$type = get_post_meta( $post_id, self::META_KEY_FORMAT, true );

		// If the post has a format set, return that.
		if ( ! empty( $type ) ) {
			return self::$available_formats[ $type ];
		}

		// If not, return the default 'first-line' format.
		return self::$available_formats['first-linebreak'];
	}

	/**
	 * Calls the function to format the content.
	 *
	 * @param string $content The content to format.
	 * @param int    $post_id The post ID.
	 * @return string Formatted content.
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
	 * Grab first sentence.
	 *
	 * @param string $content The content to format.
	 * @return string Formatted content.
	 */
	public static function format_content_first_sentence( $content ) {

		// Grab the first sentence of the content.
		$content = preg_replace( '/(.*?[?!.](?=\s|$)).*/', '\\1', $content );

		// Strip it of all non-accepted tags.
		$content = wp_strip_all_tags( $content, '<strong></strong><em></em><span></span><img>' );

		return $content;
	}


	/**
	 * Grab first paragraph/linebreak.
	 *
	 * @param string $content The content to format.
	 * @return string Formatted content.
	 */
	public static function format_content_first_linebreak( $content ) {

		// Standardise returns into <br /> for linebreaks.
		$content = str_replace( array( "\r", "\n" ), '<br />', $content );

		// Explode the content by the linebreaks.
		$content = explode( '<br />', $content );

		// Strip it of all non-accepted tags.
		$content = wp_strip_all_tags( $content[0], '<strong></strong><em></em><span></span><img>' );

		return $content;
	}

	/**
	 * Builds the box to display key entries.
	 *
	 * @param array $atts The shortcode attributes.
	 * @return string|null The shortcode output.
	 */
	public static function shortcode( $atts ) {
		global $post;

		if ( ! is_single() ) {
			return;
		}

		// Define the default shortcode attributes.
		$atts = shortcode_atts(
			array(
				'title' => 'Key Events',
			),
			$atts
		);

		// The args to pass into the entry query.
		$args = array(
			'meta_key'   => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => self::META_VALUE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		);

		$limit = get_post_meta( $post->ID, self::META_KEY_LIMIT, true );
		if ( isset( $limit ) ) {
			$args['number'] = $limit;
		}

		// Build the entry query.
		$entry_query = new WPCOM_Liveblog_Entry_Query( $post->ID, WPCOM_Liveblog::KEY );

		// Execute the entry query with the previously defined args.
		$entries = (array) $entry_query->get_all( $args );

		// Grab the template to use.
		$template = self::get_current_template( $post->ID );

		// Only run the shortcode on an archived or enabled post.
		if ( WPCOM_Liveblog::get_liveblog_state( $post->ID ) ) {

			// Render the actual template.
			return WPCOM_Liveblog::get_template_part(
				'liveblog-key-events.php',
				array(
					'entries'  => $entries,
					'title'    => $atts['title'],
					'template' => $template[0],
					'wrap'     => $template[1],
					'class'    => $template[2],
				)
			);
		}
	}

	/**
	 * Get all key events.
	 *
	 * @return array Array of key events.
	 */
	public static function all() {
		$query      = new WPCOM_Liveblog_Entry_Query( WPCOM_Liveblog::$post_id, WPCOM_Liveblog::KEY );
		$key_events = $query->get(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_KEY,
						'value'   => self::META_VALUE,
						'compare' => '===',
					),
				),
			)
		);

		if ( null === $key_events ) {
			return array();
		}
		return $key_events;
	}
}
