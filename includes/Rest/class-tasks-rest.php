<?php
/**
 * REST API for tasks (native notes + 4WP comment meta).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo\Rest;

use ForWP\Todo\Integration_Api;
use ForWP\Todo\Settings;
use ForWP\Todo\Task_Category;
use ForWP\Todo\Task_Repository;
use ForWP\Todo\Task_Status;
use ForWP\Todo\Task_Taxonomy;
use ForWP\Todo\Task_Workflow;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Tasks REST routes — consumed by React admin (apiFetch, no admin-ajax).
 */
final class Tasks_Rest {

	public const NAMESPACE = 'forwp-todo/v1';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_tasks' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => self::list_tasks_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_task' ),
					'permission_callback' => array( __CLASS__, 'can_create_task' ),
					'args'                => self::create_task_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tasks/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'reorder_tasks' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array(
						'scope'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( Task_Repository::class, 'sanitize_sort_scope' ),
						),
						'task_ids' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array(
								'type' => 'integer',
							),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_task' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_task' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array_merge(
						array(
							'id' => array(
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
							),
						),
						self::update_task_args()
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_task' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)/thread',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_thread' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)/replies',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_reply' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
					'args'                => array_merge(
						array(
							'id' => array(
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
							),
						),
						self::create_reply_args()
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_stats' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/meta',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_meta' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'args'                => self::update_settings_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/integration',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_integration' ),
					'permission_callback' => array( __CLASS__, 'can_manage_tasks' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/integration/context',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_integration_context' ),
					'permission_callback' => array( __CLASS__, 'is_logged_in' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/access/check',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'check_access' ),
					'permission_callback' => array( __CLASS__, 'is_logged_in' ),
					'args'                => array(
						'user_id' => array(
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function is_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_integration(): WP_REST_Response {
		return new WP_REST_Response( Integration_Api::registry(), 200 );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_integration_context(): WP_REST_Response {
		return new WP_REST_Response( Integration_Api::context(), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function check_access( WP_REST_Request $request ): WP_REST_Response {
		$user_id = absint( $request->get_param( 'user_id' ) );
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'You cannot check access for other users.', '4wp-todo' ),
				),
				403
			);
		}

		return new WP_REST_Response(
			array(
				'user_id'    => $user_id,
				'can_access' => Settings::user_can_access_todo( $user_id ),
			),
			200
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage_tasks(): bool {
		return Settings::user_can_access_todo();
	}

	/**
	 * POST /tasks — TODO access plus edit_post (or storage post) for the target post ID.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_create_task( WP_REST_Request $request ): bool {
		if ( ! self::can_manage_tasks() ) {
			return false;
		}

		$post_id = Task_Repository::resolve_attachment_post_id(
			array(
				'post_id'         => $request->get_param( 'post_id' ),
				'context_url'     => $request->get_param( 'context_url' ),
				'storage_post_id' => $request->get_param( 'storage_post_id' ),
			)
		);

		if ( $post_id <= 0 ) {
			return true;
		}

		return Task_Repository::user_can_attach_task_to_post( $post_id );
	}

	/**
	 * @return bool
	 */
	public static function can_manage_settings(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array_merge(
				Settings::get(),
				array(
					'catalog' => Settings::get_access_catalog(),
					'pages'   => Settings::get_pages_catalog(),
				)
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$changes = array();

		if ( null !== $request->get_param( 'frontend_enabled' ) ) {
			$changes['frontend_enabled'] = (bool) $request->get_param( 'frontend_enabled' );
		}

		if ( null !== $request->get_param( 'hide_admin_bar' ) ) {
			$changes['hide_admin_bar'] = (bool) $request->get_param( 'hide_admin_bar' );
		}

		if ( null !== $request->get_param( 'show_admin_bar_todo' ) ) {
			$changes['show_admin_bar_todo'] = (bool) $request->get_param( 'show_admin_bar_todo' );
		}

		if ( null !== $request->get_param( 'visibility_rules' ) ) {
			$changes['visibility_rules'] = $request->get_param( 'visibility_rules' );
		}

		if ( null !== $request->get_param( 'super_admin_user_ids' ) ) {
			$changes['super_admin_user_ids'] = $request->get_param( 'super_admin_user_ids' );
		}

		if ( null !== $request->get_param( 'tasks_page_id' ) ) {
			$changes['tasks_page_id'] = $request->get_param( 'tasks_page_id' );
		}

		$updated = Settings::update( $changes );

		return new WP_REST_Response(
			array_merge(
				$updated,
				array(
					'catalog' => Settings::get_access_catalog(),
					'pages'   => Settings::get_pages_catalog(),
				)
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_stats(): WP_REST_Response {
		$query        = array(
			'note_state' => 'open',
			'number'     => 500,
		);
		$closed_query = array(
			'note_state' => 'closed',
			'number'     => 500,
		);

		if ( Settings::user_can_view_all_tasks() ) {
			$open   = Task_Repository::query( $query );
			$closed = Task_Repository::query( $closed_query );
		} else {
			$open   = Task_Repository::query_visible_to_user( get_current_user_id(), $query );
			$closed = Task_Repository::query_visible_to_user( get_current_user_id(), $closed_query );
		}

		return new WP_REST_Response(
			array(
				'total'  => count( $open ) + count( $closed ),
				'open'   => count( $open ),
				'closed' => count( $closed ),
			),
			200
		);
	}

	/**
	 * Return catalog data for Lists and overlay pickers.
	 *
	 * The permission_callback remains TODO access so the UI can load. Users who cannot
	 * view all tasks receive only people and posts from notes they can already see,
	 * not a site-wide user or notes-post dump.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_meta(): WP_REST_Response {
		$posts = Task_Repository::get_posts_with_notes();

		return new WP_REST_Response(
			array(
				'taxonomy'           => Task_Taxonomy::registry(
					array(
						'posts' => $posts,
					)
				),
				'categories'         => Task_Category::choices(),
				'open_workflows'     => Task_Workflow::choices( true ),
				'closed_workflows'   => Task_Workflow::choices( false ),
				'users'              => Task_Repository::get_meta_users(),
				'posts'              => $posts,
				'assignable_posts'   => Task_Repository::get_assignable_posts(),
				'current_user'       => array(
					'id'   => get_current_user_id(),
					'name' => wp_get_current_user()->display_name,
				),
				'can_view_all_tasks' => Settings::user_can_view_all_tasks(),
				'all_tasks_url'      => Settings::resolve_all_tasks_url(),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_tasks( WP_REST_Request $request ): WP_REST_Response {
		$query = array(
			'number' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 50 ) ),
		);

		if ( ! empty( $request['post_id'] ) ) {
			$post_id = absint( $request->get_param( 'post_id' ) );
			if ( $post_id > 0 && ! Task_Repository::user_can_attach_task_to_post( $post_id ) && ! Settings::user_can_view_all_tasks() ) {
				return new WP_REST_Response(
					array(
						'message' => __( 'You cannot list tasks for that post.', '4wp-todo' ),
					),
					403
				);
			}
			$query['post_id'] = $post_id;
		}

		$category = $request->get_param( 'category' );
		if ( is_string( $category ) && '' !== $category ) {
			$query['category'] = Task_Category::sanitize( $category );
		}

		$note_state = $request->get_param( 'note_state' );
		if ( is_string( $note_state ) && in_array( $note_state, array( 'open', 'closed' ), true ) ) {
			$query['note_state'] = $note_state;
		}

		$workflow = $request->get_param( 'workflow' );
		if ( is_string( $workflow ) && '' !== $workflow ) {
			$query['workflow'] = Task_Taxonomy::sanitize_workflow( $workflow );
		}

		$view = $request->get_param( 'view' );
		if ( 'mine' === $view ) {
			$query['assignee'] = get_current_user_id();
		} elseif ( 'authored' === $view ) {
			$query['author'] = get_current_user_id();
		}

		if ( 'tomorrow' === $view ) {
			$query['due_date'] = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		}

		$assignee = $request->get_param( 'assignee' );
		if ( null !== $assignee && 'mine' !== $view && Settings::user_can_view_all_tasks() ) {
			$query['assignee'] = absint( $assignee );
		}

		if ( Settings::user_can_view_all_tasks() ) {
			$comments = Task_Repository::query( $query );
		} else {
			$comments = Task_Repository::query_visible_to_user( get_current_user_id(), $query );
		}

		$items = array_map(
			static function ( $comment ) {
				return Task_Repository::get_task_data( (int) $comment->comment_ID );
			},
			$comments
		);

		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => count( $items ),
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function reorder_tasks( WP_REST_Request $request ): WP_REST_Response {
		$scope    = Task_Repository::sanitize_sort_scope( (string) $request->get_param( 'scope' ) );
		$task_ids = $request->get_param( 'task_ids' );

		if ( '' === $scope || ! is_array( $task_ids ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not reorder tasks.', '4wp-todo' ),
				),
				400
			);
		}

		$sanitized_ids = array();
		foreach ( $task_ids as $task_id ) {
			$task_id = absint( $task_id );
			if ( $task_id <= 0 || ! Task_Repository::is_root_note( $task_id ) ) {
				continue;
			}

			if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
				return new WP_REST_Response(
					array(
						'message' => __( 'Could not reorder tasks.', '4wp-todo' ),
					),
					403
				);
			}

			$sanitized_ids[] = $task_id;
		}

		if ( empty( $sanitized_ids ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not reorder tasks.', '4wp-todo' ),
				),
				400
			);
		}

		Task_Repository::set_sort_orders( $scope, $sanitized_ids );

		$items = array_map(
			static function ( $task_id ) {
				return Task_Repository::get_task_data( (int) $task_id );
			},
			$sanitized_ids
		);

		return new WP_REST_Response(
			array(
				'scope' => $scope,
				'items' => $items,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_task( WP_REST_Request $request ): WP_REST_Response {
		$task_id = absint( $request->get_param( 'id' ) );

		if ( ! Task_Repository::is_root_note( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Note not found.', '4wp-todo' ),
				),
				404
			);
		}

		if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Note not found.', '4wp-todo' ),
				),
				404
			);
		}

		return new WP_REST_Response( Task_Repository::get_task_data( $task_id ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_task( WP_REST_Request $request ): WP_REST_Response {
		$post_id = Task_Repository::resolve_attachment_post_id(
			array(
				'post_id'         => $request->get_param( 'post_id' ),
				'context_url'     => $request->get_param( 'context_url' ),
				'storage_post_id' => $request->get_param( 'storage_post_id' ),
			)
		);

		if ( $post_id > 0 && ! Task_Repository::user_can_attach_task_to_post( $post_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'You cannot create a task on that post.', '4wp-todo' ),
				),
				403
			);
		}

		$task_id = forwp_todo_create_task(
			array(
				'content'      => (string) $request->get_param( 'content' ),
				'category'     => (string) $request->get_param( 'category' ),
				'assignee'     => absint( $request->get_param( 'assignee' ) ),
				'post_id'      => absint( $request->get_param( 'post_id' ) ),
				'context_url'  => (string) $request->get_param( 'context_url' ),
				'admin_screen' => (string) $request->get_param( 'admin_screen' ),
				'selector'     => (string) $request->get_param( 'selector' ),
				'anchor'       => (string) $request->get_param( 'anchor' ),
				'due_date'     => (string) $request->get_param( 'due_date' ),
				'viewport'     => (string) $request->get_param( 'viewport' ),
				'source'       => (string) $request->get_param( 'source' ),
				'workflow'     => (string) $request->get_param( 'workflow' ),
			)
		);

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not create task note.', '4wp-todo' ),
				),
				400
			);
		}

		return new WP_REST_Response( Task_Repository::get_task_data( $task_id ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function update_task( WP_REST_Request $request ): WP_REST_Response {
		$task_id = absint( $request->get_param( 'id' ) );

		if ( ! Task_Repository::is_root_note( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not update task note.', '4wp-todo' ),
				),
				400
			);
		}

		if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not update task note.', '4wp-todo' ),
				),
				403
			);
		}

		$payload = array();

		if ( null !== $request->get_param( 'content' ) ) {
			$payload['content'] = (string) $request->get_param( 'content' );
		}

		if ( null !== $request->get_param( 'note_status' ) ) {
			$status = sanitize_key( (string) $request->get_param( 'note_status' ) );
			if ( in_array( $status, array( Task_Status::REOPEN, Task_Status::RESOLVED ), true ) ) {
				$payload['note_status'] = $status;
			}
		}

		foreach ( array( 'category', 'assignee', 'due_date', 'workflow', 'viewport', 'context_url', 'admin_screen' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$payload[ $key ] = $request->get_param( $key );
			}
		}

		$updated = Task_Repository::update( $task_id, $payload );

		if ( ! $updated ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not update task note.', '4wp-todo' ),
				),
				400
			);
		}

		return new WP_REST_Response( Task_Repository::get_task_data( $task_id ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_task( WP_REST_Request $request ): WP_REST_Response {
		$task_id = absint( $request->get_param( 'id' ) );

		if ( ! Task_Repository::is_root_note( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not delete task note.', '4wp-todo' ),
				),
				400
			);
		}

		if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not delete task note.', '4wp-todo' ),
				),
				403
			);
		}

		if ( ! Task_Repository::delete( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not delete task note.', '4wp-todo' ),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $task_id,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_thread( WP_REST_Request $request ): WP_REST_Response {
		$task_id = absint( $request->get_param( 'id' ) );

		if ( ! Task_Repository::is_root_note( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Note not found.', '4wp-todo' ),
				),
				404
			);
		}

		if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Note not found.', '4wp-todo' ),
				),
				404
			);
		}

		$replies = array_map(
			static function ( $comment ) {
				return Task_Repository::get_reply_data( (int) $comment->comment_ID );
			},
			Task_Repository::get_replies( $task_id )
		);

		return new WP_REST_Response(
			array(
				'task'    => Task_Repository::get_task_data( $task_id ),
				'replies' => $replies,
			),
			200
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function create_reply( WP_REST_Request $request ): WP_REST_Response {
		$task_id = absint( $request->get_param( 'id' ) );
		$content = (string) $request->get_param( 'content' );

		if ( ! Task_Repository::is_root_note( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not add reply.', '4wp-todo' ),
				),
				400
			);
		}

		if ( ! Task_Repository::user_can_view_task( $task_id ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not add reply.', '4wp-todo' ),
				),
				403
			);
		}

		$reply_id = Task_Repository::create_reply( $task_id, $content );

		if ( $reply_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Could not add reply.', '4wp-todo' ),
				),
				400
			);
		}

		$replies = array_map(
			static function ( $comment ) {
				return Task_Repository::get_reply_data( (int) $comment->comment_ID );
			},
			Task_Repository::get_replies( $task_id )
		);

		return new WP_REST_Response(
			array(
				'reply'   => Task_Repository::get_reply_data( $reply_id ),
				'task'    => Task_Repository::get_task_data( $task_id ),
				'replies' => $replies,
			),
			201
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function list_tasks_args(): array {
		return array(
			'per_page'   => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'post_id'    => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'category'   => array(
				'type'              => 'string',
				'sanitize_callback' => array( Task_Category::class, 'sanitize' ),
			),
			'note_state' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'workflow'   => array(
				'type'              => 'string',
				'sanitize_callback' => array( Task_Taxonomy::class, 'sanitize_workflow' ),
			),
			'view'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'assignee'   => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function create_task_args(): array {
		return array(
			'content'      => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_task_content' ),
			),
			'category'     => array(
				'type'              => 'string',
				'sanitize_callback' => array( Task_Category::class, 'sanitize' ),
			),
			'assignee'     => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'post_id'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'context_url'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'admin_screen' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'selector'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'anchor'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'due_date'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'viewport'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'source'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'workflow'     => array(
				'type'              => 'string',
				'sanitize_callback' => array( Task_Taxonomy::class, 'sanitize_workflow' ),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function update_task_args(): array {
		$args = self::create_task_args();
		unset( $args['content']['required'] );

		$args['note_status'] = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
		);

		return $args;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function create_reply_args(): array {
		return array(
			'content' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_task_content' ),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function update_settings_args(): array {
		return array(
			'frontend_enabled'     => array(
				'type' => 'boolean',
			),
			'hide_admin_bar'       => array(
				'type' => 'boolean',
			),
			'show_admin_bar_todo'  => array(
				'type' => 'boolean',
			),
			'visibility_rules'     => array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_visibility_rules' ),
			),
			'super_admin_user_ids' => array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_super_admin_user_ids' ),
			),
			'tasks_page_id'        => array(
				'type'              => 'integer',
				'sanitize_callback' => array( Settings::class, 'sanitize_tasks_page_id' ),
			),
		);
	}

	/**
	 * @param mixed $value Raw content.
	 * @return string
	 */
	public static function sanitize_task_content( $value ): string {
		return sanitize_textarea_field( (string) $value );
	}
}
