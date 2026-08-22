<?php
/**
 * @package ForWP\Todo
 */

declare( strict_types=1 );

namespace ForWP\Todo\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ForWP\Todo\Integration_Api;
use PHPUnit\Framework\TestCase;

/**
 * Integration API registry tests.
 */
class Integration_ApiTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'rest_url' )->returnArg( 1 );
		Functions\when( 'apply_filters' )->returnArg( 1 );

		if ( ! defined( 'FORWP_TODO_VERSION' ) ) {
			define( 'FORWP_TODO_VERSION', '0.3.0' );
		}
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_registry_includes_integration_routes(): void {
		$registry = Integration_Api::registry();

		$this->assertSame( '4wp-todo', $registry['plugin'] );
		$this->assertSame( 'forwp-todo/v1', $registry['namespace'] );
		$this->assertNotEmpty( $registry['routes'] );
		$this->assertArrayHasKey( 'forwp_todo_create_task', $registry['functions'] );

		$paths = array_column( $registry['routes'], 'path' );
		$this->assertContains( '/integration', $paths );
		$this->assertContains( '/integration/context', $paths );
		$this->assertContains( '/access/check', $paths );
	}
}
