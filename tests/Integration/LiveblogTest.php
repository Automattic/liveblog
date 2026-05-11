<?php
/**
 * Integration tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Application\Config\LiveblogConfiguration;
use Automattic\Liveblog\Domain\Entity\LiveblogPost;
use Automattic\Liveblog\Tests\Integration\IntegrationTestCase;

/**
 * Liveblog integration test case.
 *
 * Tests taxonomy-based liveblog state management.
 */
final class LiveblogTest extends IntegrationTestCase {

	/**
	 * Test that liveblog state is managed via taxonomy, not post meta.
	 */
	public function test_liveblog_state_uses_taxonomy_not_meta(): void {
		$post_id  = self::factory()->post->create();
		$liveblog = LiveblogPost::from_id( $post_id );

		$this->assertNotNull( $liveblog );
		$this->assertFalse( $liveblog->is_liveblog() );

		// Enable via taxonomy.
		$liveblog->enable();
		$this->assertTrue( $liveblog->is_enabled() );
		$this->assertTrue( $liveblog->is_liveblog() );

		// Archive via taxonomy.
		$liveblog->archive();
		$this->assertTrue( $liveblog->is_archived() );
		$this->assertTrue( $liveblog->is_liveblog() );

		// Disable via taxonomy.
		$liveblog->disable();
		$this->assertFalse( $liveblog->is_liveblog() );
		$this->assertEquals( LiveblogConfiguration::STATE_DISABLED, $liveblog->state() );
	}
}
