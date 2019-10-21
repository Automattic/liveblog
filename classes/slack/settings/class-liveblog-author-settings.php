<?php

class Liveblog_Author_Settings {

	const SETTING_META       = 'slack_user_id';
	const SETTING_META_NONCE = 'slack_user';

	/**
	 * Register Hooks
	 */
	public static function hooks() {
		add_action( 'show_user_profile', [ __CLASS__, 'display_field' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'display_field' ] );
		add_action( 'personal_options_update', [ __CLASS__, 'save_fields' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'save_fields' ] );

		add_filter( 'coauthors_guest_author_fields', [ __CLASS__, 'contributor_profile_fields' ], 99, 2 );
	}

	/**
	 * Display Slack ID profile field
	 *
	 * @param $user
	 */
	public static function display_field( $user ) {
		$slack_id = get_user_meta( $user->ID, self::SETTING_META, true ); // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_get_user_meta
		wp_nonce_field( self::SETTING_META_NONCE, self::SETTING_META_NONCE . '-nonce' );
		?>
		<table class="form-table hm-profile-fields">
			<tr>
				<th><label for="slack-user-id">Slack User ID</label></th>
				<td>
					<input type="text" id="slack-user-id" name="<?php echo esc_attr( self::SETTING_META ); ?>" value="<?php echo esc_attr( $slack_id ); ?>" class="regular-text" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save Slack user id meta
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	public static function save_fields( $user_id ) {
		if ( ! isset( $_POST[ self::SETTING_META_NONCE . '-nonce' ] ) || ( isset( $_POST[ self::SETTING_META_NONCE . '-nonce' ] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::SETTING_META_NONCE . '-nonce' ] ) ), self::SETTING_META_NONCE ) ) ) { // input var
			return false;
		}

		if ( ! current_user_can( 'edit_users', $user_id ) && ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$slack_user_id = filter_input( INPUT_POST, self::SETTING_META, FILTER_SANITIZE_STRING );

		if ( ! empty( $slack_user_id ) ) {
			update_user_meta( $user_id, self::SETTING_META, $slack_user_id );  // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_update_user_meta
		} else {
			delete_user_meta( $user_id, self::SETTING_META ); // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_delete_user_meta
		}
	}

	/**
	 * Add Slack user id field to co-authors
	 *
	 * @param $fields
	 * @param $group
	 *
	 * @return array
	 */
	public static function contributor_profile_fields( $fields, $group ) {
		$group = reset( $group );

		if ( 'name' === $group || 'all' === $group ) {
			$fields[] = [
				'key'               => self::SETTING_META,
				'label'             => 'Slack User ID',
				'group'             => 'name',
				'sanitize_function' => 'sanitize_text_field',
			];
		}

		return $fields;

	}

}
