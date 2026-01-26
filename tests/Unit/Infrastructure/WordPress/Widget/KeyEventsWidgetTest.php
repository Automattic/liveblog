<?php
/**
 * Unit tests for KeyEventsWidget.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\WordPress\Widget
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\WordPress\Widget;

use Automattic\Liveblog\Infrastructure\WordPress\Widget\KeyEventsWidget;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * KeyEventsWidget unit test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\WordPress\Widget\KeyEventsWidget
 */
final class KeyEventsWidgetTest extends TestCase {

	/**
	 * Test widget class exists and extends WP_Widget.
	 */
	public function test_class_exists(): void {
		$this->assertTrue( class_exists( KeyEventsWidget::class ) );
	}

	/**
	 * Test widget has no-argument constructor (required by WordPress).
	 */
	public function test_constructor_has_no_required_parameters(): void {
		$reflection  = new \ReflectionClass( KeyEventsWidget::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor );
		$this->assertCount( 0, $constructor->getParameters() );
	}

	/**
	 * Test widget has the required WP_Widget methods.
	 */
	public function test_has_widget_methods(): void {
		$this->assertTrue( method_exists( KeyEventsWidget::class, 'widget' ) );
		$this->assertTrue( method_exists( KeyEventsWidget::class, 'form' ) );
		$this->assertTrue( method_exists( KeyEventsWidget::class, 'update' ) );
	}

	/**
	 * Test widget method has correct signature.
	 */
	public function test_widget_method_signature(): void {
		$reflection = new \ReflectionMethod( KeyEventsWidget::class, 'widget' );
		$params     = $reflection->getParameters();

		$this->assertCount( 2, $params );
		$this->assertSame( 'args', $params[0]->getName() );
		$this->assertSame( 'instance', $params[1]->getName() );
	}

	/**
	 * Test update method returns array.
	 */
	public function test_update_method_return_type(): void {
		$reflection = new \ReflectionMethod( KeyEventsWidget::class, 'update' );

		$this->assertSame( 'array', $reflection->getReturnType()->getName() );
	}
}
