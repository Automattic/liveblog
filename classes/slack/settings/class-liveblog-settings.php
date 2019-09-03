<?php

class WPCOM_Liveblog_Channel_Settings {

	const SLACK_CHANNEL_META = 'slack_channel';
	const NONCE_KEY          = 'slack_channel_nonce';

	/**
	 * Register Hooks
	 */
	public static function hooks() {
		add_action( 'fte_liveblog_metabox', [ __CLASS__, 'display_metabox' ] );
		add_action( 'fte_liveblog_save_metabox', [ __CLASS__, 'save_post' ] );
	}

	/**
	 * Display Slack setting metabox
	 *
	 * @param $post
	 */
	public static function display_metabox( $post ) {
		$channel = get_post_meta( $post->ID, self::SLACK_CHANNEL_META, true );
		?>
		<p>
			<label for="slack-channel">Slack Channel </label>
			<input type="text" id="slack-channel" name="<?php echo esc_attr( self::SLACK_CHANNEL_META ); ?>" class="widefat" value="<?php echo esc_attr( $channel ); ?>">
		</p>

		<?php
	}

	/**
	 * Save slack settings
	 *
	 * @param $response
	 * @param $post_id
	 */
	public static function save_post( $post_id ) {

		$slack_channel = filter_input( INPUT_POST, self::SLACK_CHANNEL_META, FILTER_SANITIZE_STRING );
		if ( ! empty( $slack_channel ) ) {
			update_post_meta( $post_id, self::SLACK_CHANNEL_META, sanitize_text_field( wp_unslash( $slack_channel ) ) );
		} else {
			delete_post_meta( $post_id, self::SLACK_CHANNEL_META );
		}

	}

}
