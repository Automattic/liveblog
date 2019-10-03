<?php

/**
 * Represents a liveblog entry
 */
class WPCOM_Liveblog_Entry {

	const DEFAULT_AVATAR_SIZE = 30;

	private $entry;
	private $type                       = 'new';
	private static $default_post_status = 'draft';
	private static $allowed_tags_for_entry;
	private static $rendered_content = [];

	/**
	 * Define the Lookup array for any shortcodes that should be stripped and replaced
	 * upon new entry being posted or existing entry being updated.
	 *
	 * @var array|mixed|void
	 */
	public static $restricted_shortcodes = [
		'liveblog_key_events' => '',
	];

	public function __construct( $entry ) {
		$this->entry = $entry;
	}

	public static function generate_allowed_tags_for_entry() {
		/**
		 * Use html tags allowed for post as a base.
		 */
		self::$allowed_tags_for_entry = wp_kses_allowed_html( 'post' );
		/**
		 * Expand with additional tags that we want to allow.
		*/
		$additional_tags           = [];
		$additional_tags['iframe'] = [
			'src'             => [],
			'height'          => [],
			'width'           => [],
			'frameborder'     => [],
			'allowfullscreen' => [],
		];
		$additional_tags['source'] = [
			'src'  => [],
			'type' => [],
		];

		self::$allowed_tags_for_entry = array_merge(
			$additional_tags,
			self::$allowed_tags_for_entry
		);
	}


	public static function from_post( $post ) {
		$entry = new WPCOM_Liveblog_Entry( $post );
		return $entry;
	}

	public function get_id() {
		return $this->entry->ID;
	}

	public function get_post_id() {
		return $this->entry->post_parent;
	}

	public function get_content() {
		return $this->entry->post_content;
	}

	public function get_status() {
		return $this->entry->post_status;
	}

	public function get_headline() {
		return $this->entry->post_title;
	}

	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the GMT timestamp for the entry
	 *
	 * @return string
	 */
	public function get_timestamp() {
		// For draft post we need to use post_modified_gmt as post_date_gtm is set to 00:00:00
		if ( 'draft' === $this->entry->post_status && '0000-00-00 00:00:00' === $this->entry->post_date_gmt ) {
			if ( '0000-00-00 00:00:00' === $this->entry->post_modified_gmt ) {
				return mysql2date( 'G', get_gmt_from_date( $this->entry->post_modified ) );
			}
			return mysql2date( 'G', $this->entry->post_modified_gmt );
		}

		return mysql2date( 'G', $this->entry->post_date_gmt );
	}

	/**
	 * Retrieve the entry date of the current entry using gmt.
	 * @param string      $d       Optional. The format of the date. Default user's setting.
	 * @param int|WP_Post $post_id WP_Post or ID of the post for which to get the date.
	 *                             Default current post.
	 * @return string The post's date.
	 */
	public function get_entry_date_gmt( $d = '', $post_id = 0 ) {
		if ( ! $post_id ) {
			return;
		}

		$entry = get_post( $post_id );
		if ( ! is_object( $entry ) ) {
			return;
		}

		// For draft post we need to use post_modified_gmt as post_date_gtm is set to 00:00:00
		if ( 'draft' === $entry->post_status && '0000-00-00 00:00:00' === $this->entry->post_date_gmt ) {
			if ( '' === $d ) {
				if ( '0000-00-00 00:00:00' === $entry->post_modified_gmt ) {
					$date = mysql2date( get_option( 'date_format' ), get_gmt_from_date( $entry->post_modified ) );
				} else {
					$date = mysql2date( get_option( 'date_format' ), $entry->post_modified_gmt );
				}
			} else {
				if ( '0000-00-00 00:00:00' === $entry->post_modified_gmt ) {
					$date = mysql2date( $d, get_gmt_from_date( $entry->post_modified ) );
				} else {
					$date = mysql2date( $d, $entry->post_modified_gmt );
				}
			}
		} else {
			if ( '' === $d ) {
				$date = mysql2date( get_option( 'date_format' ), $entry->post_date_gmt );
			} else {
				$date = mysql2date( $d, $entry->post_date_gmt );
			}
		}

		return $date;
	}

