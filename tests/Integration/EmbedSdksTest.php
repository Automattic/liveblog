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
	 * Test that embed SDKs are enqueued on liveblog posts.
	 *
	 * @covers ::enqueue_embed_sdks
	 */
	public function test_embed_sdks_enqueued_on_liveblog_post(): void {
		// Set up as viewing a liveblog post.
		$this->go_to( get_permalink( $this->post_id ) );

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();
		$asset_manager->enqueue_embed_sdks();

		$this->assertTrue( wp_script_is( 'facebook', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'twitter', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'instagram', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'reddit', 'enqueued' ) );
	}

	/**
	 * Test that embed SDKs are not enqueued on non-liveblog posts.
	 *
	 * @covers ::enqueue_embed_sdks
	 */
	public function test_embed_sdks_not_enqueued_on_regular_post(): void {
		$regular_post = self::factory()->post->create();
		$this->go_to( get_permalink( $regular_post ) );

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();
		$asset_manager->enqueue_embed_sdks();

		$this->assertFalse( wp_script_is( 'facebook', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'twitter', 'enqueued' ) );
	}

	/**
	 * Test that the liveblog_embed_sdks filter can customise SDKs.
	 *
	 * @covers ::init_embed_sdks
	 * @covers ::enqueue_embed_sdks
	 */
	public function test_embed_sdks_filter_customises_sdks(): void {
		$this->go_to( get_permalink( $this->post_id ) );

		// Filter to only load Twitter SDK.
		add_filter(
			'liveblog_embed_sdks',
			function () {
				return array( 'twitter' => 'https://platform.twitter.com/widgets.js' );
			}
		);

		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();
		$asset_manager->enqueue_embed_sdks();

		$this->assertTrue( wp_script_is( 'twitter', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'facebook', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'instagram', 'enqueued' ) );
	}

	/**
	 * Test that async attribute is added to embed SDK scripts.
	 *
	 * @covers ::add_async_to_embed_sdks
	 */
	public function test_async_attribute_added_to_embed_scripts(): void {
		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();

		$tag    = '<script src="https://platform.twitter.com/widgets.js"></script>';
		$result = $asset_manager->add_async_to_embed_sdks( $tag, 'twitter' );

		$this->assertStringContainsString( 'async="async"', $result );
	}

	/**
	 * Test that async attribute is not added to non-embed scripts.
	 *
	 * @covers ::add_async_to_embed_sdks
	 */
	public function test_async_attribute_not_added_to_other_scripts(): void {
		$asset_manager = Container::instance()->asset_manager();
		$asset_manager->init_embed_sdks();

		$tag    = '<script src="https://example.com/other.js"></script>';
		$result = $asset_manager->add_async_to_embed_sdks( $tag, 'some-other-script' );

		$this->assertStringNotContainsString( 'async', $result );
		$this->assertSame( $tag, $result );
	}
}
