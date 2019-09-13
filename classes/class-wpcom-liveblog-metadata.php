<?php

/**
 * Class WPCOM_Liveblog_Metadata
 *
 * Adds Support for LiveBlogPosting Metadata
 */
class WPCOM_Liveblog_Metadata {
	const METABOX_KEY             = 'liveblog_event_metdata_metabox';
	const METADATA_KEY            = 'liveblog_event_metadata';
	const METADATA_NONCE          = 'liveblog_event_metadata_nonce';
	const METADATA_NONCE_FIELD    = 'liveblog_event_metadata_nonce_';
	const METADATA_START_TIME     = 'start_time';
	const METADATA_END_TIME       = 'end_time';
	const METADATA_EVENT_TITLE    = 'event_title';
	const METADATA_EVENT_URL      = 'event_url';
	const METADATA_EVENT_LOCATION = 'event_location';
	const METADATA_SLACK_CHANNEL  = 'event_location';

	/**
	 * Called by WPCOM_Liveblog::load(),
	 */
	public static function load() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 3 );
	}

	/**
	 * Add Liveblog Additional Metadata Metabox.
	 *
	 * @return void
	 */
	public static function add_meta_box( $post_type ) {
		// Bail if not supported
		if ( ! post_type_supports( $post_type, WPCOM_Liveblog::KEY ) ) {
			return;
		}

		add_meta_box(
			self::METABOX_KEY,
			__( 'Liveblog Event Metadata', 'liveblog' ),
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

		// Save meta data.
		$metadata = isset( $_POST[ self::METADATA_KEY ] ) ? wp_unslash( $_POST[ self::METADATA_KEY ] ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return self::save_post_meta( $metadata, $post_id );
	}

	/**
	 * Save event metadata.
	 *
	 * @param  array $metadata An array of event metadata.
	 * @param  int   $post_id  Post ID.
	 * @return bool            Boolean true if successful update, false on failure.
	 */
	protected static function save_post_meta( $metadata, $post_id ) {
		// Save the Liveblog Metadata
		if ( isset( $metadata ) && is_array( $metadata ) ) {
			$fields = [
				self::METADATA_START_TIME,
				self::METADATA_END_TIME,
				self::METADATA_EVENT_TITLE,
				self::METADATA_EVENT_URL,
				self::METADATA_EVENT_LOCATION,
				self::METADATA_SLACK_CHANNEL,
			];

			$values = [];
			foreach ( $fields as $field ) {
				$values[ $field ] = isset( $metadata[ $field ] )
				? sanitize_text_field( wp_unslash( $metadata[ $field ] ) )
				: '';
			}
			return update_post_meta( $post_id, self::METADATA_KEY, $values );
		}

		return false;
	}

	/**
	 * Liveblog metadata metabox render callback.
	 *
	 * @param  \WP_Post $post The post object.
	 * @return void
	 */
	public static function liveblog_metadata_metabox( $post ) {
		$meta     = get_post_meta( $post->ID, self::METADATA_KEY, true );
		$start    = isset( $meta[ self::METADATA_START_TIME ] ) ? $meta[ self::METADATA_START_TIME ] : '';
		$end      = isset( $meta[ self::METADATA_END_TIME ] ) ? $meta[ self::METADATA_END_TIME ] : '';
		$url      = isset( $meta[ self::METADATA_EVENT_URL ] ) ? $meta[ self::METADATA_EVENT_URL ] : '';
		$title    = isset( $meta[ self::METADATA_EVENT_TITLE ] ) ? $meta[ self::METADATA_EVENT_TITLE ] : '';
		$location = isset( $meta[ self::METADATA_EVENT_LOCATION ] ) ? $meta[ self::METADATA_EVENT_LOCATION ] : '';
		$slack    = isset( $meta[ self::METADATA_SLACK_CHANNEL ] ) ? $meta[ self::METADATA_SLACK_CHANNEL ] : '';
		$format   = '<p><label for="%1$s">%3$s</label><input type="%2$s" id="%1$s" class="widefat" name="%1$s" value="%4$s"/></p>';
		$name     = function( $key ) {
			return WPCOM_Liveblog_Metadata::METADATA_KEY . '[' . $key . ']';
		};

		wp_nonce_field( self::METADATA_NONCE_FIELD . $post->ID, self::METADATA_NONCE );

		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_START_TIME ) ), 'date', 'Coverage Start Date', $start ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_END_TIME ) ), 'date', 'Coverage End Date', $end ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_EVENT_TITLE ) ), 'text', 'Event Title', $title ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_EVENT_URL ) ), 'text', 'Event URL', $url ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_EVENT_LOCATION ) ), 'text', 'Event Location', $location ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );
		echo wp_kses( sprintf( $format, esc_attr( $name( self::METADATA_SLACK_CHANNEL ) ), 'text', 'Slack Channel ID', $location ), WPCOM_Liveblog_Helpers::$meta_box_allowed_tags );

		do_action( 'liveblog_after_metadata' );
	}

	/**
	 * Append additional user defined metadata live blog metadata.
	 *
	 * @param  array    $metadata An array of metadata.
	 * @param  \WP_Post $post     WP_Post object.
	 * @return array              An array of metadata.
	 */
	public static function liveblog_append_metadata( $metadata, $post ) {
		$meta = get_post_meta( $post->ID, self::METADATA_KEY, true );

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
