<?php

/**
 * Class WPCOM_Liveblog_Cron
 *
 * This class integrates with the WP CRON API to handle Housekeeping for the plugin
 * It registers hooks to handle automated tasks across the plugin.
 *
 */

class WPCOM_Liveblog_Cron {

	/**
	 * Sets up the class.
	 */
	public static function load() {
		self::configure_autoarchive_cron();
	}

	/**
	 * Configures the CRON Entry to Check for the Auto Archive Expiry on all Live Blogs.
	 * @return mixed
	 */
	private static function configure_autoarchive_cron() {

		if ( ! wp_next_scheduled( 'auto_archive_check_hook' ) ) {
			wp_schedule_event( strtotime( 'today midnight' ), 'daily', 'auto_archive_check_hook' );
		}

		add_action( 'auto_archive_check_hook', array( __CLASS__, 'execute_auto_archive_housekeeping' ) );
	}

	/**
	 * The method that details the housekeepng to undertake during the execution of the CRON
	 * task.
	 * @return mixed
	 */
	public static function execute_auto_archive_housekeeping() {

		//If Auto Archive is enabled,
		if ( null !== WPCOM_Liveblog::$auto_archive_days ) {

			$posts = new WP_Query( array(
				'order'      => 'ASC',
				'orderby'    => 'ID',
				'meta_key'   => 'liveblog',
			) );

			foreach ( $posts->posts as $post ) {

				$post_id = $post->ID;

				//Lets grab todays day, convert it to a timestamp and look for any set auto archive date.
				$today = strtotime( date( 'Y-m-d H:i:s' ) );
				$expiry = get_post_meta( $post_id, WPCOM_Liveblog::$auto_archive_expiry_key, true );

				//if we have an expiry date lets compare them and if the
				// expiry is less than today i.e. its in the past lets archive the liveblog.
				if ( $expiry ) {

					if ( (int) $expiry < $today ) {
						WPCOM_Liveblog::set_liveblog_state( $post_id, 'archive' );
					}
				}
			}
		}
	}
}
