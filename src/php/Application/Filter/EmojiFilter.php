<?php
/**
 * Emoji filter for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;

/**
 * Filters entry content for emoji patterns (:emoji:).
 *
 * Emojis are replaced with image tags pointing to the emoji CDN.
 */
final class EmojiFilter implements ContentFilterInterface {

	/**
	 * Default class prefix for emojis.
	 *
	 * @var string
	 */
	public const DEFAULT_CLASS_PREFIX = 'emoji-';

	/**
	 * Default emoji CDN URL.
	 *
	 * @var string
	 */
	public const DEFAULT_CDN = '//s.w.org/images/core/emoji/72x72/';

	/**
	 * Character prefixes that trigger this filter.
	 *
	 * @var array<string>
	 */
	private array $prefixes = array( ':', '\x{003a}' );

	/**
	 * Regex pattern for matching emojis.
	 *
	 * @var string|null
	 */
	private ?string $regex = null;

	/**
	 * Regex pattern for reverting emojis.
	 *
	 * @var string|null
	 */
	private ?string $revert_regex = null;

	/**
	 * Class prefix for emoji CSS classes.
	 *
	 * @var string
	 */
	private string $class_prefix;

	/**
	 * Emoji CDN URL.
	 *
	 * @var string
	 */
	private string $emoji_cdn;

