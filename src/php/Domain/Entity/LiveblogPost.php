<?php
/**
 * LiveblogPost entity representing a post with liveblog functionality.
 *
 * @package Automattic\Liveblog\Domain\Entity
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\Entity;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use WP_Post;

/**
 * Domain entity wrapping WP_Post with liveblog-specific behaviour.
 *
 * Encapsulates the liveblog state and operations for a WordPress post.
 * State is stored via the liveblog_state taxonomy (terms: enabled, archived)
 * rather than post meta, providing a clean domain interface for working
 * with liveblog posts.
 */
final class LiveblogPost {

	/**
	 * Liveblog state: enabled.
	 */
	public const STATE_ENABLED = 'enable';

	/**
	 * Liveblog state: archived.
	 */
	public const STATE_ARCHIVED = 'archive';

	/**
	 * Liveblog state: disabled (empty string).
	 */
	public const STATE_DISABLED = '';

	/**
	 * The underlying WordPress post.
	 *
	 * @var WP_Post
	 */
	private WP_Post $post;

	/**
	 * Private constructor - use factory methods.
	 *
	 * @param WP_Post $post The WordPress post object.
	 */
	private function __construct( WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Create a LiveblogPost from a post ID.
	 *
	 * Returns null if the post doesn't exist or its post type doesn't support liveblog.
	 *
	 * @param int $post_id The post ID.
	 * @return self|null The LiveblogPost instance, or null if post not found or unsupported.
	 */
	public static function from_id( int $post_id ): ?self {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		// Check if post type supports liveblog.
		if ( ! post_type_supports( $post->post_type, LiveblogConfiguration::KEY ) ) {
			return null;
		}

		return new self( $post );
	}

	/**
	 * Create a LiveblogPost from a WP_Post object.
	 *
	 * @param WP_Post $post The WordPress post object.
	 * @return self
	 */
	public static function from_post( WP_Post $post ): self {
		return new self( $post );
	}

	/**
	 * Get the post ID.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->post->ID;
	}

	/**
	 * Get the underlying WP_Post object.
	 *
	 * @return WP_Post
	 */
	public function post(): WP_Post {
		return $this->post;
	}

	/**
	 * Get the liveblog state from the liveblog_state taxonomy.
	 *
	 * @return string One of STATE_ENABLED, STATE_ARCHIVED, or STATE_DISABLED.
	 */
	public function state(): string {
		$terms = wp_get_post_terms( $this->post->ID, LiveblogConfiguration::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::STATE_DISABLED;
		}

		if ( in_array( LiveblogConfiguration::TERM_ENABLED, $terms, true ) ) {
			return self::STATE_ENABLED;
		}

		if ( in_array( LiveblogConfiguration::TERM_ARCHIVED, $terms, true ) ) {
			return self::STATE_ARCHIVED;
		}

		return self::STATE_DISABLED;
	}

	/**
	 * Check if liveblog is enabled for this post.
	 *
	 * @return bool True if liveblog is enabled (active).
	 */
	public function is_enabled(): bool {
		return self::STATE_ENABLED === $this->state();
	}

	/**
	 * Check if liveblog is archived for this post.
	 *
	 * @return bool True if liveblog is archived.
	 */
	public function is_archived(): bool {
		return self::STATE_ARCHIVED === $this->state();
	}

	/**
	 * Check if this is a liveblog post (enabled or archived).
	 *
	 * @return bool True if liveblog is enabled or archived.
	 */
	public function is_liveblog(): bool {
		return $this->is_enabled() || $this->is_archived();
	}

	/**
	 * Enable the liveblog on this post.
	 *
	 * @return void
	 */
	public function enable(): void {
		$this->set_state( self::STATE_ENABLED );
	}

	/**
	 * Archive the liveblog on this post.
	 *
	 * @return void
	 */
	public function archive(): void {
		$this->set_state( self::STATE_ARCHIVED );
	}

	/**
	 * Disable the liveblog on this post.
	 *
	 * @return void
	 */
	public function disable(): void {
		$this->set_state( self::STATE_DISABLED );
	}

	/**
	 * Set the liveblog state.
	 *
	 * @param string $new_state The new state to set.
	 * @return bool True if state was set successfully, false for unknown states.
	 */
	public function set_state( string $new_state ): bool {
		if ( self::STATE_ENABLED === $new_state ) {
			wp_set_object_terms( $this->post->ID, LiveblogConfiguration::TERM_ENABLED, LiveblogConfiguration::TAXONOMY, false );
			wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );
			do_action( 'liveblog_enable_post', $this->post->ID );
			return true;
		} elseif ( self::STATE_ARCHIVED === $new_state ) {
			wp_set_object_terms( $this->post->ID, LiveblogConfiguration::TERM_ARCHIVED, LiveblogConfiguration::TAXONOMY, false );
			wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );
			do_action( 'liveblog_archive_post', $this->post->ID );
			return true;
		} elseif ( self::STATE_DISABLED === $new_state ) {
			wp_remove_object_terms( $this->post->ID, array( LiveblogConfiguration::TERM_ENABLED, LiveblogConfiguration::TERM_ARCHIVED ), LiveblogConfiguration::TAXONOMY );
			wp_cache_delete( 'liveblog_child_post_ids', 'liveblog' );
			do_action( 'liveblog_disable_post', $this->post->ID );
			return true;
		}

