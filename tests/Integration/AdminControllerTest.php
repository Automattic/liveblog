<?php
/**
 * Integration tests for AdminController.
 *
 * @package Automattic\Liveblog\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Integration;

use Automattic\Liveblog\Infrastructure\WordPress\AdminController;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * AdminController integration test case.
 *
 * Focused on the post-scoped permission helper used by the
 * `set_liveblog_state_for_post` admin-ajax handler.
 */
final class AdminControllerTest extends TestCase {

	/**
	 * The post owner can edit liveblog state on their own post.
	 */
	public function test_current_user_can_edit_for_post_allows_owner(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		wp_set_current_user( $author_id );

		$this->assertTrue( AdminController::current_user_can_edit_for_post( $post_id ) );
	}

	/**
	 * An author who does not own the post is denied even when they hold
	 * `publish_posts` globally.
	 */
	public function test_current_user_can_edit_for_post_denies_non_owner_author(): void {
		$owner_id    = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id     = self::factory()->post->create( array( 'post_author' => $owner_id ) );
		$attacker_id = self::factory()->user->create( array( 'role' => 'author' ) );

		wp_set_current_user( $attacker_id );

		$this->assertFalse( AdminController::current_user_can_edit_for_post( $post_id ) );
	}

	/**
	 * Editors hold `edit_others_posts` and may edit any post's liveblog.
	 */
	public function test_current_user_can_edit_for_post_allows_editor_on_others_post(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor_id );

		$this->assertTrue( AdminController::current_user_can_edit_for_post( $post_id ) );
	}

	/**
	 * Anonymous callers cannot edit liveblog state.
	 */
	public function test_current_user_can_edit_for_post_denies_anonymous(): void {
		$post_id = self::factory()->post->create();

		wp_set_current_user( 0 );

		$this->assertFalse( AdminController::current_user_can_edit_for_post( $post_id ) );
	}

	/**
	 * A non-existent post cannot be edited regardless of capability.
	 */
	public function test_current_user_can_edit_for_post_denies_missing_post(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertFalse( AdminController::current_user_can_edit_for_post( 0 ) );
		$this->assertFalse( AdminController::current_user_can_edit_for_post( 999999 ) );
	}

	/**
	 * The check is capability-driven, not role-driven: stripping
	 * `edit_others_posts` from an Editor denies write access, even though
	 * the role still nominally exists.
	 */
	public function test_current_user_can_edit_for_post_follows_capability_not_role(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( $editor_id );

		// Baseline: a default Editor can edit another user's post.
		$this->assertTrue( AdminController::current_user_can_edit_for_post( $post_id ) );

		$strip_cap = static function ( $allcaps ) {
			unset( $allcaps['edit_others_posts'] );
			return $allcaps;
		};
		add_filter( 'user_has_cap', $strip_cap );

		try {
			$this->assertFalse( AdminController::current_user_can_edit_for_post( $post_id ) );
		} finally {
			remove_filter( 'user_has_cap', $strip_cap );
		}
	}

	/**
	 * The `liveblog_current_user_can_edit_liveblog` filter still applies and
	 * can deny an otherwise permitted caller.
	 */
	public function test_current_user_can_edit_for_post_filter_can_deny(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );

		wp_set_current_user( $author_id );

		add_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );
		$result = AdminController::current_user_can_edit_for_post( $post_id );
		remove_filter( 'liveblog_current_user_can_edit_liveblog', '__return_false' );

		$this->assertFalse( $result );
	}
}
