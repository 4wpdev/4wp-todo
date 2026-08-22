<?php
/**
 * Block forwp/todo-tasks — full tasks kanban for logged-in users.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo\Blocks;

use ForWP\Todo\Settings;
use ForWP\Todo\Task_Page_Display;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the public tasks list block.
 */
final class Tasks_Block {

	public const EDITOR_SCRIPT_HANDLE = 'forwp-todo-tasks-block-editor';
	public const EDITOR_STYLE_HANDLE  = 'forwp-todo-tasks-block-editor';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function register_block(): void {
		self::register_editor_assets();

		register_block_type(
			FORWP_TODO_PATH . 'assets/blocks/todo-tasks',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
				'editor_script'   => self::EDITOR_SCRIPT_HANDLE,
				'editor_style'    => self::EDITOR_STYLE_HANDLE,
			)
		);
	}

	/**
	 * @return void
	 */
	private static function register_editor_assets(): void {
		$script_file  = FORWP_TODO_PATH . 'build/blocks/todo-tasks/index.asset.php';
		$editor_style = FORWP_TODO_PATH . 'build/blocks/todo-tasks/index.css';

		if ( is_readable( $script_file ) ) {
			$asset = include $script_file;

			wp_register_script(
				self::EDITOR_SCRIPT_HANDLE,
				forwp_todo_asset_url( 'build/blocks/todo-tasks/index.js' ),
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_localize_script(
				self::EDITOR_SCRIPT_HANDLE,
				'forwpTodoTasksBlockEditor',
				array(
					'isLoggedIn' => is_user_logged_in(),
					'canAccess'  => is_user_logged_in() && Settings::user_can_access_todo(),
				)
			);
		}

		if ( is_readable( $editor_style ) ) {
			$style_version = filemtime( $editor_style );
			$style_deps    = array( 'wp-components' );

			wp_register_style(
				self::EDITOR_STYLE_HANDLE,
				forwp_todo_asset_url( 'build/blocks/todo-tasks/index.css' ),
				$style_deps,
				false !== $style_version ? (string) $style_version : FORWP_TODO_VERSION
			);
		}
	}

	/**
	 * Ensure kanban styles load in the block editor canvas.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_assets(): void {
		wp_enqueue_style(
			'wp-components',
			includes_url( 'css/dist/components/style.min.css' ),
			array(),
			get_bloginfo( 'version' )
		);

		if ( wp_style_is( self::EDITOR_STYLE_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::EDITOR_STYLE_HANDLE );
		}
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $attributes );

		if ( ! is_user_logged_in() ) {
			return '<p class="forwp-todo-tasks-root forwp-todo-tasks-root--guest">' . esc_html__( 'Log in to view your tasks.', '4wp-todo' ) . '</p>';
		}

		if ( ! Settings::user_can_access_todo() ) {
			return '<p class="forwp-todo-tasks-root forwp-todo-tasks-root--denied">' . esc_html__( 'You do not have access to TODO.', '4wp-todo' ) . '</p>';
		}

		self::enqueue_assets();

		return self::render_mount_markup();
	}

	/**
	 * @return string
	 */
	public static function render_mount_markup(): string {
		return '<div id="forwp-todo-tasks-root" class="forwp-todo-tasks-root"></div>';
	}

	/**
	 * @return void
	 */
	public static function enqueue_page_assets(): void {
		self::enqueue_assets();
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$asset_file = FORWP_TODO_PATH . 'build/tasks-page/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset   = include $asset_file;
		$handle  = 'forwp-todo-tasks-page';
		$version = $asset['version'];

		wp_enqueue_style(
			'wp-components',
			includes_url( 'css/dist/components/style.min.css' ),
			array(),
			get_bloginfo( 'version' )
		);

		wp_enqueue_style(
			$handle,
			forwp_todo_asset_url( 'build/tasks-page/style-index.css' ),
			array( 'wp-components' ),
			$version
		);

		wp_enqueue_script(
			$handle,
			forwp_todo_asset_url( 'build/tasks-page/index.js' ),
			$asset['dependencies'],
			$version,
			true
		);

		wp_localize_script(
			$handle,
			'forwpTodoTasksPage',
			array(
				'restRoot' => rest_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'loadError' => __( 'Could not load tasks.', '4wp-todo' ),
				),
			)
		);

		wp_add_inline_script(
			$handle,
			'window.forwpTodoTasksPageInit && window.forwpTodoTasksPageInit();',
			'after'
		);
	}
}
