<?php

class WPCOM_Liveblog_Entry {
	const default_avatar_size = 30;
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
		$comment_text      = apply_filters( 'comment_text', get_comment_text( $entry_id ), $this->comment );
		$avatar_size       = apply_filters( 'liveblog_entry_avatar_size', self::default_avatar_size );
		$avatar_img        = get_avatar( $this->comment->comment_author_email, $avatar_size );
		$author_link       = get_comment_author_link( $entry_id );
		$entry_time        = get_comment_date( 'M j, Y - g:i:s A', $entry_id );
		$can_edit_liveblog = WPCOM_Liveblog::current_user_can_edit_liveblog();

		return WPCOM_Liveblog::get_template_part( 'liveblog-single-entry.php', compact(
			'post_id',
			'entry_id',
			'css_classes',
			'comment_text',
			'avatar_img',
			'author_link',
			'entry_time',
			'can_edit_liveblog'
		) );
	}
}
