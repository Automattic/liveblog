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

		// For each lookup key, check if it exists in the content.
		if ( is_array( $restricted_shortcodes ) ) {
			foreach ( $restricted_shortcodes as $shortcode => $replacement ) {
				// Regex pattern will match all shortcode formats.
				$pattern = get_shortcode_regex( array( $shortcode ) );

				// Replace matches with the configured replacement string.
				$args['content'] = preg_replace( '/' . $pattern . '/s', $replacement, $args['content'] );
			}
		}

		return $args;
	}
}
