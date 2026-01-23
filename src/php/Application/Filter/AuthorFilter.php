<?php
/**
 * Author filter for liveblog entries.
 *
 * @package Automattic\Liveblog\Application\Filter
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Application\Filter;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Infrastructure\WordPress\RestApiController;

/**
 * Filters entry content for author patterns (@author).
 *
 * Authors are replaced with links to their post archives.
 * The filter also provides an AJAX endpoint for autocomplete.
 */
final class AuthorFilter implements ContentFilterInterface {

	/**
	 * Default class prefix for authors.
	 *
	 * @var string
	 */
	public const DEFAULT_CLASS_PREFIX = 'author-';

	/**
	 * Character prefixes that trigger this filter.
	 *
	 * @var array<string>
	 */
	private array $prefixes = array( '@', '\x{0040}' );

	/**
	 * Regex pattern for matching authors.
	 *
	 * @var string|null
	 */
	private ?string $regex = null;

	/**
	 * Regex pattern for reverting authors.
	 *
	 * @var string|null
	 */
	private ?string $revert_regex = null;

	/**
	 * Class prefix for author CSS classes.
	 *
	 * @var string
	 */
	private string $class_prefix;

	/**
	 * Cached author nicenames.
	 *
	 * @var array<string>
	 */
	private array $authors = array();

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
		return 'authors';
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
		 * Filter the author class prefix.
		 *
		 * @param string $class_prefix The class prefix.
		 */
		$this->class_prefix = apply_filters( 'liveblog_author_class', self::DEFAULT_CLASS_PREFIX );

		// Build the revert regex.
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<a href="', '~' ),
				'[^"]+',
				preg_quote( '" class="liveblog-author ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '"', '~' ),
				'[^>]*>\\1',
				preg_quote( '</a>', '~' ),
			)
		);

		/**
		 * Filter the author revert regex.
		 *
		 * @param string $revert_regex The revert regex.
		 */
		$this->revert_regex = apply_filters( 'liveblog_author_revert_regex', $this->revert_regex );

		// Add CSS classes to entries.
		add_filter( 'comment_class', array( $this, 'add_author_class_to_entry' ), 10, 3 );

		// Add AJAX endpoint for autocomplete.
		add_action( 'wp_ajax_liveblog_authors', array( $this, 'ajax_authors' ) );
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

		// Load authors for matching.
		$args = array(
			'capability' => 'edit_posts',
			'fields'     => array( 'user_nicename' ),
		);

		/**
		 * Filter the list of authors for matching.
		 *
		 * @param array  $authors The authors list.
		 * @param string $term    The search term (empty during content filter).
		 */
		$authors       = apply_filters( 'liveblog_author_list', get_users( $args ), '' );
		$this->authors = array_map( array( $this, 'map_author_nicename' ), $authors );

		$entry['content'] = preg_replace_callback(
			$this->regex,
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		) ?? $entry['content'];

		return $entry;
	}

	/**
	 * Map author to nicename.
	 *
	 * @param object $author The author object.
	 * @return string
	 */
	public function map_author_nicename( object $author ): string {
		return strtolower( $author->user_nicename );
	}

	/**
	 * Callback for preg_replace_callback.
	 *
	 * @param array<int, string> $regex_match The regex match array.
	 * @return string
	 */
	public function preg_replace_callback( array $regex_match ): string {
		/**
		 * Filter the matched author.
		 *
		 * @param string $author The author nicename.
		 */
		$author = apply_filters( 'liveblog_author', $regex_match[2] );

		// If not a registered author, return unchanged.
		if ( ! in_array( $author, $this->authors, true ) ) {
			return $regex_match[0];
		}

		// Get user display name.
		$user         = get_user_by( 'slug', $author );
		$display_name = $user ? $user->display_name : $author;

		return str_replace(
			$regex_match[1],
			'<a href="' . get_author_posts_url( -1, $author ) . '" class="liveblog-author ' . $this->class_prefix . $author . '">' . esc_html( $display_name ) . '</a>',
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

		return preg_replace( '~' . $this->revert_regex . '~', '@$1', $content ) ?? $content;
	}

	/**
	 * Get autocomplete configuration.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_autocomplete_config(): ?array {
		$endpoint_url = admin_url( 'admin-ajax.php' ) . '?action=liveblog_authors';

		if ( LiveblogConfiguration::use_rest_api() ) {
			$endpoint_url = trailingslashit( trailingslashit( RestApiController::build_endpoint_base() ) . 'authors' );
		}

		/**
		 * Filter the author autocomplete config.
		 *
		 * @param array $config The autocomplete config.
		 */
		return apply_filters(
			'liveblog_author_config',
			array(
				'type'        => 'ajax',
				'cache'       => 1000 * 60 * 30,
				'url'         => esc_url( $endpoint_url ),
				'displayKey'  => 'key',
				'search'      => 'key',
				'regex'       => '@([\w\-]*)$',
				'replacement' => '@${key}',
				'template'    => '${avatar} ${name}',
				'trigger'     => '@',
				'name'        => 'Author',
				'replaceText' => '@$',
			)
		);
	}

	/**
	 * Add author-{nicename} class to entry.
	 *
	 * @param array<string>     $classes    The existing classes.
	 * @param string|array<int> $css_class  The class name(s).
	 * @param int               $comment_id The comment ID.
	 * @return array<string>
	 */
	public function add_author_class_to_entry( array $classes, $css_class, int $comment_id ): array {
		$authors = array();
		$comment = get_comment( $comment_id );

		if ( ! $comment || LiveblogConfiguration::KEY !== $comment->comment_type ) {
			return $classes;
		}

		preg_match_all(
			'/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/',
			$comment->comment_content,
			$authors
		);

		return array_merge( $classes, $authors[0] );
	}

	/**
	 * AJAX handler for author autocomplete.
	 *
	 * @return void
	 */
	public function ajax_authors(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public autocomplete endpoint.
		$term = isset( $_GET['autocomplete'] ) ? sanitize_text_field( wp_unslash( $_GET['autocomplete'] ) ) : '';

		$users = $this->get_authors( $term );

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $users );
		exit;
	}

	/**
	 * Get authors matching a search term.
	 *
	 * @param string $term The search term.
	 * @return array<array<string, mixed>>
	 */
	public function get_authors( string $term ): array {
		$args = array(
			'capability' => 'edit_posts',
			'fields'     => array( 'ID', 'user_nicename', 'display_name' ),
			'number'     => 10,
		);

		if ( strlen( trim( $term ) ) > 0 ) {
			$args['search'] = $term . '*';
		}

		/**
		 * Filter the list of authors for autocomplete.
		 *
		 * @param array  $authors The authors list.
		 * @param string $term    The search term.
		 */
		$authors = apply_filters( 'liveblog_author_list', get_users( $args ), $term );

		return array_map( array( $this, 'map_ajax_author' ), $authors );
	}

	/**
	 * Map author for AJAX response.
	 *
	 * @param object $author The author object.
	 * @return array<string, mixed>
	 */
	public function map_ajax_author( object $author ): array {
		return array(
			'id'     => $author->ID,
			'key'    => strtolower( $author->user_nicename ),
			'name'   => $author->display_name,
			'avatar' => get_avatar( $author->ID, 20 ),
		);
	}
}
