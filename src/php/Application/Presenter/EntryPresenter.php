<?php
/**
 * Entry presenter for transforming Entry entities into presentation formats.
 *
 * @package Automattic\Liveblog\Application\Presenter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Presenter;

use Automattic\Liveblog\Application\Config\AllowedTagsConfiguration;
use Automattic\Liveblog\Application\Renderer\ContentRendererInterface;
use Automattic\Liveblog\Application\Service\KeyEventService;
use Automattic\Liveblog\Domain\Entity\Entry;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Infrastructure\DI\Container;
use WP_Comment;

/**
 * Transforms Entry entities into various presentation formats.
 *
 * This class separates presentation concerns from the domain model,
 * handling WordPress-specific formatting like CSS classes, share links,
 * rendered content, and timestamp formatting.
 *
 * The presenter supports two main output formats:
 * - for_json(): For JavaScript API responses (polling updates)
 * - for_render(): For PHP template rendering
 */
final class EntryPresenter {

	/**
	 * Default avatar size in pixels.
	 *
	 * @var int
	 */
	private const DEFAULT_AVATAR_SIZE = 30;

	/**
	 * The entry being presented.
	 *
	 * @var Entry
	 */
	private Entry $entry;

	/**
	 * The underlying comment object for WordPress integration.
	 *
	 * @var WP_Comment|null
	 */
	private ?WP_Comment $comment;

	/**
	 * Content renderer for transforming raw content to HTML.
	 *
	 * @var ContentRendererInterface
	 */
	private ContentRendererInterface $renderer;

	/**
	 * Key event service for checking key event status.
	 *
	 * @var KeyEventService
	 */
	private KeyEventService $key_event_service;

	/**
	 * Constructor.
	 *
	 * @param Entry                         $entry             The entry to present.
	 * @param KeyEventService               $key_event_service Service for key event operations.
	 * @param WP_Comment|null               $comment           Optional comment for additional WordPress data.
	 * @param ContentRendererInterface|null $renderer          Optional content renderer (defaults to container renderer).
	 */
	public function __construct(
		Entry $entry,
		KeyEventService $key_event_service,
		?WP_Comment $comment = null,
		?ContentRendererInterface $renderer = null
	) {
		$this->entry             = $entry;
		$this->key_event_service = $key_event_service;
		$this->comment           = $comment;
		$this->renderer          = $renderer ?? Container::instance()->content_renderer();
	}

	/**
	 * Create a presenter from an Entry.
	 *
	 * Fetches the comment automatically and uses the default WordPress renderer.
	 *
	 * @param Entry           $entry             The entry to present.
	 * @param KeyEventService $key_event_service Service for key event operations.
	 * @return self
	 */
	public static function from_entry( Entry $entry, KeyEventService $key_event_service ): self {
		$comment = get_comment( $entry->id()->to_int() );

		return new self(
			$entry,
			$key_event_service,
			$comment instanceof WP_Comment ? $comment : null
		);
	}

	/**
	 * Transform entry for JSON API responses.
	 *
	 * Used by JavaScript polling to update the liveblog display.
	 * Matches the format expected by liveblog.js.
	 *
	 * @return object
	 */
	public function for_json(): object {
		$entry_id    = $this->get_display_id();
		$css_classes = $this->get_css_classes( $entry_id );
		$share_link  = $this->get_share_link( $entry_id );

		$entry = array(
			'id'          => $entry_id,
			'type'        => $this->entry->type()->value,
			'render'      => $this->get_rendered_content(),
			'content'     => $this->get_editable_content(),
			'css_classes' => $css_classes,
			'timestamp'   => $this->entry->timestamp(),
			'authors'     => $this->get_authors_array(),
			'entry_time'  => $this->get_entry_time( 'U' ),
			'share_link'  => $share_link,
		);

		/**
		 * Filter the entry data for JSON output.
		 *
		 * @param array $entry The entry data.
		 * @param Entry $domain_entry The domain Entry entity.
		 */
		$entry = apply_filters( 'liveblog_entry_for_json', $entry, $this->entry );

		return (object) $entry;
	}

	/**
	 * Transform entry for PHP template rendering.
	 *
	 * Provides all data needed by the liveblog-single-entry.php template.
	 *
	 * @return array
	 */
	public function for_render(): array {
		$entry_id    = $this->get_display_id();
		$avatar_size = $this->get_avatar_size();
		$time_format = $this->get_time_format();

		return array(
			'entry_id'               => $entry_id,
			'post_id'                => $this->entry->post_id(),
			'css_classes'            => $this->get_css_classes( $entry_id ),
			'content'                => $this->get_rendered_content(),
			'original_content'       => $this->get_editable_content(),
			'avatar_size'            => $avatar_size,
			'avatar_img'             => $this->get_avatar_img( $avatar_size ),
			'author_link'            => $this->get_author_link( $entry_id ),
			'authors'                => $this->get_authors_array(),
			'entry_date'             => $this->get_entry_date(),
			'entry_time'             => $this->get_entry_time( $time_format ),
			'entry_timestamp'        => $this->get_entry_time( 'c' ),
			'timestamp'              => $this->entry->timestamp(),
			'share_link'             => $this->get_share_link( $entry_id, 'liveblog-entry-' ),
			'key_event'              => $this->is_key_event( $entry_id ),
			'is_liveblog_editable'   => self::is_liveblog_editable( $this->entry->post_id() ),
			'allowed_tags_for_entry' => AllowedTagsConfiguration::get(),
		);
	}

