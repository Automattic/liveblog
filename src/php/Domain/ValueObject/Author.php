<?php
/**
 * Author value object.
 *
 * @package Automattic\Liveblog\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\ValueObject;

use WP_Comment;
use WP_User;

/**
 * Represents an author of a liveblog entry.
 *
 * Encapsulates author data with factory methods for creating from different sources.
 * Immutable once created.
 */
final class Author {

	/**
	 * Default avatar size in pixels.
	 *
	 * @var int
	 */
	public const DEFAULT_AVATAR_SIZE = 30;

	/**
	 * User ID, or null for anonymous/external authors.
	 *
	 * @var int|null
	 */
	private $id;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Email address.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * Author URL (website or profile).
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Unique key for the author (typically lowercase nicename).
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 *
	 * @param int|null $id    User ID, or null for anonymous/external authors.
	 * @param string   $name  Display name.
	 * @param string   $email Email address.
	 * @param string   $url   Author URL (website or profile).
	 * @param string   $key   Unique key for the author (typically lowercase nicename).
	 */
	private function __construct( ?int $id, string $name, string $email, string $url, string $key ) {
		$this->id    = $id;
		$this->name  = $name;
		$this->email = $email;
		$this->url   = $url;
		$this->key   = $key;
	}

	/**
	 * Create an Author from a WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return self
	 */
	public static function from_user_id( int $user_id ): self {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return self::anonymous();
		}

		return self::from_user( $user );
	}

	/**
	 * Create an Author from a WordPress user object.
	 *
	 * @param WP_User $user WordPress user object.
	 * @return self
	 */
	public static function from_user( WP_User $user ): self {
		return new self(
			(int) $user->ID,
			$user->display_name,
			$user->user_email,
			$user->user_url,
			strtolower( $user->user_nicename )
		);
	}

	/**
	 * Create an Author from a WordPress comment.
	 *
	 * Uses the comment's stored author information.
	 *
	 * @param WP_Comment $comment WordPress comment object.
	 * @return self
	 */
	public static function from_comment( WP_Comment $comment ): self {
		return new self(
			$comment->user_id ? (int) $comment->user_id : null,
			$comment->comment_author,
			$comment->comment_author_email,
			$comment->comment_author_url,
			strtolower( sanitize_title( $comment->comment_author ) )
		);
	}

	/**
	 * Create an Author from an array.
	 *
	 * Useful for backwards compatibility with existing code.
	 *
	 * @param array $data Author data array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			isset( $data['id'] ) ? (int) $data['id'] : null,
			$data['name'] ?? '',
			$data['email'] ?? '',
			$data['url'] ?? '',
			$data['key'] ?? ''
		);
	}

	/**
	 * Create an anonymous author.
	 *
	 * Used when no author information is available.
	 *
	 * @return self
	 */
	public static function anonymous(): self {
		return new self( null, '', '', '', '' );
	}

	/**
	 * Get the user ID.
	 *
	 * @return int|null
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Get the display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Get the display name (alias for name()).
	 *
	 * @return string
	 */
	public function display_name(): string {
		return $this->name;
	}

	/**
	 * Get the email address.
	 *
	 * @return string
	 */
	public function email(): string {
		return $this->email;
	}

	/**
	 * Get the author URL.
	 *
	 * @return string
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Get the author key.
	 *
	 * @return string
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Get the avatar URL.
	 *
	 * @param int $size Avatar size in pixels.
	 * @return string Avatar URL.
	 */
	public function avatar_url( int $size = self::DEFAULT_AVATAR_SIZE ): string {
		if ( $this->email ) {
			return (string) get_avatar_url( $this->email, array( 'size' => $size ) );
		}

		if ( $this->id ) {
			return (string) get_avatar_url( $this->id, array( 'size' => $size ) );
		}

		return '';
	}

	/**
	 * Get the avatar HTML.
	 *
	 * @param int $size Avatar size in pixels.
	 * @return string Avatar HTML.
	 */
	public function avatar_html( int $size = self::DEFAULT_AVATAR_SIZE ): string {
		if ( $this->email ) {
			return (string) get_avatar( $this->email, $size );
		}

		if ( $this->id ) {
			return (string) get_avatar( $this->id, $size );
		}

		return '';
	}

	/**
	 * Get the author's profile URL.
	 *
	 * Returns the author URL if set, otherwise attempts to get the WordPress
	 * author posts URL for registered users.
	 *
	 * @return string
	 */
	public function profile_url(): string {
		if ( $this->url ) {
			return $this->url;
		}

		if ( $this->id ) {
			return (string) get_author_posts_url( $this->id );
		}

		return '';
	}

	/**
	 * Check if this is an anonymous author.
	 *
	 * @return bool
	 */
	public function is_anonymous(): bool {
		return null === $this->id && '' === $this->name;
	}

	/**
	 * Convert to array for backwards compatibility.
	 *
	 * Matches the format used by the legacy liveblog entry author arrays.
	 *
	 * @param int $avatar_size Avatar size in pixels.
	 * @return array
	 */
	public function to_array( int $avatar_size = self::DEFAULT_AVATAR_SIZE ): array {
		return array(
			'id'     => $this->id ?? 0,
			'key'    => $this->key,
			'name'   => $this->name,
			'avatar' => $this->avatar_html( $avatar_size ),
		);
	}

	/**
	 * Convert to schema.org Person object.
	 *
	 * @return object
	 */
	public function to_schema(): object {
		$person = (object) array(
			'@type' => 'Person',
			'name'  => $this->name,
		);

		$url = $this->profile_url();
		if ( $url ) {
			$person->url = $url;
		}

		return $person;
	}

	/**
	 * Check equality with another Author.
	 *
	 * @param self $other The other Author to compare.
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->id === $other->id
			&& $this->name === $other->name
			&& $this->email === $other->email;
	}
}
