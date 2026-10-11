<?php
/**
 * @package ForWP\Todo
 */

declare( strict_types=1 );

namespace ForWP\Todo\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ForWP\Todo\Task_Repository;
use PHPUnit\Framework\TestCase;

/**
 * GET /meta catalog helpers.
 */
class Task_Repository_MetaCatalogTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Limited TODO users must not receive a site-wide user dump from note catalogs.
	 *
	 * @return void
	 */
	public function test_collect_user_ids_from_notes_includes_viewer_author_and_assignee_only(): void {
		Functions\expect( 'get_comment_meta' )
			->once()
			->with( 11, Task_Repository::META_ASSIGNEE, true )
			->andReturn( 7 );

		$note            = new \stdClass();
		$note->comment_ID = 11;
		$note->user_id    = 3;

		$ids = Task_Repository::collect_user_ids_from_notes( array( $note ), 42 );

		sort( $ids );
		$this->assertSame( array( 3, 7, 42 ), $ids );
	}
}
