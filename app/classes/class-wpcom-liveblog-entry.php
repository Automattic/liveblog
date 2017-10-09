<?php

/**
 * Represents a liveblog entry
 */
class WPCOM_Liveblog_Entry {

	const default_avatar_size = 30;

	/**
	 * @var string In case the current entry is an edit (replaces) of
	 * another entry, we store the other entry's ID in this meta key.
	 */
	const replaces_meta_key   = 'liveblog_replaces';

	private $comment;
	private $type = 'new';
	private static $allowed_tags_for_entry;

	/**
	 * Define the Lookup array for any shortcodes that should be stripped and replaced
	 * upon new entry being posted or existing entry being updated.
	 *
	 * @var array|mixed|void
	 */
	public static $restricted_shortcodes = array(
		'liveblog_key_events' => '',
	);

	public function __construct( $comment ) {
		$this->comment  = $comment;
		$this->replaces = get_comment_meta( $comment->comment_ID, self::replaces_meta_key, true );
		if ( $this->replaces && $this->get_content() ) {
			$this->type = 'update';
		}
		if ( $this->replaces && !$this->get_content() ) {
			$this->type = 'delete';
		}
	}

	public static function generate_allowed_tags_for_entry() {
		/**
		 * Use html tags allowed for post as a base.
		 */
		self::$allowed_tags_for_entry = wp_kses_allowed_html( 'post' );
		/**
		 * Expand with additional tags that we want to allow.
		*/
		$additional_tags = array();
		$additional_tags['iframe'] = array(
			'src'             => array(),
			'height'          => array(),
			'width'           => array(),
			'frameborder'     => array(),
			'allowfullscreen' => array(),
		);
		$additional_tags['source'] = array(
			'src'           => array(),
			'type'          => array(),
		);

		self::$allowed_tags_for_entry = array_merge( $additional_tags,
			self::$allowed_tags_for_entry);
	}

	public static function from_comment( $comment ) {
		$entry = new WPCOM_Liveblog_Entry( $comment );
		return $entry;
	}

	public function get_id() {
		return $this->comment->comment_ID;
	}

	public function get_post_id() {
		return $this->comment->comment_post_ID;
	}

	public function get_content() {
		return $this->comment->comment_content;
	}

	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the GMT timestamp for the comment
	 *
	 * @return string
	 */
	public function get_timestamp() {
		return mysql2date( 'G', $this->comment->comment_date_gmt );
	}

	public function for_json() {
		$entry = array(
			'id'   => $this->replaces ? $this->replaces : $this->get_id(),
			'type' => $this->get_type(),
			'html' => $this->render(),
		);
		$entry = apply_filters( 'liveblog_entry_for_json', $entry, $this );
		return (object) $entry;
	}

	public function get_fields_for_render() {
		$entry_id     = $this->replaces ? $this->replaces : $this->comment->comment_ID;
		$post_id      = $this->comment->comment_post_ID;
		$avatar_size  = apply_filters( 'liveblog_entry_avatar_size', self::default_avatar_size );
		$comment_text = get_comment_text( $entry_id );
		$css_classes  = implode( ' ', get_comment_class( '', $entry_id, $post_id ) );
		$entry = array(
			'entry_id'              => $entry_id,
			'post_id'               => $post_id,
			'css_classes'           => $css_classes ,
			'content'               => self::render_content( $comment_text, $this->comment ),
			'original_content'      => apply_filters( 'liveblog_before_edit_entry', $comment_text ),
			'avatar_size'           => $avatar_size,
			'avatar_img'            => get_avatar( $this->comment->comment_author_email, $avatar_size ),
			'author_link'           => get_comment_author_link( $entry_id ),
			'entry_date'            => get_comment_date( get_option('date_format'), $entry_id ),
			'entry_time'            => get_comment_date( get_option('time_format'), $entry_id ),
			'timestamp'             => $this->get_timestamp(),
			'is_liveblog_editable'  => WPCOM_Liveblog::is_liveblog_editable(),
			'allowed_tags_for_entry' => self::$allowed_tags_for_entry,
		);

		return $entry;
	}

	public function render( $template = 'liveblog-single-entry.php' ) {

		$output = apply_filters( 'liveblog_pre_entry_output', '', $this );
		if ( ! empty( $output ) )
			return $output;

		if ( empty( $this->comment->comment_content ) )
			return $output;

		$entry = $this->get_fields_for_render();

		$entry = apply_filters( 'liveblog_entry_template_variables', $entry );

		return WPCOM_Liveblog::get_template_part( $template, $entry );
	}

