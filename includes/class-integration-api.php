<?php
/**
 * Public integration contract — REST discovery, hooks, and PHP helpers for other 4wp-* plugins.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

use ForWP\Todo\Rest\Tasks_Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Stable integration surface (extend via filters, not by renaming routes/hooks).
 */
final class Integration_Api {

	/**
	 * Machine-readable registry for external integrations.
	 *
	 * @return array<string, mixed>
	 */
	public static function registry(): array {
		$registry = array(
			'plugin'    => '4wp-todo',
			'version'   => FORWP_TODO_VERSION,
			'namespace' => Tasks_Rest::NAMESPACE,
			'rest_root' => rest_url( Tasks_Rest::NAMESPACE . '/' ),
			'routes'    => self::routes(),
			'hooks'     => self::hooks(),
			'functions' => self::functions(),
			'meta_keys' => self::meta_keys(),
			'caps'      => array(
				Settings::ACCESS_CAP => __( 'Dynamic capability — granted when visibility rules allow TODO access.', '4wp-todo' ),
			),
		);

		/**
		 * Filter the public integration registry.
		 *
		 * @param array<string, mixed> $registry Discovery payload.
		 */
		return apply_filters( 'forwp_todo_integration_registry', $registry );
	}

	/**
	 * Runtime context for the active or requested user.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return array<string, mixed>
	 */
	public static function context( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		$settings = Settings::get();
		$payload  = array(
			'user_id'              => $user_id,
			'can_access'           => $user_id > 0 ? Settings::user_can_access_todo( $user_id ) : false,
			'can_view_all_tasks'   => $user_id > 0 ? Settings::user_can_view_all_tasks( $user_id ) : false,
			'can_manage_settings'  => current_user_can( 'manage_options' ),
			'features'             => array(
				'frontend_overlay' => (bool) $settings['frontend_enabled'],
				'admin_bar_todo'   => (bool) $settings['show_admin_bar_todo'],
				'hide_admin_bar'   => (bool) $settings['hide_admin_bar'],
			),
			'visibility_configured' => ! empty( $settings['visibility_rules'] ),
		);

		/**
		 * Filter integration context for the current request.
		 *
		 * @param array<string, mixed> $payload Context payload.
		 * @param int                  $user_id User ID.
		 */
		return apply_filters( 'forwp_todo_integration_context', $payload, $user_id );
	}

	/**
	 * @return list<array{method: string, path: string, scope: string, description: string}>
	 */
	private static function routes(): array {
		return array(
			array(
				'method'      => 'GET',
				'path'        => '/integration',
				'scope'       => 'public',
				'description' => __( 'Integration discovery (routes, hooks, functions).', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/integration/context',
				'scope'       => 'auth',
				'description' => __( 'Current user access and feature flags.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/access/check',
				'scope'       => 'auth',
				'description' => __( 'Check TODO access for a user (self or manage_options).', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/tasks',
				'scope'       => 'todo',
				'description' => __( 'List root notes with filters.', '4wp-todo' ),
			),
			array(
				'method'      => 'POST',
				'path'        => '/tasks',
				'scope'       => 'todo',
				'description' => __( 'Create a root note.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/tasks/{id}',
				'scope'       => 'todo',
				'description' => __( 'Single task (via list/update response shape).', '4wp-todo' ),
			),
			array(
				'method'      => 'POST',
				'path'        => '/tasks/{id}',
				'scope'       => 'todo',
				'description' => __( 'Update a root note.', '4wp-todo' ),
			),
			array(
				'method'      => 'DELETE',
				'path'        => '/tasks/{id}',
				'scope'       => 'todo',
				'description' => __( 'Delete a root note and its replies.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/tasks/{id}/thread',
				'scope'       => 'todo',
				'description' => __( 'Task with reply thread.', '4wp-todo' ),
			),
			array(
				'method'      => 'POST',
				'path'        => '/tasks/{id}/replies',
				'scope'       => 'todo',
				'description' => __( 'Add a reply to a root note.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/meta',
				'scope'       => 'todo',
				'description' => __( 'Taxonomy, categories, workflows, users, posts.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/stats',
				'scope'       => 'todo',
				'description' => __( 'Open/closed note counts.', '4wp-todo' ),
			),
			array(
				'method'      => 'GET',
				'path'        => '/settings',
				'scope'       => 'manage_options',
				'description' => __( 'Read plugin settings and access catalog.', '4wp-todo' ),
			),
			array(
				'method'      => 'POST',
				'path'        => '/settings',
				'scope'       => 'manage_options',
				'description' => __( 'Update plugin settings.', '4wp-todo' ),
			),
		);
	}

	/**
	 * @return array{actions: array<string, array<string, mixed>>, filters: array<string, array<string, mixed>>}
	 */
	private static function hooks(): array {
		return array(
			'actions' => array(
				'forwp_todo_create_task'  => array(
					'args'        => array( 'args' ),
					'description' => __( 'Programmatic task creation (same args as forwp_todo_create_task).', '4wp-todo' ),
				),
				'forwp_todo_task_created' => array(
					'args'        => array( 'task_id', 'args' ),
					'description' => __( 'Fires after a root note is created.', '4wp-todo' ),
				),
			),
			'filters' => array(
				'forwp_todo_create_task_args'   => array(
					'args'        => array( 'args' ),
					'description' => __( 'Adjust args before task creation.', '4wp-todo' ),
				),
				'forwp_todo_user_can_access'    => array(
					'args'        => array( 'allowed', 'user_id' ),
					'description' => __( 'Extend or override TODO access for a user.', '4wp-todo' ),
				),
				'forwp_todo_hide_admin_bar'     => array(
					'args'        => array( 'hide' ),
					'description' => __( 'Whether to hide WP admin bar on frontend overlay.', '4wp-todo' ),
				),
				'forwp_todo_integration_registry' => array(
					'args'        => array( 'registry' ),
					'description' => __( 'Extend public integration discovery payload.', '4wp-todo' ),
				),
				'forwp_todo_integration_context'  => array(
					'args'        => array( 'payload', 'user_id' ),
					'description' => __( 'Extend per-user integration context.', '4wp-todo' ),
				),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function functions(): array {
		return array(
			'forwp_todo_create_task'           => __( 'Create a root note; returns comment ID or 0.', '4wp-todo' ),
			'forwp_todo_get_task'              => __( 'Get normalized task payload by comment ID.', '4wp-todo' ),
			'forwp_todo_user_can_access'       => __( 'Whether a user may use TODO (lists, overlay, REST).', '4wp-todo' ),
			'forwp_todo_get_integration_registry' => __( 'PHP mirror of GET /integration.', '4wp-todo' ),
			'forwp_todo_get_integration_context'  => __( 'PHP mirror of GET /integration/context.', '4wp-todo' ),
		);
	}

	/**
	 * @return list<string>
	 */
	private static function meta_keys(): array {
		return array(
			Task_Repository::META_CATEGORY,
			Task_Repository::META_ASSIGNEE,
			Task_Repository::META_CONTEXT_URL,
			Task_Repository::META_DUE_DATE,
			Task_Repository::META_SOURCE,
			Task_Repository::META_VIEWPORT,
			Task_Repository::META_SELECTOR,
			Task_Repository::META_ANCHOR,
			Task_Workflow::META_KEY,
			Task_Status::CORE_META,
		);
	}
}
