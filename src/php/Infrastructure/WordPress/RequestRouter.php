<?php
/**
 * Request router for liveblog endpoints.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Service\EntryOperations;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\KeyEventService;

/**
 * Routes liveblog AJAX requests to appropriate handlers.
 *
 * Handles the non-REST-API legacy endpoints for backwards compatibility.
 */
final class RequestRouter {

	/**
	 * Entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $entry_query_service;

	/**
	 * Entry operations service.
	 *
	 * @var EntryOperations
	 */
	private EntryOperations $entry_operations;

	/**
	 * Key event service.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $key_event_service;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService $entry_query_service The entry query service.
	 * @param EntryOperations   $entry_operations    The entry operations service.
	 * @param KeyEventService   $key_event_service   The key event service.
	 */
	public function __construct(
		EntryQueryService $entry_query_service,
		EntryOperations $entry_operations,
		KeyEventService $key_event_service
	) {
		$this->entry_query_service = $entry_query_service;
		$this->entry_operations    = $entry_operations;
		$this->key_event_service   = $key_event_service;
	}

	/**
	 * Check if this is an initial page request (not AJAX).
	 *
	 * @return bool True if this is an initial page request.
	 */
	public function is_initial_page_request(): bool {
		global $wp_query;

		return ! isset( $wp_query->query_vars[ LiveblogConfiguration::KEY ] );
	}

	/**
	 * Check if this is an entries AJAX request.
	 *
	 * @return bool True if this is an entries AJAX request.
	 */
	public function is_entries_ajax_request(): bool {
		return (bool) get_query_var( LiveblogConfiguration::URL_ENDPOINT );
	}

	/**
	 * Get the AJAX method name for the current request.
	 *
	 * @param string $endpoint_suffix The endpoint suffix from the URL.
	 * @return string The method name to call.
	 */
	public function get_ajax_method( string $endpoint_suffix ): string {
		$suffix_to_method = array(
			'\d+/\d+'  => 'entries_between',
			'crud'     => 'crud_entry',
			'entry'    => 'single_entry',
			'lazyload' => 'lazyload_entries',
			'preview'  => 'preview_entry',
		);

		foreach ( $suffix_to_method as $suffix_re => $method ) {
			if ( preg_match( "%^$suffix_re/?%", $endpoint_suffix ) ) {
				return $method;
			}
		}

		return 'unknown';
	}

	/**
	 * Get entries between timestamps.
	 *
	 * @param int $post_id         The post ID.
	 * @param int $start_timestamp Start timestamp.
	 * @param int $end_timestamp   End timestamp.
	 * @return array The entries response array.
	 */
	public function get_entries_between(
		int $post_id,
		int $start_timestamp,
		int $end_timestamp
	): array {
		$latest_timestamp = null;
		$entries_for_json = array();

		$all_entries = $this->entry_query_service->get_all_entries_asc( $post_id );
		$entries     = $this->entry_query_service->find_between_timestamps(
			$all_entries,
			$start_timestamp,
			$end_timestamp
		);

		$lazyload = new LazyloadConfiguration();
		$per_page = $lazyload->get_entries_per_page();
		$pages    = false;

		if ( ! empty( $entries ) ) {
			foreach ( $entries as $entry ) {
				$latest_timestamp = max( $latest_timestamp, $entry->timestamp() );
			}

			$entries_for_json = $this->entries_to_json( $entries );
			$pages            = (int) ceil(
				count( $this->entry_query_service->flatten_entries( $all_entries ) ) / $per_page
			);
		}

		$result = array(
			'entries'          => $entries_for_json,
			'latest_timestamp' => $latest_timestamp,
			'refresh_interval' => LiveblogConfiguration::get_refresh_interval( $post_id ),
			'pages'            => $pages,
		);

		if ( ! empty( $entries_for_json ) ) {
			do_action( 'liveblog_entry_request', $result );
		} else {
			do_action( 'liveblog_entry_request_empty' );
		}

		return $result;
	}

	/**
	 * Get a single entry with navigation timestamps.
	 *
	 * @param int $post_id  The post ID.
	 * @param int $entry_id The entry ID.
	 * @return array The entry response array.
	 */
	public function get_single_entry( int $post_id, int $entry_id ): array {
		$result = $this->entry_query_service->get_single_entry( $post_id, $entry_id );

		$entries_for_json = array();
		if ( null !== $result['entry'] ) {
			$entries_for_json = $this->entries_to_json( array( $result['entry'] ) );
		}

		$response = array(
			'entries' => $entries_for_json,
		);

		if ( ! empty( $entries_for_json ) ) {
			$response['index']             = (int) filter_input( INPUT_GET, 'index', FILTER_SANITIZE_NUMBER_INT );
			$response['nextTimestamp']     = $result['next_timestamp'];
			$response['previousTimestamp'] = $result['previous_timestamp'];

			do_action( 'liveblog_entry_request', $response );
		} else {
			do_action( 'liveblog_entry_request_empty' );
		}

		return $response;
	}