	/**
	 * Available emojis (name => unicode code).
	 *
	 * @var array<string, string>
	 */
	private array $emojis;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->class_prefix = self::DEFAULT_CLASS_PREFIX;
		$this->emoji_cdn    = self::DEFAULT_CDN;
		$this->emojis       = $this->get_default_emojis();
	}

	/**
	 * Get the filter name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'emojis';
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
	 * Modifies the standard regex to allow for emoji-specific character matching.
	 *
	 * @param string $regex The regex pattern.
	 */
	public function set_regex( string $regex ): void {
		// Modify the regex for emoji matching.
		$regex_prefix  = substr( $regex, 0, strlen( $regex ) - 10 );
		$regex_postfix = substr( $regex, strlen( $regex ) - 10 );
		$this->regex   = $regex_prefix . '(?:' . implode( '|', $this->get_prefixes() ) . ')' . $regex_postfix;
		$this->regex   = str_replace( '\p{L}', '\p{L}\\+\\-0-9', $this->regex );
	}

	/**
	 * Initialise the filter.
	 */
	public function load(): void {
		/**
		 * Filter the emoji class prefix.
		 *
		 * @param string $class_prefix The class prefix.
		 */
		$this->class_prefix = apply_filters( 'liveblog_emoji_class', self::DEFAULT_CLASS_PREFIX );

		/**
		 * Filter the active emojis.
		 *
		 * @param array $emojis The available emojis.
		 */
		$this->emojis = apply_filters( 'liveblog_active_emojis', $this->emojis );

		/**
		 * Filter the emoji CDN URL.
		 *
		 * @param string $cdn The CDN URL.
		 */
		$this->emoji_cdn = apply_filters( 'liveblog_cdn_emojis', $this->emoji_cdn );

		// Build the revert regex (matches both old format without size and new format with size).
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<img src="', '~' ),
				preg_quote( $this->emoji_cdn, '~' ),
				'[^"]+',               // Image filename.
				'[^>]*',               // Any other attributes (alt, width, height).
				preg_quote( 'data-emoji="', '~' ),
				'([^"]+)',             // Capture emoji name.
				preg_quote( '">', '~' ),
			)
		);

		/**
		 * Filter the emoji revert regex.
		 *
		 * @param string $revert_regex The revert regex.
		 */
		$this->revert_regex = apply_filters( 'liveblog_emoji_revert_regex', $this->revert_regex );

		// Add CSS classes to entries.
		add_filter( 'comment_class', array( $this, 'add_emoji_class_to_entry' ), 10, 3 );
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

		$entry['content'] = preg_replace_callback(
			$this->regex,
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		) ?? $entry['content'];

		return $entry;
	}

	/**
	 * Callback for preg_replace_callback.
	 *
	 * @param array<int, string> $regex_match The regex match array.
	 * @return string
	 */
	public function preg_replace_callback( array $regex_match ): string {
		// If the emoji doesn't exist, return unchanged.
		if ( ! isset( $this->emojis[ $regex_match[2] ] ) ) {
			return $regex_match[0];
		}

		$emoji = $regex_match[2];
		$image = $this->map_emoji( $this->emojis[ $emoji ], $emoji );
		$image = $image['image'];

		return str_replace(
			$regex_match[1],
			'<img src="' . $this->emoji_cdn . $image . '.png" alt="' . esc_attr( $emoji ) . '" width="20" height="20" class="liveblog-emoji ' . $this->class_prefix . $emoji . '" data-emoji="' . $emoji . '">',
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

		return preg_replace( '~' . $this->revert_regex . '~', ':$1:', $content ) ?? $content;
	}

	/**
	 * Get autocomplete configuration.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_autocomplete_config(): ?array {
		$emojis = array();

		foreach ( $this->emojis as $key => $val ) {
			// Keys like +1, -1, 100 may be integers, so cast to string.
			$emojis[] = $this->map_emoji( $val, (string) $key );
		}

		/**
		 * Filter the emoji autocomplete config.
		 *
		 * @param array $config The autocomplete config.
		 */
		return apply_filters(
			'liveblog_emoji_config',
			array(
				'type'        => 'static',
				'data'        => $emojis,
				'search'      => 'key',
				'replacement' => ':${key}:',
				'displayKey'  => 'key',
				'replaceText' => ':$:',
				'trigger'     => ':',
				'name'        => 'Emoji',
				'cdn'         => esc_url( $this->emoji_cdn ),
				'template'    => '<img src="' . esc_url( $this->emoji_cdn ) . '${image}.png" height="20" width="20" /> ${name}',
			)
		);
	}

	/**
	 * Map emoji data for autocomplete.
	 *
	 * @param string $val The emoji unicode value.
	 * @param string $key The emoji name.
	 * @return array<string, string>
	 */
	public function map_emoji( string $val, string $key ): array {
		/**
		 * Filter the emoji map data.
		 *
		 * @param array $emoji The emoji data.
		 */
		return apply_filters(
			'liveblog_emoji_map',
			array(
				'key'   => $key,
				'name'  => $key,
				'image' => strtolower( $val ),
			)
		);
	}

	/**
	 * Get available emojis.
	 *
	 * @return array<string, string>
	 */
	public function get_emojis(): array {
		return $this->emojis;
	}

	/**
	 * Add emoji-{name} class to entry.
	 *
	 * @param array<string>     $classes    The existing classes.
	 * @param string|array<int> $css_class  The class name(s).
	 * @param int               $comment_id The comment ID.
	 * @return array<string>
	 */
	public function add_emoji_class_to_entry( array $classes, $css_class, int $comment_id ): array {
		$emojis  = array();
		$comment = get_comment( $comment_id );

		if ( ! $comment || LiveblogConfiguration::KEY !== $comment->comment_type ) {
			return $classes;
		}

		preg_match_all(
			'/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/',
			$comment->comment_content,
			$emojis
		);

		return array_merge( $classes, $emojis[0] );
	}

	/**
	 * Get default emoji set.
	 *
	 * @return array<string, string>
	 */
	private function get_default_emojis(): array {
		// phpcs:disable Generic.Files.LineLength.TooLong -- Emoji data table.
		return array(
			'+1'           => '1F44D',
			'-1'           => '1F44E',
			'100'          => '1F4AF',
			'smile'        => '1F604',
			'smiley'       => '1F603',
			'grinning'     => '1F600',
			'blush'        => '1F60A',
			'wink'         => '1F609',
			'heart_eyes'   => '1F60D',
			'kissing'      => '1F617',
			'sunglasses'   => '1F60E',
			'neutral_face' => '1F610',
			'confused'     => '1F615',
			'worried'      => '1F61F',
			'angry'        => '1F620',
			'cry'          => '1F622',
			'sob'          => '1F62D',
			'joy'          => '1F602',
			'scream'       => '1F631',
			'poop'         => '1F4A9',
			'thumbsup'     => '1F44D',
			'thumbsdown'   => '1F44E',
			'clap'         => '1F44F',
			'wave'         => '1F44B',
			'fire'         => '1F525',
			'heart'        => '2764',
			'star'         => '2B50',
			'sparkles'     => '2728',
			'tada'         => '1F389',
			'rocket'       => '1F680',
			'eyes'         => '1F440',
			'thinking'     => '1F914',
			'ok_hand'      => '1F44C',
			'raised_hands' => '1F64C',
			'pray'         => '1F64F',
			'muscle'       => '1F4AA',
			'point_up'     => '261D',
			'point_down'   => '1F447',
			'point_left'   => '1F448',
			'point_right'  => '1F449',
			'fist'         => '270A',
			'v'            => '270C',
			'checkmark'    => '2714',
			'x'            => '274C',
			'warning'      => '26A0',
			'question'     => '2753',
			'exclamation'  => '2757',
			'zzz'          => '1F4A4',
			'bulb'         => '1F4A1',
			'bomb'         => '1F4A3',
			'boom'         => '1F4A5',
			'zap'          => '26A1',
			'sunny'        => '2600',
			'cloud'        => '2601',
			'umbrella'     => '2614',
			'snowflake'    => '2744',
			'coffee'       => '2615',
			'beer'         => '1F37A',
			'pizza'        => '1F355',
			'cake'         => '1F370',
			'apple'        => '1F34E',
			'dog'          => '1F436',
			'cat'          => '1F431',
			'trophy'       => '1F3C6',
			'medal'        => '1F3C5',
			'bell'         => '1F514',
			'gift'         => '1F381',
			'calendar'     => '1F4C5',
			'clock'        => '1F550',
			'phone'        => '260E',
			'email'        => '2709',
			'link'         => '1F517',
			'lock'         => '1F512',
			'unlock'       => '1F513',
			'key'          => '1F511',
			'pencil'       => '270F',
			'book'         => '1F4D6',
			'newspaper'    => '1F4F0',
			'camera'       => '1F4F7',
			'video'        => '1F4F9',
			'computer'     => '1F4BB',
			'globe'        => '1F310',
			'flag'         => '1F3C1',
			'construction' => '1F6A7',
			'ambulance'    => '1F691',
			'police'       => '1F693',
			'car'          => '1F697',
			'bus'          => '1F68C',
			'airplane'     => '2708',
			'anchor'       => '2693',
			'house'        => '1F3E0',
			'hospital'     => '1F3E5',
			'bank'         => '1F3E6',
			'stadium'      => '1F3DF',
		);
		// phpcs:enable
	}
}
