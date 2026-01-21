<?php
/**
 * Represents a liveblog entry.
 *
 * @package Liveblog
 */

use Automattic\Liveblog\Domain\ValueObject\Author;
use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Domain\ValueObject\EntryType;
use Automattic\Liveblog\Infrastructure\DI\Container;

/**
 * Represents a liveblog entry.
 *
 * @deprecated 1.10.0 This class is being migrated to DDD architecture.
 *             New code should use:
 *             - Automattic\Liveblog\Domain\Entity\Entry for entry entities
 *             - Automattic\Liveblog\Application\Service\EntryService for CRUD operations
 *             - Automattic\Liveblog\Application\Presenter\EntryPresenter for JSON formatting
 *             - Automattic\Liveblog\Application\Service\ContentProcessor for content rendering
 *             The class will be removed in a future major version.
 */
class WPCOM_Liveblog_Entry {

	/**
	 * Default avatar size.
	 *
	 * @var int
	 */
	const DEFAULT_AVATAR_SIZE = 30;

	/**
	 * Meta key for storing the ID of the entry this one replaces.
	 *
	 * @var string
	 */
	const REPLACES_META_KEY = 'liveblog_replaces';

	/**
	 * Meta key for storing contributor IDs.
	 *
	 * @var string
	 */
	const CONTRIBUTORS_META_KEY = 'liveblog_contributors';

	/**
	 * Meta key for hiding authors on an entry.
	 *
	 * @var string
	 */
	const HIDE_AUTHORS_KEY = 'liveblog_hide_authors';

	/**
	 * The comment object.
	 *
	 * @var WP_Comment
	 */
	private $comment;

	/**
	 * The entry type (new, update, delete).
	 *
	 * @var EntryType
	 */
	private EntryType $type;

	/**
	 * The ID of the entry this one replaces (for updates/deletes).
	 *
	 * @var string|int|false
	 */
	public $replaces;

	/**
	 * Allowed HTML tags for entry content.
	 *
	 * @var array
	 */
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

	/**
	 * Constructor.
	 *
	 * @param WP_Comment $comment The comment object.
	 */
	public function __construct( $comment ) {
		$this->comment  = $comment;
		$this->replaces = get_comment_meta( $comment->comment_ID, self::REPLACES_META_KEY, true );
		$this->type     = EntryType::from_replaces_and_content(
			$this->replaces ? (int) $this->replaces : null,
			$comment->comment_content ?? ''
		);
	}

	/**
	 * Generate allowed HTML tags for entry content.
	 *
	 * @return void
	 */
	public static function generate_allowed_tags_for_entry() {
		// Use HTML tags allowed for post as a base.
		self::$allowed_tags_for_entry = wp_kses_allowed_html( 'post' );

		// Expand with additional tags that we want to allow.
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

	/**
	 * Create an entry from a comment.
	 *
	 * @param WP_Comment $comment The comment object.
	 * @return WPCOM_Liveblog_Entry The entry object.
	 */
	public static function from_comment( $comment ) {
		$entry = new WPCOM_Liveblog_Entry( $comment );
		return $entry;
	}

	/**
	 * Get the entry ID.
	 *
	 * @return int The entry ID.
	 */
	public function get_id() {
		return $this->comment->comment_ID;
	}

	/**
	 * Get the post ID.
	 *
	 * @return int The post ID.
	 */
	public function get_post_id() {
		return $this->comment->comment_post_ID;
	}

	/**
	 * Get the entry content.
	 *
	 * @return string The entry content.
	 */
	public function get_content() {
		return $this->comment->comment_content;
	}

	/**
	 * Get the entry type.
	 *
	 * @return string The entry type (new, update, delete).
	 */
	public function get_type(): string {
		return $this->type->value;
	}

	/**
	 * Get the entry type as an enum.
	 *
	 * @return EntryType The entry type enum.
	 */
	public function entry_type(): EntryType {
		return $this->type;
	}

	/**
	 * Get the GMT timestamp for the comment.
	 *
	 * @return string The timestamp.
	 */
	public function get_timestamp() {
		return mysql2date( 'G', $this->comment->comment_date_gmt );
	}


	/**
	 * Get the comment date in GMT.
	 *
	 * @param string $d          Optional. PHP date format. Default empty, uses date_format option.
	 * @param int    $comment_id Optional. Comment ID. Default 0.
	 * @return string|int The formatted date string, or Unix timestamp if format is 'U' or 'G'.
	 */
	public function get_comment_date_gmt( $d = '', $comment_id = 0 ) {
		$comment = get_comment( $comment_id );

		// For Unix timestamp format, use DateTimeImmutable to avoid timezone issues with mysql2date.
		if ( 'U' === $d || 'G' === $d ) {
			$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $comment->comment_date_gmt, new DateTimeZone( 'UTC' ) );
			return $datetime->getTimestamp();
		}

		if ( '' === $d ) {
			$date = mysql2date( get_option( 'date_format' ), $comment->comment_date_gmt );
		} else {
			$date = mysql2date( $d, $comment->comment_date_gmt );
		}

		return $date;
	}

