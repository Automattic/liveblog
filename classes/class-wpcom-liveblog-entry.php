<?php

/**
 * Represents a liveblog entry
 */
class WPCOM_Liveblog_Entry {

	const DEFAULT_AVATAR_SIZE = 30;

	/**
	 * @var string In case the current entry is an edit (replaces) of
	 * another entry, we store the other entry's ID in this meta key.
	 */
	const REPLACES_META_KEY = 'liveblog_replaces';

	/**
	 * @var string If author editing is enabled, we stored contributors
	 *  in this meta key.
	 */
	const CONTRIBUTORS_META_KEY = 'liveblog_contributors';

	/**
	 * @var string Whether or not an entry should show an author
	 */
	const HIDE_AUTHORS_KEY = 'liveblog_hide_authors';

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
		$this->replaces = get_comment_meta( $comment->comment_ID, self::REPLACES_META_KEY, true );
		if ( $this->replaces && $this->get_content() ) {
			$this->type = 'update';
		}
		if ( $this->replaces && ! $this->get_content() ) {
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
		$additional_tags           = array();
		$additional_tags['iframe'] = array(
			'src'             => array(),
			'height'          => array(),
			'width'           => array(),
			'frameborder'     => array(),
			'allowfullscreen' => array(),
		);
		$additional_tags['source'] = array(
			'src'  => array(),
			'type' => array(),
		);

		self::$allowed_tags_for_entry = array_merge(
			$additional_tags,
			self::$allowed_tags_for_entry
		);
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


	/**
	 * Retrieve the comment date of the current comment using gmt.
	 * @param string          $d          Optional. The format of the date. Default user's setting.
	 * @param int|WP_Comment  $comment_ID WP_Comment or ID of the comment for which to get the date.
	 *                                    Default current comment.
	 * @return string The comment's date.
	 */
	public function get_comment_date_gmt( $d = '', $comment_id = 0 ) {
		$comment = get_comment( $comment_id );
		if ( '' === $d ) {
			$date = mysql2date( get_option( 'date_format' ), $comment->comment_date_gmt );
		} else {
			$date = mysql2date( $d, $comment->comment_date_gmt );
		}

		return $date;
	}

	public function for_json() {
		$entry_id    = $this->replaces ? $this->replaces : $this->get_id();
		$css_classes = implode( ' ', get_comment_class( '', $entry_id, $this->comment->comment_post_ID ) );
		$share_link  = get_permalink( $this->get_post_id() ) . '#' . $entry_id;

		$entry = array(
			'id'          => $entry_id,
			'type'        => $this->get_type(),
			'render'      => self::render_content( $this->get_content(), $this->comment ),
			'content'     => apply_filters( 'liveblog_before_edit_entry', $this->get_content() ),
			'css_classes' => $css_classes,
			'timestamp'   => $this->get_timestamp(),
			'authors'     => self::get_authors( $entry_id ),
			'entry_time'  => $this->get_comment_date_gmt( 'U', $entry_id ),
			'share_link'  => $share_link,
		);
		$entry = apply_filters( 'liveblog_entry_for_json', $entry, $this );
		return (object) $entry;
	}

	public static function render_content( $content, $comment = false ) {
		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) ) {
				$wpcom_liveblog_entry_embed = new WPCOM_Liveblog_Entry_Embed();
				$content                    = $wpcom_liveblog_entry_embed->autoembed( $content, $comment );
			}
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

		$args['user'] = self::handle_author_select( $args, false );

