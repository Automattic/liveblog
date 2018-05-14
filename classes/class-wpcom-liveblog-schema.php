<?php

/**
 * Class WPCOM_Liveblog_Schema
 *
 * Generate LiveBlogPosting Schema
 * http://schema.org/LiveBlogPosting
 *
 */
class WPCOM_Liveblog_Schema {

	protected $query;
	protected $post_id;
	protected $permalink;


	/**
	 * WPCOM_Liveblog_Schema constructor.
	 *
	 * @param $post_id
	 */
	public function __construct( $post_id ) {
		$this->query   = new WPCOM_Liveblog_Entry_Query( $post_id, WPCOM_Liveblog::KEY );
		$this->post_id = $post_id;

		// store permalink because it's used in a loop @ convert_entry_to_schema_blog_posting
		$this->permalink = get_permalink( $this->post_id );
	}


	/**
	 * Generate the full schema HTML
	 *
	 * @return string
	 */
	public function render() {

		$schema = $this->generate_schema();

		if ( ! is_array( $schema ) ) {
			return '';
		}

		$output = '';
		$output .= '<script type="application/ld+json">';
		$output .= wp_json_encode( $schema );
		$output .= '</script>';

		return $output;
	}

	/**
	 * Generate an array of all the schema properties from liveblog updates
	 *
	 * @return array|mixed|void
	 */
	public function generate_schema() {

		$updates    = (array) $this->get_live_blog_updates();
		$start_time = $this->get_start_time( $updates );
		$end_time   = $this->get_end_time( $updates );
		
		$schema = array(
			"@context"          => "http://schema.org",
			"@type"             => "LiveBlogPosting",
			"url"               => $this->permalink,
			"name"              => get_the_title( $this->post_id ),
			"coverageStartTime" => $start_time,
			"liveBlogUpdate"    => $updates,
		);

		if ( ! empty( $end_time ) ) {
			$schema['coverageEndTime'] = $end_time;
		}

		/**
		 * Filter LiveBlogPosting schema - allow schema customization
		 *
		 * @param string                $schema  - an array of already generated schema data
		 * @param int                   $post_id - the post_id shcema is generated for
		 * @param WPCOM_Liveblog_Schema $this    - current schema class instance for $post_id
		 */
		return apply_filters( 'liveblog_schema_liveblogposting', $schema, $this->post_id, $this );
	}

	/**
	 *  Get all Liveblog entries and convert them to Schema BlogPosting
	 *
	 * @return array
	 */
	public function get_live_blog_updates() {

		// `get_all_entries_asc` may return null, cast to array:
		$entries = (array) $this->query->get_all_entries_asc();

		if ( empty( $entries ) ) {
			return array();
		}

		return array_map( array( $this, 'convert_entry_to_schema_blog_posting' ), $entries );
	}


	/**
	 * Get the coverage start time, based on the first entry
	 *
	 * @param $entries_asc
	 *
	 * @return string
	 */
	public function get_start_time( $entries_asc ) {

		if ( ! is_array( $entries_asc ) || empty( $entries_asc ) ) {
			return '';
		}

		$first_value = array_shift( $entries_asc );

		if ( empty( $first_value['datePublished'] ) ) {
			return '';
		}

		return $first_value['datePublished'];
	}

	/**
	 * Get the coverage end time, based on the last entry
	 *
	 * @param $entries_asc
	 *
	 * @return string
	 */
	public function get_end_time( $entries_asc ) {

		// Make sure entries exist, and
		// Only show end time if the Liveblog has ended ( is archived )
		if ( ! is_array( $entries_asc ) || empty( $entries_asc ) || 'archive' !== WPCOM_Liveblog::get_liveblog_state() ) {
			return '';
		}

		$latest_update = array_pop($entries_asc);
		if ( ! isset( $latest_update['datePublished'] ) ) {
			return '';
		}

		return $latest_update['datePublished'];
	}

	/**
	 * Convert a Liveblog entry to Schema.org BlogPosting
	 * @url http://schema.org/BlogPosting
	 *
	 * @param WPCOM_Liveblog_Entry $entry
	 *
	 * @return array
	 */
	protected function convert_entry_to_schema_blog_posting( $entry ) {

		$dateTime = new DateTime();

		return array(
			"@type"         => "BlogPosting",
			// ID Format can be found in the DOM ".liveblog-feed > .liveblog-entry#id_**":
			"url"           => $this->permalink . '#id_' . $entry->get_id(),
			"datePublished" => $dateTime->setTimeStamp( $entry->get_timestamp() )->format( DateTime::ISO8601 ),
			// articleBody doesn't support HTML - remove any HTML from the content
			"articleBody"   => wp_strip_all_tags( $entry->get_content() ),
			"author"        => array(
				"name" => $entry->get_author_name()
			),
		);
	}
}
