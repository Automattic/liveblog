<?php
/**
 * Entry content value object.
 *
 * @package Automattic\Liveblog\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\ValueObject;

/**
 * Represents the content of a liveblog entry.
 *
 * Handles raw content storage and provides methods for rendering and
 * extracting plain text. Immutable once created.
 */
final class EntryContent {

	/**
	 * The raw content.
	 *
	 * @var string
	 */
	private string $raw;

	/**
	 * Cached rendered content.
	 *
	 * @var string|null
	 */
	private ?string $rendered = null;

	/**
	 * Constructor.
	 *
	 * @param string $raw Raw content.
	 */
	private function __construct( string $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Create an EntryContent from raw content.
	 *
	 * @param string $content Raw content.
	 * @return self
	 */
	public static function from_raw( string $content ): self {
		return new self( $content );
	}

	/**
	 * Create an empty EntryContent.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( '' );
	}

	/**
	 * Get the raw content.
	 *
	 * @return string
	 */
	public function raw(): string {
		return $this->raw;
	}

	/**
	 * Get the rendered content.
	 *
	 * If a renderer callable is provided, it will be used to render the content.
	 * The result is cached for subsequent calls without a renderer.
	 *
	 * @param callable|null $renderer Optional renderer function that takes raw content and returns rendered HTML.
	 * @return string
	 */
	public function rendered( ?callable $renderer = null ): string {
		if ( null !== $renderer ) {
			$this->rendered = $renderer( $this->raw );
		}

		if ( null === $this->rendered ) {
			// Default: return raw content if no renderer provided.
			return $this->raw;
		}

		return $this->rendered;
	}

	/**
	 * Get plain text content suitable for schema.org articleBody.
	 *
	 * Strips HTML tags (replacing with spaces to preserve word boundaries),
	 * decodes HTML entities, and normalises whitespace.
	 *
	 * @return string
	 */
	public function plain(): string {
		$content = $this->raw;

		// Strip /key command (plain text version).
		$content = preg_replace( '/(^|[>\s])\/key\s*/i', '$1', $content );

		// Strip /key command (span version from editor).
		$content = preg_replace(
			'/<span[^>]*class="[^"]*type-key[^"]*"[^>]*>[^<]*<\/span>\s*/i',
			'',
			$content
		);

		// Replace HTML tags with spaces to preserve word boundaries.
		$content = preg_replace( '/<[^>]+>/', ' ', $content );

		// Decode HTML entities.
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		// Normalise whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Check if the content is empty.
	 *
	 * Considers content empty if it has no meaningful text after stripping HTML.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return '' === $this->plain();
	}

	/**
	 * Strip a command from the content.
	 *
	 * Returns a new EntryContent with the command removed.
	 *
	 * @param string $command Command to strip (e.g., 'key').
	 * @return self
	 */
	public function strip_command( string $command ): self {
		$content = $this->raw;

		// Strip plain text command (e.g., /key).
		$content = preg_replace(
			'/(^|[>\s])\/' . preg_quote( $command, '/' ) . '\s*/i',
			'$1',
			$content
		);

		// Strip span-wrapped command from editor.
		$content = preg_replace(
			'/<span[^>]*class="[^"]*type-' . preg_quote( $command, '/' ) . '[^"]*"[^>]*>[^<]*<\/span>\s*/i',
			'',
			$content
		);

		return new self( $content );
	}

	/**
	 * Get a truncated version for use as a headline.
	 *
	 * @param int    $word_count Maximum number of words.
	 * @param string $ellipsis   String to append if truncated.
	 * @return string
	 */
	public function truncate( int $word_count = 10, string $ellipsis = "…" ): string {
		$plain = $this->plain();
		$words = preg_split( '/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY );

		if ( count( $words ) <= $word_count ) {
			return $plain;
		}

		return implode( ' ', array_slice( $words, 0, $word_count ) ) . $ellipsis;
	}

	/**
	 * Check equality with another EntryContent.
	 *
	 * @param self $other The other EntryContent to compare.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->raw === $other->raw;
	}

	/**
	 * Get the content as a string (returns raw content).
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->raw;
	}
}
