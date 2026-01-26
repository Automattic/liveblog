<?php
/**
 * Unit tests for FixArchiveCommand.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\ArchiveRepairServiceInterface;
use Automattic\Liveblog\Infrastructure\CLI\FixArchiveCommand;
use Mockery;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * FixArchiveCommand unit test case.
 *
 * Tests that the command has correct constructor signature and dependencies.
 * Full CLI behaviour testing is done via integration tests since WP_CLI
 * static methods cannot be mocked with Brain Monkey.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\FixArchiveCommand
 */
final class FixArchiveCommandTest extends TestCase {

	/**
	 * Test command accepts service via constructor.
	 */
	public function test_constructor_accepts_service(): void {
		$service = Mockery::mock( ArchiveRepairServiceInterface::class );
		$command = new FixArchiveCommand( $service );

		$this->assertInstanceOf( FixArchiveCommand::class, $command );
	}

	/**
	 * Test command has invoke method.
	 */
	public function test_has_invoke_method(): void {
		$service = Mockery::mock( ArchiveRepairServiceInterface::class );
		$command = new FixArchiveCommand( $service );

		$this->assertTrue( method_exists( $command, '__invoke' ) );
	}

	/**
	 * Test invoke method signature accepts args.
	 */
	public function test_invoke_accepts_args_and_assoc_args(): void {
		$service = Mockery::mock( ArchiveRepairServiceInterface::class );
		$command = new FixArchiveCommand( $service );

		$reflection = new \ReflectionMethod( $command, '__invoke' );
		$params     = $reflection->getParameters();

		$this->assertCount( 2, $params );
		$this->assertSame( 'args', $params[0]->getName() );
		$this->assertSame( 'assoc_args', $params[1]->getName() );
	}
}
