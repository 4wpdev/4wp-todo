<?php
/**
 * Admin menu + React shell bootstrap.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo\Admin;

use ForWP\Todo\Settings;
use ForWP\Todo\Task_Repository;
use ForWP\Todo\Task_Status;

defined( 'ABSPATH' ) || exit;

/**
 * Registers 4WP TODO admin (React + REST).
 */
final class Menu {

	public const PAGE_SLUG           = 'forwp-todo';
	public const LISTS_PAGE_SLUG     = 'forwp-todo-lists';
	public const TAXONOMY_PAGE_SLUG  = 'forwp-todo-taxonomy';
	public const SETTINGS_PAGE_SLUG  = 'forwp-todo-settings';

	/** @var string Active admin screen key for React. */
	private static string $screen = 'dashboard';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'highlight_taxonomy_parent_menu' ) );
	}

	/**
	 * @return void
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( '4WP TODO', '4wp-todo' ),
			__( '4WP TODO', '4wp-todo' ),
			Settings::ACCESS_CAP,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-list-view',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Dashboard', '4wp-todo' ),
			__( 'Dashboard', '4wp-todo' ),
			Settings::ACCESS_CAP,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'TODO Lists', '4wp-todo' ),
			__( 'TODO Lists', '4wp-todo' ),
			Settings::ACCESS_CAP,
			self::LISTS_PAGE_SLUG,
			array( __CLASS__, 'render_lists' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', '4wp-todo' ),
			__( 'Settings', '4wp-todo' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard(): void {
		self::$screen = 'dashboard';
		self::render_shell(
			__( 'Team overview — open notes, closed items, and checklist activity across the site.', '4wp-todo' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_lists(): void {
		self::$screen = 'lists';

		if ( ! current_user_can( Settings::ACCESS_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '4wp-todo' ) );
		}

		$count = Task_Repository::count();
		$svg_allowed = array(
			'svg'  => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'focusable'   => true,
				'aria-hidden' => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
		);
		$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m-6 9 2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		?>
		<div class="wrap forwp-todo-admin-shell forwp-todo-admin-shell--lists">
			<h1 class="forwp-todo-admin-heading">
				<span class="forwp-todo-admin-heading__icon" aria-hidden="true"><?php echo wp_kses( $icon, $svg_allowed ); ?></span>
				<span class="forwp-todo-admin-heading__text"><?php echo esc_html__( '4WP TODO', '4wp-todo' ); ?></span>
			</h1>
			<p class="forwp-todo-admin-lead">
				<?php
				printf(
					/* translators: %d: note count */
					esc_html__( 'Checklist of native Gutenberg notes (comment_type = note). %d item(s) on this site.', '4wp-todo' ),
					absint( $count )
				);
				?>
			</p>
			<div
				id="forwp-todo-admin-root"
				class="forwp-todo-admin-root forwp-todo-admin-root--lists"
				data-screen="<?php echo esc_attr( self::$screen ); ?>"
				aria-live="polite"
			>
				<div class="forwp-todo-kanban-loading forwp-todo-kanban-loading--boot">
					<span class="spinner is-active" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Loading notes…', '4wp-todo' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @deprecated 0.4.0 Taxonomies use native wp-admin term screens.
	 * @return void
	 */
	public static function render_taxonomy(): void {
		wp_safe_redirect(
			admin_url(
				'edit-tags.php?taxonomy=' . rawurlencode( \ForWP\Todo\Task_Taxonomies::CATEGORY ) . '&post_type=' . rawurlencode( \ForWP\Todo\Task_Post_Type::POST_TYPE )
			)
		);
		exit;
	}

	/**
	 * @return void
	 */
	public static function render_settings(): void {
		self::$screen = 'settings';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '4wp-todo' ) );
		}

		self::render_shell(
			__( 'Frontend overlay, admin toolbar, and who can see the TODO list.', '4wp-todo' )
		);
	}

	/**
	 * @param string $lead Lead paragraph for the active screen.
	 * @return void
	 */
	private static function render_shell( string $lead ): void {
		if ( ! current_user_can( Settings::ACCESS_CAP ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '4wp-todo' ) );
		}

		$svg_allowed = array(
			'svg'  => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'focusable'   => true,
				'aria-hidden' => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
		);

		$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m-6 9 2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		?>
		<div class="wrap forwp-todo-admin-shell">
			<h1 class="forwp-todo-admin-heading">
				<span class="forwp-todo-admin-heading__icon" aria-hidden="true"><?php echo wp_kses( $icon, $svg_allowed ); ?></span>
				<span class="forwp-todo-admin-heading__text"><?php echo esc_html__( '4WP TODO', '4wp-todo' ); ?></span>
			</h1>
			<p class="forwp-todo-admin-lead"><?php echo esc_html( $lead ); ?></p>
			<div
				id="forwp-todo-admin-root"
				class="forwp-todo-admin-root"
				data-screen="<?php echo esc_attr( self::$screen ); ?>"
				aria-live="polite"
			></div>
		</div>
		<?php
	}

	/**
	 * Always-visible fallback list (PHP) until React loads notes from REST.
	 *
	 * @param \WP_Comment[] $comments Note comments.
	 * @return void
	 */
	private static function render_server_notes_list( array $comments ): void {
		?>
		<div id="forwp-todo-server-list" class="forwp-todo-server-list">
			<h2 class="forwp-todo-server-list__heading"><?php esc_html_e( 'Notes checklist', '4wp-todo' ); ?></h2>
			<?php if ( empty( $comments ) ) : ?>
				<p class="forwp-todo-admin-muted">
					<?php esc_html_e( 'No native notes in the database yet. Add one in the block editor (Notes panel on a post).', '4wp-todo' ); ?>
				</p>
			<?php else : ?>
				<table class="widefat striped forwp-todo-server-list__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Note', '4wp-todo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Post', '4wp-todo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', '4wp-todo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', '4wp-todo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $comments as $comment ) : ?>
							<?php
							$task    = Task_Repository::get_task_data( (int) $comment->comment_ID );
							$is_open = ! Task_Status::is_resolved( (int) $comment->comment_ID );
							$edit    = get_edit_post_link( (int) $comment->comment_post_ID, 'raw' );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 14, '…' ) ); ?></strong>
									<div class="forwp-todo-admin-muted"><?php echo esc_html( wp_strip_all_tags( $comment->comment_content ) ); ?></div>
								</td>
								<td><?php echo esc_html( $task['post_title'] ?: __( '(Untitled)', '4wp-todo' ) ); ?></td>
								<td>
									<span class="forwp-todo-badge forwp-todo-badge--<?php echo $is_open ? 'open' : 'closed'; ?>">
										<?php echo esc_html( $is_open ? __( 'Open', '4wp-todo' ) : __( 'Closed', '4wp-todo' ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( is_string( $edit ) && '' !== $edit ) : ?>
										<a class="button button-secondary" href="<?php echo esc_url( $edit ); ?>">
											<?php esc_html_e( 'Open in editor', '4wp-todo' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! self::is_plugin_page_request() ) {
			return;
		}

		if ( ! current_user_can( Settings::ACCESS_CAP ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$style_path = FORWP_TODO_PATH . 'build/admin/style-index.css';
		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				'forwp-todo-admin',
				forwp_todo_asset_url( 'build/admin/style-index.css' ),
				array(),
				FORWP_TODO_VERSION
			);
		}

		$asset_file = FORWP_TODO_PATH . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p><strong>' . esc_html__( '4WP TODO admin UI is not built.', '4wp-todo' ) . '</strong> ';
					echo esc_html__( 'Run npm install && npm run build in the plugin directory, then refresh this page.', '4wp-todo' );
					echo '</p></div>';
				}
			);
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_script(
			'forwp-todo-admin',
			forwp_todo_asset_url( 'build/admin/index.js' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'forwp-todo-admin', '4wp-todo', FORWP_TODO_PATH . 'languages' );

		wp_localize_script(
			'forwp-todo-admin',
			'forwpTodoAdmin',
			array(
				'restRoot' => self::rest_root_for_browser(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'screen'   => self::screen_from_page_request(),
				'pages'    => array(
					'dashboard' => self::PAGE_SLUG,
					'lists'     => self::LISTS_PAGE_SLUG,
					'taxonomy'  => self::TAXONOMY_PAGE_SLUG,
					'settings'  => self::SETTINGS_PAGE_SLUG,
				),
			)
		);
	}

	/**
	 * Keep 4WP TODO menu highlighted on native taxonomy screens.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public static function highlight_taxonomy_parent_menu( string $parent_file ): string {
		global $pagenow, $taxonomy;

		if ( 'edit-tags.php' !== $pagenow || ! is_string( $taxonomy ) ) {
			return $parent_file;
		}

		$todo_taxonomies = array(
			\ForWP\Todo\Task_Taxonomies::CATEGORY,
			\ForWP\Todo\Task_Taxonomies::STATUS,
			\ForWP\Todo\Task_Taxonomies::WORKFLOW,
		);

		return in_array( $taxonomy, $todo_taxonomies, true ) ? self::PAGE_SLUG : $parent_file;
	}

	/**
	 * @return bool
	 */
	private static function is_plugin_page_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return in_array(
			$page,
			array(
				self::PAGE_SLUG,
				self::LISTS_PAGE_SLUG,
				self::SETTINGS_PAGE_SLUG,
			),
			true
		);
	}

	/**
	 * @return string
	 */
	private static function screen_from_page_request(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::LISTS_PAGE_SLUG === $page ) {
			return 'lists';
		}
		if ( self::SETTINGS_PAGE_SLUG === $page ) {
			return 'settings';
		}

		return 'dashboard';
	}

	/**
	 * REST root on the current host (Local often uses :10144 while siteurl has no port).
	 *
	 * @return string Path like /wp-json/ so apiFetch uses the active browser origin.
	 */
	private static function rest_root_for_browser(): string {
		$path = wp_parse_url( rest_url(), PHP_URL_PATH );

		return trailingslashit( is_string( $path ) && '' !== $path ? $path : '/wp-json/' );
	}
}
