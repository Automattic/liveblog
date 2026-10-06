<?php
/**
 * Integration tests for the AMP liveblog render path.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;
use WPCOM_Liveblog_AMP;

/**
 * AMP integration test case.
 */
final class AmpTest extends TestCase {

	/**
	 * Clear the shared global post context set by the render tests.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * The AMP content render path must not append liveblog entries for a
	 * password-protected post until the password is satisfied. Without the guard
	 * the entries are server-rendered straight into the AMP output, even though
	 * WordPress shows the password form for the post body.
	 *
	 * Covers the AMP vector of GHSA-w34c-54x5-vm9p / HackerOne #3784474
	 * (CWE-862 / CWE-200).
	 */
	public function test_append_liveblog_to_content_is_blocked_for_password_protected_post(): void {
		$post_id = $this->create_liveblog_post( array( 'post_password' => 'secret-amp' ) );
		$this->insert_entry( $post_id, '<p>AMP_SECRET_MARKER protected amp entry body</p>' );

		$GLOBALS['post'] = get_post( $post_id );

		$original = '<p>ORIGINAL POST BODY</p>';
		$result   = WPCOM_Liveblog_AMP::append_liveblog_to_content( $original );

		$this->assertStringNotContainsString(
			'AMP_SECRET_MARKER',
			$result,
			'The AMP render must not disclose entry content for a password-protected post.'
		);
		$this->assertSame(
			$original,
			$result,
			'The AMP render must return the content untouched for a password-protected post.'
		);
	}

	/**
	 * Create a published post with liveblog enabled and return its ID.
	 *
	 * @param array<string, mixed> $post_args Optional overrides for the created post.
	 * @return int The new post ID.
	 */
	private function create_liveblog_post( array $post_args = array() ): int {
		$post_id = self::factory()->post->create( $post_args );

		WPCOM_Liveblog::admin_set_liveblog_state_for_post( $post_id, 'enable', array( 'state' => 'enable' ) );

		return $post_id;
	}

	/**
	 * Insert a single liveblog entry (a liveblog-type comment) on a post.
	 *
	 * @param int    $post_id The post to attach the entry to.
	 * @param string $content The entry content.
	 * @return void
	 */
	private function insert_entry( int $post_id, string $content ): void {
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => $content,
				'comment_type'     => WPCOM_Liveblog::KEY,
				'comment_approved' => WPCOM_Liveblog::KEY,
			)
		);
	}
}
