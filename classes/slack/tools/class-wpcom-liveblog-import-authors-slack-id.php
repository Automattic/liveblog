<?php

class WPCOM_Liveblog_Import_Authors_Slack_ID {

	/**
	 * @var int file ID
	 */
	public static $file_id = 0;

	/**
	 * Register hooks and filters
	 */
	public static function hooks() {
		add_action( 'admin_init', [ __CLASS__, 'register_importer' ] );
	}

	/**
	 * Register importer
	 */
	public static function register_importer() {
		register_importer(
			'author-slack-id',
			'Liveblog Slack Author IDs',
			'Populate author Slack IDs to be used in the Liveblog integration.',
			[
				__CLASS__,
				'slack_id_import',
			]
		);
	}

	/**
	 * Process file upload
	 *
	 * @return bool
	 */
	public static function handle_upload() {
		$file = wp_import_handle_upload();
		if ( isset( $file['error'] ) ) {
			echo '<p><strong>Sorry, there has been an error.</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';

			return false;
		} elseif ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>Sorry, there has been an error.</strong><br />';
			printf( 'The import file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', esc_html( $file['file'] ) );
			echo '</p>';

			return false;
		}

		self::$file_id = (int) $file['id'];

		return true;
	}

	/**
	 * Open CSV and import the slack ids
	 * format: user_id, user_type, user_name, email, slack_id
	 *
	 * @param $file
	 *
	 * @return array
	 */
	public static function import( $file ) {
		$rows = [];
		$row  = 1;

		$csv_content = array_map( 'str_getcsv', file( $file ) );

		foreach ( $csv_content as $data ) {
			// Skip header
			if ( 1 === $row++ ) {
				continue;
			}

			$user_id   = (int) $data[0];
			$user_type = $data[1];
			$user_name = $data[2];
			$slack_id  = $data[4];

			// Skip user if slack id column is empty
			if ( empty( $slack_id ) ) {
				continue;
			}

			if ( 'Contributor' === $user_type && 'guest-author' === get_post_type( $user_id ) ) {
				update_post_meta( $user_id, 'cap-' . WPCOM_Liveblog_Author_Settings::SETTING_META, $slack_id );
				$rows[] = sprintf( '<a href="%s">%s (%s)</a>', esc_url( get_edit_post_link( $user_id ) ), esc_html( $user_name ), esc_html( $slack_id ) );
			} elseif ( get_user_by( 'ID', $user_id ) ) {
				update_user_meta( $user_id, WPCOM_Liveblog_Author_Settings::SETTING_META, $slack_id ); //phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_update_user_meta
				$rows[] = sprintf( '<a href="%s">%s (%s)</a>', esc_url( get_edit_user_link( $user_id ) ), esc_html( $user_name ), esc_html( $slack_id ) );
			}
		}

		return $rows;
	}

	/**
	 * Import admin screen
	 */
	public static function slack_id_import() {
		?>
		<div class="wrap">
			<h2>Import Slack Author IDs</h2>

			<?php
			$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step']; //phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected
			switch ( $step ) {
				case 0:
					echo '<div class="narrow">';
					echo '<p>Upload a CSV of the authors you would like to assign a slack ID.</p>';
					wp_import_upload_form( 'admin.php?import=author-slack-id&amp;step=1' );
					echo '</div>';
					break;
				case 1:
					check_admin_referer( 'import-upload' );
					self::handle_upload();
					$file = get_attached_file( self::$file_id );
					set_time_limit( 0 );
					$users      = self::import( $file );
					$user_count = $users ? count( $users ) : 0;
					printf( '<p>%s users were assigned a Slack ID!</p>', $user_count );
					if ( $user_count ) {
						echo '<ol>';
						foreach ( $users as $user ) {
							printf( '<li>%s</li>', wp_kses_post( $user ) );
						}
						echo '</ol>';
					}
					break;
			}
			?>
		</div>
		<?php
	}
}
