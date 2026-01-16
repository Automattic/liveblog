<?php
/**
 * Unit tests for Author value object.
 *
 * @package Automattic\Liveblog\Tests\Unit\Domain\ValueObject
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Domain\ValueObject;

use Automattic\Liveblog\Domain\ValueObject\Author;
use Brain\Monkey\Functions;
use Mockery;
use WP_Comment;
use WP_User;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Author unit test case.
 *
 * @covers \Automattic\Liveblog\Domain\ValueObject\Author
 */
final class AuthorTest extends TestCase {

	/**
	 * Test from_user creates Author from WP_User.
	 */
	public function test_from_user(): void {
		$user                = Mockery::mock( WP_User::class );
		$user->ID            = 42;
		$user->display_name  = 'John Doe';
		$user->user_email    = 'john@example.com';
		$user->user_url      = 'https://example.com';
		$user->user_nicename = 'john-doe';

		$author = Author::from_user( $user );

		$this->assertSame( 42, $author->id() );
		$this->assertSame( 'John Doe', $author->name() );
		$this->assertSame( 'john@example.com', $author->email() );
		$this->assertSame( 'https://example.com', $author->url() );
		$this->assertSame( 'john-doe', $author->key() );
	}

	/**
	 * Test from_user_id creates Author when user exists.
	 */
	public function test_from_user_id_when_user_exists(): void {
		$user                = Mockery::mock( WP_User::class );
		$user->ID            = 42;
		$user->display_name  = 'Jane Doe';
		$user->user_email    = 'jane@example.com';
		$user->user_url      = '';
		$user->user_nicename = 'jane-doe';

		Functions\expect( 'get_userdata' )
			->once()
			->with( 42 )
			->andReturn( $user );

		$author = Author::from_user_id( 42 );

		$this->assertSame( 42, $author->id() );
		$this->assertSame( 'Jane Doe', $author->name() );
	}

	/**
	 * Test from_user_id returns anonymous when user does not exist.
	 */
	public function test_from_user_id_when_user_not_found(): void {
		Functions\expect( 'get_userdata' )
			->once()
			->with( 999 )
			->andReturn( false );

		$author = Author::from_user_id( 999 );

		$this->assertTrue( $author->is_anonymous() );
	}

	/**
	 * Test from_comment creates Author from WP_Comment.
	 */
	public function test_from_comment(): void {
		$comment                       = Mockery::mock( WP_Comment::class );
		$comment->user_id              = 42;
		$comment->comment_author       = 'Commenter';
		$comment->comment_author_email = 'commenter@example.com';
		$comment->comment_author_url   = 'https://commenter.example.com';

		Functions\expect( 'sanitize_title' )
			->once()
			->with( 'Commenter' )
			->andReturn( 'commenter' );

		$author = Author::from_comment( $comment );

		$this->assertSame( 42, $author->id() );
		$this->assertSame( 'Commenter', $author->name() );
		$this->assertSame( 'commenter@example.com', $author->email() );
		$this->assertSame( 'https://commenter.example.com', $author->url() );
		$this->assertSame( 'commenter', $author->key() );
	}

	/**
	 * Test from_comment handles zero user_id as null.
	 */
	public function test_from_comment_with_zero_user_id(): void {
		$comment                       = Mockery::mock( WP_Comment::class );
		$comment->user_id              = 0;
		$comment->comment_author       = 'Guest';
		$comment->comment_author_email = 'guest@example.com';
		$comment->comment_author_url   = '';

		Functions\expect( 'sanitize_title' )
			->once()
			->andReturn( 'guest' );

		$author = Author::from_comment( $comment );

		$this->assertNull( $author->id() );
	}

	/**
	 * Test from_array creates Author from array.
	 */
	public function test_from_array(): void {
		$data = array(
			'id'    => 10,
			'name'  => 'Array Author',
			'email' => 'array@example.com',
			'url'   => 'https://array.example.com',
			'key'   => 'array-author',
		);

		$author = Author::from_array( $data );

		$this->assertSame( 10, $author->id() );
		$this->assertSame( 'Array Author', $author->name() );
		$this->assertSame( 'array@example.com', $author->email() );
		$this->assertSame( 'https://array.example.com', $author->url() );
		$this->assertSame( 'array-author', $author->key() );
	}

	/**
	 * Test from_array handles missing keys.
	 */
	public function test_from_array_with_missing_keys(): void {
		$author = Author::from_array( array( 'name' => 'Partial' ) );

		$this->assertNull( $author->id() );
		$this->assertSame( 'Partial', $author->name() );
		$this->assertSame( '', $author->email() );
		$this->assertSame( '', $author->url() );
		$this->assertSame( '', $author->key() );
	}

	/**
	 * Test anonymous creates anonymous author.
	 */
	public function test_anonymous(): void {
		$author = Author::anonymous();

		$this->assertNull( $author->id() );
		$this->assertSame( '', $author->name() );
		$this->assertSame( '', $author->email() );
		$this->assertSame( '', $author->url() );
		$this->assertSame( '', $author->key() );
		$this->assertTrue( $author->is_anonymous() );
	}

	/**
	 * Test display_name returns name.
	 */
	public function test_display_name(): void {
		$author = Author::from_array( array( 'name' => 'Display Name' ) );

		$this->assertSame( 'Display Name', $author->display_name() );
	}