		$comment = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}

		if ( isset( $args['contributor_ids'] ) ) {
			self::add_contributors( $comment->comment_ID, $args['contributor_ids'] );
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
		if ( ! $args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		// always use the original author for the update entry, otherwise until refresh
		// users will see the user who editd the entry as  the author
		$args['user'] = self::user_object_from_comment_id( $args['entry_id'] );
		if ( is_wp_error( $args['user'] ) ) {
			return $args['user'];
		}

		$args['user'] = self::handle_author_select( $args, $args['entry_id'] );

		if ( isset( $args['contributor_ids'] ) ) {
			self::add_contributors( $args['entry_id'], $args['contributor_ids'] );
		}

		$args = apply_filters( 'liveblog_before_update_entry', $args );

		$comment = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}

		do_action( 'liveblog_update_entry', $comment->comment_ID, $args['post_id'] );
		add_comment_meta( $comment->comment_ID, self::REPLACES_META_KEY, $args['entry_id'] );
		wp_update_comment(
			array(
				'comment_ID'      => $args['entry_id'],
				'comment_content' => wp_filter_post_kses( $args['content'] ),
			)
		);
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
		if ( ! $args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}
		$args['content'] = '';
		$comment         = self::insert_comment( $args );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}
		do_action( 'liveblog_delete_entry', $comment->comment_ID, $args['post_id'] );
		add_comment_meta( $comment->comment_ID, self::REPLACES_META_KEY, $args['entry_id'] );
		wp_delete_comment( $args['entry_id'] );
		$entry = self::from_comment( $comment );
		return $entry;
	}

	public static function delete_key( $args ) {
		if ( ! $args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		$args['content'] = WPCOM_Liveblog_Entry_Key_Events::remove_key_action( $args['content'], $args['entry_id'] );

		$entry = self::update( $args );
		return $entry;
	}

	private static function insert_comment( $args ) {
		$valid_args = self::validate_args( $args );
		if ( is_wp_error( $valid_args ) ) {
			return $valid_args;
		}
		$new_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $args['post_id'],
				'comment_content'      => wp_filter_post_kses( $args['content'] ),
				'comment_approved'     => 'liveblog',
				'comment_type'         => 'liveblog',
				'user_id'              => $args['user']->ID,

				'comment_author'       => $args['user']->display_name,
				'comment_author_email' => $args['user']->user_email,
				'comment_author_url'   => $args['user']->user_url,
			)
		);
		wp_cache_delete( 'liveblog_entries_asc_' . $args['post_id'], 'liveblog' );
		if ( empty( $new_comment_id ) || is_wp_error( $new_comment_id ) ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}
		$comment = get_comment( $new_comment_id );
		if ( ! $comment ) {
			return new WP_Error( 'get-comment', __( 'Error retrieving comment', 'liveblog' ) );
		}
		return $comment;
	}

	private static function validate_args( $args ) {
		$required_keys = array( 'post_id', 'user' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $args[ $key ] ) || ! $args[ $key ] ) {
				// translators: 1: argument
				return new WP_Error( 'entry-invalid-args', sprintf( __( 'Missing entry argument: %s', 'liveblog' ), $key ) );
			}
		}
		return true;
	}

	private static function user_object_from_comment_id( $comment_id ) {
		$original_comment = get_comment( $comment_id );
		if ( ! $original_comment ) {
			return new WP_Error( 'get-comment', __( 'Error retrieving comment', 'liveblog' ) );
		}
		$user_object = get_userdata( $original_comment->user_id );
		if ( ! $user_object ) {
			return new WP_Error( 'get-userdata', __( 'Error retrieving user', 'liveblog' ) );
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
		if ( is_array( self::$restricted_shortcodes ) ) {
			foreach ( self::$restricted_shortcodes as $key => $value ) {

				// Regex Pattern will match all shortcode formats.
				$pattern = get_shortcode_regex( array( $key ) );

				// if there's a match we replace it with the configured replacement.
				$args['content'] = preg_replace( '/' . $pattern . '/s', $value, $args['content'] );
			}
		}

		// Return the Original entry arguments with any modifications.
		return $args;
	}

	/**
	 * Return the user using author_id, if user not found then set as current
	 * user as a fallback, we store a meta to show that authors are hidden as
	 * a comment must have an author.
	 *
	 * If a entry_id is supplied we should update it as its the
	 * original entry which is used for displaying author information.
	 *
	 *
	 * @param array $args The new Live blog Entry.
	 * @param int   $entry_id If set we should update the original entry
	 * @return mixed
	 */
	private static function handle_author_select( $args, $entry_id ) {
		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$user_object = self::get_userdata_with_filter( $args['author_id'] );
			if ( $user_object ) {
				$args['user'] = $user_object;

				wp_update_comment(
					array(
						'comment_ID'           => $entry_id,
						'user_id'              => $args['user']->ID,
						'comment_author'       => $args['user']->display_name,
						'comment_author_email' => $args['user']->user_email,
						'comment_author_url'   => $args['user']->user_url,
					)
				);

				update_comment_meta( $entry_id, self::HIDE_AUTHORS_KEY, false );
			}
		} else {
			update_comment_meta( $entry_id, self::HIDE_AUTHORS_KEY, true );
		}

		if ( isset( $args['contributor_ids'] ) ) {
			self::add_contributors( $entry_id, $args['contributor_ids'] );
		}

		return $args['user'];
	}

	/**
	 * Store the contributors as comment meta.
	 *
	 * @param int $comment_id The comment id for the meta we should update.
	 * @param array $contributors Array of ids to store as meta.
	 */
	private static function add_contributors( $comment_id, $contributors ) {
		if ( ! $contributors ) {
			delete_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY );
		}

		if ( is_array( $contributors ) ) {
			if ( metadata_exists( 'comment', $comment_id, self::CONTRIBUTORS_META_KEY ) ) {
				update_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, $contributors );
				return;
			}

			add_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, $contributors, true );
		}
	}

	/**
	 * Returns a list of contributor user objects.
	 *
	 * @param int $comment_id The comment id to retrive the metadata.
	 */
	private static function get_contributors_for_json( $comment_id ) {
		$contributors = get_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, true );

		if ( ! $contributors ) {
			return array();
		}

		return array_map(
			function( $contributor ) {
					$user_object = self::get_userdata_with_filter( $contributor );
					return self::get_user_data_for_json( $user_object );
			},
			$contributors
		);
	}

	public static function get_userdata_with_filter( $author_id ) {
		return apply_filters( 'liveblog_userdata', get_userdata( $author_id ), $author_id );
	}

	/**
	 * Returns a formatted array of user data.
	 *
	 * @param object $user The user object
	 */
	private static function get_user_data_for_json( $user ) {
		if ( is_wp_error( $user ) || ! is_object( $user ) ) {
			return array();
		}

		$avatar_size = apply_filters( 'liveblog_entry_avatar_size', self::DEFAULT_AVATAR_SIZE );
		return array(
			'id'     => $user->ID,
			'key'    => strtolower( $user->user_nicename ),
			'name'   => $user->display_name,
			'avatar' => WPCOM_Liveblog::get_avatar( $user->ID, $avatar_size ),
		);
	}

	/**
	 * Return an array of authors, based on the original comment author and its contributors.
	 *
	 * @param number $comment_id The id of the comment.
	 */
	public static function get_authors( $comment_id ) {
		$hide_authors = get_comment_meta( $comment_id, self::HIDE_AUTHORS_KEY, true );

		if ( $hide_authors ) {
			return array();
		}

		$author       = [ self::get_user_data_for_json( self::user_object_from_comment_id( $comment_id ) ) ];
		$contributors = self::get_contributors_for_json( $comment_id );

		return array_merge( $author, $contributors );
	}

	/**
	 * Work out Entry title
	 *
	 * @param  object $entry Entry.
	 * @return string        Title
	 */
	public static function get_entry_title( $entry ) {
		return wp_trim_words( $entry->content, 10, '…' );
	}

}

WPCOM_Liveblog_Entry::generate_allowed_tags_for_entry();
