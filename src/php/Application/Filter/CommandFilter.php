<?php
/**
 * Command filter for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

use WPCOM_Liveblog;

/**
 * Filters entry content for command patterns (/command).
 *
 * Commands are special patterns that trigger actions when entries are saved.
 * They appear as /key, /breaking, etc. and can trigger custom behavior.
 */
final class CommandFilter implements ContentFilterInterface {

	/**
	 * Default class prefix for commands.
	 *
	 * @var string
	 */
	public const DEFAULT_CLASS_PREFIX = 'type-';

	/**
	 * Character prefixes that trigger this filter.
	 *
	 * @var array<string>
	 */
	private array $prefixes = array( '/', '\x{002f}' );

	/**
	 * Regex pattern for matching commands.
	 *
	 * @var string|null
	 */
	private ?string $regex = null;

	/**
	 * Regex pattern for reverting commands.
	 *
	 * @var string|null
	 */
	private ?string $revert_regex = null;

	/**
	 * Class prefix for command CSS classes.
	 *
	 * @var string
	 */
	private string $class_prefix;

	/**
	 * Available commands.
	 *
	 * @var array<string>
	 */
	private array $commands = array();

	/**
	 * Filters to apply during content processing.
	 *
	 * @var array<string>
	 */
	private array $filters = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->class_prefix = self::DEFAULT_CLASS_PREFIX;
	}

	/**
	 * Get the filter name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'commands';
	}

	/**
	 * Get the character prefixes.
	 *
	 * @return array<string>
	 */
	public function get_prefixes(): array {
		return $this->prefixes;
	}

	/**
	 * Set the character prefixes.
	 *
	 * @param array<string> $prefixes The prefixes to set.
	 */
	public function set_prefixes( array $prefixes ): void {
		$this->prefixes = $prefixes;
	}

	/**
	 * Get the regex pattern.
	 *
	 * @return string|null
	 */
	public function get_regex(): ?string {
		return $this->regex;
	}

	/**
	 * Set the regex pattern.
	 *
	 * @param string $regex The regex pattern.
	 */
	public function set_regex( string $regex ): void {
		$this->regex = $regex;
	}

	/**
	 * Initialise the filter.
	 */
	public function load(): void {
		/**
		 * Filter the command class prefix.
		 *
		 * @param string $class_prefix The class prefix.
		 */
		$this->class_prefix = apply_filters( 'liveblog_command_class', self::DEFAULT_CLASS_PREFIX );

		// Load custom commands after theme setup.
		add_action( 'after_setup_theme', array( $this, 'load_custom_commands' ), 10 );

		// Build the revert regex.
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<span class="liveblog-command ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '">', '~' ),
				'([^"]+)',
				preg_quote( '</span>', '~' ),
			)
		);

		/**
		 * Filter the command revert regex.
		 *
		 * @param string $revert_regex The revert regex.
		 */
		$this->revert_regex = apply_filters( 'liveblog_command_revert_regex', $this->revert_regex );

		// Add CSS classes to entries.
		add_filter( 'comment_class', array( $this, 'add_type_class_to_entry' ), 10, 3 );

		// Execute command actions after entry is saved.
		add_action( 'liveblog_insert_entry', array( $this, 'do_action_per_type' ), 10, 2 );
	}

	/**
	 * Load custom commands via filter.
	 *
	 * @return void
	 */
	public function load_custom_commands(): void {
		/**
		 * Filter the active commands.
		 *
		 * @param array $commands The available commands.
		 */
		$this->commands = apply_filters( 'liveblog_active_commands', $this->commands );
	}

	/**
	 * Filter entry content.
	 *
	 * @param array<string, mixed> $entry The entry data.
	 * @return array<string, mixed>
	 */
	public function filter( array $entry ): array {
		if ( ! isset( $entry['content'] ) || ! is_string( $entry['content'] ) ) {
			return $entry;
		}

		if ( null === $this->regex ) {
			return $entry;
		}

		// Reset filters for this pass.
		$this->filters = array();

		// Replace command patterns.
		$entry['content'] = preg_replace_callback(
			$this->regex,
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		) ?? $entry['content'];

		// Apply collected filters.
		foreach ( $this->filters as $filter_name ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook name for liveblog_command_{type}_before filters.
			$entry['content'] = apply_filters( $filter_name, $entry['content'] );
		}

		return $entry;
	}

	/**
	 * Callback for preg_replace_callback.
	 *
	 * @param array<int, string> $regex_match The regex match array.
	 * @return string
	 */
	public function preg_replace_callback( array $regex_match ): string {
		/**
		 * Filter the command type.
		 *
		 * @param string $type The command type.
		 */
		$type = apply_filters( 'liveblog_command_type', $regex_match[2] );

		// If it's not a registered command, skip it.
		if ( ! in_array( $type, $this->commands, true ) ) {
			return $regex_match[0];
		}

		// Add the filter to be applied later.
		$this->filters[] = "liveblog_command_{$type}_before";

		// Replace with a hidden span showing the command was matched.
		return str_replace(
			$regex_match[1],
			'<span class="liveblog-command ' . $this->class_prefix . $type . '">' . $type . '</span>',
			$regex_match[0]
		);
	}

	/**
	 * Revert filtered content.
	 *
	 * @param string $content The rendered content.
	 * @return string
	 */
	public function revert( string $content ): string {
		if ( null === $this->revert_regex ) {
			return $content;
		}

		return preg_replace( '~' . $this->revert_regex . '~', '/$1', $content ) ?? $content;
	}

	/**
	 * Get autocomplete configuration.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_autocomplete_config(): ?array {
		/**
		 * Filter the command autocomplete config.
		 *
		 * @param array $config The autocomplete config.
		 */
		return apply_filters(
			'liveblog_command_config',
			array(
				'trigger'     => '/',
				'data'        => $this->commands,
				'displayKey'  => false,
				'regex'       => '/(\w*)$',
				'replacement' => '/${term}',
				'replaceText' => '/$',
				'name'        => 'Command',
				'template'    => false,
			)
		);
	}

	/**
	 * Get available commands.
	 *
	 * @return array<string>
	 */
	public function get_commands(): array {
		return $this->commands;
	}

	/**
	 * Add type-{command} class to entry.
	 *
	 * @param array<string>     $classes    The existing classes.
	 * @param string|array<int> $css_class  The class name(s).
	 * @param int               $comment_id The comment ID.
	 * @return array<string>
	 */
	public function add_type_class_to_entry( array $classes, $css_class, int $comment_id ): array {
		$types   = array();
		$comment = get_comment( $comment_id );

		if ( ! $comment || WPCOM_Liveblog::KEY !== $comment->comment_type ) {
			return $classes;
		}

		// Find all command type classes in the content.
		preg_match_all(
			'/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/',
			$comment->comment_content,
			$types
		);

		return array_merge( $classes, $types[0] );
	}

	/**
	 * Run actions after entry is saved.
	 *
	 * @param int $id      The entry ID.
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function do_action_per_type( int $id, int $post_id ): void {
		$types   = array();
		$content = get_comment_text( $id );

		// Find all command types.
		preg_match_all(
			'/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/',
			$content,
			$types
		);

		foreach ( $types[0] as $type ) {
			$type = ltrim( $type, $this->class_prefix );

			/**
			 * Action fired after a command entry is saved.
			 *
			 * @param string $content The entry content.
			 * @param int    $id      The entry ID.
			 * @param int    $post_id The post ID.
			 */
			do_action( "liveblog_command_{$type}_after", $content, $id, $post_id );
		}
	}
}