	/**
	 * Get entry data for JSON output.
	 *
	 * @return object Entry data object.
	 */
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

	/**
	 * Get fields for rendering the entry.
	 *
	 * @return array Entry fields for rendering.
	 */
	public function get_fields_for_render() {
		$entry_id     = $this->replaces ? $this->replaces : $this->comment->comment_ID;
		$post_id      = $this->comment->comment_post_ID;
		$avatar_size  = apply_filters( 'liveblog_entry_avatar_size', self::DEFAULT_AVATAR_SIZE );
		$comment_text = get_comment_text( $entry_id );
		$css_classes  = implode( ' ', get_comment_class( '', $entry_id, $post_id ) );
		$time_format  = apply_filters( 'liveblog_timestamp_format', get_option( 'time_format' ) );
		$share_link   = get_permalink( $post_id ) . '#liveblog-entry-' . $entry_id;

		$entry = array(
			'entry_id'               => $entry_id,
			'post_id'                => $post_id,
			'css_classes'            => $css_classes,
			'content'                => self::render_content( $comment_text, $this->comment ),
			'original_content'       => apply_filters( 'liveblog_before_edit_entry', $comment_text ),
			'avatar_size'            => $avatar_size,
			'avatar_img'             => WPCOM_Liveblog::get_avatar( $this->comment->comment_author_email, $avatar_size ),
			'author_link'            => get_comment_author_link( $entry_id ),
			'authors'                => self::get_authors( $entry_id ),
			'entry_date'             => get_comment_date( get_option( 'date_format' ), $entry_id ),
			'entry_time'             => get_comment_date( $time_format, $entry_id ),
			'entry_timestamp'        => $this->get_comment_date_gmt( 'c', $entry_id ),
			'timestamp'              => $this->get_timestamp(),
			'share_link'             => $share_link,
			'key_event'              => \Automattic\Liveblog\Infrastructure\DI\Container::instance()->key_event_service()->is_key_event( $entry_id ),
			'is_liveblog_editable'   => WPCOM_Liveblog::is_liveblog_editable(),
			'allowed_tags_for_entry' => self::$allowed_tags_for_entry,
		);

		return $entry;
	}

	/**
	 * Render the entry using the PHP template.
	 *
	 * @return string The rendered HTML.
	 */
	public function render() {
		return WPCOM_Liveblog::get_template_part( 'liveblog-single-entry.php', $this->get_fields_for_render() );
	}

