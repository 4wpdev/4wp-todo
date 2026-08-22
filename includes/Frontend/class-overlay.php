<?php
/**
 * Page notes overlay — frontend, block editor, and admin preview.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo\Frontend;

use ForWP\Todo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Floating page-notes UI for logged-in editors.
 */
final class Overlay {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend_root' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_admin_root' ), 20 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar_node' ), 100 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );
	}

	/**
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function register_admin_bar_node( $wp_admin_bar ): void {
		if ( ! is_user_logged_in() || ! Settings::show_admin_bar_todo() || ! Settings::user_can_access_todo() ) {
			return;
		}

		$lists_url = Settings::resolve_all_tasks_url();
		$icon      = '<span class="ab-icon dashicons dashicons-list-view" aria-hidden="true"></span>';
		$label     = esc_html__( 'TODO', '4wp-todo' );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'forwp-todo-admin-bar',
				'parent' => false,
				'href'   => $lists_url,
				'title'  => $icon . '<span class="ab-label">' . $label . '</span>',
				'meta'   => array(
					'class' => 'forwp-todo-admin-bar-root',
					'title' => esc_attr__( 'Open 4WP TODO lists.', '4wp-todo' ),
				),
			)
		);

		if ( self::is_active() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'forwp-todo-page-notes',
					'parent' => 'forwp-todo-admin-bar',
					'title'  => esc_html__( 'Page notes', '4wp-todo' ),
					'href'   => '#forwp-todo-open',
					'meta'   => array(
						'class' => 'forwp-todo-admin-bar-trigger',
					),
				)
			);
		}
	}

	/**
	 * @param bool $show Whether to show admin bar.
	 * @return bool
	 */
	public static function maybe_hide_admin_bar( bool $show ): bool {
		if ( is_admin() || ! self::is_active() ) {
			return $show;
		}

		return apply_filters( 'forwp_todo_hide_admin_bar', Settings::should_hide_admin_bar() ) ? false : $show;
	}

	/**
	 * @return bool
	 */
	public static function is_active(): bool {
		return self::can_use_overlay() && Settings::is_frontend_enabled();
	}

	/**
	 * Whether the current user may load the page-notes overlay.
	 *
	 * Visibility is controlled solely by Settings::user_can_access_todo() — do not
	 * re-gate with edit_posts (e.g. WooCommerce customer has TODO access but no edit_posts).
	 *
	 * @return bool
	 */
	public static function can_use_overlay(): bool {
		return is_user_logged_in() && Settings::user_can_access_todo();
	}

	/**
	 * @return bool
	 */
	public static function should_show_overlay(): bool {
		if ( self::is_frontend_context() ) {
			return true;
		}

		return self::is_block_editor_screen() && self::resolve_post_id() > 0;
	}

	/**
	 * @return bool
	 */
	public static function is_frontend_context(): bool {
		if ( is_singular() ) {
			return true;
		}

		return is_preview() && get_queried_object_id() > 0;
	}

	/**
	 * @return bool
	 */
	public static function is_block_editor_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
				return true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		return 'edit' === $action && $post_id > 0;
	}

	/**
	 * @return int
	 */
	public static function resolve_post_id(): int {
		if ( is_admin() ) {
			return self::get_editing_post_id();
		}

		return (int) get_queried_object_id();
	}

	/**
	 * @return int
	 */
	private static function get_editing_post_id(): int {
		global $post;

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		if ( isset( $_GET['post'] ) ) {
			return absint( wp_unslash( $_GET['post'] ) );
		}

		return 0;
	}

	/**
	 * Canonical admin screen URL for pin context (current request, without pin query flag).
	 *
	 * @return string
	 */
	public static function resolve_admin_screen_url(): string {
		if ( ! is_admin() ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return '';
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$query = wp_parse_url( $request_uri, PHP_URL_QUERY );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$args = array();
		if ( is_string( $query ) && '' !== $query ) {
			wp_parse_str( $query, $args );
		}

		unset( $args['forwp-todo-pin'] );

		$url = home_url( $path );
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return esc_url_raw( $url );
	}

	/**
	 * Stable admin screen slug (page hook, post editor, or pagenow).
	 *
	 * Examples: page-forwp-seo, post-434-edit, plugins-php
	 *
	 * @return string
	 */
	public static function resolve_admin_screen_key(): string {
		if ( ! is_admin() ) {
			return '';
		}

		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
		if ( isset( $_GET['page'] ) ) {
			return 'page-' . sanitize_key( wp_unslash( $_GET['page'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
			$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'edit';

			return 'post-' . $post_id . '-' . $action;
		}

		return sanitize_key( (string) $pagenow );
	}

	/**
	 * @return void
	 */
	public static function enqueue_admin_assets(): void {
		if ( ! self::is_active() || ! is_admin() ) {
			return;
		}

		if ( self::is_block_editor_screen() && self::get_editing_post_id() > 0 ) {
			return;
		}

		$post_id  = self::get_editing_post_id();
		$page_url = $post_id > 0 ? esc_url_raw( get_permalink( $post_id ) ) : self::resolve_admin_screen_url();

		self::enqueue_assets( 'admin', $post_id, $page_url );
	}

	/**
	 * @return void
	 */
	public static function render_admin_root(): void {
		if ( ! self::is_active() || ! is_admin() ) {
			return;
		}

		if ( self::is_block_editor_screen() && self::get_editing_post_id() > 0 ) {
			return;
		}

		self::render_root( 'admin' );
	}

	/**
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		if ( ! self::is_active() || ! self::is_frontend_context() ) {
			return;
		}

		self::enqueue_assets(
			'frontend',
			self::resolve_post_id(),
			esc_url_raw( get_permalink() )
		);
	}

	/**
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		if ( ! self::is_active() ) {
			return;
		}

		$post_id = self::get_editing_post_id();
		if ( $post_id <= 0 ) {
			return;
		}

		self::enqueue_assets(
			'editor',
			$post_id,
			esc_url_raw( get_permalink( $post_id ) )
		);
	}

	/**
	 * @param string $context   frontend|editor|admin
	 * @param int    $post_id   Current post ID.
	 * @param string $page_url  Permalink for note context.
	 * @return void
	 */
	private static function enqueue_assets( string $context, int $post_id, string $page_url ): void {
		$asset_file = FORWP_TODO_PATH . 'build/frontend/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset   = include $asset_file;
		$handle  = 'forwp-todo-frontend';
		$version = $asset['version'];

		wp_enqueue_style(
			$handle,
			forwp_todo_asset_url( 'build/frontend/style-index.css' ),
			array(),
			$version
		);

		wp_enqueue_script(
			$handle,
			forwp_todo_asset_url( 'build/frontend/index.js' ),
			$asset['dependencies'],
			$version,
			true
		);

		wp_localize_script(
			$handle,
			'forwpTodoFrontend',
			array(
				'context'     => $context,
				'restRoot'    => self::rest_root_for_browser(),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'postId'          => $post_id,
				'pageUrl'         => $page_url,
				'adminScreenUrl'  => 'admin' === $context ? self::resolve_admin_screen_url() : '',
				'adminScreenKey'  => 'admin' === $context ? self::resolve_admin_screen_key() : '',
				'storagePostId'   => Settings::get_note_storage_post_id(),
				'currentUser'     => self::current_user_payload(),
				'allTasksUrl' => Settings::resolve_all_tasks_url(),
				'i18n'        => self::i18n_strings(),
			)
		);

		if ( in_array( $context, array( 'editor', 'admin' ), true ) ) {
			wp_add_inline_script(
				$handle,
				'window.forwpTodoInit && window.forwpTodoInit();',
				'after'
			);
		}
	}

	/**
	 * @return array<string, int|string>
	 */
	private static function current_user_payload(): array {
		$user = wp_get_current_user();

		return array(
			'id'   => (int) $user->ID,
			'name' => (string) $user->display_name,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function i18n_strings(): array {
		return array(
			'checklist'     => __( 'Page notes', '4wp-todo' ),
			'allTasksList'  => __( 'Tasks', '4wp-todo' ),
			'fabLabel'      => __( 'Page notes', '4wp-todo' ),
			'fabLabelAdmin' => __( 'Tasks', '4wp-todo' ),
			'fabTooltip'    => __( 'Page notes — open list, pin to a section', '4wp-todo' ),
			'fabTooltipPin' => __( 'Cancel selection (Esc)', '4wp-todo' ),
			'panelIntro'    => __( 'Pick a section on the page, then leave a short note — like IDE comments.', '4wp-todo' ),
			'pinToSection'  => __( 'Pin note to section…', '4wp-todo' ),
			'shortNote'     => __( 'Short note', '4wp-todo' ),
			'closePanel'    => __( 'Close', '4wp-todo' ),
			'editDone'      => __( 'Done', '4wp-todo' ),
			'loading'       => __( 'Loading notes…', '4wp-todo' ),
			'addNoteFab'    => __( 'Add page note', '4wp-todo' ),
			'exitPin'       => __( 'Exit selection mode', '4wp-todo' ),
			'addNote'       => __( 'Add note on page', '4wp-todo' ),
			'cancelPin'     => __( 'Cancel', '4wp-todo' ),
			'pinHint'       => __( 'Click a section to attach your note', '4wp-todo' ),
			'pinEscHint'    => __( 'Press Esc to cancel', '4wp-todo' ),
			'pinnedTo'      => __( 'Pinned to selected section', '4wp-todo' ),
			'newNote'       => __( 'New note', '4wp-todo' ),
			'save'          => __( 'Save note', '4wp-todo' ),
			'close'         => __( 'Close note', '4wp-todo' ),
			'reopen'        => __( 'Reopen note', '4wp-todo' ),
			'empty'         => __( 'No notes on this page yet.', '4wp-todo' ),
			'addTask'       => __( 'Add a task', '4wp-todo' ),
			'taskScope'     => __( 'Task scope', '4wp-todo' ),
			'scopeAll'      => __( 'All', '4wp-todo' ),
			'scopeMine'     => __( 'Mine', '4wp-todo' ),
			'scopeForMe'    => __( 'For me', '4wp-todo' ),
			'openAllTasks'  => __( 'Open all tasks', '4wp-todo' ),
			'dragToReorder' => __( 'Drag to reorder', '4wp-todo' ),
			'selectPage'    => __( 'Page', '4wp-todo' ),
			'noPages'       => __( 'No pages available', '4wp-todo' ),
			'takeElement'   => __( 'Take element', '4wp-todo' ),
			'taskMenu'      => __( 'Task menu', '4wp-todo' ),
			'editDetails'   => __( 'Edit details', '4wp-todo' ),
			'editTask'      => __( 'Edit task', '4wp-todo' ),
			'assign'        => __( 'Assign', '4wp-todo' ),
			'assignDepartment' => __( 'Set department…', '4wp-todo' ),
			'clearDueDate'  => __( 'Clear due date', '4wp-todo' ),
			'pickDueDate'   => __( 'Pick date…', '4wp-todo' ),
			'routeTo'       => __( 'Route to', '4wp-todo' ),
			'cancelComposer' => __( 'Cancel', '4wp-todo' ),
			'deleteTask'    => __( 'Delete task', '4wp-todo' ),
			'today'         => __( 'Today', '4wp-todo' ),
			'tomorrow'      => __( 'Tomorrow', '4wp-todo' ),
			'comment'       => __( 'Comment', '4wp-todo' ),
			'reply'         => __( 'Reply', '4wp-todo' ),
			'openList'      => __( 'Open list', '4wp-todo' ),
			'saveHint'      => __( 'Enter in the note field also saves', '4wp-todo' ),
			'placeholder'   => __( 'What should change here?', '4wp-todo' ),
			'openCount'     => __( 'Open notes', '4wp-todo' ),
			'openLabel'     => __( 'open', '4wp-todo' ),
			'closedLabel'   => __( 'closed', '4wp-todo' ),
			'jumpTo'        => __( 'Jump to section', '4wp-todo' ),
			'clickOpenList' => __( 'Click to open list', '4wp-todo' ),
			'notesCount'    => __( '%d notes', '4wp-todo' ),
			'markDone'      => __( 'Mark as done', '4wp-todo' ),
			'markOpen'      => __( 'Mark as open', '4wp-todo' ),
			'department'    => __( 'Department', '4wp-todo' ),
			'selectDepartment' => __( 'Select department', '4wp-todo' ),
			'mapTo'         => __( 'Map to', '4wp-todo' ),
			'team'          => __( 'Department', '4wp-todo' ),
			'assignee'      => __( 'Assignee', '4wp-todo' ),
			'selectAssignee' => __( 'Select assignee', '4wp-todo' ),
			'createdBy'      => __( 'By', '4wp-todo' ),
			'assignedTo'     => __( '→', '4wp-todo' ),
			'unassigned'    => __( 'Unassigned', '4wp-todo' ),
			'switchToDepartment' => __( 'Switch to department', '4wp-todo' ),
			'switchToAssignee'   => __( 'Switch to assignee', '4wp-todo' ),
			'dueDate'       => __( 'Due date', '4wp-todo' ),
			'dueDateSoon'   => __( 'Due date (coming soon)', '4wp-todo' ),
		);
	}

	/**
	 * @return void
	 */
	public static function render_frontend_root(): void {
		if ( ! self::is_active() || ! self::is_frontend_context() ) {
			return;
		}

		self::render_root( 'frontend' );
	}

	/**
	 * @param string $context frontend|editor|admin
	 * @return void
	 */
	private static function render_root( string $context ): void {
		$class = 'forwp-todo-frontend-root';
		if ( 'editor' === $context ) {
			$class .= ' forwp-todo-frontend-root--editor';
		} elseif ( 'admin' === $context ) {
			$class .= ' forwp-todo-frontend-root--admin';
		}

		printf(
			'<div id="forwp-todo-frontend-root" class="%1$s" aria-live="polite">
				<div class="forwp-todo-fab-wrap">
					<span id="forwp-todo-fab-tooltip" class="forwp-todo-fab__tooltip">%3$s</span>
					<button type="button" id="forwp-todo-fab-trigger" class="forwp-todo-fab" aria-label="%2$s" title="%3$s">
						<span class="forwp-todo-fab__icon forwp-todo-fab__icon--comment" aria-hidden="true"></span>
						<span class="forwp-todo-fab__icon forwp-todo-fab__icon--close" aria-hidden="true"></span>
						<span class="forwp-todo-fab__badge" hidden></span>
					</button>
				</div>
			</div>',
			esc_attr( $class ),
			esc_attr__( 'Page notes', '4wp-todo' ),
			esc_attr__( 'Page notes — open list, pin to a section', '4wp-todo' )
		);
	}

	/**
	 * @return string
	 */
	private static function rest_root_for_browser(): string {
		$path = wp_parse_url( rest_url(), PHP_URL_PATH );

		return trailingslashit( is_string( $path ) && '' !== $path ? $path : '/wp-json/' );
	}
}
