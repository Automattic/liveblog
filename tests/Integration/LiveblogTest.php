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
}