	/**
	 * Render entry content.
	 *
	 * @param string          $content The content to render.
	 * @param WP_Comment|bool $comment The comment object or false.
	 * @return string Rendered content.
	 */
	public static function render_content( $content, $comment = false ) {
		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) ) {
				$wpcom_liveblog_entry_embed = new WPCOM_Liveblog_Entry_Embed();
				$content                    = $wpcom_liveblog_entry_embed->autoembed( $content, $comment );
			}
			$content = do_shortcode( $content );
		}

		// Filter image attributes based on allowed list.
		$content = self::filter_image_attributes( $content );

		return apply_filters( 'comment_text', $content, $comment ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter.
	}

	/**
	 * Filter image attributes based on an allowed list.
	 *
	 * By default, only 'src' and 'alt' attributes are preserved on <img> tags.
	 * Developers can extend this using the 'liveblog_image_allowed_attributes' filter.
	 *
	 * @param string $content The HTML content to filter.
	 * @return string The filtered HTML content.
	 *
	 * @example
	 * // Allow additional attributes:
	 * add_filter( 'liveblog_image_allowed_attributes', function( $attrs ) {
	 *     return array_merge( $attrs, [ 'class', 'width', 'height', 'loading', 'data-*' ] );
	 * } );
	 *
	 * @example
	 * // Allow all attributes:
	 * add_filter( 'liveblog_image_allowed_attributes', fn() => [ '*' ] );
	 */
	public static function filter_image_attributes( $content ) {
		// Get allowed attributes. Default to src and alt for backwards compatibility.
		$allowed_attributes = apply_filters( 'liveblog_image_allowed_attributes', array( 'src', 'alt' ) );

		// If wildcard is present, return content unchanged.
		if ( in_array( '*', $allowed_attributes, true ) ) {
			return $content;
		}

		// Use regex to find and process img tags.
		return preg_replace_callback(
			'/<img\s+([^>]*)>/i',
			function ( $matches ) use ( $allowed_attributes ) {
				$attrs_string = $matches[1];

				// Parse attributes from the img tag.
				$parsed_attrs = array();
				if ( preg_match_all( '/(\w[\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $attrs_string, $attr_matches, PREG_SET_ORDER ) ) {
					foreach ( $attr_matches as $attr_match ) {
						$name                  = strtolower( $attr_match[1] );
						$value                 = $attr_match[2] ?? $attr_match[3] ?? $attr_match[4] ?? '';
						$parsed_attrs[ $name ] = $value;
					}
				}

				// Filter to only allowed attributes.
				$filtered_attrs = array();
				foreach ( $parsed_attrs as $name => $value ) {
					if ( self::is_attribute_allowed( $name, $allowed_attributes ) ) {
						$filtered_attrs[ $name ] = $value;
					}
				}

				// Rebuild the img tag.
				$new_attrs = array();
				foreach ( $filtered_attrs as $name => $value ) {
					$new_attrs[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
				}

				return '<img ' . implode( ' ', $new_attrs ) . '>';
			},
			$content
		);
	}

	/**
	 * Check if an attribute name is allowed based on the allowed list.
	 * Supports exact matches and wildcard patterns like 'data-*'.
	 *
	 * @param string $name       The attribute name to check.
	 * @param array  $allowed    The list of allowed attribute patterns.
	 * @return bool Whether the attribute is allowed.
	 */
	private static function is_attribute_allowed( $name, $allowed ) {
		foreach ( $allowed as $pattern ) {
			// Exact match.
			if ( $pattern === $name ) {
				return true;
			}
			// Wildcard pattern (e.g., 'data-*').
			if ( str_ends_with( $pattern, '*' ) ) {
				$prefix = substr( $pattern, 0, -1 );
				if ( str_starts_with( $name, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Inserts a new entry.
	 *
	 * @param array $args The entry properties: content, post_id, user (current user object).
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry.
	 */
	public static function insert( $args ) {
		$args = apply_filters( 'liveblog_before_insert_entry', $args );

		// Validate required arguments.
		if ( empty( $args['post_id'] ) ) {
			return new WP_Error( 'entry-invalid-args', __( 'Missing entry argument: post_id', 'liveblog' ) );
		}

		// Set the author if provided, otherwise use current user as fallback.
		// Comments require an author, but we can hide it via meta.
		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$args['user'] = self::get_userdata_with_filter( $args['author_id'] );
		}

		if ( empty( $args['user'] ) ) {
			return new WP_Error( 'entry-invalid-args', __( 'Missing entry argument: user', 'liveblog' ) );
		}

		// Determine if we should hide the author.
		$hide_author = ! isset( $args['author_id'] ) || ! $args['author_id'];

		// Normalize contributor_ids - can be false, empty array, or array of IDs.
		$contributor_ids = ! empty( $args['contributor_ids'] ) ? $args['contributor_ids'] : null;

		try {
			$entry_id = Container::instance()->entry_service()->create(
				(int) $args['post_id'],
				$args['content'] ?? '',
				$args['user'],
				$hide_author,
				$contributor_ids
			);
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'entry-invalid-args', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}

		do_action( 'liveblog_insert_entry', $entry_id->to_int(), $args['post_id'] );

		$comment = get_comment( $entry_id->to_int() );
		return self::from_comment( $comment );
	}

	/**
	 * Updates an existing entry.
	 *
	 * Inserts a new entry, which replaces the original entry.
	 *
	 * @param array $args The entry properties: entry_id (which entry to update), content, post_id.
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry, which replaces the original.
	 */
	public static function update( $args ) {
		if ( empty( $args['entry_id'] ) ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		if ( empty( $args['post_id'] ) ) {
			return new WP_Error( 'entry-invalid-args', __( 'Missing entry argument: post_id', 'liveblog' ) );
		}

		// Always use the original author for the update entry, otherwise until refresh
		// users will see the user who edited the entry as the author.
		$args['user'] = self::user_object_from_comment_id( $args['entry_id'] );
		if ( is_wp_error( $args['user'] ) ) {
			return $args['user'];
		}

		// Handle author selection - may update the original entry's author.
		$args['user'] = self::handle_author_select( $args, $args['entry_id'] );

		// Add contributors to the original entry.
		if ( isset( $args['contributor_ids'] ) ) {
			self::add_contributors( $args['entry_id'], $args['contributor_ids'] );
		}

		$args = apply_filters( 'liveblog_before_update_entry', $args );

		try {
			$new_entry_id = Container::instance()->entry_service()->update(
				(int) $args['post_id'],
				EntryId::from_int( (int) $args['entry_id'] ),
				$args['content'] ?? '',
				$args['user']
			);
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'entry-invalid-args', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}

		do_action( 'liveblog_update_entry', $new_entry_id->to_int(), $args['post_id'] );

		$comment = get_comment( $new_entry_id->to_int() );
		return self::from_comment( $comment );
	}

	/**
	 * Deletes an existing entry.
	 *
	 * Inserts a new entry, which replaces the original entry.
	 *
	 * @param array $args The entry properties: entry_id (which entry to delete), post_id, user (current user object).
	 * @return WPCOM_Liveblog_Entry|WP_Error The newly inserted entry, which replaces the original.
	 */
	public static function delete( $args ) {
		if ( empty( $args['entry_id'] ) ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		if ( empty( $args['post_id'] ) ) {
			return new WP_Error( 'entry-invalid-args', __( 'Missing entry argument: post_id', 'liveblog' ) );
		}

		if ( empty( $args['user'] ) ) {
			return new WP_Error( 'entry-invalid-args', __( 'Missing entry argument: user', 'liveblog' ) );
		}

		try {
			$delete_marker_id = Container::instance()->entry_service()->delete(
				(int) $args['post_id'],
				EntryId::from_int( (int) $args['entry_id'] ),
				$args['user']
			);
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'entry-invalid-args', $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'comment-insert', __( 'Error posting entry', 'liveblog' ) );
		}

		do_action( 'liveblog_delete_entry', $delete_marker_id->to_int(), $args['post_id'] );

		$comment = get_comment( $delete_marker_id->to_int() );
		return self::from_comment( $comment );
	}

	/**
	 * Delete a key event from an entry.
	 *
	 * @param array $args The entry properties.
	 * @return WPCOM_Liveblog_Entry|WP_Error The updated entry.
	 */
	public static function delete_key( $args ) {
		if ( ! $args['entry_id'] ) {
			return new WP_Error( 'entry-delete', __( 'Missing entry ID', 'liveblog' ) );
		}

		$args['content'] = \Automattic\Liveblog\Infrastructure\DI\Container::instance()->key_event_service()->remove_key_action( $args['content'], $args['entry_id'] );

		$entry = self::update( $args );
		return $entry;
	}

	/**
	 * Get a user object from a comment ID.
	 *
	 * @param int $comment_id The comment ID.
	 * @return WP_User|WP_Error The user object or error.
	 */
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

		// Runs the restricted shortcode array through the filter before being applied.
		self::$restricted_shortcodes = apply_filters( 'liveblog_entry_restrict_shortcodes', self::$restricted_shortcodes );

		// For each lookup key, does it exist in the content.
		if ( is_array( self::$restricted_shortcodes ) ) {
			foreach ( self::$restricted_shortcodes as $key => $value ) {

				// Regex pattern will match all shortcode formats.
				$pattern = get_shortcode_regex( array( $key ) );

				// If there's a match we replace it with the configured replacement.
				$args['content'] = preg_replace( '/' . $pattern . '/s', $value, $args['content'] );
			}
		}

		// Return the original entry arguments with any modifications.
		return $args;
	}

	/**
	 * Return the user using author_id, if user not found then set as current
	 * user as a fallback, we store a meta to show that authors are hidden as
	 * a comment must have an author.
	 *
	 * If an entry_id is supplied we should update it as it is the
	 * original entry which is used for displaying author information.
	 *
	 * @param array    $args     The new Liveblog entry.
	 * @param int|bool $entry_id If set we should update the original entry.
	 * @return WP_User The user object.
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
	 * @param int   $comment_id   The comment ID for the meta we should update.
	 * @param array $contributors Array of IDs to store as meta.
	 * @return void
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
	 * Get user data with filter applied.
	 *
	 * @param int $author_id The author ID.
	 * @return WP_User|false The user data or false.
	 */
	public static function get_userdata_with_filter( $author_id ) {
		return apply_filters( 'liveblog_userdata', get_userdata( $author_id ), $author_id );
	}

	/**
	 * Return an array of authors, based on the original comment author and its contributors.
	 *
	 * @param int $comment_id The ID of the comment.
	 * @return array The authors.
	 */
	public static function get_authors( $comment_id ) {
		$avatar_size = apply_filters( 'liveblog_entry_avatar_size', self::DEFAULT_AVATAR_SIZE );
		return self::get_author_collection( $comment_id )->to_array( $avatar_size );
	}

	/**
	 * Return an AuthorCollection for the entry.
	 *
	 * @param int $comment_id The ID of the comment.
	 * @return AuthorCollection The authors collection.
	 */
	public static function get_author_collection( $comment_id ): AuthorCollection {
		$hide_authors = get_comment_meta( $comment_id, self::HIDE_AUTHORS_KEY, true );

		if ( $hide_authors ) {
			return AuthorCollection::empty();
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return AuthorCollection::empty();
		}

		$authors = array();

		// Primary author from the comment.
		$authors[] = Author::from_comment( $comment );

		// Contributors from meta.
		$contributor_ids = get_comment_meta( $comment_id, self::CONTRIBUTORS_META_KEY, true );
		if ( is_array( $contributor_ids ) ) {
			foreach ( $contributor_ids as $contributor_id ) {
				$authors[] = Author::from_user_id( (int) $contributor_id );
			}
		}

		return AuthorCollection::from_authors( ...$authors );
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
