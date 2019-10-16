<?php

class Liveblog_Slash_Command_API {
	const START_ENDPOINT = '/v1/slack/start';
	const END_ENDPOINT   = '/v1/slack/end';

	/**
	 * Register Hooks
	 */
	public static function hooks() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_endpoints' ] );
	}

	/**
	 * Register slack slash command endpoints
	 */
	public static function register_endpoints() {
		register_rest_route(
			'liveblog',
			self::START_ENDPOINT,
			[
				'methods'       => \WP_REST_Server::CREATABLE,
				'callback'      => [ __CLASS__, 'start_request' ],
				'show_in_index' => false,
			]
		);

		register_rest_route(
			'liveblog',
			self::END_ENDPOINT,
			[
				'methods'       => \WP_REST_Server::CREATABLE,
				'callback'      => [ __CLASS__, 'end_request' ],
				'show_in_index' => false,
			]
		);
	}


	/**
	 * Start liveblog this also enabled the slack webhook api endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return string|WP_Error
	 */
	public static function start_request( WP_REST_Request $request ) {
		//Validate slack request
		$liveblog = self::validate_request( $request );
		if ( is_wp_error( $liveblog ) ) {
			return $liveblog;
		}

		$settings                          = get_option( Liveblog_Slack_Settings::OPTION_NAME, [] );
		$settings['enable_event_endpoint'] = 'on';
		update_option( Liveblog_Slack_Settings::OPTION_NAME, $settings, 'no' );

		\Liveblog::set_liveblog_state( $liveblog, 'enable' );

		return rest_ensure_response( [ 'text' => sprintf( '%s has been started!', get_the_title( $liveblog ) ) ] );
	}

	/**
	 * End liveblog this also disables the slack webhook api endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return string|WP_Error
	 */
	public static function end_request( WP_REST_Request $request ) {
		//Validate slack request
		$liveblog = self::validate_request( $request );
		if ( is_wp_error( $liveblog ) ) {
			return $liveblog;
		}

		$settings = get_option( Liveblog_Slack_Settings::OPTION_NAME, [] );
		unset( $settings['enable_event_endpoint'] );
		update_option( Liveblog_Slack_Settings::OPTION_NAME, $settings );

		\Liveblog::set_liveblog_state( $liveblog, 'archive' );

		return rest_ensure_response( [ 'text' => sprintf( '%s has been ended!', get_the_title( $liveblog ) ) ] );
	}

	/**
	 * Validate that the slash command is coming from slack and that it belongs
	 * to a valida liveblog
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return integer|WP_Error
	 */
	public static function validate_request( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		parse_str( $raw_body, $body );
		$channel_id = $body['channel_id'] ?? false;

		if ( ! class_exists( 'Liveblog' ) ) {
			return new WP_Error( 'slack_liveblog_missing', 'Liveblog plugin is not installed or enabled on this site', [ 'status' => 200 ] );
		}

		//Validate slack request
		$validate = Liveblog_Webhook_API::validate_request( $request );
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		//verify channel
		$liveblog = Liveblog_Webhook_API::get_liveblog_by_channel_id( $channel_id );
		if ( ! $liveblog ) {
			return new WP_Error( 'slack_missing_channel', "Liveblog with channel $channel_id not found", [ 'status' => 200 ] );
		}

		return $liveblog;
	}
}