	public function for_json() {
		$entry_id = $this->get_id();
		if ( ! $entry_id ) {
			return false;
		}

		$css_classes = implode( ' ', apply_filters( 'post_class', [ 'liveblog' ], 'liveblog', $entry_id ) );
		$share_link  = add_query_arg( [ 'lbup' => $entry_id ], get_permalink( $this->get_post_id() ) );

		$entry = [
			'id'          => $entry_id,
			'type'        => $this->get_type(),
			'render'      => self::render_content( $this->get_content(), $this->entry ),
			'headline'    => $this->get_headline(),
			'content'     => apply_filters( 'liveblog_before_edit_entry', $this->get_content() ),
			'css_classes' => $css_classes,
			'timestamp'   => $this->get_timestamp(),
			'authors'     => self::get_authors( $entry_id ),
			'entry_time'  => $this->get_entry_date_gmt( 'U', $entry_id ),
			'share_link'  => $share_link,
			'status'      => self::get_status(),
		];


		$entry = apply_filters( 'liveblog_entry_for_json', $entry, $this );
		return (object) $entry;
	}

	public static function render_content( $content, $entry = false ) {
		// Cache rendered entry content to avoid double running shortcodes.
		if ( isset( self::$rendered_content[ $entry->ID ] ) ) {
			return self::$rendered_content[ $entry->ID ];
		}

		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) ) {
				$wpcom_liveblog_entry_embed = new WPCOM_Liveblog_Entry_Embed();
				$content                    = $wpcom_liveblog_entry_embed->autoembed( $content, $entry );
			}
			$content = do_shortcode( $content );
		}

		// Remove the filter as it's causing amp pages to crash
		if ( function_exists( 'amp_activate' ) && is_amp_endpoint() ) {
			remove_filter( 'the_content', [ 'WPCOM_Liveblog_AMP', 'append_liveblog_to_content' ], 7 );
		}

		self::$rendered_content[ $entry->ID ] = apply_filters( 'the_content', $content );

		return self::$rendered_content[ $entry->ID ];
	}

	/**
	 * Inserts a new entry
	 *
	 * @param array $args The entry properties: content, post_id, user (current user object)
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry
	 */
	public static function insert( $args ) {
		$args = apply_filters( 'liveblog_before_insert_entry', $args );

		$entry = self::insert_entry( $args );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		do_action( 'liveblog_insert_entry', $entry->ID, $args['post_id'] );
		$entry = self::from_post( $entry );
		return $entry;
	}

	/**
	 * Updates an exsting entry
	 *
	 * @param array $args The entry properties: entry_id (which entry to update), content, post_id
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry, which replaces the original
	 */
	public static function update( $args ) {

		if ( ! $args['entry_id'] ) {
			return new WP_Error( 'entry-update', __( 'Missing entry ID', 'liveblog' ) );
		}

		$args = apply_filters( 'liveblog_before_update_entry', $args );

		$post_data = [
			'ID'           => $args['entry_id'],
			'post_content' => $args['content'],
			'post_title'   => $args['headline'],
			'post_status'  => empty( $args['status'] ) ? self::$default_post_status : $args['status'],
		];

		$updated_entry_id = wp_update_post( $post_data );
		if ( ! $updated_entry_id ) {
			return new WP_Error( 'entry-update', __( 'Updating post failed', 'liveblog' ) );
		}

		global $coauthors_plus;
		$coauthors_plus->add_coauthors( $args['entry_id'], $args['author_ids'], false, 'id' );

		wp_cache_delete( 'liveblog_entries_asc_' . $args['post_id'], 'liveblog' );
		do_action( 'liveblog_update_entry', $args['entry_id'], $args['post_id'] );

		$entry_post = get_post( $updated_entry_id );

		// When an entry transitions from publish to draft we need to hide it on the front-end
		self::toggle_entry_visibility( $entry_post->ID, $entry_post->post_parent, $args['status'] );
		self::store_updated_entries( $entry_post, $entry_post->post_parent );

		$entry       = self::from_post( $entry_post );
		$entry->type = 'update';

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
		$entry = get_post( $args['entry_id'] );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		wp_cache_delete( 'liveblog_entries_asc_' . $args['post_id'], 'liveblog' );
		do_action( 'liveblog_delete_entry', $entry->ID, $args['post_id'] );

		$entry_post = wp_delete_post( $args['entry_id'] );

		// When an entry is deleted we need to hide it on the front-end
		self::toggle_entry_visibility( $entry->ID, $entry->post_parent, 'delete' );
		$entry = self::from_post( $entry_post );

		$entry->type    = 'delete';
		$entry->content = '';

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

	private static function insert_entry( $args, $content_required = true ) {
		$valid_args = self::validate_args( $args, $content_required );
		if ( is_wp_error( $valid_args ) ) {
			return $valid_args;
		}

		$new_entry_id = wp_insert_post(
			[
				'post_parent'  => $args['post_id'],
				'post_content' => $args['content'],
				'post_title'   => $args['headline'],
				'post_type'    => WPCOM_Liveblog_CPT::$cpt_slug,
				'post_status'  => empty( $args['status'] ) ? self::$default_post_status : $args['status'],
			]
		);

		wp_cache_delete( 'liveblog_entries_asc_' . $args['post_id'], 'liveblog' );
		if ( empty( $new_entry_id ) || is_wp_error( $new_entry_id ) ) {
			return new WP_Error( 'entry-insert', __( 'Error posting entry', 'liveblog' ) );
		}

		global $coauthors_plus;
		$coauthors_plus->add_coauthors( $new_entry_id, $args['author_ids'], false, 'id' );

		$entry = get_post( $new_entry_id );
		if ( ! $entry ) {
			return new WP_Error( 'get-entry', __( 'Error retrieving entry', 'liveblog' ) );
		}
		return $entry;
	}

	private static function validate_args( $args, $content_required = true ) {
		$required_keys = [ 'post_id', 'user' ];

		if ( $content_required ) {
			array_push( $required_keys, 'content' );
		}

		foreach ( $required_keys as $key ) {
			if ( ! isset( $args[ $key ] ) || ! $args[ $key ] ) {
				// translators: 1: argument
				return new WP_Error( 'entry-invalid-args', sprintf( __( 'Missing entry argument: %s', 'liveblog' ), $key ) );
			}
		}
		return true;
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
				$pattern = get_shortcode_regex( [ $key ] );

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
	 * a entry must have an author.
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

				wp_update_post(
					[
						'ID'      => $entry_id,
						'user_id' => isset( $args['user']->ID ) ? $args['user']->ID : '',
					]
				);

			}
		} elseif ( empty( $args['author_id'] ) && WPCOM_Liveblog::is_author_required() ) {
			return false;
		}

		return $args['user'];
	}

	public static function get_userdata_with_filter( $author_id ) {
		if ( apply_filters( 'liveblog_fetch_userdata', true ) ) {
			$userdata = get_userdata( $author_id );
		} else {
			$userdata = null;
		}

		return apply_filters( 'liveblog_userdata', $userdata, $author_id );
	}

	/**
	 * Returns a formatted array of user data.
	 *
	 * @param object $user The user object
	 */
	private static function get_user_data_for_json( $user ) {
		if ( is_wp_error( $user ) || ! is_object( $user ) ) {
			return [];
		}

		$avatar_size = apply_filters( 'liveblog_entry_avatar_size', self::DEFAULT_AVATAR_SIZE );
		return [
			'id'     => $user->ID,
			'key'    => strtolower( $user->user_nicename ),
			'name'   => $user->display_name,
			'avatar' => WPCOM_Liveblog::get_avatar( $user->ID, $avatar_size ),
		];
	}


	/**
	 * Return an array of authors.
	 *
	 * @param number $entry_id The id of the post.
	 */
	public static function get_authors( $entry_id ) {
		$contributors = get_coauthors( $entry_id );
		if ( ! $contributors ) {
			return [];
		}

		return array_map(
			function( $contributor ) {
				if ( 0 === $contributor->ID ) {
					return false;
				}
				$user_object = self::get_userdata_with_filter( $contributor->ID );
				return self::get_user_data_for_json( $user_object );
			},
			$contributors
		);
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

	/**
	 * Get Entry first image if present.
	 *
	 * @param  object $entry Entry.
	 * @return string        Image src.
	 */
	public static function get_entry_first_image( $entry ) {
		$entry_id      = ( $entry instanceof WPCOM_Liveblog_Entry ) ? $entry->get_id() : $entry->id;
		$entry_content = ( $entry instanceof WPCOM_Liveblog_Entry ) ? $entry->get_content() : $entry->content;
		$key           = 'liveblog_entry_' . $entry_id . 'first_image';
		$cached        = wp_cache_get( $key, 'liveblog' );

		if ( false === $cached ) {
			preg_match( '/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $entry_content, $image );
			$cached = isset( $image['src'] ) ? $image['src'] : '';
			wp_cache_set( $key, $cached, 'liveblog' );
		}

		return $cached;
	}

	/**
	 * Get featured image for entry.
	 *
	 * @param  object $entry Entry.
	 * @return string        Featured image src.
	 */
	public static function get_entry_featured_image_src( $entry ) {
		$image = self::get_entry_first_image( $entry );
		return apply_filters( 'liveblog_entry_featured_image', $image, $entry );
	}

	/**
	 * Add entries to a list stored in object cache of post that need to be hidden
	 * on the front-end or other author who are maneging the back end of the liveblog.
	 * This list will be used to hide entries when polling the API for new updates.
	 *
	 * @param      $post_id
	 * @param      $liveblog_id
	 * @param      $add
	 */
	public static function toggle_entry_visibility( $post_id, $liveblog_id, $status ) {
		$cached_key     = 'hidden_entries_' . $liveblog_id;
		$hidden_entries = wp_cache_get( $cached_key, 'liveblog' );

		if ( empty( $hidden_entries ) || ! is_array( $hidden_entries ) ) {
			$hidden_entries = [];
		}

		if ( 'publish' !== $status ) {
			$hidden_entries[ $post_id ] = $status;
		} else {
			// remove entry from cache when entry is published
			unset( $hidden_entries[ $post_id ] );
		}

		wp_cache_set( $cached_key, array_filter( $hidden_entries ), 'liveblog', 30 ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.LowCacheTime
	}

	/**
	 * Return a list of hidden entries
	 *
	 * @param      $liveblog_id
	 * @param bool $only_deleted
	 *
	 * @return array
	 */
	public static function get_hidden_entries( $liveblog_id, $only_deleted = true ) {
		$entries        = [];
		$cached_key     = 'hidden_entries_' . $liveblog_id;
		$hidden_entries = wp_cache_get( $cached_key, 'liveblog' );

		if ( empty( $hidden_entries ) ) {
			return $entries;
		}

		foreach ( (array) $hidden_entries as $entry_id => $status ) {
			if ( $only_deleted && 'delete' !== $status ) {
				continue;
			}

			if ( empty( $entry_id ) ) {
				continue;
			}

			$entries[] = [
				'id'          => $entry_id,
				'type'        => 'delete',
				'render'      => '',
				'headline'    => '',
				'content'     => '',
				'css_classes' => '',
				'timestamp'   => 0,
				'authors'     => [],
				'entry_time'  => 0,
				'share_link'  => 0,
				'status'      => 'delete',
			];
		}

		return $entries;
	}

	/**
	 * Store entries that have been updated so we can pass the update to the admin and front end
	 *
	 * @param $entry_post
	 * @param $liveblog_id
	 */
	public static function store_updated_entries( $entry_post, $liveblog_id ) {
		$cached_key      = 'updated_entries_' . $liveblog_id;
		$updated_entries = wp_cache_get( $cached_key, 'liveblog' );

		if ( empty( $updated_entries ) || ! is_array( $updated_entries ) ) {
			$updated_entries = [];
		}

		$entry                              = self::from_post( $entry_post );
		$entry->type                        = 'update';
		$updated_entries[ $entry_post->ID ] = $entry->for_json();

		wp_cache_set( $cached_key, $updated_entries, 'liveblog', 30 ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.LowCacheTime
	}

	/**
	 * Return a list of updated entries
	 *
	 * @param $liveblog_id
	 *
	 * @return array
	 */
	public static function get_updated_entries( $liveblog_id, $only_published = true ) {
		$entries         = [];
		$cached_key      = 'updated_entries_' . $liveblog_id;
		$updated_entries = wp_cache_get( $cached_key, 'liveblog' );
		$selected_status = apply_filters( 'liveblog_updated_entry_status', '' );

		if ( empty( $updated_entries ) ) {
			return $entries;
		}

		foreach ( (array) $updated_entries as $entry_id => $entry ) { //  phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
			if ( $only_published && 'draft' === $entry->status ) {
				continue;
			}

			if ( empty( $entry ) ) {
				continue;
			}

			if ( ! empty( $selected_status ) && $selected_status === $entry->status ) {
				$entries[] = $entry;
			} elseif ( empty( $selected_status ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}
}

WPCOM_Liveblog_Entry::generate_allowed_tags_for_entry();