	/**
	 * Get lazyload entries.
	 *
	 * @param int $post_id       The post ID.
	 * @param int $max_timestamp Maximum timestamp (0 for none).
	 * @param int $min_timestamp Minimum timestamp (0 for none).
	 * @return array The lazyload response array.
	 */
	public function get_lazyload_entries(
		int $post_id,
		int $max_timestamp,
		int $min_timestamp
	): array {
		$max_ts = $max_timestamp > 0 ? $max_timestamp : null;
		$min_ts = $min_timestamp > 0 ? $min_timestamp : null;

		$entries = $this->entry_query_service->get_for_lazyloading( $post_id, $max_ts, $min_ts );

		$entries_for_json = array();
		if ( ! empty( $entries ) ) {
			$lazyload         = new LazyloadConfiguration();
			$entries          = array_slice( $entries, 0, $lazyload->get_entries_per_page() );
			$entries_for_json = $this->entries_to_json( $entries );
		}

		$result = array(
			'entries' => $entries_for_json,
			'index'   => (int) filter_input( INPUT_GET, 'index', FILTER_SANITIZE_NUMBER_INT ),
		);

		if ( ! empty( $entries_for_json ) ) {
			do_action( 'liveblog_entry_request', $result );
		} else {
			do_action( 'liveblog_entry_request_empty' );
		}

		return $result;
	}

	/**
	 * Get paginated entries.
	 *
	 * @param int         $post_id          The post ID.
	 * @param int         $page             Page number.
	 * @param string|null $last_known_entry Last known entry ID-timestamp.
	 * @param int|null    $jump_to_id       Entry ID to jump to.
	 * @return array The paginated response array.
	 */
	public function get_entries_paged(
		int $post_id,
		int $page,
		?string $last_known_entry = null,
		?int $jump_to_id = null
	): array {
		$lazyload = new LazyloadConfiguration();

		$result = $this->entry_query_service->get_entries_paged(
			$post_id,
			$page,
			$lazyload->get_entries_per_page(),
			$last_known_entry,
			$jump_to_id
		);

		$response = array(
			'entries' => $this->entries_to_json( $result['entries'] ),
			'page'    => $result['page'],
			'pages'   => $result['pages'],
			'total'   => $result['total'],
		);

		if ( ! empty( $response['entries'] ) ) {
			do_action( 'liveblog_entry_request', $response );
		} else {
			do_action( 'liveblog_entry_request_empty' );
		}

		return $response;
	}

	/**
	 * Format a preview entry.
	 *
	 * @param string $content The content to preview.
	 * @return array The preview response.
	 */
	public function preview_entry( string $content ): array {
		return $this->entry_operations->format_preview( $content );
	}

	/**
	 * Get request data for pagination.
	 *
	 * @return object Request data with page, last, and id properties.
	 */
	public function get_request_data(): object {
		return (object) array(
			'page' => (int) get_query_var( 'liveblog_page', 1 ),
			'last' => get_query_var( 'liveblog_last', false ),
			'id'   => get_query_var( 'liveblog_id', false ),
		);
	}

	/**
	 * Parse timestamps from the URL query var.
	 *
	 * @return array{0: int, 1: int} Array of [start_timestamp, end_timestamp].
	 */
	public function get_timestamps_from_query(): array {
		$original_timestamps = explode( '/', get_query_var( LiveblogConfiguration::URL_ENDPOINT ) );

		$start_timestamp = isset( $original_timestamps[0] ) ? (int) $original_timestamps[0] : 0;
		$end_timestamp   = isset( $original_timestamps[1] ) ? (int) $original_timestamps[1] : 0;

		return array( $start_timestamp, $end_timestamp );
	}

	/**
	 * Check if the current user can edit liveblog.
	 *
	 * @return bool True if user can edit.
	 */
	public function current_user_can_edit(): bool {
		$cap    = LiveblogConfiguration::get_edit_capability();
		$retval = current_user_can( $cap );

		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}

	/**
	 * Verify the nonce for a request.
	 *
	 * @param string $action The nonce action.
	 * @return bool True if nonce is valid.
	 */
	public function verify_nonce( string $action = LiveblogConfiguration::NONCE_ACTION ): bool {
		$nonce_key = LiveblogConfiguration::NONCE_KEY;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This method performs the verification.
		if ( ! isset( $_REQUEST[ $nonce_key ] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		return (bool) wp_verify_nonce( wp_unslash( $_REQUEST[ $nonce_key ] ), $action );
	}

	/**
	 * Convert entries to JSON format.
	 *
	 * @param array $entries Array of Entry domain objects.
	 * @return array Array of JSON-ready objects.
	 */
	private function entries_to_json( array $entries ): array {
		$result = array();

		foreach ( $entries as $entry ) {
			$presenter = EntryPresenter::from_entry( $entry, $this->key_event_service );
			$result[]  = $presenter->for_json();
		}

		return $result;
	}
}
