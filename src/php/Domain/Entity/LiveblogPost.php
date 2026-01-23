<?php
/**
 * LiveblogPost entity representing a post with liveblog functionality.
 *
 * @package Automattic\Liveblog\Domain\Entity
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\Entity;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use DateTimeImmutable;
use WP_Post;

/**
 * Domain entity wrapping WP_Post with liveblog-specific behaviour.
 *
 * Encapsulates the liveblog state and operations for a WordPress post,
 * providing a clean domain interface for working with liveblog posts.
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
	 * Get the liveblog state.
	 *
	 * @return string One of STATE_ENABLED, STATE_ARCHIVED, or STATE_DISABLED.
	 */
	public function state(): string {
		$state = get_post_meta( $this->post->ID, LiveblogConfiguration::KEY, true );

		// Handle backwards compatibility with older values.
		if ( 1 === $state ) {
			return self::STATE_ENABLED;
		}

		if ( self::STATE_ENABLED === $state || self::STATE_ARCHIVED === $state ) {
			return $state;
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
	 * Handles auto-archive expiry updates when enabling/re-enabling.
	 *
	 * @param string $new_state The new state to set.
	 * @return void
	 */
	private function set_state( string $new_state ): void {
		$current_state = $this->state();

		// Handle auto-archive when enabling/re-enabling.
		if ( LiveblogConfiguration::is_auto_archive_enabled() ) {
			$should_update_auto_archive =
				( self::STATE_DISABLED === $current_state && self::STATE_ENABLED === $new_state ) ||
				( self::STATE_ARCHIVED === $current_state && self::STATE_ENABLED === $new_state );

			if ( $should_update_auto_archive ) {
				$this->set_auto_archive_from_now();
			}
		}

		// Update the state.
		if ( self::STATE_ENABLED === $new_state || self::STATE_ARCHIVED === $new_state ) {
			update_post_meta( $this->post->ID, LiveblogConfiguration::KEY, $new_state );
			do_action( "liveblog_{$new_state}_post", $this->post->ID );
		} elseif ( self::STATE_DISABLED === $new_state ) {
			delete_post_meta( $this->post->ID, LiveblogConfiguration::KEY );
			delete_post_meta( $this->post->ID, LiveblogConfiguration::AUTO_ARCHIVE_EXPIRY_KEY );
			do_action( 'liveblog_disable_post', $this->post->ID );
		}
	}

	/**
	 * Get the auto-archive expiry date.
	 *
	 * @return DateTimeImmutable|null The expiry date, or null if not set.
	 */
	public function auto_archive_expiry(): ?DateTimeImmutable {
		$expiry = get_post_meta( $this->post->ID, LiveblogConfiguration::AUTO_ARCHIVE_EXPIRY_KEY, true );

		if ( empty( $expiry ) ) {
			return null;
		}

		return ( new DateTimeImmutable() )->setTimestamp( (int) $expiry );
	}

	/**
	 * Extend the auto-archive expiry by a number of days from a given timestamp.
	 *
	 * @param int $days             Number of days to extend.
	 * @param int $from_timestamp   Optional. Unix timestamp to calculate from. Default is now.
	 * @return void
	 */
	public function extend_auto_archive_expiry( int $days, int $from_timestamp = 0 ): void {
		if ( 0 === $from_timestamp ) {
			$from_timestamp = time();
		}

		$expiry = strtotime( ' + ' . $days . ' days', $from_timestamp );
		update_post_meta( $this->post->ID, LiveblogConfiguration::AUTO_ARCHIVE_EXPIRY_KEY, $expiry );
	}

	/**
	 * Set auto-archive expiry from now using the configured days.
	 *
	 * @return void
	 */
	private function set_auto_archive_from_now(): void {
		$days = LiveblogConfiguration::get_auto_archive_days();

		if ( null === $days ) {
			return;
		}

		$this->extend_auto_archive_expiry( $days );
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
