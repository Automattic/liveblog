<?php
/**
 * Integration tests for embed SDK loading.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Infrastructure\DI\Container;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for social embed SDK loading functionality.
 *
 * Provider SDKs are no longer enqueued on every liveblog post. Instead the
 * (filtered) SDK URL map is passed to the front-end app, which lazy-loads each
 * SDK on demand only when a matching embed is rendered.
 *
 * @coversDefaultClass \Automattic\Liveblog\Infrastructure\WordPress\AssetManager
 */
final class EmbedSdksTest extends TestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset script queue between tests.
		$this->reset_scripts();

		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, LiveblogConfiguration::KEY, 'enable' );
	}

	/**
	 * Reset the WordPress scripts queue.
	 */
	private function reset_scripts(): void {
		global $wp_scripts;
		$wp_scripts = new \WP_Scripts(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for test isolation.
	}

	/**
	 * Get the data localised onto the main liveblog front-end script.
	 *
	 * @return string The `liveblog_settings` JavaScript data string.
	 */
	private function get_localised_settings(): string {
		$data = wp_scripts()->get_data( LiveblogConfiguration::KEY, 'data' );

		return is_string( $data ) ? $data : '';
	}

	/**
	 * Test that provider SDKs are not enqueued on liveblog posts.
	 *
	 * This is the behaviour change: SDKs must no longer be loaded unconditionally
	 * on every liveblog post.
	 *
	 * @covers ::maybe_enqueue_frontend_scripts
	 */
	public function test_embed_sdks_not_enqueued_on_liveblog_post(): void {
		$this->go_to( get_permalink( $this->post_id ) );

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();
		$asset_manager->maybe_enqueue_frontend_scripts();

		$this->assertFalse( wp_script_is( 'facebook', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'twitter', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'instagram', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'reddit', 'enqueued' ) );
	}

	/**
	 * Test that the SDK URL map is exposed to the front-end settings so the
	 * client can lazy-load each SDK on demand.
	 *
	 * @covers ::maybe_enqueue_frontend_scripts
	 */
	public function test_embed_sdks_exposed_in_frontend_settings(): void {
		$this->go_to( get_permalink( $this->post_id ) );

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();
		$asset_manager->maybe_enqueue_frontend_scripts();

		$settings = $this->get_localised_settings();

		$this->assertStringContainsString( 'embed_sdks', $settings );
		$this->assertStringContainsString( 'connect.facebook.net', $settings );
		$this->assertStringContainsString( 'platform.twitter.com', $settings );
		$this->assertStringContainsString( 'instagram.com', $settings );
		$this->assertStringContainsString( 'embed.reddit.com', $settings );
	}

	/**
	 * Test that the default SDK list contains the expected providers and that
	 * the Facebook URL is not HTML-encoded (so it works as a client-side src).
	 *
	 * @covers ::init_embed_sdks
	 * @covers ::get_embed_sdks
	 */
	public function test_get_embed_sdks_returns_defaults(): void {
		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();

		$sdks = $asset_manager->get_embed_sdks();

		$this->assertSame(
			array( 'facebook', 'twitter', 'instagram', 'reddit' ),
			array_keys( $sdks )
		);
		$this->assertStringNotContainsString( '&amp;', $sdks['facebook'] );
		$this->assertStringContainsString( 'xfbml=1&version=v2.5', $sdks['facebook'] );
	}

	/**
	 * Test that the liveblog_embed_sdks filter can customise the SDK map.
	 *
	 * @covers ::init_embed_sdks
	 * @covers ::get_embed_sdks
	 */
	public function test_embed_sdks_filter_customises_sdks(): void {
		add_filter(
			'liveblog_embed_sdks',
			function () {
				return array( 'twitter' => 'https://platform.twitter.com/widgets.js' );
			}
		);

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();

		$this->assertSame(
			array( 'twitter' => 'https://platform.twitter.com/widgets.js' ),
			$asset_manager->get_embed_sdks()
		);
	}

	/**
	 * Test that the liveblog_embed_sdks filter can disable all SDK loading.
	 *
	 * @covers ::init_embed_sdks
	 * @covers ::get_embed_sdks
	 */
	public function test_embed_sdks_filter_can_disable_all(): void {
		add_filter( 'liveblog_embed_sdks', '__return_empty_array' );

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();

		$this->assertSame( array(), $asset_manager->get_embed_sdks() );
	}
}