	/**
	 * Test avatar_url uses email when available.
	 */
	public function test_avatar_url_with_email(): void {
		Functions\expect( 'get_avatar_url' )
			->once()
			->with( 'test@example.com', array( 'size' => 30 ) )
			->andReturn( 'https://example.com/avatar.jpg' );

		$author = Author::from_array(
			array(
				'id'    => 1,
				'email' => 'test@example.com',
			)
		);

		$this->assertSame( 'https://example.com/avatar.jpg', $author->avatar_url() );
	}

	/**
	 * Test avatar_url uses id when no email.
	 */
	public function test_avatar_url_with_id_only(): void {
		Functions\expect( 'get_avatar_url' )
			->once()
			->with( 42, array( 'size' => 50 ) )
			->andReturn( 'https://example.com/avatar-id.jpg' );

		$author = Author::from_array( array( 'id' => 42 ) );

		$this->assertSame( 'https://example.com/avatar-id.jpg', $author->avatar_url( 50 ) );
	}

	/**
	 * Test avatar_url returns empty string for anonymous.
	 */
	public function test_avatar_url_for_anonymous(): void {
		$author = Author::anonymous();

		$this->assertSame( '', $author->avatar_url() );
	}

	/**
	 * Test avatar_html uses email when available.
	 */
	public function test_avatar_html_with_email(): void {
		Functions\expect( 'get_avatar' )
			->once()
			->with( 'test@example.com', 30 )
			->andReturn( '<img src="avatar.jpg" />' );

		$author = Author::from_array(
			array(
				'id'    => 1,
				'email' => 'test@example.com',
			)
		);

		$this->assertSame( '<img src="avatar.jpg" />', $author->avatar_html() );
	}

	/**
	 * Test profile_url returns url when set.
	 */
	public function test_profile_url_with_url(): void {
		$author = Author::from_array( array( 'url' => 'https://author.example.com' ) );

		$this->assertSame( 'https://author.example.com', $author->profile_url() );
	}

	/**
	 * Test profile_url uses author posts URL when no url set.
	 */
	public function test_profile_url_uses_author_posts_url(): void {
		Functions\expect( 'get_author_posts_url' )
			->once()
			->with( 42 )
			->andReturn( 'https://example.com/author/john/' );

		$author = Author::from_array( array( 'id' => 42 ) );

		$this->assertSame( 'https://example.com/author/john/', $author->profile_url() );
	}

	/**
	 * Test profile_url returns empty string for anonymous.
	 */
	public function test_profile_url_for_anonymous(): void {
		$author = Author::anonymous();

		$this->assertSame( '', $author->profile_url() );
	}

	/**
	 * Test is_anonymous returns true for anonymous author.
	 */
	public function test_is_anonymous_true(): void {
		$author = Author::anonymous();

		$this->assertTrue( $author->is_anonymous() );
	}

	/**
	 * Test is_anonymous returns false for author with id.
	 */
	public function test_is_anonymous_false_with_id(): void {
		$author = Author::from_array( array( 'id' => 1 ) );

		$this->assertFalse( $author->is_anonymous() );
	}

	/**
	 * Test is_anonymous returns false for author with name.
	 */
	public function test_is_anonymous_false_with_name(): void {
		$author = Author::from_array( array( 'name' => 'Named' ) );

		$this->assertFalse( $author->is_anonymous() );
	}

	/**
	 * Test to_array returns expected format.
	 */
	public function test_to_array(): void {
		Functions\expect( 'get_avatar' )
			->once()
			->with( 'test@example.com', 30 )
			->andReturn( '<img />' );

		$author = Author::from_array(
			array(
				'id'    => 42,
				'key'   => 'john-doe',
				'name'  => 'John Doe',
				'email' => 'test@example.com',
			)
		);

		$this->assertSame(
			array(
				'id'     => 42,
				'key'    => 'john-doe',
				'name'   => 'John Doe',
				'avatar' => '<img />',
			),
			$author->to_array()
		);
	}

	/**
	 * Test to_schema returns Person object.
	 */
	public function test_to_schema_basic(): void {
		$author = Author::from_array(
			array(
				'name' => 'Schema Author',
			)
		);

		$schema = $author->to_schema();

		$this->assertSame( 'Person', $schema->{'@type'} );
		$this->assertSame( 'Schema Author', $schema->name );
	}

	/**
	 * Test to_schema includes URL when available.
	 */
	public function test_to_schema_with_url(): void {
		$author = Author::from_array(
			array(
				'name' => 'Schema Author',
				'url'  => 'https://author.example.com',
			)
		);

		$schema = $author->to_schema();

		$this->assertSame( 'https://author.example.com', $schema->url );
	}

	/**
	 * Test equals returns true for same data.
	 */
	public function test_equals_true(): void {
		$author1 = Author::from_array(
			array(
				'id'    => 1,
				'name'  => 'Same',
				'email' => 'same@example.com',
			)
		);
		$author2 = Author::from_array(
			array(
				'id'    => 1,
				'name'  => 'Same',
				'email' => 'same@example.com',
			)
		);

		$this->assertTrue( $author1->equals( $author2 ) );
	}

	/**
	 * Test equals returns false for different data.
	 */
	public function test_equals_false(): void {
		$author1 = Author::from_array(
			array(
				'id'   => 1,
				'name' => 'One',
			)
		);
		$author2 = Author::from_array(
			array(
				'id'   => 2,
				'name' => 'Two',
			)
		);

		$this->assertFalse( $author1->equals( $author2 ) );
	}
}
