<?php

/**
 * Class Liveblog_Metadata
 *
 * Adds Support for LiveBlogPosting Metadata
 */
class Liveblog_Metadata {
	const METABOX_KEY             = 'liveblog_event_metdata_metabox';
	const EVENT_METADATA_KEY      = 'liveblog_event_metadata';
	const METADATA_NONCE          = 'liveblog_event_metadata_nonce';
	const METADATA_NONCE_FIELD    = 'liveblog_event_metadata_nonce_';
	const METADATA_START_TIME     = 'start_time';
	const METADATA_END_TIME       = 'end_time';
	const METADATA_EVENT_TITLE    = 'event_title';
	const METADATA_EVENT_URL      = 'event_url';
	const METADATA_EVENT_LOCATION = 'event_location';
	const METADATA_SLACK_CHANNEL  = 'slack_channel';

	public static $text_format = '<p><label for="%1$s">%3$s</label><input type="%2$s" id="%1$s" class="widefat" name="%1$s" value="%4$s"/></p>';


	/**
	 * Called by Liveblog::load(),
	 */
	public static function load() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_post' ] );
		add_action( 'liveblog_metabox', [ __CLASS__, 'liveblog_event_metadata' ] );
		add_action( 'liveblog_metabox', [ __CLASS__, 'liveblog_slack_metadata' ] );
		add_action( 'liveblog_metabox', [ __CLASS__, 'liveblog_state' ], 12 );
		add_action( 'save_liveblog_metabox', [ __CLASS__, 'save_event_metadata' ] );
		add_action( 'save_liveblog_metabox', [ __CLASS__, 'save_slack_metadata' ] );
	}

	/**
	 * Add Liveblog Additional Metadata Metabox.
	 *
	 * @return void
	 */
	public static function add_meta_box( $post_type ) {
		// Bail if not supported
		if ( ! post_type_supports( $post_type, Liveblog::KEY ) ) {
			return;
		}

		add_meta_box(
			self::METABOX_KEY,
			__( 'Liveblog', 'liveblog' ),
			[ __CLASS__, 'liveblog_metadata_metabox' ],
			null,
			'side',
			'low'
		);
	}

	/**
	 * Update event metadata on save_post.
	 *
	 * @param  int  $post_id Post ID.
	 * @return bool          Boolean true if successful update, false on failure.
	 */
	public static function save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Prevent quick edit from clearing custom fields.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		// Confirm nonce is present.
		if ( ! isset( $_POST[ self::METADATA_NONCE ] ) ) {
			return false;
		}

		// Verify nonces.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::METADATA_NONCE ] ) ), self::METADATA_NONCE_FIELD . $post_id ) ) {
			return false;
		};

		// save state (archive, enabled)
		$new_state = filter_input( INPUT_POST, 'state', FILTER_SANITIZE_STRING );
		Liveblog::set_liveblog_state( $post_id, $new_state );

		do_action( 'save_liveblog_metabox', $post_id );
	}

	/**
	 * Save event metadata.
	 *
	 * @param  int   $post_id  Post ID.
	 * @return bool            Boolean true if successful update, false on failure.
	 */
	public static function save_event_metadata( $post_id ) {
		$fields = [
			self::METADATA_START_TIME,
			self::METADATA_END_TIME,
			self::METADATA_EVENT_TITLE,
			self::METADATA_EVENT_URL,
			self::METADATA_EVENT_LOCATION,
		];

		$values = [];
		foreach ( $fields as $meta_key ) {
			$values[ $meta_key ] = filter_input( INPUT_POST, $meta_key, FILTER_SANITIZE_STRING );
		}

		if ( $values ) {
			return update_post_meta( $post_id, self::EVENT_METADATA_KEY, $values );
		} else {
			return delete_post_meta( $post_id, self::EVENT_METADATA_KEY );
		}

		return false;
	}

	/**
	 * Save slack channel metadata.
	 *
	 * @param  int   $post_id  Post ID.
	 * @return bool            Boolean true if successful update, false on failure.
	 */
	public static function save_slack_metadata( $post_id ) {
		$slack_channel = filter_input( INPUT_POST, self::METADATA_SLACK_CHANNEL, FILTER_SANITIZE_STRING );

		if ( $slack_channel ) {
			return update_post_meta( $post_id, self::METADATA_SLACK_CHANNEL, $slack_channel );
		} else {
			return delete_post_meta( $post_id, self::METADATA_SLACK_CHANNEL );
		}
	}

	/**
	 * Print a text field for the metabox
	 *
	 * @param  string $field_id DOM ID for field
	 * @param  string $field_type type of field
	 * @param  string $label label for field
	 * @param  string $value value for field
	 */
	public static function print_text_field( $field_id, $field_type, $label, $value ) {
		echo wp_kses( sprintf( self::$text_format, esc_attr( $field_id ), esc_attr( $field_type ), esc_html( $label ), esc_attr( $value ) ), Liveblog_Helpers::$meta_box_allowed_tags );
	}

	/**
	 * Liveblog metadata metabox render callback.
	 *
	 * @param  \WP_Post $post The post object.
	 * @return void
	 */
	public static function liveblog_metadata_metabox( $post ) {
		wp_nonce_field( self::METADATA_NONCE_FIELD . $post->ID, self::METADATA_NONCE );

		do_action( 'liveblog_metabox', $post->ID );
	}

	/**
	 * Liveblog metadata: state
	 *
	 * @param  $post_id
	 * @return void
	 */
	public static function liveblog_state( $post_id ) {
		$current_state = Liveblog::get_liveblog_state( $post_id );

		$template_variables            = [];
		$template_variables['buttons'] = [
			'enable'  => [
				'value'       => 'enable',
				'text'        => __( 'Enable', 'liveblog' ),
				'description' => __( 'Enables liveblog on this post. Posting tools are enabled for editors, visitors get the latest updates.', 'liveblog' ),
				// translators: 1: post url
				'active-text' => __( 'There is an <strong>enabled</strong> liveblog on this post.', 'liveblog' ),
				'primary'     => true,
				'disabled'    => false,
			],
			'archive' => [
				'value'       => 'archive',
				'text'        => __( 'Archive', 'liveblog' ),
				'description' => __( 'Archives the liveblog on this post. Visitors still see the liveblog entries, but posting tools are hidden.', 'liveblog' ),
				// translators: 1: archive url
				'active-text' => __( 'There is an <strong>archived</strong> liveblog on this post.', 'liveblog' ),
				'primary'     => false,
				'disabled'    => false,
			],
		];
		if ( $current_state ) {
			$template_variables['active_text']                           = $template_variables['buttons'][ $current_state ]['active-text'];
			$template_variables['buttons'][ $current_state ]['disabled'] = true;
		} else {
			$template_variables['active_text']                    = __( 'This is a normal WordPress post, without a liveblog.', 'liveblog' );
			$template_variables['buttons']['archive']['disabled'] = true;
		}

		echo Liveblog::get_template_part( 'meta-box.php', $template_variables ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Liveblog metadata: event data
	 *
	 * @param  \WP_Post $post The post object.
	 * @return void
	 */
	public static function liveblog_event_metadata( $post_id ) {
		$meta = get_post_meta( $post_id, self::EVENT_METADATA_KEY, true );

		$start    = isset( $meta[ self::METADATA_START_TIME ] ) ? $meta[ self::METADATA_START_TIME ] : '';
		$end      = isset( $meta[ self::METADATA_END_TIME ] ) ? $meta[ self::METADATA_END_TIME ] : '';
		$url      = isset( $meta[ self::METADATA_EVENT_URL ] ) ? $meta[ self::METADATA_EVENT_URL ] : '';
		$title    = isset( $meta[ self::METADATA_EVENT_TITLE ] ) ? $meta[ self::METADATA_EVENT_TITLE ] : '';
		$location = isset( $meta[ self::METADATA_EVENT_LOCATION ] ) ? $meta[ self::METADATA_EVENT_LOCATION ] : '';

		echo '<hr>';
		echo '<p><b>Event Metadata</b></p>';

		self::print_text_field( self::METADATA_START_TIME, 'date', 'Coverage Start Date', $start );
		self::print_text_field( self::METADATA_END_TIME, 'date', 'Coverage End Date', $end );
		self::print_text_field( self::METADATA_EVENT_TITLE, 'text', 'Event Title', $title );
		self::print_text_field( self::METADATA_EVENT_URL, 'text', 'Event URL', $url );
		self::print_text_field( self::METADATA_EVENT_LOCATION, 'text', 'Event Location', $location );
	}

	/**
	 * Liveblog metadata: slack channel
	 *
	 * @param  \WP_Post $post The post object.
	 * @return void
	 */
	public static function liveblog_slack_metadata( $post_id ) {
		$slack = get_post_meta( $post_id, self::METADATA_SLACK_CHANNEL, true );

		echo '<hr>';
		echo '<p><b>Slack</b></p>';
		self::print_text_field( self::METADATA_SLACK_CHANNEL, 'text', 'Channel ID', $slack );
	}

	/**
	 * Append additional user defined metadata live blog metadata.
	 *
	 * @param  array    $metadata An array of metadata.
	 * @param  \WP_Post $post     WP_Post object.
	 * @return array              An array of metadata.
	 */
	public static function liveblog_append_metadata( $metadata, $post ) {
		$meta = get_post_meta( $post->ID, self::EVENT_METADATA_KEY, true );

		if ( ! $meta ) {
			return $metadata;
		}

		$start    = isset( $meta[ self::METADATA_START_TIME ] ) ? $meta[ self::METADATA_START_TIME ] : false;
		$end      = isset( $meta[ self::METADATA_END_TIME ] ) ? $meta[ self::METADATA_END_TIME ] : false;
		$url      = isset( $meta[ self::METADATA_EVENT_URL ] ) ? $meta[ self::METADATA_EVENT_URL ] : false;
		$title    = isset( $meta[ self::METADATA_EVENT_TITLE ] ) ? $meta[ self::METADATA_EVENT_TITLE ] : false;
		$location = isset( $meta[ self::METADATA_EVENT_LOCATION ] ) ? $meta[ self::METADATA_EVENT_LOCATION ] : '';

		// Assume the start time is the very beginning of the day.
		$formatted_start = self::format_metadata_8601( $start . ' 00:00:00' );
		if ( $formatted_start ) {
			$metadata['coverageStartTime'] = $formatted_start;
		}

		// Assume the end time is the very end of the day.
		$formatted_end = self::format_metadata_8601( $end . ' 23:59:59' );
		if ( $formatted_end ) {
			$metadata['coverageEndTime'] = $formatted_end;
		}

		// Add Event metadata if required fields are set.
		if ( $url && $title ) {
			$metadata['about']['@type'] = 'Event';

			if ( $title ) {
				$metadata['about']['name'] = $title;
			}

			if ( $url ) {
				$metadata['about']['url'] = $url;
			}

			if ( $location ) {
				$metadata['about']['location'] = [
					'@type'   => 'Place',
					'address' => $location,
				];
			}

			if ( $formatted_start ) {
				$metadata['about']['startDate'] = $formatted_start;
			}

			if ( $formatted_end ) {
				$metadata['about']['endDate'] = $formatted_end;
			}
		}

		return $metadata;
	}

	/**
	 * Formats a date to RFC8601 standards.
	 *
	 * @param string $date A date string.
	 * @return mixed       A RFC8601 formatted date string or false if error.
	 */
	protected static function format_metadata_8601( $date ) {
		$utc = DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
		if ( $utc ) {
			$utc->setTimezone( new DateTimeZone( 'UTC' ) );
			return $utc->format( 'c' );
		}
		return false;
	}
}
