<?php
/**
 * @package ForWP\Todo
 */

declare( strict_types=1 );

namespace ForWP\Todo\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ForWP\Todo\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Visibility rule sanitization tests.
 */
class Settings_VisibilityTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'translate_user_role' )->returnArg( 1 );

		Functions\when( 'wp_roles' )->justReturn(
			new class() {
				/**
				 * @var array<string, array{name: string}>
				 */
				public $roles = array(
					'administrator' => array( 'name' => 'Administrator' ),
					'shop_manager'  => array( 'name' => 'Shop manager' ),
				);
			}
		);
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
	public function test_sanitize_visibility_rules_keeps_role_and_mode(): void {
		Functions\when( 'get_userdata' )->justReturn(
			(object) array(
				'roles' => array( 'administrator' ),
			)
		);

		$rules = Settings::sanitize_visibility_rules(
			array(
				array(
					'role'      => 'administrator',
					'mode'      => 'selected',
					'user_ids'  => array( 3, 3, '7' ),
				),
				array(
					'role'     => 'shop_manager',
					'mode'     => 'all',
					'user_ids' => array(),
				),
				array(
					'role' => 'invalid_role',
					'mode' => 'all',
				),
			)
		);

		$this->assertCount( 2, $rules );
		$this->assertSame( 'administrator', $rules[0]['role'] );
		$this->assertSame( 'selected', $rules[0]['mode'] );
		$this->assertSame( array( 3, 7 ), $rules[0]['user_ids'] );
		$this->assertSame( 'all', $rules[1]['mode'] );
	}

	/**
	 * @return void
	 */
	public function test_user_can_access_todo_respects_role_all_mode(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'visibility_rules' => array(
					array(
						'role'     => 'customer',
						'mode'     => 'all',
						'user_ids' => array(),
					),
				),
			)
		);

		Functions\when( 'get_userdata' )->alias(
			function ( int $user_id ) {
				if ( 42 !== $user_id ) {
					return false;
				}

				return (object) array(
					'ID'    => 42,
					'roles' => array( 'customer' ),
				);
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		$this->assertTrue( Settings::user_can_access_todo( 42 ) );
	}

	/**
	 * @return void
	 */
	public function test_user_can_access_todo_selected_mode_requires_user_id(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'visibility_rules' => array(
					array(
						'role'     => 'customer',
						'mode'     => 'selected',
						'user_ids' => array( 42 ),
					),
				),
			)
		);

		Functions\when( 'get_userdata' )->alias(
			function ( int $user_id ) {
				if ( 42 === $user_id ) {
					return (object) array(
						'ID'    => 42,
						'roles' => array( 'customer' ),
					);
				}

				if ( 99 === $user_id ) {
					return (object) array(
						'ID'    => 99,
						'roles' => array( 'customer' ),
					);
				}

				return false;
			}
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		$this->assertTrue( Settings::user_can_access_todo( 42 ) );
		$this->assertFalse( Settings::user_can_access_todo( 99 ) );
	}

	/**
	 * @return void
	 */
	public function test_sanitize_super_admin_user_ids_requires_manage_options(): void {
		Functions\when( 'get_userdata' )->alias(
			function ( int $user_id ) {
				if ( 1 === $user_id ) {
					return (object) array( 'ID' => 1 );
				}

				if ( 2 === $user_id ) {
					return (object) array( 'ID' => 2 );
				}

				return false;
			}
		);

		Functions\when( 'user_can' )->alias(
			function ( int $user_id, string $cap ) {
				return 1 === $user_id && 'manage_options' === $cap;
			}
		);

		$this->assertSame(
			array( 1 ),
			Settings::sanitize_super_admin_user_ids( array( 1, 2, '3' ) )
		);
	}

	/**
	 * @return void
	 */
	public function test_user_can_view_all_tasks_uses_super_admin_list_when_rules_set(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'visibility_rules'     => array(
					array(
						'role'     => 'customer',
						'mode'     => 'all',
						'user_ids' => array(),
					),
				),
				'super_admin_user_ids' => array( 5 ),
			)
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'user_can' )->justReturn( false );

		$this->assertTrue( Settings::user_can_view_all_tasks( 5 ) );
		$this->assertFalse( Settings::user_can_view_all_tasks( 42 ) );
	}

	/**
	 * @return void
	 */
	public function test_user_can_view_all_tasks_legacy_edit_posts_when_no_rules(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'visibility_rules'     => array(),
				'super_admin_user_ids' => array(),
			)
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'user_can' )->alias(
			function ( int $user_id, string $cap ) {
				return 7 === $user_id && 'edit_posts' === $cap;
			}
		);

		$this->assertTrue( Settings::user_can_view_all_tasks( 7 ) );
	}
}
