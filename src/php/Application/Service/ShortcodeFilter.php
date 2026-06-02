<?php
/**
 * Shortcode filter service for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Service
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Service;

/**
 * Filters restricted shortcodes from liveblog entry content.
 *
 * This service handles stripping out shortcodes that should not be allowed
 * in liveblog entries. By default, the `liveblog_key_events` shortcode is
 * restricted to prevent recursion issues.
 */
final class ShortcodeFilter {

	/**
	 * Default restricted shortcodes lookup.
	 *
	 * Keys are shortcode names, values are replacement strings.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_RESTRICTED_SHORTCODES = array(
		'liveblog_key_events' => '',
	);

	/**
	 * Filter restricted shortcodes from entry arguments.
	 *
	 * This method is designed to be used as a filter callback for
	 * 'liveblog_before_insert_entry' and 'liveblog_before_update_entry'.
	 *
	 * @param array{content?: string} $args The entry arguments.
	 * @return array{content?: string} The filtered entry arguments.
	 */
	public function filter( array $args ): array {
		if ( ! isset( $args['content'] ) ) {
			return $args;
		}

		/**
		 * Filter the restricted shortcodes array before being applied.
		 *
		 * @param array<string, string> $restricted_shortcodes Array of shortcode => replacement pairs.
		 */
		$restricted_shortcodes = apply_filters(
			'liveblog_entry_restrict_shortcodes',
			self::DEFAULT_RESTRICTED_SHORTCODES
		);

		// Strip every restricted shortcode, re-applying the whole set until the
		// content stabilises. A single pass is bypassable by nesting a restricted
		// shortcode inside fragments of a tag name, so that removing the inner
		// match reconstructs a working shortcode. That happens both within one tag
		// (`[liveblog_key[liveblog_key_events]_events]` -> `[liveblog_key_events]`)
		// and across tags when more than one is restricted (removing `[embed]`
		// from `[gall[embed]ery]` reconstructs `[gallery]`). Looping the whole set
		// rather than each tag in isolation removes the order dependence between
		// tags. The loop only continues while the content strictly shrinks, so a
		// replacement that is the same length or longer than the match (an unusual
		// configuration) cannot make it spin.
		if ( is_array( $restricted_shortcodes ) ) {
			do {
				$previous = $args['content'];

				foreach ( $restricted_shortcodes as $shortcode => $replacement ) {
					$pattern         = '/' . get_shortcode_regex( array( $shortcode ) ) . '/s';
					$args['content'] = preg_replace( $pattern, $replacement, $args['content'] );
				}

				$shrank = ( null !== $args['content'] && strlen( $args['content'] ) < strlen( $previous ) );
			} while ( $shrank );
		}

		return $args;
	}
}
