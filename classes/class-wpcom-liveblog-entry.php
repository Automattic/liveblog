<?php

/**
 * Single Liveblog Entry class
 *
 * Currently inherted from comments, and assigned by the entry-query class.
 */
class WPCOM_Liveblog_Entry {

	/**
	 * @var int Default avatar size to use in template
	 */
	const default_avatar_size = 30;

	/**
	 * @var string Meta key to use when looking to replace an existing entry
	 */
	const replaces_meta_key   = 'liveblog_replaces';

	/**
	 * @var array Single comment to inherit data from
	 */
	private $comment;

	/**
	 * Sets up the comment and whether or not it's replacing an existing one
	 *
	 * @param array() $comment
	 */
	public function __construct( $comment ) {
		$this->comment  = $comment;
		$this->replaces = get_comment_meta( $comment->comment_ID, self::replaces_meta_key, true );
	}

	/**
	 * Accepts a comment, and turns it into a WPCOM_Liveblog_Entry
	 *
	 * @param array $comment
	 * @return WPCOM_Liveblog_Entry
	 */
	public static function from_comment( $comment ) {
		$entry = new WPCOM_Liveblog_Entry( $comment );
		return $entry;
	}

	/**
	 * Get the WordPress comment ID to use as the liveblog ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->comment->comment_ID;
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
		return (object) array(
			'id'   => $this->replaces ? $this->replaces : $this->get_id(),
			'html' => $this->render(),
		);
	}

	/**
	 * Render a single liveblog entry
	 *
	 * @return string
	 */
	public function render() {

		// Allow plugins to override the output
		$output = apply_filters( 'liveblog_pre_entry_output', '', $this );
		if ( ! empty( $output ) )
			return $output;

		// Bail if content is empty
		if ( empty( $this->comment->comment_content ) )
			return $output;

		// These variables are used in the liveblog-single-entry.php template
		$entry_id          = $this->comment->comment_ID;
		$post_id           = $this->comment->comment_post_ID;
		$css_classes       = comment_class( '', $entry_id, $post_id, false );
		$content           = self::render_content( get_comment_text( $entry_id ) );
		$avatar_size       = apply_filters( 'liveblog_entry_avatar_size', self::default_avatar_size );
		$avatar_img        = get_avatar( $this->comment->comment_author_email, $avatar_size );
		$author_link       = get_comment_author_link( $entry_id );
		$entry_date        = get_comment_date( get_option('date_format'), $entry_id );
		$entry_time        = get_comment_date( get_option('time_format'), $entry_id );
		$can_edit_liveblog = WPCOM_Liveblog::current_user_can_edit_liveblog();

		return WPCOM_Liveblog::get_template_part( 'liveblog-single-entry.php', compact(
			'post_id',
			'entry_id',
			'css_classes',
			'content',
			'avatar_img',
			'author_link',
			'entry_date',
			'entry_time',
			'can_edit_liveblog'
		) );
	}

	public static function render_content( $raw_content ) {
		return apply_filters( 'comment_text', $raw_content );
	}
}
