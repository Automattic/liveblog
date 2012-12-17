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

	public function __construct( $comment ) {
		$this->comment  = $comment;
		$this->replaces = get_comment_meta( $comment->comment_ID, self::replaces_meta_key, true );
	}

	public static function from_comment( $comment ) {
		$entry = new WPCOM_Liveblog_Entry( $comment );
		return $entry;
	}

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

	public function render() {

		$output = apply_filters( 'liveblog_pre_entry_output', '', $this );
		if ( ! empty( $output ) )
			return $output;

		if ( empty( $this->comment->comment_content ) )
			return $output;

		// These variables are used in the liveblog-single-entry.php template
		$entry_id          = $this->comment->comment_ID;
		$post_id           = $this->comment->comment_post_ID;
		$css_classes       = comment_class( '', $entry_id, $post_id, false );
		$content           = self::render_content( get_comment_text( $entry_id ), $this->comment );
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

	public static function render_content( $content, $comment ) {
		global $wp_embed;

		if ( apply_filters( 'liveblog_entry_enable_embeds', true ) ) {
			if ( get_option( 'embed_autourls' ) )
				$content = $wp_embed->autoembed( $content );
			$content = do_shortcode( $content );
		}

		$content = apply_filters( 'comment_text', $content, $comment );

		return $content;
	}
}
