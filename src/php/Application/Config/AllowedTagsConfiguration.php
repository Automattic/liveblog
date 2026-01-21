<?php
/**
 * Configuration for allowed HTML tags in liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Config
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Config;

/**
 * Manages the allowed HTML tags configuration for liveblog entries.
 *
 * This class centralises the allowed tags configuration that was previously
 * stored as a static property in WPCOM_Liveblog_Entry. Tags are lazily
 * generated and cached for the request lifetime.
 */
final class AllowedTagsConfiguration {

	/**
	 * Cached allowed tags array.
	 *
	 * @var array<string, array<string, array<mixed>>>|null
	 */
	private static ?array $allowed_tags = null;

	/**
	 * Get the allowed HTML tags for entry content.
	 *
	 * @return array<string, array<string, array<mixed>>> The allowed tags array.
	 */
	public static function get(): array {
		if ( null === self::$allowed_tags ) {
			self::generate();
		}

		return self::$allowed_tags;
	}

	/**
	 * Generate the allowed HTML tags.
	 *
	 * Uses the WordPress 'post' context as a base, then adds additional
	 * tags needed for liveblog content (iframes for embeds, source for media).
	 *
	 * @return void
	 */
	private static function generate(): void {
		// Use HTML tags allowed for post as a base.
		self::$allowed_tags = wp_kses_allowed_html( 'post' );

		// Expand with additional tags that we want to allow.
		$additional_tags           = array();
		$additional_tags['iframe'] = array(
			'src'             => array(),
			'height'          => array(),
			'width'           => array(),
			'frameborder'     => array(),
			'allowfullscreen' => array(),
		);
		$additional_tags['source'] = array(
			'src'  => array(),
			'type' => array(),
		);

		self::$allowed_tags = array_merge(
			$additional_tags,
			self::$allowed_tags
		);
	}

	/**
	 * Reset the cached tags (primarily for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$allowed_tags = null;
	}
}
