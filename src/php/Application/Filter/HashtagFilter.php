<?php
/**
 * Hashtag filter for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;

/**
 * Filters entry content for hashtag patterns (#hashtag).
 *
 * Hashtags are replaced with links to taxonomy archives.
 * The filter also registers the hashtag taxonomy and
 * provides an AJAX endpoint for autocomplete.
 */
final class HashtagFilter implements ContentFilterInterface {

	/**
	 * Taxonomy name for hashtags.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'hashtags';

	/**
	 * Default class prefix for hashtags.
	 *
	 * @var string
	 */
	public const DEFAULT_CLASS_PREFIX = 'term-';

	/**
	 * Character prefixes that trigger this filter.
	 *
	 * @var array<string>
	 */
	private array $prefixes = array( '#', '\x{ff03}' );

	/**
	 * Regex pattern for matching hashtags.
	 *
	 * @var string|null
	 */
	private ?string $regex = null;

	/**
	 * Regex pattern for reverting hashtags.
	 *
	 * @var string|null
	 */
	private ?string $revert_regex = null;

	/**
	 * Class prefix for hashtag CSS classes.
	 *
	 * @var string
	 */
	private string $class_prefix;

	/**
	 * Current post ID being processed.
	 *
	 * @var int
	 */
	private int $current_post_id = 0;

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
		return 'hashtags';
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
	 * Uses a custom regex for hashtags to allow for hex values in content.
	 *
	 * @param string $regex The regex pattern (ignored, custom regex used).
	 */
	public function set_regex( string $regex ): void {
		$prefixes = implode( '|', $this->get_prefixes() );

		// Custom regex that allows for hex values in content.
		// See https://regex101.com/r/CLWsCo/.
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound, Squiz.Commenting.InlineComment.InvalidEndChar -- Regex formatting.
		$this->regex =
			'~'
			. '(?:'
			.     '(?<!\S)'          // any visible character
			. '|'
			.     '>?'               // possible right angle bracket(s)
			. ')'
			. '(?:'
			.     '(?<!'
			.         '&'            // literal ampersand
			.     '|'
			.         '&amp;'        // encoded ampersand
			.     ')'
			. ')'
			. '('
			.     "(?:{$prefixes})"  // hashtag prefixes
			.     '([0-9_\-\p{L}]*)' // numerals, underscores, dashes, any letter
			. ')'
			. '~um';
		// phpcs:enable
	}

