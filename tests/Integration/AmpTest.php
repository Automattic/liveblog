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
	 * AMP support stays on unless a site turns it off, so existing AMP
	 * liveblogs keep working after an upgrade.
	 */
	public function test_is_enabled_by_default(): void {
		$this->assertTrue( WPCOM_Liveblog_AMP::is_enabled() );
	}

	/**
	 * The liveblog_enable_amp filter turns AMP support off.
	 */
	public function test_is_enabled_can_be_turned_off_with_filter(): void {
		add_filter( 'liveblog_enable_amp', '__return_false' );

		$this->assertFalse( WPCOM_Liveblog_AMP::is_enabled() );
	}

	/**
	 * With AMP support off, a liveblog post is opted out of AMP so readers
	 * get the standard Liveblog instead of an empty container. There is no
	 * singular query here, as when the AMP plugin checks a post over REST.
	 */
	public function test_skip_liveblog_post_skips_liveblog_when_disabled(): void {
		$post_id = $this->create_liveblog_post();
		add_filter( 'liveblog_enable_amp', '__return_false' );

		$this->assertTrue( WPCOM_Liveblog_AMP::skip_liveblog_post( false, $post_id ) );
	}

	/**
	 * With AMP support on, the AMP skip value is passed through untouched.
	 */
	public function test_skip_liveblog_post_keeps_value_when_enabled(): void {
		$post_id = $this->create_liveblog_post();

		$this->assertFalse( WPCOM_Liveblog_AMP::skip_liveblog_post( false, $post_id ) );
		$this->assertTrue( WPCOM_Liveblog_AMP::skip_liveblog_post( true, $post_id ) );
	}

	/**
	 * Posts without a liveblog are left alone, even with AMP support off.
	 */
	public function test_skip_liveblog_post_ignores_non_liveblog_post(): void {
		$post_id = self::factory()->post->create();
		add_filter( 'liveblog_enable_amp', '__return_false' );

		$this->assertFalse( WPCOM_Liveblog_AMP::skip_liveblog_post( false, $post_id ) );
	}

	/**
	 * With AMP support off, setup() bails before touching any hooks, so
	 * wpautop stays on the_content and the AMP render is not added.
	 */
	public function test_setup_bails_when_disabled(): void {
		add_filter( 'liveblog_enable_amp', '__return_false' );

		WPCOM_Liveblog_AMP::setup();

		$this->assertSame( 10, has_filter( 'the_content', 'wpautop' ) );
		$this->assertFalse( has_filter( 'the_content', array( 'WPCOM_Liveblog_AMP', 'append_liveblog_to_content' ) ) );
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
