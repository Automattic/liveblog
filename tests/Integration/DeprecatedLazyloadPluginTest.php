<?php
/**
 * Tests for disabling the deprecated standalone Lazyload Liveblog Entries plugin.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Infrastructure\WordPress\PluginBootstrapper;

/**
 * Deprecated lazyload plugin handling test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\WordPress\PluginBootstrapper::handle_deprecated_lazyload_plugin
 */
final class DeprecatedLazyloadPluginTest extends IntegrationTestCase {

	/**
	 * Remove the simulated deprecated hook so it cannot leak between tests.
	 */
	public function tear_down(): void {
		remove_action( 'init', 'Lazyload_Liveblog_Entries' );
		parent::tear_down();
	}

	/**
	 * The handler must be hooked on init at priority 0.
	 *
	 * It previously ran on template_redirect, which is front-end only (so the
	 * admin notice never showed) and fires after init (too late to unhook the
	 * deprecated plugin). Running early on init fixes both.
	 */
	public function test_handler_runs_early_on_init(): void {
		global $wp_filter;

		$found_priority = null;
		$collection     = $wp_filter['init'] ?? array();

		foreach ( $collection as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] )
					&& isset( $callback['function'][1] )
					&& 'handle_deprecated_lazyload_plugin' === $callback['function'][1]
				) {
					$found_priority = $priority;
					break 2;
				}
			}
		}

		$this->assertNotNull( $found_priority, 'The deprecated-lazyload handler should be hooked to init.' );
		$this->assertSame(
			0,
			$found_priority,
			'The handler must run before the deprecated plugin default-priority init callback.'
		);
	}

	/**
	 * Invoking the handler unhooks the deprecated plugin init callback.
	 */
	public function test_handler_unhooks_deprecated_plugin(): void {
		add_action( 'init', 'Lazyload_Liveblog_Entries' );
		$this->assertNotFalse(
			has_action( 'init', 'Lazyload_Liveblog_Entries' ),
			'Precondition: the deprecated init callback should be registered.'
		);

		( new PluginBootstrapper( $this->container() ) )->handle_deprecated_lazyload_plugin();

		$this->assertFalse(
			has_action( 'init', 'Lazyload_Liveblog_Entries' ),
			'The handler should remove the deprecated plugin init callback.'
		);
	}
}
