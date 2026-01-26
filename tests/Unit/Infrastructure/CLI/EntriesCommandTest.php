<?php
/**
 * Unit tests for EntriesCommand.
 *
 * @package Automattic\Liveblog\Tests\Unit\Infrastructure\CLI
 */

declare( strict_types=1 );

namespace Automattic\Liveblog\Tests\Unit\Infrastructure\CLI;

use Automattic\Liveblog\Application\Service\EntryQueryService;
use Automattic\Liveblog\Infrastructure\CLI\EntriesCommand;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * EntriesCommand unit test case.
 *
 * @covers \Automattic\Liveblog\Infrastructure\CLI\EntriesCommand
 */
final class EntriesCommandTest extends TestCase {

	/**
	 * Test command requires service in constructor.
	 */
	public function test_constructor_requires_service(): void {
		$reflection  = new \ReflectionClass( EntriesCommand::class );
		$constructor = $reflection->getConstructor();
		$params      = $constructor->getParameters();

		$this->assertCount( 1, $params );
		$this->assertSame( 'entry_query_service', $params[0]->getName() );
		$this->assertSame( EntryQueryService::class, $params[0]->getType()->getName() );
	}

	/**
	 * Test command has invoke method with correct signature.
	 */
	public function test_has_invoke_method(): void {
		$this->assertTrue( method_exists( EntriesCommand::class, '__invoke' ) );

		$reflection = new \ReflectionMethod( EntriesCommand::class, '__invoke' );
		$params     = $reflection->getParameters();

		$this->assertCount( 2, $params );
		$this->assertSame( 'args', $params[0]->getName() );
		$this->assertSame( 'assoc_args', $params[1]->getName() );
	}
}
