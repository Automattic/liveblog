<?php
/**
 * Service container for dependency injection.
 *
 * @package Automattic\Liveblog\Infrastructure
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Infrastructure;

use Automattic\Liveblog\Application\Service\EntryService;
use Automattic\Liveblog\Domain\Repository\EntryRepositoryInterface;
use Automattic\Liveblog\Infrastructure\Repository\CommentEntryRepository;

/**
 * Simple service container for wiring up dependencies.
 *
 * This acts as the composition root for the application, creating and
 * providing access to service instances. Services are lazily instantiated
 * and cached for the lifetime of the request.
 *
 * Usage:
 *   $container = ServiceContainer::instance();
 *   $service   = $container->entry_service();
 */
final class ServiceContainer {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Cached entry repository instance.
	 *
	 * @var EntryRepositoryInterface|null
	 */
	private ?EntryRepositoryInterface $entry_repository = null;

	/**
	 * Cached entry service instance.
	 *
	 * @var EntryService|null
	 */
	private ?EntryService $entry_service = null;

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the container (primarily for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Get the entry repository.
	 *
	 * @return EntryRepositoryInterface
	 */
	public function entry_repository(): EntryRepositoryInterface {
		if ( null === $this->entry_repository ) {
			$this->entry_repository = new CommentEntryRepository();
		}

		return $this->entry_repository;
	}

	/**
	 * Get the entry service.
	 *
	 * @return EntryService
	 */
	public function entry_service(): EntryService {
		if ( null === $this->entry_service ) {
			$this->entry_service = new EntryService( $this->entry_repository() );
		}

		return $this->entry_service;
	}

	/**
	 * Set a custom entry repository (for testing or alternative implementations).
	 *
	 * @param EntryRepositoryInterface $repository Repository instance.
	 * @return self
	 */
	public function set_entry_repository( EntryRepositoryInterface $repository ): self {
		$this->entry_repository = $repository;
		// Clear the service so it gets recreated with the new repository.
		$this->entry_service = null;

		return $this;
	}

	/**
	 * Set a custom entry service (for testing).
	 *
	 * @param EntryService $service Service instance.
	 * @return self
	 */
	public function set_entry_service( EntryService $service ): self {
		$this->entry_service = $service;

		return $this;
	}
}
