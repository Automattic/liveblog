<?php
/**
 * Service for sanitizing entry input.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

/**
 * Sanitizes entry content before processing.
 *
 * This service handles input sanitisation for liveblog entries,
 * including stripping autocomplete markup and fixing div-wrapped links.
 */
final class InputSanitizer {

	/**
	 * Sanitize entry content.
	 *
	 * Applies all sanitisation operations to the entry content.
	 *
	 * @param array<string, mixed> $entry The entry data with 'content' key.
	 * @return array<string, mixed> The sanitized entry data.
	 */
	public function sanitize( array $entry ): array {
		if ( ! isset( $entry['content'] ) || ! is_string( $entry['content'] ) ) {
			return $entry;
		}

		$entry['content'] = $this->normalize_spaces( $entry['content'] );
		$entry['content'] = $this->strip_atwho_spans( $entry['content'] );

		return $entry;
	}

	/**
	 * Fix div-wrapped links in entry content.
	 *
	 * Replaces div-wrapped links with paragraph-wrapped links so WordPress
	 * oEmbed can pick them up. The div wrapping comes from Webkit browsers'
	 * contenteditable behavior.
	 *
	 * @param array<string, mixed> $entry The entry data with 'content' key.
	 * @return array<string, mixed> The entry with fixed links.
	 */
	public function fix_links_wrapped_in_div( array $entry ): array {
		if ( ! isset( $entry['content'] ) || ! is_string( $entry['content'] ) ) {
			return $entry;
		}

		$entry['content'] = $this->replace_div_wrapped_links( $entry['content'] );

		return $entry;
	}

	/**
	 * Normalize non-breaking spaces to regular spaces.
	 *
	 * Replaces &nbsp; entities with regular spaces to allow
	 * pattern matching to work as expected.
	 *
	 * @param string $content The content to normalize.
	 * @return string The normalized content.
	 */
	public function normalize_spaces( string $content ): string {
		return str_replace( '&nbsp;', ' ', $content );
	}

	/**
	 * Strip atwho autocomplete spans from content.
	 *
	 * Removes span elements with atwho-* classes that may have been
	 * generated from frontend autocompletion.
	 *
	 * @param string $content The content to strip.
	 * @return string The content with atwho spans removed.
	 */
	public function strip_atwho_spans( string $content ): string {
		return preg_replace(
			'~\\<span\\s+class\\=\\\\?"atwho\\-\\w+\\\\?"\\s*>([^<]*)\\</span\\>~',
			'$1',
			$content
		) ?? $content;
	}

	/**
	 * Replace div-wrapped links with paragraph-wrapped links.
	 *
	 * @param string $content The content to process.
	 * @return string The content with div-wrapped links converted to paragraphs.
	 */
	public function replace_div_wrapped_links( string $content ): string {
		return preg_replace(
			'|(<div(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/div>)|i',
			'<p>${2}</p>',
			$content
		) ?? $content;
	}
}
