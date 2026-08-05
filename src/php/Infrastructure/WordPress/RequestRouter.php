<?php
/**
 * Entry read facade for liveblog endpoints.
 *
 * @package Automattic\Liveblog\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure\WordPress;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\KeyEventService;

/**
 * Builds the JSON-ready entry payloads consumed by the REST API and AMP render path.
 */
final class RequestRouter {

	/**
	 * Entry query service.
	 *
	 * @var EntryQueryService
	 */
	private EntryQueryService $entry_query_service;

	/**
	 * Key event service.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $key_event_service;

	/**
	 * Content renderer used by the entry presenter.
	 *
	 * @var ContentRendererInterface
	 */
	private ContentRendererInterface $content_renderer;

	/**
	 * Constructor.
	 *
	 * @param EntryQueryService        $entry_query_service The entry query service.
	 * @param KeyEventService          $key_event_service   The key event service.
	 * @param ContentRendererInterface $content_renderer    Content renderer used by the entry presenter.
	 */
	public function __construct(
		EntryQueryService $entry_query_service,
		KeyEventService $key_event_service,
		ContentRendererInterface $content_renderer
	) {
		$this->entry_query_service = $entry_query_service;
		$this->key_event_service   = $key_event_service;
		$this->content_renderer    = $content_renderer;
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
	 * Convert entries to JSON format.
	 *
	 * @param array $entries Array of Entry domain objects.
	 * @return array Array of JSON-ready objects.
	 */
	private function entries_to_json( array $entries ): array {
		$result = array();

		foreach ( $entries as $entry ) {
			$presenter = EntryPresenter::from_entry( $entry, $this->key_event_service, $this->content_renderer );
			$result[]  = $presenter->for_json();
		}

		return $result;
	}
}
