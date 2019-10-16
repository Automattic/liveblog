<?php

class Liveblog_Export_Authors {

	/**
	 * Generate CSV to download of all the WordPress users and contributors
	 */
	public function download() {

		$output   = [];
		$output[] = [
			'User ID',
			'User Type',
			'User Name',
			'User Email',
			'Slack ID',
		];

		$output = $this->get_users( $output );
		$output = $this->get_contributors( $output );

		// create and save the csv
		$file_name = 'author-export-' . date( 'Y-m-d' ) . '.csv';

		Header( 'HTTP/1.1 200 OK' );
		Header( 'Content-Type: text/csv' );
		Header( "Content-Disposition: attachment; filename={$file_name}" );

		$output_stream = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		foreach ( $output as $row ) {
			fputcsv( $output_stream, array_values( $row ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		}

		fclose( $output_stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		die();
	}

	/**
	 * Return list of WordPress users to export
	 *
	 * @param $output
	 *
	 * @return array
	 */
	public function get_users( $output ) {
		$page     = 1;
		$can_loop = true;

		$query_args = [
			'number' => 100,
		];

		while ( $can_loop ) {
			$query_args['paged'] = $page;
			$users               = new \WP_User_Query( $query_args );

			if ( $users->get_results() ) {
				foreach ( $users->get_results() as $user ) {
					$output[] = [
						'User ID'    => $user->ID,
						'User Type'  => 'WP User',
						'User Name'  => $user->display_name,
						'User Email' => $user->user_email,
						'Slack ID'   => get_user_meta( $user->ID, Liveblog_Author_Settings::SETTING_META, true ), //phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_get_user_meta
					];
				}
			} else {
				$can_loop = false;
			}
			$page ++;
		}

		return $output;
	}

	/**
	 * Return list of contributors to export
	 *
	 * @param $output
	 *
	 * @return array
	 */
	public function get_contributors( $output ) {
		$page     = 1;
		$can_loop = true;

		$query_args = [
			'posts_per_page' => 100,
			'post_type'      => 'guest-author',
			'post_status'    => 'publish',
		];

		while ( $can_loop ) {
			$query_args['paged'] = $page;
			$contributors        = new \WP_Query( $query_args );

			if ( $contributors->have_posts() ) {
				foreach ( $contributors->get_posts() as $contributor ) {
					$output[] = [
						'User ID'    => $contributor->ID,
						'User Type'  => 'Contributor',
						'User Name'  => $contributor->post_title,
						'User Email' => get_post_meta( $contributor->ID, 'cap-user_email', true ),
						'Slack ID'   => get_post_meta( $contributor->ID, 'cap-' . Liveblog_Author_Settings::SETTING_META, true ),
					];
				}
			} else {
				$can_loop = false;
			}
			$page ++;
		}

		return $output;
	}
}