	public static function render_content( $content, $comment = false ) {
		global $wp_embed;

		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) )
				$content = $wp_embed->autoembed( $content );
			$content = do_shortcode( $content );
		}

		return apply_filters( 'comment_text', $content, $comment );
	}

	/**
	 * Inserts a new entry
	 *
	 * @param array $args The entry properties: content, post_id, user (current user object)
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry
	 */
	public static function insert( $args ) {
        $args = apply_filters( 'liveblog_before_insert_entry', $args );
		$comment = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}
		do_action( 'liveblog_insert_entry', $comment->comment_ID, $args['post_id'] );
		$entry = self::from_comment( $comment );
		return $entry;
	}

	/**
	 * Updates an exsting entry
	 *
	 * Inserts a new entry, which replaces the original entry.
	 *
	 * @param array $args The entry properties: entry_id (which entry to update), content, post_id
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry, which replaces the original
	 */
	public static function update( $args ) {
		if ( !$args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		// always use the original author for the update entry, otherwise until refresh
		// users will see the user who editd the entry as  the author
		$args['user'] = self::user_object_from_comment_id( $args['entry_id'] );
		if ( is_wp_error( $args['user'] ) ) {
			return $args['user'];
		}

        $args = apply_filters( 'liveblog_before_update_entry', $args );
		$comment = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}
		do_action( 'liveblog_update_entry', $comment->comment_ID, $args['post_id'] );
		add_comment_meta( $comment->comment_ID, self::replaces_meta_key, $args['entry_id'] );
		wp_update_comment( array(
			'comment_ID'      => $args['entry_id'],
			'comment_content' => wp_filter_post_kses( $args['content'] ),
		) );
		$entry = self::from_comment( $comment );
		return $entry;
	}

	/**
	 * Deletes an existing entry
	 *
	 * Inserts a new entry, which replaces the original entry.
	 *
	 * @param array $args The entry properties: entry_id (which entry to delete), post_id, user (current user object)
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry, which replaces the original
	 */
	public static function delete( $args ) {
		if ( !$args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}
		$args['content'] = '';
		$comment = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}
		do_action( 'liveblog_delete_entry', $comment->comment_ID, $args['post_id'] );
		add_comment_meta( $comment->comment_ID, self::replaces_meta_key, $args['entry_id'] );
		wp_delete_comment( $args['entry_id'] );
		$entry = self::from_comment( $comment );
		return $entry;
	}

	public static function delete_key( $args ) {
		if ( !$args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}
		if ( ! WPCOM_Liveblog_Entry_Key_Events::remove_key_action( $args['entry_id'] ) ) {
			return new WP_Error( 'entry-delete-key', __( 'Key event not deleted' ) );
		}
		$entry = self::update( $args );
		return $entry;
	}

	private static function insert_comment( $args ) {
		$valid_args = self::validate_args( $args );
		if ( is_wp_error( $valid_args ) ) {
			return $valid_args;
		}
		$new_comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $args['post_id'],
			'comment_content'      => wp_filter_post_kses( $args['content'] ),
			'comment_approved'     => 'liveblog',
			'comment_type'         => 'liveblog',
			'user_id'              => $args['user']->ID,

			'comment_author'       => $args['user']->display_name,
			'comment_author_email' => $args['user']->user_email,
			'comment_author_url'   => $args['user']->user_url,
		) );
		wp_cache_delete( 'liveblog_entries_asc_' . $args['post_id'], 'liveblog' );
		if ( empty( $new_comment_id ) || is_wp_error( $new_comment_id ) ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}
		$comment = get_comment( $new_comment_id );
		if ( !$comment ) {
			return new WP_Error( 'get-comment', __( 'Error retrieving comment', 'liveblog' ) );
		}
		return $comment;
	}

	private static function validate_args( $args ) {
		$required_keys = array( 'post_id', 'user', );
		foreach( $required_keys as $key ) {
			if ( !isset( $args[$key] ) || !$args[$key] ) {
				return new WP_Error( 'entry-invalid-args', sprintf( __( 'Missing entry argument: %s', 'liveblog' ), $key ) );
			}
		}
		return true;
	}

	private static function user_object_from_comment_id( $comment_id ) {
		$original_comment = get_comment( $comment_id );
		if ( !$original_comment ) {
			return new WP_Error( 'get-comment', __( 'Error retrieving comment', 'liveblog' ) );
		}
		$user_object = get_userdata( $original_comment->user_id );
		if ( !$user_object ) {
			return new WP_Error( 'get-usedata', __( 'Error retrieving user', 'liveblog' ) );
		}
		return $user_object;
	}

	/**
	 * Handles stripping out any Restricted Shortcodes and replacing them with the
	 * preconfigured string entry.
	 *
	 * @param array $args The new Live blog Entry.
	 * @return mixed
	 */
	public static function handle_restricted_shortcodes( $args ) {

		// Runs the restricted shortcode array through the filter to modify it where applicable before being applied.
		self::$restricted_shortcodes = apply_filters( 'liveblog_entry_restrict_shortcodes', self::$restricted_shortcodes );

		// Foreach lookup key, does it exist in the content.
		if( is_array( self::$restricted_shortcodes ) ) {
			foreach ( self::$restricted_shortcodes as $key => $value ) {

				// Regex Pattern will match all shortcode formats.
				$pattern = get_shortcode_regex();

				// if there's a match we replace it with the configured replacement.
				$args['content'] = preg_replace( '/' . $pattern . '/s', $value, $args['content'] );
			}
		}

		// Return the Original entry arguments with any modifications.
		return $args;
	}
}

WPCOM_Liveblog_Entry::generate_allowed_tags_for_entry();
