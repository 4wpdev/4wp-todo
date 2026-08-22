<?php
/**
 * Public integration helpers (global namespace for cross-plugin hooks).
 *
 * @package ForWP\Todo
 */

defined( 'ABSPATH' ) || exit;

use ForWP\Todo\Task_Repository;

/**
 * Create a todo task (integration entry point for other 4wp-* plugins).
 *
 * @param array<string, mixed> $args {
 *     @type string $title       Required task summary.
 *     @type string $content     Optional long description.
 *     @type string $category    qa|dev|seo|content|client
 *     @type string $status      open|done (default open)
 *     @type int    $assignee    User ID (0 = unassigned)
 *     @type int    $post_id     Related post ID
 *     @type string $context_url Page URL where the note was created
 *     @type string $due_date    Y-m-d optional
 *     @type string $source      manual|integration (default manual)
 *     @type string $viewport    desktop|mobile (optional)
 * }
 * @return int Task post ID, or 0 on failure.
 */
function forwp_todo_create_task( array $args ): int {
	$args = apply_filters( 'forwp_todo_create_task_args', $args );

	$task_id = Task_Repository::create( $args );

	if ( $task_id > 0 ) {
		/**
		 * Fires after a task is created.
		 *
		 * @param int                  $task_id New task post ID.
		 * @param array<string, mixed> $args    Sanitized args used for creation.
		 */
		do_action( 'forwp_todo_task_created', $task_id, $args );
	}

	return $task_id;
}

/**
 * Whether a user may use TODO (overlay, lists, REST tasks).
 *
 * @param int $user_id User ID (0 = current user).
 * @return bool
 */
function forwp_todo_user_can_access( int $user_id = 0 ): bool {
	return \ForWP\Todo\Settings::user_can_access_todo( $user_id );
}

/**
 * Whether the user may view every task (not only authored/assigned).
 *
 * @param int $user_id User ID (0 = current user).
 * @return bool
 */
function forwp_todo_user_can_view_all_tasks( int $user_id = 0 ): bool {
	return \ForWP\Todo\Settings::user_can_view_all_tasks( $user_id );
}

/**
 * URL for the full tasks list (admin Lists or public tasks page).
 *
 * @param int $user_id User ID (0 = current user).
 * @return string
 */
function forwp_todo_all_tasks_url( int $user_id = 0 ): string {
	return \ForWP\Todo\Settings::resolve_all_tasks_url( $user_id );
}

/**
 * Integration discovery payload (routes, hooks, functions).
 *
 * @return array<string, mixed>
 */
function forwp_todo_get_integration_registry(): array {
	return \ForWP\Todo\Integration_Api::registry();
}

/**
 * Per-user integration context (access + feature flags).
 *
 * @param int $user_id User ID (0 = current user).
 * @return array<string, mixed>
 */
function forwp_todo_get_integration_context( int $user_id = 0 ): array {
	return \ForWP\Todo\Integration_Api::context( $user_id );
}

/**
 * Normalized root note payload.
 *
 * @param int $task_id Note comment ID.
 * @return array<string, mixed>
 */
function forwp_todo_get_task( int $task_id ): array {
	if ( $task_id <= 0 || ! Task_Repository::is_root_note( $task_id ) ) {
		return array();
	}

	return Task_Repository::get_task_data( $task_id );
}

/**
 * Plugin asset URL relative to the current site origin (Local often uses a non-default port).
 *
 * @param string $relative_path Path under the plugin root, e.g. build/admin/index.js.
 * @return string
 */
function forwp_todo_asset_url( string $relative_path ): string {
	$url      = plugins_url( ltrim( $relative_path, '/' ), FORWP_TODO_FILE );
	$relative = wp_make_link_relative( $url );

	return is_string( $relative ) && '' !== $relative ? $relative : $url;
}
