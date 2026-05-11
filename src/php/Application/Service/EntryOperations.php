<?php
/**
 * Entry operations service - facade for CRUD operations.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

use Automattic\Liveblog\Application\Presenter\EntryPresenter;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use WP_Error;
use WP_User;

/**
 * Entry operations service - provides CRUD operations for liveblog entries.
 *
 * This facade coordinates between the EntryService (for database operations)
 * to provide a unified interface for legacy code that previously used
 * Container::instance() directly.
 */
final class EntryOperations {

	/**
	 * Valid CRUD actions.
	 *
	 * @var string[]
	 */
	private const VALID_ACTIONS = array( 'insert', 'update', 'delete' );

	/**
	 * Entry service for CRUD operations.
	 *
	 * @var EntryService
	 */
	private EntryService $entry_service;

	/**
	 * Entry repository for fetching entries.
	 *
	 * @var EntryRepositoryInterface
	 */
	private EntryRepositoryInterface $repository;

	/**
	 * Content processor for preview rendering.
	 *
	 * @var ContentProcessor
	 */
	private ContentProcessor $content_processor;

	/**
	 * Constructor.
	 *
	 * @param EntryService             $entry_service     The entry service.
	 * @param EntryRepositoryInterface $repository        The entry repository.
	 * @param ContentProcessor         $content_processor The content processor.
	 */
	public function __construct(
		EntryService $entry_service,
		EntryRepositoryInterface $repository,
		ContentProcessor $content_processor
	) {
		$this->entry_service     = $entry_service;
		$this->repository        = $repository;
		$this->content_processor = $content_processor;
	}

	/**
	 * Insert a new entry.
	 *
	 * @param int        $post_id         The post ID.
	 * @param string     $content         The entry content.
	 * @param WP_User    $user            The author user object.
	 * @param bool       $hide_author     Whether to hide the author.
	 * @param int[]|null $contributor_ids Optional contributor IDs.
	 * @return EntryId The new entry ID.
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry creation fails.
	 */
	public function insert(
		int $post_id,
		string $content,
		WP_User $user,
		bool $hide_author = false,
		?array $contributor_ids = null
	): EntryId {
		return $this->entry_service->create(
			$post_id,
			$content,
			$user,
			$hide_author,
			$contributor_ids
		);
	}

	/**
	 * Update an existing entry.
	 *
	 * @param int     $post_id  The post ID.
	 * @param EntryId $entry_id The entry ID to update.
	 * @param string  $content  The new content.
	 * @param WP_User $user     The user making the update.
	 * @return EntryId The new entry ID.
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry update fails.
	 */
	public function update( int $post_id, EntryId $entry_id, string $content, WP_User $user ): EntryId {
		return $this->entry_service->update( $post_id, $entry_id, $content, $user );
	}

	/**
	 * Delete an entry.
	 *
	 * @param int     $post_id  The post ID.
	 * @param EntryId $entry_id The entry ID to delete.
	 * @param WP_User $user     The user making the deletion.
	 * @return EntryId The delete marker entry ID.
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws \RuntimeException If entry deletion fails.
	 */
	public function delete( int $post_id, EntryId $entry_id, WP_User $user ): EntryId {
		return $this->entry_service->delete( $post_id, $entry_id, $user );
	}

	/**
	 * Get the entry service.
	 *
	 * @return EntryService
	 */
	public function entry_service(): EntryService {
		return $this->entry_service;
	}

	/**
	 * Check if a CRUD action is valid.
	 *
	 * @param string $action The action to check.
	 * @return bool True if valid.
	 */
	public function is_valid_action( string $action ): bool {
		return in_array( $action, self::VALID_ACTIONS, true );
	}

