<?php
/**
 * Metadata presenter for JSON-LD schema.org output.
 *
 * @package Automattic\Liveblog\Application\Presenter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Presenter;

use Automattic\Liveblog\Application\Config\LazyloadConfiguration;
use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Application\Service\KeyEventService;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use WP_Post;

/**
 * Generates schema.org JSON-LD metadata for liveblogs.
 *
 * This presenter creates structured data that helps search engines
 * understand the liveblog content, including LiveBlogPosting schema
 * with liveBlogUpdate entries.
 */
final class MetadataPresenter {

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
	 * Constructor.
	 *
	 * @param EntryQueryService $entry_query_service Entry query service.
	 * @param KeyEventService   $key_event_service   Key event service.
	 */
	public function __construct(
		EntryQueryService $entry_query_service,
		KeyEventService $key_event_service
	) {
		$this->entry_query_service = $entry_query_service;
		$this->key_event_service   = $key_event_service;
	}

	/**
	 * Generate metadata for a liveblog post.
	 *
	 * @param WP_Post $post              The post object.
	 * @param array   $existing_metadata Existing metadata to merge with.
	 * @return array The complete metadata array.
	 */
	public function generate( WP_Post $post, array $existing_metadata = array() ): array {
		$liveblog_post = LiveblogPost::from_post( $post );

		if ( ! $liveblog_post->is_liveblog() ) {
			return $existing_metadata;
		}

		$entries        = $this->get_paginated_entries( $post->ID );
		$blog_updates   = $this->build_blog_updates( $entries, $existing_metadata );
		$liveblog_state = $liveblog_post->state();

		$metadata = $existing_metadata;

		$metadata['@context']      = 'https://schema.org';
		$metadata['@type']         = 'LiveBlogPosting';
		$metadata['headline']      = get_the_title( $post );
		$metadata['url']           = get_permalink( $post );
		$metadata['datePublished'] = get_post_datetime( $post, 'date', 'gmt' )->format( 'c' );
		$metadata['dateModified']  = get_post_datetime( $post, 'modified', 'gmt' )->format( 'c' );

		// Add coverage times for LiveBlogPosting (helps with Google's "LIVE" badge).
		$metadata['coverageStartTime'] = $metadata['datePublished'];

		// Add coverageEndTime only if the liveblog is archived.
		if ( LiveblogPost::STATE_ARCHIVED === $liveblog_state ) {
			$metadata['coverageEndTime'] = $metadata['dateModified'];
		}

		$metadata['liveBlogUpdate'] = $blog_updates;

		/**
		 * Filters the Liveblog metadata.
		 *
		 * @param array   $metadata An array of metadata.
		 * @param WP_Post $post     The post object.
		 */
		return apply_filters( 'liveblog_metadata', $metadata, $post );
	}

	/**
	 * Print the metadata as JSON-LD script tag.
	 *
	 * @param WP_Post $post The post object.
	 * @return void
	 */
	public function print_json_ld( WP_Post $post ): void {
		$liveblog_post = LiveblogPost::from_post( $post );

		if ( ! $liveblog_post->is_liveblog() ) {
			return;
		}

		$metadata = $this->generate( $post );

		if ( empty( $metadata ) ) {
			return;
		}

		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $metadata ); ?></script>
		<?php
	}

	/**
	 * Get paginated entries for metadata.
	 *
	 * @param int $post_id The post ID.
	 * @return array The entries for JSON output.
	 */
	private function get_paginated_entries( int $post_id ): array {
		$request_data = $this->get_request_data();
		$lazyload     = new LazyloadConfiguration();
		$per_page     = $lazyload->get_entries_per_page();

		$result = $this->entry_query_service->get_entries_paged(
			$post_id,
			(int) $request_data->page,
			$per_page,
			$request_data->last ? (string) $request_data->last : null,
			$request_data->id ? (int) $request_data->id : null
		);

		$entries_for_json = array();

		foreach ( $result['entries'] as $entry ) {
			$presenter          = EntryPresenter::from_entry( $entry, $this->key_event_service );
			$entries_for_json[] = $presenter->for_json();
		}

		return $entries_for_json;
	}

	/**
	 * Build the blog updates array for JSON-LD.
	 *
	 * @param array $entries           Entry objects from JSON transformation.
	 * @param array $existing_metadata Existing metadata (for publisher info).
	 * @return array Array of BlogPosting items.
	 */
	private function build_blog_updates( array $entries, array $existing_metadata ): array {
		$blog_updates = array();

		foreach ( $entries as $entry ) {
			$content = $entry->content ?? '';

			// Strip /key command (plain and span versions) from content.
			$content = preg_replace( '/<span[^>]*class="[^"]*type-key[^"]*"[^>]*>[^<]*<\/span>\s*/i', '', $content );
			$content = preg_replace( '/(^|[>\s])\/key\s*/i', '$1', $content );

			// Replace HTML tags with spaces to preserve word boundaries, then strip.
			$article_body = preg_replace( '/<[^>]+>/', ' ', $content );
			$article_body = html_entity_decode( $article_body, ENT_QUOTES, 'UTF-8' );
			$article_body = preg_replace( '/\s+/', ' ', $article_body );
			$article_body = trim( $article_body );

			// Skip entries with no meaningful content.
			if ( empty( $article_body ) ) {
				continue;
			}

			$headline = wp_trim_words( $article_body, 10, '…' );

			$blog_item = array(
				'@type'            => 'BlogPosting',
				'headline'         => $headline,
				'url'              => $entry->share_link ?? '',
				'mainEntityOfPage' => $entry->share_link ?? '',
				'datePublished'    => gmdate( 'c', (int) ( $entry->entry_time ?? 0 ) ),
				'dateModified'     => gmdate( 'c', (int) ( $entry->timestamp ?? 0 ) ),
				'articleBody'      => $article_body,
			);

			// Add authors if available.
			if ( ! empty( $entry->authors ) ) {
				$authors = $this->build_authors_array( $entry->authors );
				if ( count( $authors ) === 1 ) {
					$blog_item['author'] = $authors[0];
				} elseif ( count( $authors ) > 1 ) {
					$blog_item['author'] = $authors;
				}
			}

			// Inherit publisher if provided.
			if ( isset( $existing_metadata['publisher'] ) ) {
				$blog_item['publisher'] = $existing_metadata['publisher'];
			}

			$blog_updates[] = json_decode( wp_json_encode( $blog_item ) );
		}

		return $blog_updates;
	}

	/**
	 * Build authors array for JSON-LD.
	 *
	 * @param array $authors Raw authors array.
	 * @return array Formatted authors for schema.org.
	 */
	private function build_authors_array( array $authors ): array {
		$result = array();

		foreach ( $authors as $author ) {
			if ( empty( $author['name'] ) ) {
				continue;
			}

			$author_data = array(
				'@type' => 'Person',
				'name'  => $author['name'],
			);

			if ( ! empty( $author['link'] ) ) {
				$author_data['url'] = $author['link'];
			}

			$result[] = $author_data;
		}

		return $result;
	}

	/**
	 * Get request data for pagination.
	 *
	 * @return object Request data with page, last, and id properties.
	 */
	private function get_request_data(): object {
		return (object) array(
			'page' => get_query_var( 'liveblog_page', 1 ),
			'last' => get_query_var( 'liveblog_last', false ),
			'id'   => get_query_var( 'liveblog_id', false ),
		);
	}
}
