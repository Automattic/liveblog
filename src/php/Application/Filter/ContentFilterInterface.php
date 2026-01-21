<?php
/**
 * Interface for content filters.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

/**
 * Interface for content filters that transform entry content.
 *
 * Content filters handle pattern matching and replacement in liveblog entries.
 * They support both filtering (on insert/update) and reverting (for editing).
 * Each filter can optionally provide autocomplete configuration for the frontend.
 */
interface ContentFilterInterface {

	/**
	 * Get the unique name identifier for this filter.
	 *
	 * @return string The filter name (e.g., 'commands', 'emojis', 'hashtags', 'authors').
	 */
	public function get_name(): string;

	/**
	 * Get the character prefixes that trigger this filter.
	 *
	 * @return array<string> Array of prefix characters (e.g., ['/', '\x{002f}'] for commands).
	 */
	public function get_prefixes(): array;

	/**
	 * Set the character prefixes for this filter.
	 *
	 * @param array<string> $prefixes The prefixes to set.
	 */
	public function set_prefixes( array $prefixes ): void;

	/**
	 * Get the regex pattern for matching content.
	 *
	 * @return string|null The regex pattern or null if not set.
	 */
	public function get_regex(): ?string;

	/**
	 * Set the regex pattern for matching content.
	 *
	 * @param string $regex The regex pattern.
	 */
	public function set_regex( string $regex ): void;

	/**
	 * Filter entry content on insert or update.
	 *
	 * Transforms matched patterns in the content (e.g., :smile: becomes <img>).
	 *
	 * @param array<string, mixed> $entry The entry data with 'content' key.
	 * @return array<string, mixed> The filtered entry data.
	 */
	public function filter( array $entry ): array;

	/**
	 * Revert filtered content back to original syntax for editing.
	 *
	 * Transforms rendered HTML back to original input format (e.g., <img> back to :smile:).
	 *
	 * @param string $content The rendered content.
	 * @return string The reverted content.
	 */
	public function revert( string $content ): string;

	/**
	 * Get the autocomplete configuration for the frontend.
	 *
	 * Returns configuration used by the JavaScript autocomplete system.
	 * Return null if this filter doesn't support autocomplete.
	 *
	 * @return array<string, mixed>|null The autocomplete config or null.
	 */
	public function get_autocomplete_config(): ?array;

	/**
	 * Perform any initialisation required by the filter.
	 *
	 * Called when the filter is loaded. Use this to register hooks,
	 * set up revert regex, etc.
	 */
	public function load(): void;
}