	/**
	 * Perform a CRUD operation and return the result for JSON.
	 *
	 * @param string  $action The CRUD action (insert, update, delete).
	 * @param array   $args   Arguments including post_id, content, entry_id, author_id, contributor_ids.
	 * @param WP_User $user   The user performing the action.
	 * @return array|WP_Error The result array with entries, or WP_Error on failure.
	 */
	public function do_crud( string $action, array $args, WP_User $user ) {
		try {
			$entry_id = $this->execute_action( $action, $args, $user );

			$entry     = $this->repository->get_entry( $entry_id );
			$presenter = EntryPresenter::from_entry( $entry );

			return array(
				'entries'          => array( $presenter->for_json() ),
				'latest_timestamp' => null,
			);
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'entry-invalid-args', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'entry-insert', __( 'Error posting entry', 'liveblog' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Execute a CRUD action.
	 *
	 * @param string  $action The CRUD action.
	 * @param array   $args   Arguments for the action.
	 * @param WP_User $user   The user performing the action.
	 * @return EntryId The resulting entry ID.
	 * @throws \InvalidArgumentException If action is invalid.
	 */
	private function execute_action( string $action, array $args, WP_User $user ): EntryId {
		$post_id = (int) ( $args['post_id'] ?? 0 );

		switch ( $action ) {
			case 'insert':
				return $this->execute_insert( $args, $user, $post_id );

			case 'update':
				return $this->execute_update( $args, $post_id );

			case 'delete':
				return $this->execute_delete( $args, $user, $post_id );

			default:
				throw new \InvalidArgumentException( 'Invalid CRUD action: ' . esc_html( $action ) );
		}
	}

	/**
	 * Execute insert action.
	 *
	 * @param array   $args    Arguments.
	 * @param WP_User $user    User performing action.
	 * @param int     $post_id Post ID.
	 * @return EntryId The new entry ID.
	 */
	private function execute_insert( array $args, WP_User $user, int $post_id ): EntryId {
		$args = apply_filters( 'liveblog_before_insert_entry', $args );

		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$author_user = get_userdata( $args['author_id'] );
			if ( $author_user instanceof WP_User ) {
				$user = apply_filters( 'liveblog_userdata', $author_user, $args['author_id'] );
			}
		}

		$hide_author     = ! isset( $args['author_id'] ) || ! $args['author_id'];
		$contributor_ids = ! empty( $args['contributor_ids'] ) ? $args['contributor_ids'] : null;

		$entry_id = $this->entry_service->create(
			$post_id,
			$args['content'] ?? '',
			$user,
			$hide_author,
			$contributor_ids
		);

		do_action( 'liveblog_insert_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Execute update action.
	 *
	 * @param array $args    Arguments.
	 * @param int   $post_id Post ID.
	 * @return EntryId The new entry ID.
	 * @throws \RuntimeException If the original entry is not found or the author cannot be determined.
	 */
	private function execute_update( array $args, int $post_id ): EntryId {
		$this->handle_update_metadata( $args );

		$args = apply_filters( 'liveblog_before_update_entry', $args );

		$original_post = get_post( (int) $args['entry_id'] );
		if ( ! $original_post ) {
			throw new \RuntimeException( 'Entry not found for update.' );
		}

		$author_user = get_userdata( (int) $original_post->post_author );
		$user        = $author_user instanceof WP_User
			? $author_user
			: wp_get_current_user();

		if ( ! $user instanceof WP_User ) {
			throw new \RuntimeException( 'Unable to determine author for update.' );
		}

		$entry_id = $this->entry_service->update(
			$post_id,
			EntryId::from_int( (int) $args['entry_id'] ),
			$args['content'] ?? '',
			$user
		);

		do_action( 'liveblog_update_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Execute delete action.
	 *
	 * @param array   $args    Arguments.
	 * @param WP_User $user    User performing action.
	 * @param int     $post_id Post ID.
	 * @return EntryId The delete marker entry ID.
	 */
	private function execute_delete( array $args, WP_User $user, int $post_id ): EntryId {
		$entry_id = $this->entry_service->delete(
			$post_id,
			EntryId::from_int( (int) $args['entry_id'] ),
			$user
		);

		do_action( 'liveblog_delete_entry', $entry_id->to_int(), $post_id );

		return $entry_id;
	}

	/**
	 * Handle metadata updates for entry updates (author, contributors).
	 *
	 * @param array $args The update arguments.
	 * @return void
	 */
	private function handle_update_metadata( array $args ): void {
		$entry_id = (int) $args['entry_id'];

		if ( isset( $args['author_id'] ) && $args['author_id'] ) {
			$author_user = get_userdata( $args['author_id'] );
			if ( $author_user instanceof WP_User ) {
				$user = apply_filters( 'liveblog_userdata', $author_user, $args['author_id'] );
			}
			if ( isset( $user ) && $user instanceof WP_User ) {
				$post_data = array(
					'ID'          => $entry_id,
					'post_author' => $user->ID,
				);
				wp_update_post( $post_data, true );
				$this->repository->set_authors_hidden( EntryId::from_int( $entry_id ), false );
			}
		} else {
			$this->repository->set_authors_hidden( EntryId::from_int( $entry_id ), true );
		}

		if ( isset( $args['contributor_ids'] ) ) {
			$this->repository->set_contributors(
				EntryId::from_int( $entry_id ),
				! empty( $args['contributor_ids'] ) ? $args['contributor_ids'] : array()
			);
		}
	}

	/**
	 * Format entry content for preview.
	 *
	 * @param string $content The entry content to preview.
	 * @return array{html: string} The preview result.
	 */
	public function format_preview( string $content ): array {
		$content = stripslashes( wp_filter_post_kses( $content ) );
		$content = apply_filters( 'liveblog_before_preview_entry', array( 'content' => $content ) );
		$content = $content['content'];

		$html = $this->content_processor->render( $content );

		do_action( 'liveblog_preview_entry', $html );

		return array( 'html' => $html );
	}
}
