<?php
/**
 * Task persistence — native Gutenberg notes (comment_type = note) + comment meta.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for todo tasks backed by core notes.
 */
final class Task_Repository {

	public const META_CATEGORY    = '_forwp_todo_category';
	public const META_ASSIGNEE    = '_forwp_todo_assignee';
	public const META_CONTEXT_URL = '_forwp_todo_context_url';
	public const META_ADMIN_SCREEN = '_forwp_todo_admin_screen';
	public const META_DUE_DATE    = '_forwp_todo_due_date';
	public const META_SOURCE      = '_forwp_todo_source';
	public const META_VIEWPORT    = '_forwp_todo_viewport';
	public const META_SELECTOR    = '_forwp_todo_selector';
	public const META_ANCHOR      = '_forwp_todo_anchor';
	public const META_SORT_MAP    = '_forwp_todo_sort_map';

	/**
	 * @param array<string, mixed> $args See forwp_todo_create_task().
	 * @return int Note comment ID or 0.
	 */
	public static function create( array $args ): int {
		$content = isset( $args['content'] ) ? wp_kses_post( (string) $args['content'] ) : '';
		if ( '' === $content && ! empty( $args['title'] ) ) {
			$content = sanitize_text_field( (string) $args['title'] );
		}

		if ( '' === $content ) {
			return 0;
		}

		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( $post_id <= 0 && ! empty( $args['context_url'] ) ) {
			$post_id = (int) url_to_postid( (string) $args['context_url'] );
		}
		if ( $post_id <= 0 && ! empty( $args['storage_post_id'] ) ) {
			$post_id = absint( $args['storage_post_id'] );
		}
		if ( $post_id <= 0 && ! empty( $args['context_url'] ) && self::is_admin_context_url( (string) $args['context_url'] ) ) {
			$post_id = Settings::get_note_storage_post_id();
		}
		if ( $post_id <= 0 ) {
			return 0;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 && ! empty( $args['author'] ) ) {
			$user_id = absint( $args['author'] );
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => $content,
				'comment_type'         => 'note',
				'user_id'              => max( 0, $user_id ),
				'comment_approved'     => 0,
				'comment_author'       => $user_id > 0 ? (string) wp_get_current_user()->display_name : '',
				'comment_author_email' => $user_id > 0 ? (string) wp_get_current_user()->user_email : '',
			),
			true
		);

		if ( is_wp_error( $comment_id ) || ! is_int( $comment_id ) ) {
			return 0;
		}

		Task_Status::set_core_status( $comment_id, Task_Status::REOPEN );

		if ( ! isset( $args['viewport'] ) || '' === (string) $args['viewport'] ) {
			$args['viewport'] = wp_is_mobile() ? 'mobile' : 'desktop';
		}

		if ( empty( $args['context_url'] ) && $post_id > 0 ) {
			$args['context_url'] = (string) get_permalink( $post_id );
		}

		self::update_meta( $comment_id, $args );

