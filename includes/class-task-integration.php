<?php
/**
 * Hook bridge for programmatic task creation.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Wires forwp_todo_create_task action to repository.
 */
final class Task_Integration {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'forwp_todo_create_task', array( __CLASS__, 'handle_create' ), 10, 1 );
	}

	/**
	 * @param array<string, mixed> $args Task args.
	 * @return void
	 */
	public static function handle_create( array $args ): void {
		forwp_todo_create_task( $args );
	}
}
