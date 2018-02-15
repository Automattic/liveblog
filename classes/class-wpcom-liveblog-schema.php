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


	public function __construct( $post_id ) {
		$this->query   = new WPCOM_Liveblog_Entry_Query( $post_id, WPCOM_Liveblog::key );
		$this->post_id = $post_id;

		// store permalink because it's used in a loop @ convert_comment_to_schema_entry
		$this->permalink = get_permalink( $this->post_id );
	}
	

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
	 * @return mixed|void
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

		return apply_filters( 'liveblog_schema_liveblogposting', $schema );
	}

	/*
	 * Get all Liveblog entries and convert them to Schema BlogPosting
	 */
	public function get_live_blog_updates() {
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
		if ( ! isset( $entries_asc[0] ) || ! isset( $entries_asc[0]['datePublished'] ) ) {
			return '';
		}

		return $start_time = $entries_asc[0]['datePublished'];
	}

	/**
	 * Get the coverage end time, based on the last entry
	 *
	 * @param $entries_asc
	 *
	 * @return string
	 */
	public function get_end_time( $entries_asc ) {

		if ( ! is_array( $entries_asc ) || empty( $entries_asc ) || 'archive' !== WPCOM_Liveblog::get_liveblog_state() ) {
			return '';
		}

		$latest_update = $entries_asc[ count( $entries_asc ) - 1 ];
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
			"articleBody"   => wp_strip_all_tags( $entry->get_content() ),
			"author"        => array(
				"name" => $entry->get_author_name()
			),
		);
	}
}