	/**
	 * Initialise the filter.
	 */
	public function load(): void {
		/**
		 * Filter the hashtag class prefix.
		 *
		 * @param string $class_prefix The class prefix.
		 */
		$this->class_prefix = apply_filters( 'liveblog_hashtag_class', self::DEFAULT_CLASS_PREFIX );

		// Build the revert regex.
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<span class="liveblog-hash ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '">', '~' ),
				'([^"]+)',
				preg_quote( '</span>', '~' ),
			)
		);

		/**
		 * Filter the hashtag revert regex.
		 *
		 * @param string $revert_regex The revert regex.
		 */
		$this->revert_regex = apply_filters( 'liveblog_hashtag_revert_regex', $this->revert_regex );

		// Add CSS classes to entries.
		add_filter( 'comment_class', array( $this, 'add_term_class_to_entry' ), 10, 3 );

		// Register the hashtag taxonomy.
		add_action( 'init', array( $this, 'add_hashtag_taxonomy' ) );

		// Add AJAX endpoint for autocomplete.
		add_action( 'wp_ajax_liveblog_terms', array( $this, 'ajax_terms' ) );
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

		// Store the post ID for use in callback.
		$this->current_post_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;

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
		// Convert to ASCII and sanitize.
		$hashtag = iconv( 'UTF-8', 'ASCII//TRANSLIT', $regex_match[2] );
		$hashtag = sanitize_term( array( 'slug' => $hashtag ), self::TAXONOMY, 'db' );
		$hashtag = $hashtag['slug'];

		// Get or create the term.
		$term = get_term_by( 'slug', $hashtag, self::TAXONOMY );
		if ( ! $term ) {
			$result = wp_insert_term( $hashtag, self::TAXONOMY );
			if ( ! is_wp_error( $result ) ) {
				$term = get_term( $result['term_id'], self::TAXONOMY );
			}
		}

		// Associate the term with the post.
		if ( $term && $this->current_post_id ) {
			wp_set_object_terms( $this->current_post_id, $term->term_id, self::TAXONOMY, true );
		}

		// Get the term link.
		$term_link = $term ? get_term_link( $term, self::TAXONOMY ) : '';

		// Replace with link to archive.
		if ( $term_link && ! is_wp_error( $term_link ) ) {
			return str_replace(
				$regex_match[1],
				'<a href="' . esc_url( $term_link ) . '" class="liveblog-hash ' . $this->class_prefix . $hashtag . '">' . esc_html( $hashtag ) . '</a>',
				$regex_match[0]
			);
		}

		// Fallback to span.
		return str_replace(
			$regex_match[1],
			'<span class="liveblog-hash ' . $this->class_prefix . $hashtag . '">' . esc_html( $hashtag ) . '</span>',
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

		return preg_replace( '~' . $this->revert_regex . '~', '#$1', $content ) ?? $content;
	}

	/**
	 * Get autocomplete configuration.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_autocomplete_config(): ?array {
		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_terms';

		if ( LiveblogConfiguration::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( RestApiController::build_endpoint_base() ) . 'hashtags' );
		}

		/**
		 * Filter the hashtag autocomplete config.
		 *
		 * @param array $config The autocomplete config.
		 */
		return apply_filters(
			'liveblog_hashtag_config',
			array(
				'type'        => 'ajax',
				'cache'       => 1000 * 60,
				'regex'       => '#([\w\d\-]*)$',
				'replacement' => '#${slug}',
				'trigger'     => '#',
				'displayKey'  => 'slug',
				'name'        => 'Hashtag',
				'template'    => '${slug}',
				'replaceText' => '#$',
				'url'         => esc_url( $endpoint_url ),
			)
		);
	}

	/**
	 * Add term-{hashtag} class to entry.
	 *
	 * @param array<string>     $classes    The existing classes.
	 * @param string|array<int> $css_class  The class name(s).
	 * @param int               $comment_id The comment ID.
	 * @return array<string>
	 */
	public function add_term_class_to_entry( array $classes, $css_class, int $comment_id ): array {
		$terms   = array();
		$comment = get_comment( $comment_id );

		if ( ! $comment || LiveblogConfiguration::KEY !== $comment->comment_type ) {
			return $classes;
		}

		preg_match_all(
			'/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '(\w\-?)+/',
			$comment->comment_content,
			$terms
		);

		return array_merge( $classes, $terms[0] );
	}

	/**
	 * AJAX handler for hashtag autocomplete.
	 *
	 * @return void
	 */
	public function ajax_terms(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public autocomplete endpoint.
		$search_term = isset( $_GET['autocomplete'] ) ? sanitize_text_field( wp_unslash( $_GET['autocomplete'] ) ) : '';

		$terms = $this->get_hashtag_terms( $search_term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $terms );

		exit;
	}

	/**
	 * Get hashtag terms matching a search term.
	 *
	 * @param string $term The search term.
	 * @return array<\WP_Term>
	 */
	public function get_hashtag_terms( string $term ): array {
		$args = array(
			'hide_empty' => false,
			'number'     => 10,
			'taxonomy'   => self::TAXONOMY,
		);

		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term;
		}

		// Filter to search by slug only.
		add_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		$terms = get_terms( $args );

		remove_filter( 'terms_clauses', array( $this, 'remove_name_search' ), 10 );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Remove name from term search clauses.
	 *
	 * @param array<string, string> $clauses The query clauses.
	 * @return array<string, string>
	 */
	public function remove_name_search( array $clauses ): array {
		$clauses['where'] = preg_replace(
			array(
				'~\\(\\(.*(?=' . preg_quote( "(t.slug LIKE '", '~' ) . ')~',
				'~(%\'\\))\\)~',
			),
			'($1',
			$clauses['where']
		) ?? $clauses['where'];

		return $clauses;
	}

	/**
	 * Register the hashtag taxonomy.
	 *
	 * @return void
	 */
	public function add_hashtag_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Hashtags', 'taxonomy general name', 'liveblog' ),
			'singular_name'     => _x( 'Hashtag', 'taxonomy singular name', 'liveblog' ),
			'search_items'      => __( 'Search Hashtags', 'liveblog' ),
			'all_items'         => __( 'All Hashtags', 'liveblog' ),
			'parent_item'       => __( 'Parent Hashtag', 'liveblog' ),
			'parent_item_colon' => __( 'Parent Hashtag:', 'liveblog' ),
			'edit_item'         => __( 'Edit Hashtag', 'liveblog' ),
			'update_item'       => __( 'Update Hashtag', 'liveblog' ),
			'add_new_item'      => __( 'Add New Hashtag', 'liveblog' ),
			'new_item_name'     => __( 'New Hashtag', 'liveblog' ),
			'menu_name'         => __( 'Hashtags', 'liveblog' ),
		);

		$args = array(
			'show_ui' => true,
			'public'  => true,
			'labels'  => $labels,
		);

		register_taxonomy( self::TAXONOMY, 'post', $args );
	}
}
