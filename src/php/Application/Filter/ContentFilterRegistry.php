<?php
/**
 * Registry for content filters.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

/**
 * Registry that manages and applies content filters.
 *
 * The registry holds all registered content filters and provides methods
 * to apply them in sequence, revert content, and build autocomplete configuration.
 */
final class ContentFilterRegistry {

	/**
	 * Default autocomplete regex prefix.
	 *
	 * @var string
	 */
	private const REGEX_PREFIX = '~(?:(?<!\S)|>?)((?:';

	/**
	 * Default autocomplete regex postfix.
	 *
	 * @var string
	 */
	private const REGEX_POSTFIX = '){1}([0-9_\-\p{L}]*[_\-\p{L}][0-9_\-\p{L}]*))(?:<)?~um';

	/**
	 * Registered content filters.
	 *
	 * @var array<string, ContentFilterInterface>
	 */
	private array $filters = array();

	/**
	 * Enabled filter names.
	 *
	 * @var array<string>
	 */
	private array $enabled_features = array();

	/**
	 * Whether the registry has been initialised.
	 *
	 * @var bool
	 */
	private bool $initialised = false;

	/**
	 * Register a content filter.
	 *
	 * @param ContentFilterInterface $filter The filter to register.
	 */
	public function register( ContentFilterInterface $filter ): void {
		$this->filters[ $filter->get_name() ] = $filter;
	}

	/**
	 * Get a registered filter by name.
	 *
	 * @param string $name The filter name.
	 * @return ContentFilterInterface|null The filter or null if not found.
	 */
	public function get( string $name ): ?ContentFilterInterface {
		return $this->filters[ $name ] ?? null;
	}

	/**
	 * Get all registered filters.
	 *
	 * @return array<string, ContentFilterInterface> All registered filters.
	 */
	public function get_all(): array {
		return $this->filters;
	}

	/**
	 * Initialise the registry and all filters.
	 *
	 * This sets up the enabled features list, configures regex patterns,
	 * and calls load() on each enabled filter.
	 *
	 * @param string $features_string Comma/space/pipe separated list of features.
	 */
	public function initialise( string $features_string = 'commands, emojis, hashtags, authors' ): void {
		if ( $this->initialised ) {
			return;
		}

		// Parse the features string.
		$features = explode( ',', preg_replace( '~[ |]+~', ',', $features_string ) );
		$features = array_filter( $features, 'strlen' );

		/**
		 * Filter the enabled features list.
		 *
		 * @param array $features The list of enabled feature names.
		 */
		$this->enabled_features = apply_filters( 'liveblog_features', $features );

		// Initialise each enabled filter.
		foreach ( $this->enabled_features as $name ) {
			$filter = $this->get( $name );
			if ( ! $filter ) {
				continue;
			}

			// Allow customisation of prefixes.
			$prefixes = apply_filters( "liveblog_{$name}_prefixes", $filter->get_prefixes() );
			$filter->set_prefixes( $prefixes );

			// Build and set the regex.
			$regex = self::REGEX_PREFIX . implode( '|', $prefixes ) . self::REGEX_POSTFIX;
			$regex = apply_filters( "liveblog_{$name}_regex", $regex );
			$filter->set_regex( $regex );

			// Load the filter.
			$filter->load();
		}

		$this->initialised = true;
	}

	/**
	 * Get the list of enabled feature names.
	 *
	 * @return array<string> The enabled feature names.
	 */
	public function get_enabled_features(): array {
		return array_values( $this->enabled_features );
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @param string $name The feature name.
	 * @return bool True if enabled.
	 */
	public function is_enabled( string $name ): bool {
		return in_array( $name, $this->enabled_features, true );
	}

	/**
	 * Apply all enabled filters to entry content.
	 *
	 * @param array<string, mixed> $entry The entry data with 'content' key.
	 * @return array<string, mixed> The filtered entry data.
	 */
	public function apply_filters( array $entry ): array {
		foreach ( $this->enabled_features as $name ) {
			$filter = $this->get( $name );
			if ( $filter ) {
				$entry = $filter->filter( $entry );
			}
		}

		return $entry;
	}

	/**
	 * Revert filtered content from all enabled filters.
	 *
	 * @param string $content The rendered content.
	 * @return string The reverted content.
	 */
	public function revert_all( string $content ): string {
		foreach ( $this->enabled_features as $name ) {
			$filter = $this->get( $name );
			if ( $filter ) {
				$content = $filter->revert( $content );
			}
		}

		return $content;
	}

	/**
	 * Get the autocomplete configuration for all enabled filters.
	 *
	 * @return array<int, array<string, mixed>> The autocomplete configurations.
	 */
	public function get_autocomplete_config(): array {
		$config = array();

		foreach ( $this->enabled_features as $name ) {
			$filter = $this->get( $name );
			if ( $filter ) {
				$filter_config = $filter->get_autocomplete_config();
				if ( null !== $filter_config ) {
					$config[] = $filter_config;
				}
			}
		}

		/**
		 * Filter the autocomplete configuration.
		 *
		 * @param array $config The autocomplete configuration array.
		 */
		return apply_filters( 'liveblog_extend_autocomplete', $config );
	}

	/**
	 * Reset the registry for testing.
	 *
	 * @internal For testing use only.
	 */
	public function reset(): void {
		$this->filters          = array();
		$this->enabled_features = array();
		$this->initialised      = false;
	}
}
