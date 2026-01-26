<?php
/**
 * Unit tests for SocketioManager.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\SocketIO
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\SocketIO;

use Automattic\Liveblog\Infrastructure\SocketIO\SocketioManager;
use Automattic\Liveblog\Infrastructure\WordPress\TemplateRenderer;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * SocketioManager unit test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\SocketIO\SocketioManager
 */
final class SocketioManagerTest extends TestCase {

	/**
	 * Test class exists.
	 */
	public function test_class_exists(): void {
		$this->assertTrue( class_exists( SocketioManager::class ) );
	}

	/**
	 * Test constructor requires TemplateRenderer.
	 */
	public function test_constructor_requires_template_renderer(): void {
		$reflection  = new \ReflectionClass( SocketioManager::class );
		$constructor = $reflection->getConstructor();
		$params      = $constructor->getParameters();

		$this->assertCount( 1, $params );
		$this->assertSame( 'template_renderer', $params[0]->getName() );
		$this->assertSame( TemplateRenderer::class, $params[0]->getType()->getName() );
	}

	/**
	 * Test manager has required public methods.
	 */
	public function test_has_public_methods(): void {
		$this->assertTrue( method_exists( SocketioManager::class, 'initialize' ) );
		$this->assertTrue( method_exists( SocketioManager::class, 'is_enabled' ) );
		$this->assertTrue( method_exists( SocketioManager::class, 'is_connected' ) );
		$this->assertTrue( method_exists( SocketioManager::class, 'emit' ) );
		$this->assertTrue( method_exists( SocketioManager::class, 'get_url' ) );
		$this->assertTrue( method_exists( SocketioManager::class, 'enqueue_scripts' ) );
	}

	/**
	 * Test is_enabled returns bool.
	 */
	public function test_is_enabled_return_type(): void {
		$reflection = new \ReflectionMethod( SocketioManager::class, 'is_enabled' );

		$this->assertSame( 'bool', $reflection->getReturnType()->getName() );
	}

	/**
	 * Test is_connected returns bool.
	 */
	public function test_is_connected_return_type(): void {
		$reflection = new \ReflectionMethod( SocketioManager::class, 'is_connected' );

		$this->assertSame( 'bool', $reflection->getReturnType()->getName() );
	}

	/**
	 * Test get_url returns string.
	 */
	public function test_get_url_return_type(): void {
		$reflection = new \ReflectionMethod( SocketioManager::class, 'get_url' );

		$this->assertSame( 'string', $reflection->getReturnType()->getName() );
	}

	/**
	 * Test class is marked final.
	 */
	public function test_class_is_final(): void {
		$reflection = new \ReflectionClass( SocketioManager::class );

		$this->assertTrue( $reflection->isFinal() );
	}
}
