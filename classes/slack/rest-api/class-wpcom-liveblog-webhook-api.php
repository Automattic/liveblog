<?php

class WPCOM_Liveblog_Webhook_API {

	const EVENT_ENDPOINT  = 'v1/slack';
	const CACHE_KEY       = 'liveblog';
	const CACHE_GROUP     = 'slack';
	const MESSAGE_ID_META = 'client_msg_id';
	const ASYNC_TASK      = 'slack_process_entry';
	const INGEST_REGEX    = '/^FOR PUB:/mi';

	/**
	 * Register Hooks
	 */
	public static function hooks() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_endpoints' ] );
	}

	/**
	 * Register slack webhook endpoint
	 */
	public static function register_endpoints() {
		register_rest_route(
			'liveblog',
			self::EVENT_ENDPOINT,
			[
				'methods'       => \WP_REST_Server::CREATABLE,
				'callback'      => [ __CLASS__, 'request' ],
				'show_in_index' => false,
			]
		);
	}

	/**
	 * Process request to the slack webhook
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error
	 */
	public static function request( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		$body     = json_decode( $raw_body );
		$settings = get_option( WPCOM_Liveblog_Slack_Settings::OPTION_NAME, [] );

		if ( empty( $settings['enable_event_endpoint'] ) || 'on' !== $settings['enable_event_endpoint'] ) {
			return new WP_Error( 'slack_liveblog_disabled', 'The liveblog event api endpoint is currently disabled', [ 'status' => 200 ] );
		}

		//Make sure we have a slack signing secret
		if ( empty( $settings ) || empty( $settings['signing_secret'] ) ) {
			return new WP_Error( 'slack_not_configured', 'Slack liveblog integration is missing the slack signing secret', [ 'status' => 200 ] );
		}

		//Validate slack request
		$validate = self::validate_request( $request );
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		//If body contains a challenge property than slack is trying to verify the endpoint
		if ( property_exists( $body, 'challenge' ) ) {
			return rest_ensure_response( [ 'challenge' => $body->challenge ] );
		}

		//Only ingest entries that start with FOR PUB:
		if ( 0 === preg_match( apply_filters( 'liveblog_slack_ingest_regex', self::INGEST_REGEX ), $body->event->message->text ?? '', $matches ) && 0 === preg_match( apply_filters( 'liveblog_slack_ingest_regex', self::INGEST_REGEX ), $body->event->text ?? '', $matches ) ) {
			return new WP_Error( 'slack_not_liveblog_entry', 'The current request is not for a liveblog entry', [ 'status' => 200 ] );
		}

		/**
		 * Kickoff async task use object cache to make sure we only fire the event once
		 */
		$client_msg_id = $body->event->message->client_msg_id ?? $body->event->client_msg_id;
		$type          = property_exists( $body->event, 'message' ) ? 'update' : 'new';
		$key           = self::CACHE_KEY . '_' . $type . '_' . $client_msg_id;

		$event = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false === $event ) {
			do_action( self::ASYNC_TASK, $body );
			wp_cache_set( $key, 'true', self::CACHE_GROUP, MINUTE_IN_SECONDS ); //phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.LowCacheTime
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Validate request to make sure its from slack
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_Error
	 */
	public static function validate_request( WP_REST_Request $request ) {
		$raw_body                = $request->get_body();
		$headers_slack_signature = $request->get_header( 'x_slack_signature' );
		$headers_slack_timestamp = $request->get_header( 'x_slack_request_timestamp' );
		$version                 = explode( '=', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SLACK_SIGNATURE'] ) ) ); //phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$sig_basestring          = "{$version[0]}:$headers_slack_timestamp:$raw_body";
		$settings                = get_option( WPCOM_Liveblog_Slack_Settings::OPTION_NAME, [] );

		$hash_signature = hash_hmac( 'sha256', $sig_basestring, $settings['signing_secret'] ?? '' );

		//Make sure we're getting a request from slack
		if ( empty( $headers_slack_signature ) || empty( $headers_slack_timestamp ) ) {
			return new WP_Error( 'slack_unauthorized_request', 'Unauthorized Request', [ 'status' => 200 ] );
		}

		//Make sure we verify the request from slack
		if ( ! hash_equals( $headers_slack_signature, "v0=$hash_signature" ) ) {
			return new WP_Error( 'slack_verification_error', 'Request could not be verified', [ 'status' => 200 ] );
		}

		return true;
	}

	/**
	 * Process cron event to ingest slack message
	 *
	 * @param $raw_body
	 *
	 * @return WP_Error
	 */
	public static function process_event( $body ) {
		$body          = json_decode( wp_json_encode( $body ) );
		$slack_user_id = $body->event->user ?? false;
		$slack_channel = $body->event->channel ?? false;
		$settings      = get_option( WPCOM_Liveblog_Slack_Settings::OPTION_NAME, [] );
		$is_edit       = property_exists( $body->event, 'subtype' ) && 'message_changed' === $body->event->subtype;

		if ( $is_edit ) {
			$slack_user_id = $body->event->message->user ?? false;
		}

		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'allow_html_tags' ], 10, 3 );

		// return early if the event was a delete event. We don't want liveblog entries getting deleted from slack
		if ( property_exists( $body->event, 'subtype' ) && 'message_deleted' === $body->event->subtype ) {
			return;
		}

		//verify channel
		$liveblog = self::get_liveblog_by_channel_id( $slack_channel );
		if ( ! $liveblog ) {
			return new WP_Error( 'slack_missing_channel', "Liveblog with channel $slack_channel not found", [ 'status' => 200 ] );
		}

		//verify user
		$user = self::get_contributor_by_slack_id( $slack_user_id );
		if ( ! $user ) {
			return new WP_Error( 'slack_missing_author', "Author with slack id $slack_user_id not found", [ 'status' => 200 ] );
		}

		/**
		 * Both \WPCOM_Liveblog_Entry::update and \WPCOM_Liveblog_Entry::insert require the author_id parameter
		 * and convert them to a user object that is then used by \WPCOM_Liveblog_Entry::insert_entry. This function validates
		 * the provided args to make sure it includes user parameter is set before inserting the comment. Because not everyone writing in
		 * slack will have a corresponding WordPress user will just user the liveblog author user id.
		 */
		$liveblog_author = self::get_liveblog_author( $liveblog );

		$client_msg_id  = $body->event->message->client_msg_id ?? $body->event->client_msg_id;
		$liveblog_entry = self::get_entry_by_message_id( $client_msg_id ?? 0 );
		$allow_edits    = isset( $settings['enable_entry_updates'] ) && 'on' === $settings['enable_entry_updates'];

		if ( $allow_edits && $is_edit && $liveblog_entry && 'draft' === $liveblog_entry->post_status ) {
			$original_text = $body->event->message->text;
			$entry_data    = self::sanitize_entry( $original_text );

			$entry = WPCOM_Liveblog_Entry::update(
				[
					'post_id'    => $liveblog,
					'entry_id'   => $liveblog_entry->ID,
					'headline'   => $entry_data['headline'],
					'content'    => $entry_data['content'],
					'author_ids' => apply_filters( 'liveblog_slack_authors', [ $user ], $original_text ),
					'user'       => $liveblog_author,
				]
			);
		} elseif ( false === $liveblog_entry && property_exists( $body->event, 'text' ) ) {
			$original_text = $body->event->text;
			$entry_data    = self::sanitize_entry( $original_text, $liveblog, $body->event->files ?? [] );

			$entry = WPCOM_Liveblog_Entry::insert(
				[
					'post_id'    => $liveblog,
					'headline'   => $entry_data['headline'],
					'content'    => $entry_data['content'],
					'author_ids' => apply_filters( 'liveblog_slack_authors', [ $user ], $original_text ),
					'user'       => $liveblog_author,
				]
			);

			if ( ! is_wp_error( $entry ) ) {
				update_post_meta( $entry->get_id(), self::MESSAGE_ID_META, sanitize_text_field( $body->event->client_msg_id ) );
			}
		}
	}

	/**
	 * Sanitize slack message text and process embeds and links
	 *
	 * @param       $content
	 * @param int   $liveblog_id
	 * @param array $files
	 *
	 * @return mixed
	 */
	public static function sanitize_entry( $content, $liveblog_id = 0, $files = [] ) {

		$headline = '';

		//remove for pub
		$content = preg_replace( apply_filters( 'liveblog_slack_ingest_regex', self::INGEST_REGEX ), '', $content );

		$content = apply_filters( 'liveblog_slack_entry_content', $content );

		//Parse markdown
		$content = WPCOM_Liveblog_Markdown_Parser::render( trim( $content ) );

		$content = preg_replace_callback(
			'/<\s*(http.*?)>/mi',
			function ( $match ) {
				$link         = $match[1];
				$is_shortcode = false;

				//convert ABC news videos to shortcodes
				if ( false !== strpos( $link, 'https://abcnews.go.com/video' ) ) {
					$link_query = wp_parse_url( $link, PHP_URL_QUERY );
					if ( $link_query ) {
						parse_str( $link_query, $query_params );
						if ( ! empty( $query_params['id'] ) ) {
							$is_shortcode = true;
							$link         = sprintf( '[abcvideo id=%d]', (int) $query_params['id'] );
						}
					} else {
						$link_path = wp_parse_url( $link, PHP_URL_PATH );
						$video_id  = preg_replace( '/\D/', '', $link_path );
						if ( ! empty( $video_id ) && is_numeric( $video_id ) ) {
							$is_shortcode = true;
							$link         = sprintf( '[abcvideo id=%d]', (int) $video_id );
						}
					}
				}

				//convert espn videos to iframe
				if ( false !== strpos( $link, 'https://www.espn.com/video/clip' ) ) {
					$link_query = wp_parse_url( $link, PHP_URL_QUERY );
					if ( $link_query ) {
						parse_str( $link_query, $query_params );
						if ( ! empty( $query_params['id'] ) ) {
							$is_shortcode = true;
							$link         = sprintf( '<iframe src="https://www.espn.com/core/video/iframe?id=%d" width="640" height="360"></iframe>', (int) $query_params['id'] );
						}
					}
				} elseif ( false !== strpos( $link, 'https://www.espn.com/core/video/iframe' ) ) {
					$is_shortcode = true;
					$link         = sprintf( '<iframe src="%s" width="640" height="360"></iframe>', esc_url( $link ) );
				}

				$embed     = new \WP_oEmbed();
				$is_oembed = $embed->get_provider( $match[1] );
				if ( ! empty( $match[1] ) && ! $is_oembed && ! $is_shortcode ) {
					// Convert giphy urls to images
					if ( false !== strpos( $link, 'giphy.com' ) ) {
						$link = sprintf( '<img src="%s"/>', esc_url( $match[1] ) );
					} else {
						// Return clean url so that markdown links will work
						$link = $match[1];
					}
				} elseif ( $is_oembed ) {
					// append new line to oembeds so that you can have back to back embed links
					$link = $match[1] . PHP_EOL;
				}

				return $link;
			},
			$content
		);

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$image = self::import_file( $file->url_private, $liveblog_id );
				if ( $image && ! is_wp_error( $image ) ) {
					$filetype = wp_check_filetype( basename( $image['file'] ) );
					$content .= "\r\n";
					if ( false !== strpos( $filetype['type'], 'image' ) ) {
						$content .= sprintf( '<img class="size-full wp-image-%d" src="%s" alt="" width="%d" height="%d" />', (int) $image['attachment_id'], esc_url( $image['file'] ), (int) $image['width'], (int) $image['height'] );
					} else {
						$content .= sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $image['file'] ), esc_html( basename( $image['file'] ) ) );
					}
				}
			}
		}

		//starts with header
		if ( '<h' === substr( $content, 0, 2 ) ) {
			preg_match( '/<h\d>([^<]*)<\/h\d>/i', $content, $matches );
			if ( $matches ) {
				$headline = wp_strip_all_tags( $matches[0] );
				$content  = trim( str_replace( $matches[0], '', $content ) );
			}
		}

		$entry = [
			'headline' => $headline,
			'content'  => wp_kses_post( $content ),
		];

		return $entry;
	}

	/**
	 * Return a contributor based off their slack id
	 *
	 * @param $slack_id
	 *
	 * @return bool|mixed|\WP_Query|\WP_User_Query
	 */
	public static function get_contributor_by_slack_id( $slack_id ) {
		$key = self::CACHE_KEY . '_' . $slack_id;

		$user = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $user ) {
			return $user;
		}

		$user = new \WP_User_Query(
			[
				'number'     => 1,
				'meta_query' => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => WPCOM_Liveblog_Author_Settings::SETTING_META,
						'value'   => $slack_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( ! $user->get_results() && class_exists( 'CoAuthors_Plus' ) ) {
			$user = new \WP_Query(
				[
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'post_type'      => 'guest-author',
					'meta_query'     => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => 'cap-' . WPCOM_Liveblog_Author_Settings::SETTING_META,
							'value'   => $slack_id,
							'compare' => '=',
						],
					],
				]
			);
			if ( $user->have_posts() ) {
				$user = reset( $user->posts );
			} else {
				$user = false;
			}
		} else {
			global $coauthors_plus;
			$users        = $user->get_results();
			$users        = reset( $users );
			$guest_author = $coauthors_plus->guest_authors->get_guest_author_by( 'linked_account', $users->user_login );
			if ( $guest_author ) {
				$user = $guest_author->ID;
			} else {
				$user = false;
			}
		}

		if ( ! empty( $user ) ) {
			wp_cache_set( $key, $user, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return $user;
	}

	/**
	 * Return liveblog by channel id. By default this will only look for liveblogs with the post status
	 * of publish or private. Draft, password and scheduled post statuses can be added by using the
	 * `liveblog_slack_channel_id_lookup_statuses` filter.
	 *
	 * @param $channel_id
	 *
	 * @return bool|mixed|\WP_Query
	 */
	public static function get_liveblog_by_channel_id( $channel_id ) {
		$key = self::CACHE_KEY . '_' . $channel_id;

		$channel = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $channel ) {
			return $channel;
		}

		/**
		 * Filter the post statuses that will be used to do a liveblog lookup by channel id;
		 *
		 * @param array The post statuses that will be passed to WP_Query
		 * @param string $channel_id The Channel id used to lookup a liveblog
		 */
		$post_status = apply_filters( 'liveblog_slack_channel_id_lookup_statuses', [ 'publish', 'private' ], $channel_id );

		$channel = new WP_Query(
			[
				'posts_per_page' => 1,
				'post_status'    => $post_status,
				'fields'         => 'ids',
				'post_type'      => WPCOM_Liveblog_CPT::$cpt_slug,
				'meta_query'     => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => WPCOM_Liveblog_Metadata::METADATA_SLACK_CHANNEL,
						'value'   => $channel_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( $channel->have_posts() ) {
			$channel_id = reset( $channel->posts );
			wp_cache_set( $key, $channel_id, self::CACHE_GROUP, HOUR_IN_SECONDS );

			return $channel_id;
		}

		return false;
	}

	/**
	 * Return the liveblog entry based off the slack message id
	 *
	 * @param $message_id
	 *
	 * @return bool|mixed
	 */
	public static function get_entry_by_message_id( $message_id ) {
		remove_filter( 'parse_query', [ 'WPCOM_Liveblog_CPT', 'hierarchical_posts_filter' ] );
		remove_action( 'pre_get_posts', [ 'WPCOM_Liveblog_CPT', 'filter_children_from_query' ] );

		$entry = new WP_Query(
			[
				'posts_per_page' => 10,
				'post_type'      => WPCOM_Liveblog_CPT::$cpt_slug,
				'post_status'    => [ 'draft', 'publish' ],
				'meta_query'     => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => self::MESSAGE_ID_META,
						'value'   => $message_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( $entry->have_posts() ) {
			return reset( $entry->posts );
		}

		return false;
	}

	/**
	 * Return liveblog author by liveblog id
	 *
	 * @param $liveblog_id
	 *
	 * @return mixed
	 */
	public static function get_liveblog_author( $liveblog_id ) {
		return get_post_field( 'post_author', $liveblog_id );
	}

	/**
	 * Allow Iframe code in wp_kses_post so ESPN embeds work
	 *
	 * @param $tags
	 * @param $context
	 *
	 * @return mixed
	 */
	public static function allow_html_tags( $tags, $context ) {
		if ( 'post' === $context ) {
			$tags['iframe'] = [
				'src'             => [],
				'height'          => [],
				'width'           => [],
				'frameborder'     => [],
				'allowfullscreen' => [],
			];
		}

		return $tags;

	}

	/**
	 * Import file and return back an array of image data used for inserting the file into the entry
	 *
	 * @param string $file_url
	 * @param        $liveblog_id
	 *
	 * @return array|bool|WP_Error
	 */
	public static function import_file( $file_url = '', $liveblog_id ) {
		$settings = get_option( WPCOM_Liveblog_Slack_Settings::OPTION_NAME, [] );

		if ( empty( $file_url ) ) {
			return false;
		}

		if ( empty( $settings ) || empty( $settings['oauth_access_token'] ) ) {
			return new WP_Error( 'slack_not_configured', 'Slack liveblog integration is missing the slack OAuth access token' );
		}

		// Download image.
		$response = vip_safe_wp_remote_get(
			$file_url,
			'',
			3,
			1,
			20,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $settings['oauth_access_token'],
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'slack_image_download', "The $file_url file could not be downloaded" );
		}

		$mime_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Bail if no file.
		if ( ! $mime_type ) {
			return false;
		}

		// Upload image to local uploads.
		$uploaded = wp_upload_bits( basename( $file_url ), null, wp_remote_retrieve_body( $response ) );

		if ( empty( $uploaded ) || ! isset( $uploaded['file'] ) ) {
			//Lets try and download it again
			sleep( 5 );
			$uploaded = wp_upload_bits( basename( $file_url ), null, wp_remote_retrieve_body( $response ) );

			if ( empty( $uploaded ) || ! isset( $uploaded['file'] ) ) {
				return new WP_Error( 'slack_image_upload', "The $file_url could not be uploaded" );
			}
		}

		$attachment = [
			'post_title'     => basename( $file_url ),
			'post_mime_type' => $uploaded['type'],
			'post_status'    => 'inherit',
			'post_date'      => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'guid'           => $uploaded['url'],
		];

		// Insert attachment.
		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'], $liveblog_id );

		if ( empty( $attachment_id ) ) {
			return new WP_Error( 'slack_image_attachment', "The $file_url could not be inserted into the media library" );
		}

		$attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		return [
			'attachment_id' => $attachment_id,
			'file'          => wp_get_attachment_url( $attachment_id ),
			'width'         => $attach_data['width'] ?? 0,
			'height'        => $attach_data['height'] ?? 0,
		];
	}
}
