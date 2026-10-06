<?php
/**
 * Unit tests for the provider embed SDK loader.
 *
 * @package Automattic\Liveblog\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit;

use Yoast\WPTestUtils\BrainMonkey\TestCase;
use Brain\Monkey\Filters;
use WPCOM_Liveblog_Entry_Embed_SDKs;

/**
 * Embed SDK loader unit test case.
 */
final class EntryEmbedSdksTest extends TestCase {

	/**
	 * The default providers shipped with the plugin.
	 *
	 * @var string[]
	 */
	private const DEFAULT_PROVIDERS = array( 'facebook', 'twitter', 'instagram', 'reddit' );

	/**
	 * Test that the default SDK list contains the expected providers.
	 *
	 * @covers WPCOM_Liveblog_Entry_Embed_SDKs::get_sdks
	 */
	public function test_get_sdks_returns_default_providers(): void {
		$sdks = WPCOM_Liveblog_Entry_Embed_SDKs::get_sdks();

		$this->assertSame( self::DEFAULT_PROVIDERS, array_keys( $sdks ) );

		foreach ( $sdks as $url ) {
			$this->assertIsString( $url );
			$this->assertStringStartsWith( 'https://', $url );
		}
	}

	/**
	 * Test that the Facebook SDK URL uses an un-encoded ampersand so the SDK can
	 * be injected directly as a script src on the client.
	 *
	 * @covers WPCOM_Liveblog_Entry_Embed_SDKs::get_sdks
	 */
	public function test_facebook_sdk_url_is_not_html_encoded(): void {
		$sdks = WPCOM_Liveblog_Entry_Embed_SDKs::get_sdks();

		$this->assertStringNotContainsString( '&amp;', $sdks['facebook'] );
		$this->assertStringContainsString( 'xfbml=1&version=v2.5', $sdks['facebook'] );
	}

	/**
	 * Test that the SDK URLs are exposed to the front-end settings under
	 * `embed_sdks` so the client can lazy-load them on demand.
	 *
	 * @covers WPCOM_Liveblog_Entry_Embed_SDKs::add_sdks_to_settings
	 */
	public function test_add_sdks_to_settings_injects_sdk_map(): void {
		$settings = WPCOM_Liveblog_Entry_Embed_SDKs::add_sdks_to_settings( array( 'existing' => 'value' ) );

		$this->assertArrayHasKey( 'existing', $settings, 'Existing settings should be preserved.' );
		$this->assertArrayHasKey( 'embed_sdks', $settings );
		$this->assertSame( WPCOM_Liveblog_Entry_Embed_SDKs::get_sdks(), $settings['embed_sdks'] );
	}

	/**
	 * Test that load() applies the `liveblog_embed_sdks` filter and registers the
	 * settings filter, rather than enqueuing any scripts directly.
	 *
	 * @covers WPCOM_Liveblog_Entry_Embed_SDKs::load
	 */
	public function test_load_applies_filter_and_registers_settings_hook(): void {
		Filters\expectApplied( 'liveblog_embed_sdks' )->once();
		Filters\expectAdded( 'liveblog_settings' )->once();

		WPCOM_Liveblog_Entry_Embed_SDKs::load();
	}
}