	/**
	 * Render the entry to HTML using the template.
	 *
	 * @return string
	 */
	public function render(): string {
		return Container::instance()->template_renderer()->render( 'liveblog-single-entry.php', $this->for_render() );
	}

	/**
	 * Get the display ID for the entry.
	 *
	 * For updates/deletes, this is the original entry being replaced.
	 *
	 * @return int
	 */
	private function get_display_id(): int {
		return $this->entry->display_id()->to_int();
	}

	/**
	 * Get CSS classes for the entry.
	 *
	 * @param int $entry_id The entry ID.
	 * @return string
	 */
	private function get_css_classes( int $entry_id ): string {
		return implode( ' ', get_comment_class( '', $entry_id, $this->entry->post_id() ) );
	}

	/**
	 * Get the share link for the entry.
	 *
	 * @param int    $entry_id The entry ID.
	 * @param string $prefix   Optional prefix for the anchor.
	 * @return string
	 */
	private function get_share_link( int $entry_id, string $prefix = '' ): string {
		return get_permalink( $this->entry->post_id() ) . '#' . $prefix . $entry_id;
	}

	/**
	 * Get rendered content HTML.
	 *
	 * @return string
	 */
	private function get_rendered_content(): string {
		return $this->renderer->render(
			$this->entry->content()->raw(),
			$this->comment
		);
	}

	/**
	 * Get content prepared for editing.
	 *
	 * @return string
	 */
	private function get_editable_content(): string {
		/**
		 * Filter content before editing.
		 *
		 * @param string $content The raw content.
		 */
		return apply_filters( 'liveblog_before_edit_entry', $this->entry->content()->raw() );
	}

	/**
	 * Get authors as array for JSON/template use.
	 *
	 * @return array
	 */
	private function get_authors_array(): array {
		$avatar_size = $this->get_avatar_size();

		return $this->entry->authors()->to_array( $avatar_size );
	}

	/**
	 * Get avatar size from filter.
	 *
	 * @return int
	 */
	private function get_avatar_size(): int {
		return (int) apply_filters( 'liveblog_entry_avatar_size', self::DEFAULT_AVATAR_SIZE );
	}

	/**
	 * Get time format from filter.
	 *
	 * @return string
	 */
	private function get_time_format(): string {
		return (string) apply_filters( 'liveblog_timestamp_format', get_option( 'time_format' ) );
	}

	/**
	 * Get avatar image HTML.
	 *
	 * @param int $size Avatar size.
	 * @return string
	 */
	private function get_avatar_img( int $size ): string {
		$primary = $this->entry->authors()->primary();
		if ( $primary ) {
			return $primary->avatar_html( $size );
		}

		if ( $this->comment ) {
			return get_avatar( $this->comment->comment_author_email, $size );
		}

		return '';
	}

	/**
	 * Get author link HTML.
	 *
	 * @param int $entry_id The entry ID.
	 * @return string
	 */
	private function get_author_link( int $entry_id ): string {
		return get_comment_author_link( $entry_id );
	}

	/**
	 * Get formatted entry date.
	 *
	 * @return string
	 */
	private function get_entry_date(): string {
		return get_comment_date( get_option( 'date_format' ), $this->get_display_id() );
	}

	/**
	 * Get formatted entry time.
	 *
	 * @param string $format PHP date format.
	 * @return string
	 */
	private function get_entry_time( string $format ): string {
		return (string) get_comment_date( $format, $this->get_display_id() );
	}

	/**
	 * Check if this entry is a key event.
	 *
	 * @param int $entry_id The entry ID.
	 * @return bool
	 */
	private function is_key_event( int $entry_id ): bool {
		return $this->key_event_service->is_key_event( $entry_id );
	}

	/**
	 * Get a truncated title from entry content.
	 *
	 * Static helper for generating titles from entry content.
	 * Works with domain Entry objects or any object with a 'content' property.
	 *
	 * @param Entry|object $entry The entry object.
	 * @return string The truncated title (max 10 words).
	 */
	public static function get_entry_title( $entry ): string {
		$content = $entry instanceof Entry
			? $entry->content()->raw()
			: ( $entry->content ?? '' );

		return wp_trim_words( $content, 10, '…' );
	}

	/**
	 * Check if the liveblog is editable by the current user.
	 *
	 * Static helper that can be used without instantiating the presenter.
	 * Checks if the post has an enabled liveblog and the user can edit it.
	 *
	 * @param int|null $post_id Optional post ID. Defaults to current post.
	 * @return bool True if the current user can edit the liveblog.
	 */
	public static function is_liveblog_editable( ?int $post_id = null ): bool {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return false;
		}

		$liveblog_post = LiveblogPost::from_id( $post_id );

		if ( null === $liveblog_post ) {
			return false;
		}

		return $liveblog_post->is_editable();
	}
}
