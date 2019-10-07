<?php


class Liveblog_Slack {

	const REFRESH_INTERVAL = 5;

	/**
	 * Init
	 */
	public static function init() {

		new Liveblog_Slack_Process_Entry_Async_Task();

		Liveblog_Slack_Settings::hooks();
		Liveblog_Author_Settings::hooks();
		Liveblog_Webhook_API::hooks();
		Liveblog_Slash_Command_API::hooks();
		Liveblog_Import_Authors_Slack_ID::hooks();

		if ( 'production' !== VIP_GO_ENV ) {
			add_filter( 'restricted_site_access_is_restricted', [ __CLASS__, 'whitelist_slack_endpoint' ], 10, 2 );
		}

		add_filter( 'liveblog_refresh_interval', [ __CLASS__, 'refresh_interval' ] );
	}

	/**
	 * Allow liveblog endpoint to be accessed on staging
	 *
	 * @param $is_restricted
	 * @param $wp
	 *
	 * @return bool
	 */
	public static function whitelist_slack_endpoint( $is_restricted, $wp ) {
		if ( ! empty( $wp->query_vars['rest_route'] ) && false !== strpos( $wp->query_vars['rest_route'], '/liveblog/v1/slack' ) ) {
			$is_restricted = false;
		}

		return $is_restricted;
	}

	/**
	 * Speed up refresh interval
	 *
	 * @param $interval
	 *
	 * @return int
	 */
	public static function refresh_interval( $interval ) {
		if ( is_user_logged_in() ) {
			$interval = self::REFRESH_INTERVAL;
		}
		return $interval;
	}
}

add_action( 'init', [ __NAMESPACE__ . 'Liveblog_Slack', 'init' ] );

add_action( 'wp_async_slack_process_entry', [ 'Liveblog_Webhook_API', 'process_event' ] );
