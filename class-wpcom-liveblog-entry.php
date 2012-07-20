<?php

class WPCOM_Liveblog_Entry {
	const default_avatar_size = 30;
	const replaces_meta_key = 'liveblog_replaces';
	private $comment;

	static function from_comment( $comment ) {
		$entry = new WPCOM_Liveblog_Entry( $comment );
		return $entry;
	}

	function __construct( $comment ) {
		$this->comment = $comment;
		$replaces_comment_id = get_comment_meta( $comment->comment_ID, self::replaces_meta_key, true );

		$this->replaces = $replaces_comment_id && $comment->comment_content? $replaces_comment_id : false;
		$this->deletes = $replaces_comment_id && !$comment->comment_content? $replaces_comment_id : false;
	}

	function get_id() {
		return $this->comment->comment_ID;
	}

	function get_timestamp() {
		return mysql2date( 'G', $this->comment->comment_date_gmt );
	}

	function for_json() {
		return (object)array(
			'id' => $this->get_id(),
			'content' => $this->render(),
			'replaces' => $this->replaces,
			'deletes' => $this->deletes,
		);
	}

	function render() {
		$output = '';

		// Allow plugins to override the output
		$output = apply_filters( 'liveblog_pre_entry_output', $output, $this );
		if ( $output )
			return $output;

		if ( $this->deletes )
			return $output;

		$entry_id = $this->comment->comment_ID;
		$post_id = $this->comment->comment_post_ID;
		$css_classes = comment_class( '', $entry_id, $post_id, false );
		$comment_text = apply_filters( 'comment_text', get_comment_text( $entry_id ), $this->comment );
		$avatar_size = apply_filters( 'liveblog_entry_avatar_size', self::default_avatar_size );
		$avatar_img = get_avatar( $this->comment->comment_author_email, $avatar_size );
		$author_link = get_comment_author_link( $entry_id );
		$entry_time = sprintf( __( '%1$s at %2$s' ), get_comment_date( get_option( 'date_format' ), $entry_id ), get_comment_date( get_option( 'time_format' ), $entry_id ) );

		$can_edit_liveblog = WPCOM_Liveblog::current_user_can_edit_liveblog();

		ob_start();
		include dirname( __FILE__ ) . '/entry.tmpl.php';
		$output = ob_get_clean();

		$output = apply_filters( 'liveblog_entry_output', $output, $this );

		return $output;
	}

}
