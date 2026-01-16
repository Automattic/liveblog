<?php
/**
 * Entry type value object.
 *
 * @package Automattic\Liveblog\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Domain\ValueObject;

/**
 * Represents the type of a liveblog entry.
 *
 * An entry can be:
 * - New: A fresh entry with no replacement relationship.
 * - Update: A modification of an existing entry (has replaces ID and content).
 * - Delete: A deletion marker for an existing entry (has replaces ID but no content).
 *
 * This is a value object that emulates an enum for PHP 7.4 compatibility.
 */
final class EntryType {

	/**
	 * Entry type value for new entries.
	 *
	 * @var string
	 */
	public const NEW = 'new';

	/**
	 * Entry type value for updated entries.
	 *
	 * @var string
	 */
	public const UPDATE = 'update';

	/**
	 * Entry type value for deleted entries.
	 *
	 * @var string
	 */
	public const DELETE = 'delete';

	/**
	 * The type value.
	 *
	 * @var string
	 */
	public $value;

	/**
	 * Cached instances for flyweight pattern.
	 *
	 * @var array<string, self>
	 */
	private static $instances = array();

	/**
	 * Constructor.
	 *
	 * @param string $value The entry type value.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * Get the New entry type.
	 *
	 * @return self
	 */
	public static function new_type(): self {
		if ( ! isset( self::$instances[ self::NEW ] ) ) {
			self::$instances[ self::NEW ] = new self( self::NEW );
		}
		return self::$instances[ self::NEW ];
	}

	/**
	 * Get the Update entry type.
	 *
	 * @return self
	 */
	public static function update(): self {
		if ( ! isset( self::$instances[ self::UPDATE ] ) ) {
			self::$instances[ self::UPDATE ] = new self( self::UPDATE );
		}
		return self::$instances[ self::UPDATE ];
	}

	/**
	 * Get the Delete entry type.
	 *
	 * @return self
	 */
	public static function delete(): self {
		if ( ! isset( self::$instances[ self::DELETE ] ) ) {
			self::$instances[ self::DELETE ] = new self( self::DELETE );
		}
		return self::$instances[ self::DELETE ];
	}

	/**
	 * Determine entry type from replaces ID and content.
	 *
	 * @param int|null $replaces_id ID of the entry being replaced, or null.
	 * @param string   $content     Entry content.
	 * @return self
	 */
	public static function from_replaces_and_content( ?int $replaces_id, string $content ): self {
		if ( $replaces_id && $content ) {
			return self::update();
		}

		if ( $replaces_id && ! $content ) {
			return self::delete();
		}

		return self::new_type();
	}

	/**
	 * Check if entry is new.
	 *
	 * @return bool
	 */
	public function is_new(): bool {
		return self::NEW === $this->value;
	}

	/**
	 * Check if entry is an update.
	 *
	 * @return bool
	 */
	public function is_update(): bool {
		return self::UPDATE === $this->value;
	}

	/**
	 * Check if entry is a deletion.
	 *
	 * @return bool
	 */
	public function is_delete(): bool {
		return self::DELETE === $this->value;
	}
}
