<?php
/**
 * Entry entity representing a liveblog entry.
 *
 * @package Automattic\Liveblog\Domain\Entity
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\Entity;

use Automattic\Liveblog\Domain\ValueObject\AuthorCollection;
use Automattic\Liveblog\Domain\ValueObject\EntryContent;
use Automattic\Liveblog\Domain\ValueObject\EntryId;
use Automattic\Liveblog\Domain\ValueObject\EntryType;
use DateTimeImmutable;

/**
 * Immutable domain entity representing a liveblog entry.
 *
 * An Entry is the core domain object for liveblog content. It aggregates
 * the entry's identity, content, authorship, and metadata into a single
 * cohesive object.
 *
 * Entries are immutable - to modify an entry, create a new one that
 * references the original via the replaces property.
 */
final class Entry {

	/**
	 * Entry ID.
	 *
	 * @var EntryId
	 */
	private EntryId $id;

	/**
	 * Post ID this entry belongs to.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Entry content.
	 *
	 * @var EntryContent
	 */
	private EntryContent $content;

	/**
	 * Entry type (new, update, delete).
	 *
	 * @var EntryType
	 */
	private EntryType $type;

	/**
	 * Authors of this entry.
	 *
	 * @var AuthorCollection
	 */
	private AuthorCollection $authors;

	/**
	 * ID of the entry this one replaces, if any.
	 *
	 * @var EntryId|null
	 */
	private ?EntryId $replaces;

	/**
	 * When the entry was created.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $created_at;

	/**
	 * Private constructor - use factory methods.
	 *
	 * @param EntryId           $id         Entry ID.
	 * @param int               $post_id    Post ID.
	 * @param EntryContent      $content    Entry content.
	 * @param EntryType         $type       Entry type.
	 * @param AuthorCollection  $authors    Authors.
	 * @param EntryId|null      $replaces   ID of entry this replaces.
	 * @param DateTimeImmutable $created_at Creation timestamp.
	 */
	private function __construct(
		EntryId $id,
		int $post_id,
		EntryContent $content,
		EntryType $type,
		AuthorCollection $authors,
		?EntryId $replaces,
		DateTimeImmutable $created_at
	) {
		$this->id         = $id;
		$this->post_id    = $post_id;
		$this->content    = $content;
		$this->type       = $type;
		$this->authors    = $authors;
		$this->replaces   = $replaces;
		$this->created_at = $created_at;
	}

	/**
	 * Create an Entry from its constituent parts.
	 *
	 * @param EntryId           $id         Entry ID.
	 * @param int               $post_id    Post ID.
	 * @param EntryContent      $content    Entry content.
	 * @param AuthorCollection  $authors    Authors.
	 * @param EntryId|null      $replaces   ID of entry this replaces.
	 * @param DateTimeImmutable $created_at Creation timestamp.
	 * @return self
	 */
	public static function create(
		EntryId $id,
		int $post_id,
		EntryContent $content,
		AuthorCollection $authors,
		?EntryId $replaces,
		DateTimeImmutable $created_at
	): self {
		$type = EntryType::from_replaces_and_content(
			$replaces?->to_int(),
			$content->raw()
		);

		return new self( $id, $post_id, $content, $type, $authors, $replaces, $created_at );
	}

	/**
	 * Get the entry ID.
	 *
	 * @return EntryId
	 */
	public function id(): EntryId {
		return $this->id;
	}

	/**
	 * Get the post ID.
	 *
	 * @return int
	 */
	public function post_id(): int {
		return $this->post_id;
	}

	/**
	 * Get the entry content.
	 *
	 * @return EntryContent
	 */
	public function content(): EntryContent {
		return $this->content;
	}

	/**
	 * Get the entry type.
	 *
	 * @return EntryType
	 */
	public function type(): EntryType {
		return $this->type;
	}

	/**
	 * Get the authors.
	 *
	 * @return AuthorCollection
	 */
	public function authors(): AuthorCollection {
		return $this->authors;
	}

	/**
	 * Get the ID of the entry this one replaces.
	 *
	 * @return EntryId|null
	 */
	public function replaces(): ?EntryId {
		return $this->replaces;
	}

	/**
	 * Get the creation timestamp.
	 *
	 * @return DateTimeImmutable
	 */
	public function created_at(): DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Check if this entry is a new entry (not an update or delete).
	 *
	 * @return bool
	 */
	public function is_new(): bool {
		return $this->type->is_new();
	}

	/**
	 * Check if this entry is an update to another entry.
	 *
	 * @return bool
	 */
	public function is_update(): bool {
		return $this->type->is_update();
	}

	/**
	 * Check if this entry is a deletion marker.
	 *
	 * @return bool
	 */
	public function is_delete(): bool {
		return $this->type->is_delete();
	}

	/**
	 * Get the effective entry ID for display purposes.
	 *
	 * For updates and deletes, this returns the ID of the original entry
	 * being replaced. For new entries, returns this entry's ID.
	 *
	 * @return EntryId
	 */
	public function display_id(): EntryId {
		return $this->replaces ?? $this->id;
	}

	/**
	 * Get the Unix timestamp for the entry.
	 *
	 * @return int
	 */
	public function timestamp(): int {
		return $this->created_at->getTimestamp();
	}

	/**
	 * Check if this entry has visible authors.
	 *
	 * @return bool
	 */
	public function has_authors(): bool {
		return ! $this->authors->is_empty();
	}

	/**
	 * Create a new Entry with different authors.
	 *
	 * Since Entry is immutable, this returns a new instance.
	 *
	 * @param AuthorCollection $authors New authors.
	 * @return self
	 */
	public function with_authors( AuthorCollection $authors ): self {
		return new self(
			$this->id,
			$this->post_id,
			$this->content,
			$this->type,
			$authors,
			$this->replaces,
			$this->created_at
		);
	}

	/**
	 * Create a new Entry with different content.
	 *
	 * Since Entry is immutable, this returns a new instance.
	 * Note: This does NOT change the entry type - use for display purposes only.
	 *
	 * @param EntryContent $content New content.
	 * @return self
	 */
	public function with_content( EntryContent $content ): self {
		return new self(
			$this->id,
			$this->post_id,
			$content,
			$this->type,
			$this->authors,
			$this->replaces,
			$this->created_at
		);
	}
}