		return $comment_id;
	}

	/**
	 * @param int    $parent_id Root note comment ID.
	 * @param string $content   Reply body.
	 * @return int Reply comment ID or 0.
	 */
	public static function create_reply( int $parent_id, string $content ): int {
		if ( ! self::is_root_note( $parent_id ) ) {
			return 0;
		}

		$content = wp_kses_post( $content );
		if ( '' === $content ) {
			return 0;
		}

		$parent = get_comment( $parent_id );
		if ( ! $parent instanceof \WP_Comment ) {
			return 0;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return 0;
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => (int) $parent->comment_post_ID,
				'comment_content'      => $content,
				'comment_type'         => 'note',
				'comment_parent'       => $parent_id,
				'user_id'              => $user_id,
				'comment_approved'     => 0,
				'comment_author'       => (string) wp_get_current_user()->display_name,
				'comment_author_email' => (string) wp_get_current_user()->user_email,
			),
			true
		);

		if ( is_wp_error( $comment_id ) || ! is_int( $comment_id ) ) {
			return 0;
		}

		return $comment_id;
	}

	/**
	 * @param int $parent_id Root note comment ID.
	 * @return \WP_Comment[]
	 */
	public static function get_replies( int $parent_id ): array {
		if ( ! self::is_root_note( $parent_id ) ) {
			return array();
		}

		$comments = get_comments(
			array(
				'type'    => 'note',
				'status'  => 'all',
				'parent'  => $parent_id,
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
				'number'  => 200,
			)
		);

		return is_array( $comments ) ? $comments : array();
	}

	/**
	 * @param int $comment_id Note comment ID.
	 * @return array<string, mixed>
	 */
	public static function get_reply_data( int $comment_id ): array {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment || 'note' !== $comment->comment_type ) {
			return array();
		}

		$author_id = (int) $comment->user_id;
		$name      = self::user_display_name( $author_id );
		if ( '' === $name ) {
			$name = (string) $comment->comment_author;
		}

		return array(
			'id'          => $comment_id,
			'parent'      => (int) $comment->comment_parent,
			'content'     => $comment->comment_content,
			'author'      => $author_id,
			'author_name' => $name,
			'created'     => $comment->comment_date_gmt,
		);
	}

	/**
	 * @param int $comment_id Note comment ID.
	 * @return bool
	 */
	public static function is_root_note( int $comment_id ): bool {
		if ( ! self::is_note( $comment_id ) ) {
			return false;
		}

		$comment = get_comment( $comment_id );

		return $comment instanceof \WP_Comment && 0 === (int) $comment->comment_parent;
	}

	/**
	 * Delete a root note and its replies.
	 *
	 * @param int $comment_id Root note comment ID.
	 * @return bool
	 */
	public static function delete( int $comment_id ): bool {
		if ( ! self::is_root_note( $comment_id ) ) {
			return false;
		}

		foreach ( self::get_replies( $comment_id ) as $reply ) {
			wp_delete_comment( (int) $reply->comment_ID, true );
		}

		return (bool) wp_delete_comment( $comment_id, true );
	}

	/**
	 * @param int                  $comment_id Note comment ID.
	 * @param array<string, mixed> $args       Partial update.
	 * @return bool
	 */
	public static function update( int $comment_id, array $args ): bool {
		if ( ! self::is_note( $comment_id ) ) {
			return false;
		}

		if ( isset( $args['content'] ) ) {
			wp_update_comment(
				array(
					'comment_ID'      => $comment_id,
					'comment_content' => wp_kses_post( (string) $args['content'] ),
				)
			);
		}

		if ( isset( $args['note_status'] ) ) {
			Task_Status::set_core_status( $comment_id, (string) $args['note_status'] );
		}

		self::update_meta( $comment_id, $args );

		return true;
	}

	/**
	 * @param int    $comment_id Note comment ID.
	 * @param string $status     reopen|resolved
	 * @return bool
	 */
	public static function update_note_status( int $comment_id, string $status ): bool {
		return self::update(
			$comment_id,
			array(
				'note_status' => $status,
			)
		);
	}

	/**
	 * @param array<string, mixed> $query_args Query extensions.
	 * @return \WP_Comment[]
	 */
	public static function query( array $query_args = array() ): array {
		$defaults = array(
			'number'  => 50,
			'offset'  => 0,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $query_args, $defaults );

		$comment_args = array(
			'type'   => 'note',
			'status' => 'all',
			'parent' => 0,
			'number' => (int) $args['number'],
			'offset' => (int) $args['offset'],
			'orderby' => (string) $args['orderby'],
			'order'   => (string) $args['order'],
		);

		if ( ! empty( $query_args['post_id'] ) ) {
			$comment_args['post_id'] = absint( $query_args['post_id'] );
		}

		if ( isset( $query_args['assignee'] ) ) {
			$comment_args['meta_query'][] = array(
				'key'   => self::META_ASSIGNEE,
				'value' => absint( $query_args['assignee'] ),
			);
		}

		if ( ! empty( $query_args['author'] ) ) {
			$comment_args['user_id'] = absint( $query_args['author'] );
		}

		if ( ! empty( $query_args['category'] ) ) {
			$comment_args['meta_query'][] = array(
				'key'   => self::META_CATEGORY,
				'value' => Task_Category::sanitize( (string) $query_args['category'] ),
			);
		}

		if ( ! empty( $query_args['workflow'] ) ) {
			$workflow = Task_Taxonomy::sanitize_workflow( (string) $query_args['workflow'] );
			if ( '' !== $workflow ) {
				$comment_args['meta_query'][] = array(
					'key'   => Task_Workflow::META_KEY,
					'value' => $workflow,
				);
			}
		}

		if ( ! empty( $query_args['due_date'] ) ) {
			$comment_args['meta_query'][] = array(
				'key'   => self::META_DUE_DATE,
				'value' => sanitize_text_field( (string) $query_args['due_date'] ),
			);
		}

		if ( ! empty( $comment_args['meta_query'] ) && count( $comment_args['meta_query'] ) > 1 ) {
			$comment_args['meta_query']['relation'] = 'AND';
		}

		$comments = get_comments( $comment_args );

		if ( ! is_array( $comments ) ) {
			return array();
		}

		if ( empty( $query_args['note_state'] ) ) {
			return $comments;
		}

		$want_closed = 'closed' === sanitize_key( (string) $query_args['note_state'] );

		return array_values(
			array_filter(
				$comments,
				static function ( $comment ) use ( $want_closed ) {
					return Task_Status::is_resolved( (int) $comment->comment_ID ) === $want_closed;
				}
			)
		);
	}

	/**
	 * Tasks visible to a user: authored by them or assigned to them.
	 *
	 * @param int                  $user_id    User ID.
	 * @param array<string, mixed> $query_args Query extensions (post_id, note_state, etc.).
	 * @return \WP_Comment[]
	 */
	public static function query_visible_to_user( int $user_id, array $query_args = array() ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		unset( $query_args['author'], $query_args['assignee'], $query_args['visible_to_user'] );

		$by_author   = self::query( array_merge( $query_args, array( 'author' => $user_id ) ) );
		$by_assignee = self::query( array_merge( $query_args, array( 'assignee' => $user_id ) ) );

		$merged = array();
		foreach ( array_merge( $by_author, $by_assignee ) as $comment ) {
			$merged[ (int) $comment->comment_ID ] = $comment;
		}

		if ( empty( $merged ) ) {
			return array();
		}

		$order = strtoupper( (string) ( $query_args['order'] ?? 'DESC' ) );
		$items = array_values( $merged );

		usort(
			$items,
			static function ( $left, $right ) use ( $order ) {
				$cmp = strcmp( $left->comment_date_gmt, $right->comment_date_gmt );
				return 'ASC' === $order ? $cmp : -$cmp;
			}
		);

		$limit = isset( $query_args['number'] ) ? max( 1, (int) $query_args['number'] ) : 50;
		if ( count( $items ) > $limit ) {
			$items = array_slice( $items, 0, $limit );
		}

		return $items;
	}

	/**
	 * @param int $task_id Root note comment ID.
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_can_view_task( int $task_id, int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 || ! self::is_root_note( $task_id ) ) {
			return false;
		}

		if ( Settings::user_can_view_all_tasks( $user_id ) ) {
			return true;
		}

		$comment = get_comment( $task_id );
		if ( ! $comment instanceof \WP_Comment ) {
			return false;
		}

		$assignee_id = (int) get_comment_meta( $task_id, self::META_ASSIGNEE, true );

		return (int) $comment->user_id === $user_id || $assignee_id === $user_id;
	}

	/**
	 * Posts that have at least one root note (for admin filters).
	 *
	 * @return array<int, array{id: int, title: string, edit_url: string, view_url: string}>
	 */
	public static function get_posts_with_notes(): array {
		$comments = get_comments(
			array(
				'type'   => 'note',
				'parent' => 0,
				'status' => 'all',
				'number' => 500,
			)
		);

		$posts = array();
		foreach ( $comments as $comment ) {
			$post_id = (int) $comment->comment_post_ID;
			if ( isset( $posts[ $post_id ] ) ) {
				continue;
			}

			$title = get_the_title( $post_id );
			if ( '' === $title ) {
				/* translators: %d: post ID */
				$title = sprintf( __( 'Post #%d', '4wp-todo' ), $post_id );
			}

			$edit_url = get_edit_post_link( $post_id, 'raw' );
			$permalink = get_permalink( $post_id );

			$posts[ $post_id ] = array(
				'id'       => $post_id,
				'title'    => (string) $title,
				'edit_url' => is_string( $edit_url ) ? $edit_url : '',
				'view_url' => is_string( $permalink ) ? $permalink : '',
			);
		}

		usort(
			$posts,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return array_values( $posts );
	}

	/**
	 * Published posts the current user may attach notes to (admin quick-add picker).
	 *
	 * @return array<int, array{id: int, title: string, edit_url: string, view_url: string}>
	 */
	public static function get_assignable_posts(): array {
		$posts = array();

		foreach ( self::get_posts_with_notes() as $post ) {
			$posts[ (int) $post['id'] ] = $post;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'perm'                   => 'editable',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$post_id = (int) $post->ID;
			if ( isset( $posts[ $post_id ] ) ) {
				continue;
			}

			$edit_url  = get_edit_post_link( $post_id, 'raw' );
			$permalink = get_permalink( $post_id );

			$posts[ $post_id ] = array(
				'id'       => $post_id,
				'title'    => (string) get_the_title( $post_id ),
				'edit_url' => is_string( $edit_url ) ? $edit_url : '',
				'view_url' => is_string( $permalink ) ? $permalink : '',
			);
		}

		usort(
			$posts,
			static function ( $left, $right ) {
				return strcasecmp( (string) $left['title'], (string) $right['title'] );
			}
		);

		return array_values( $posts );
	}

	/**
	 * @param array<string, mixed> $query_args Query extensions.
	 * @return int
	 */
	public static function count( array $query_args = array() ): int {
		$query_args['number'] = 0;
		$comments             = self::query( $query_args );

		return count( $comments );
	}

	/**
	 * @param int $comment_id Note comment ID.
	 * @return array<string, mixed>
	 */
	public static function get_task_data( int $comment_id ): array {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment || 'note' !== $comment->comment_type ) {
			return array();
		}

		$is_open  = ! Task_Status::is_resolved( $comment_id );
		$workflow = (string) get_comment_meta( $comment_id, Task_Workflow::META_KEY, true );

		$anchor       = (string) get_comment_meta( $comment_id, self::META_ANCHOR, true );
		$post_id      = (int) $comment->comment_post_ID;
		$block_ref    = Editor_Note_Locator::locate( $comment_id, $post_id );

		$assignee_id = (int) get_comment_meta( $comment_id, self::META_ASSIGNEE, true );
		$author_id   = (int) $comment->user_id;

		return array(
			'id'           => $comment_id,
			'content'      => $comment->comment_content,
			'excerpt'      => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 12, '…' ),
			'author'       => $author_id,
			'author_name'  => self::user_display_name( $author_id ),
			'created'      => $comment->comment_date_gmt,
			'post_id'      => $post_id,
			'note_status'  => Task_Status::get_core_status( $comment_id ),
			'is_open'      => $is_open,
			'workflow'     => Task_Workflow::sanitize( $workflow, $is_open ),
			'category'     => Task_Category::sanitize( (string) get_comment_meta( $comment_id, self::META_CATEGORY, true ) ) ?: Task_Category::default_slug(),
			'assignee'     => $assignee_id,
			'assignee_name' => self::user_display_name( $assignee_id ),
			'context_url'  => (string) get_comment_meta( $comment_id, self::META_CONTEXT_URL, true ),
			'admin_screen' => (string) get_comment_meta( $comment_id, self::META_ADMIN_SCREEN, true ),
			'due_date'     => (string) get_comment_meta( $comment_id, self::META_DUE_DATE, true ),
			'source'       => (string) get_comment_meta( $comment_id, self::META_SOURCE, true ) ?: 'note',
			'viewport'     => (string) get_comment_meta( $comment_id, self::META_VIEWPORT, true ),
			'selector'     => (string) get_comment_meta( $comment_id, self::META_SELECTOR, true ),
			'anchor'       => $anchor,
			'block_name'   => $block_ref['block_name'] ?? '',
			'block_index'  => isset( $block_ref['block_index'] ) ? (int) $block_ref['block_index'] : null,
			'edit_post_url' => get_edit_post_link( $post_id, 'raw' ),
			'post_title'   => get_the_title( $post_id ),
			'reply_count'  => self::count_replies( $comment_id ),
			'sort_map'     => self::get_sort_map( $comment_id ),
		);
	}

	/**
	 * @param string $scope Sort scope key (page:{id} or column:{id}).
	 * @return string
	 */
	public static function sanitize_sort_scope( string $scope ): string {
		$scope = trim( $scope );
		if ( preg_match( '/^(page|column|admin):[a-z0-9_-]+$/i', $scope ) ) {
			return $scope;
		}

		return '';
	}

	/**
	 * @param int $comment_id Note comment ID.
	 * @return array<string, int>
	 */
	public static function get_sort_map( int $comment_id ): array {
		$raw = get_comment_meta( $comment_id, self::META_SORT_MAP, true );
		$map = array();

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}

		if ( ! is_array( $raw ) ) {
			return $map;
		}

		foreach ( $raw as $scope => $order ) {
			$scope = self::sanitize_sort_scope( (string) $scope );
			if ( '' === $scope ) {
				continue;
			}

			$map[ $scope ] = (int) $order;
		}

		return $map;
	}

	/**
	 * @param int    $comment_id Note comment ID.
	 * @param string $scope      Sort scope key.
	 * @return int
	 */
	public static function get_sort_order( int $comment_id, string $scope ): int {
		$scope = self::sanitize_sort_scope( $scope );
		if ( '' === $scope ) {
			return PHP_INT_MAX;
		}

		$map = self::get_sort_map( $comment_id );

		return self::resolve_sort_order_from_map( $map, $scope );
	}

	/**
	 * @param array<string, int> $map   Sort map.
	 * @param string             $scope Sort scope key.
	 * @return int
	 */
	private static function resolve_sort_order_from_map( array $map, string $scope ): int {
		if ( isset( $map[ $scope ] ) ) {
			return (int) $map[ $scope ];
		}

		if ( preg_match( '/^page:(\d+)$/', $scope, $matches ) ) {
			$legacy = 'column:post-' . $matches[1];
			if ( isset( $map[ $legacy ] ) ) {
				return (int) $map[ $legacy ];
			}
		}

		if ( preg_match( '/^column:post-(\d+)$/', $scope, $matches ) ) {
			$page_key = 'page:' . $matches[1];
			if ( isset( $map[ $page_key ] ) ) {
				return (int) $map[ $page_key ];
			}
		}

		return PHP_INT_MAX;
	}

	/**
	 * Persist manual order for a list scope.
	 *
	 * @param string       $scope    Sort scope key.
	 * @param list<int>    $task_ids Ordered task IDs.
	 * @return bool
	 */
	public static function set_sort_orders( string $scope, array $task_ids ): bool {
		$scope = self::sanitize_sort_scope( $scope );
		if ( '' === $scope ) {
			return false;
		}

		foreach ( array_values( $task_ids ) as $index => $task_id ) {
			$task_id = absint( $task_id );
			if ( $task_id <= 0 || ! self::is_root_note( $task_id ) ) {
				continue;
			}

			$map           = self::get_sort_map( $task_id );
			$map[ $scope ] = (int) $index;
			update_comment_meta( $task_id, self::META_SORT_MAP, wp_json_encode( $map ) );
		}

		return true;
	}

	/**
	 * @param list<array<string, mixed>> $tasks Task payloads.
	 * @param string                     $scope Sort scope key.
	 * @return list<array<string, mixed>>
	 */
	public static function sort_tasks_by_scope( array $tasks, string $scope ): array {
		$scope = self::sanitize_sort_scope( $scope );
		if ( '' === $scope || empty( $tasks ) ) {
			return $tasks;
		}

		usort(
			$tasks,
			static function ( array $left, array $right ) use ( $scope ) {
				$left_map  = is_array( $left['sort_map'] ?? null ) ? $left['sort_map'] : array();
				$right_map = is_array( $right['sort_map'] ?? null ) ? $right['sort_map'] : array();
				$left_order  = self::resolve_sort_order_from_map( $left_map, $scope );
				$right_order = self::resolve_sort_order_from_map( $right_map, $scope );

				if ( $left_order !== $right_order ) {
					return $left_order <=> $right_order;
				}

				return strcmp( (string) ( $right['created'] ?? '' ), (string) ( $left['created'] ?? '' ) );
			}
		);

		return array_values( $tasks );
	}

	/**
	 * @param int $parent_id Root note comment ID.
	 * @return int
	 */
	public static function count_replies( int $parent_id ): int {
		return count( self::get_replies( $parent_id ) );
	}

	/**
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public static function is_note( int $comment_id ): bool {
		$comment = get_comment( $comment_id );

		return $comment instanceof \WP_Comment && 'note' === $comment->comment_type;
	}

	/**
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function user_display_name( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$user = get_userdata( $user_id );

		return $user instanceof \WP_User ? (string) $user->display_name : '';
	}

	/**
	 * @param int                  $comment_id Note comment ID.
	 * @param array<string, mixed> $args       Meta + workflow fields.
	 * @return void
	 */
	private static function update_meta( int $comment_id, array $args ): void {
		if ( isset( $args['category'] ) ) {
			$category = Task_Category::sanitize( (string) $args['category'] );
			update_comment_meta( $comment_id, self::META_CATEGORY, $category ?: Task_Category::default_slug() );
		} elseif ( ! metadata_exists( 'comment', $comment_id, self::META_CATEGORY ) ) {
			update_comment_meta( $comment_id, self::META_CATEGORY, Task_Category::default_slug() );
		}

		if ( isset( $args['assignee'] ) ) {
			update_comment_meta( $comment_id, self::META_ASSIGNEE, absint( $args['assignee'] ) );
		}

		if ( isset( $args['context_url'] ) ) {
			update_comment_meta( $comment_id, self::META_CONTEXT_URL, esc_url_raw( (string) $args['context_url'] ) );
		}

		if ( isset( $args['admin_screen'] ) ) {
			$admin_screen = sanitize_key( (string) $args['admin_screen'] );
			if ( '' === $admin_screen ) {
				delete_comment_meta( $comment_id, self::META_ADMIN_SCREEN );
			} else {
				update_comment_meta( $comment_id, self::META_ADMIN_SCREEN, $admin_screen );
			}
		}

		if ( isset( $args['due_date'] ) ) {
			update_comment_meta( $comment_id, self::META_DUE_DATE, sanitize_text_field( (string) $args['due_date'] ) );
		}

		if ( isset( $args['source'] ) ) {
			update_comment_meta( $comment_id, self::META_SOURCE, sanitize_key( (string) $args['source'] ) );
		} elseif ( ! metadata_exists( 'comment', $comment_id, self::META_SOURCE ) ) {
			update_comment_meta( $comment_id, self::META_SOURCE, 'note' );
		}

		if ( isset( $args['viewport'] ) && '' === (string) $args['viewport'] ) {
			$args['viewport'] = wp_is_mobile() ? 'mobile' : 'desktop';
		}

		if ( isset( $args['viewport'] ) ) {
			$viewport = sanitize_key( (string) $args['viewport'] );
			if ( in_array( $viewport, array( 'desktop', 'mobile' ), true ) ) {
				update_comment_meta( $comment_id, self::META_VIEWPORT, $viewport );
			}
		}

		if ( isset( $args['selector'] ) ) {
			update_comment_meta( $comment_id, self::META_SELECTOR, sanitize_text_field( (string) $args['selector'] ) );
		}

		if ( isset( $args['anchor'] ) ) {
			update_comment_meta( $comment_id, self::META_ANCHOR, sanitize_key( (string) $args['anchor'] ) );
		}

		if ( array_key_exists( 'workflow', $args ) ) {
			$is_open = ! Task_Status::is_resolved( $comment_id );
			$value   = Task_Workflow::sanitize( (string) $args['workflow'], $is_open );
			if ( '' === $value ) {
				delete_comment_meta( $comment_id, Task_Workflow::META_KEY );
			} else {
				update_comment_meta( $comment_id, Task_Workflow::META_KEY, $value );
			}
		}
	}

	/**
	 * Whether a stored context URL points at wp-admin (not a front-end permalink).
	 *
	 * @param string $url Context URL.
	 * @return bool
	 */
	private static function is_admin_context_url( string $url ): bool {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		return str_contains( $path, '/wp-admin/' );
	}
}