		return false;
	}

	/**
	 * Check if the current user can edit this liveblog.
	 *
	 * @return bool True if the current user can edit the liveblog.
	 */
	public function current_user_can_edit(): bool {
		$cap    = LiveblogConfiguration::get_edit_capability();
		$retval = current_user_can( $cap );

		/**
		 * Filter whether the current user can edit the liveblog.
		 *
		 * @param bool $can_edit Whether the current user can edit.
		 */
		return (bool) apply_filters( 'liveblog_current_user_can_edit_liveblog', $retval );
	}

	/**
	 * Check if the liveblog is editable by the current user.
	 *
	 * Liveblog must be enabled AND user must have permission.
	 *
	 * @return bool True if the current user can edit and liveblog is enabled.
	 */
	public function is_editable(): bool {
		return $this->current_user_can_edit() && $this->is_enabled();
	}

	/**
	 * Get the post type.
	 *
	 * @return string The post type.
	 */
	public function post_type(): string {
		return $this->post->post_type;
	}

	/**
	 * Check if the post type supports liveblog.
	 *
	 * @return bool True if the post type supports liveblog.
	 */
	public function post_type_supports_liveblog(): bool {
		return post_type_supports( $this->post->post_type, LiveblogConfiguration::KEY );
	}

	/**
	 * Check if this post can be a liveblog.
	 *
	 * Only parent posts (not entries) of type 'post' can have liveblog enabled.
	 *
	 * @return bool True if post can be a liveblog.
	 */
	public function can_be_liveblog(): bool {
		// Must be 'post' type.
		if ( 'post' !== $this->post->post_type ) {
			return false;
		}

		// Must not be a child post (entries cannot have liveblog).
		if ( $this->post->post_parent > 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the permalink for this post.
	 *
	 * @return string The permalink URL.
	 */
	public function permalink(): string {
		return get_permalink( $this->post );
	}

	/**
	 * Check if the post is published.
	 *
	 * @return bool True if published.
	 */
	public function is_published(): bool {
		return 'publish' === $this->post->post_status;
	}

	/**
	 * Check if we're currently viewing a liveblog post.
	 *
	 * This static helper checks if the current request is viewing a
	 * singular liveblog post in the main query. Also checks that the
	 * post type supports liveblog.
	 *
	 * @return bool True if viewing a liveblog post.
	 */
	public static function is_viewing_liveblog_post(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// Check if post type supports liveblog.
		if ( ! post_type_supports( $post->post_type, LiveblogConfiguration::KEY ) ) {
			return false;
		}

		$liveblog_post = self::from_post( $post );

		return $liveblog_post->is_liveblog();
	}
}
