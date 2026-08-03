<?php
/**
 * Integration tests for the main Liveblog class.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;
use WPCOM_Liveblog;

/**
 * Liveblog integration test case.
 */
final class LiveblogTest extends TestCase {

	/**
	 * Reset the shared static post context mutated by the password-gate tests so
	 * it cannot bleed into other cases.
	 */
	public function tear_down(): void {
		WPCOM_Liveblog::$post_id = null;
		parent::tear_down();
	}

	/**
	 * Test that liveblog meta is protected.
	 */
	public function test_protected_liveblog_meta_should_return_true(): void {
		$this->assertTrue( is_protected_meta( WPCOM_Liveblog::KEY ) );
	}

	/**
	 * An Author who owns the post is permitted to edit its liveblog.
	 */
	public function test_current_user_can_edit_liveblog_for_post_allows_post_owner(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		wp_set_current_user( $author_id );

		$this->assertTrue( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );
	}

	/**
	 * An Author who does not own the post cannot edit its liveblog, even when
	 * they hold a global capability such as `publish_posts`.
	 */
	public function test_current_user_can_edit_liveblog_for_post_denies_non_owner_author(): void {
		$owner_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $owner_id ) );
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		wp_set_current_user( $author_id );

		$this->assertFalse( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );
	}

	/**
	 * Editors hold `edit_others_posts` and may edit any post's liveblog.
	 */
	public function test_current_user_can_edit_liveblog_for_post_allows_editor_on_others_post(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor_id );

		$this->assertTrue( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );
	}

	/**
	 * The check is capability-driven, not role-driven: a user without
	 * `edit_others_posts` is denied write access to another user's liveblog,
	 * even if they hold the Editor role by name.
	 *
	 * This protects against drift if a site customises the default role caps —
	 * the fix follows whatever WordPress maps `edit_post` to in the current
	 * environment, rather than hard-coding role names.
	 */
	public function test_current_user_can_edit_liveblog_for_post_follows_capability_not_role(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor_id );

		// Baseline: a default Editor can edit another user's post.
		$this->assertTrue( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );

		// Strip `edit_others_posts` from this user via the user_has_cap filter.
		// This is per-request and does not mutate the shared role definition.
		$strip_cap = static function ( $allcaps ) {
			unset( $allcaps['edit_others_posts'] );
			return $allcaps;
		};
		add_filter( 'user_has_cap', $strip_cap );

		try {
			$this->assertFalse( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );
		} finally {
			remove_filter( 'user_has_cap', $strip_cap );
		}
	}

	/**
	 * Anonymous callers cannot edit liveblog content.
	 */
	public function test_current_user_can_edit_liveblog_for_post_denies_anonymous(): void {
		$post_id = self::factory()->post->create();

		wp_set_current_user( 0 );

		$this->assertFalse( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id ) );
	}

	/**
	 * A non-existent post cannot be edited regardless of capability.
	 */
	public function test_current_user_can_edit_liveblog_for_post_denies_missing_post(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertFalse( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( 0 ) );
		$this->assertFalse( WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( 999999 ) );
	}

	/**
	 * The `liveblog_current_user_can_edit_liveblog` filter can deny an otherwise
	 * permitted caller, preserving the existing extension point.
	 */
	public function test_current_user_can_edit_liveblog_for_post_filter_can_deny(): void {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post_id   = self::factory()->post->create();

		wp_set_current_user( $editor_id );

		add_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );
		$result = WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id );
		remove_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );

		$this->assertFalse( $result );
	}

	/**
	 * A password-protected post must not disclose its liveblog entries through the
	 * legacy AJAX/permalink JSON read endpoints until the password is satisfied,
	 * while the self-authorising write endpoints stay reachable so authoring a
	 * protected post's liveblog still works.
	 *
	 * Covers GHSA-w34c-54x5-vm9p (CWE-862 / CWE-200, legacy read-path disclosure).
	 */
	public function test_password_gate_denies_legacy_reads_for_password_protected_post(): void {
		WPCOM_Liveblog::$post_id = $this->create_liveblog_post( array( 'post_password' => 'secret-w34c' ) );

		foreach ( array( 'ajax_entries_between', 'ajax_single_entry', 'ajax_lazyload_entries', 'ajax_unknown' ) as $method ) {
			$this->assertFalse(
				WPCOM_Liveblog::ajax_request_passes_password_gate( $method ),
				sprintf( '%s must be blocked on a password-protected post', $method )
			);
		}

		foreach ( array( 'ajax_crud_entry', 'ajax_preview_entry' ) as $method ) {
			$this->assertTrue(
				WPCOM_Liveblog::ajax_request_passes_password_gate( $method ),
				sprintf( '%s self-authorises and must remain reachable', $method )
			);
		}
	}

	/**
	 * Once the post password requirement is satisfied for the request, the legacy
	 * read endpoints are permitted again. The `post_password_required` filter
	 * stands in for a visitor who has supplied the correct password (the same
	 * mechanism WordPress core consults), keeping the test independent of
	 * cookie-hashing internals.
	 */
	public function test_password_gate_allows_legacy_reads_once_password_satisfied(): void {
		WPCOM_Liveblog::$post_id = $this->create_liveblog_post( array( 'post_password' => 'secret-w34c' ) );

		add_filter( 'post_password_required', '__return_false' );

		try {
			$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_entries_between' ) );
			$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_single_entry' ) );
			$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_lazyload_entries' ) );
		} finally {
			remove_filter( 'post_password_required', '__return_false' );
		}
	}

	/**
	 * A liveblog post with no password is unaffected: the legacy read endpoints
	 * remain reachable, so the fix does not regress ordinary public liveblogs.
	 */
	public function test_password_gate_allows_legacy_reads_for_unprotected_post(): void {
		WPCOM_Liveblog::$post_id = $this->create_liveblog_post();

		$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_entries_between' ) );
		$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_single_entry' ) );
		$this->assertTrue( WPCOM_Liveblog::ajax_request_passes_password_gate( 'ajax_lazyload_entries' ) );
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
}
