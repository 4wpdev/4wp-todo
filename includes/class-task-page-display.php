<?php
/**
 * Ensures the configured public tasks page shows the tasks block.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Public tasks page helpers (mirrors 4wp-notifications page display).
 */
final class Task_Page_Display {

	public const SEED_OPTION = 'forwp_todo_tasks_page_seeded_v1';

	public const BLOCK_NAME = 'forwp/todo-tasks';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', array( __CLASS__, 'maybe_inject_tasks_block' ), 9 );
		add_action( 'init', array( __CLASS__, 'maybe_seed_configured_page' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_page_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function maybe_enqueue_page_assets(): void {
		if ( ! is_singular( 'page' ) || ! is_user_logged_in() || ! Settings::user_can_access_todo() ) {
			return;
		}

		$page_id = absint( Settings::get()['tasks_page_id'] ?? 0 );
		if ( $page_id <= 0 || get_queried_object_id() !== $page_id ) {
			return;
		}

		Blocks\Tasks_Block::enqueue_page_assets();
	}

	/**
	 * Fallback when the configured page has no block yet.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function maybe_inject_tasks_block( string $content ): string {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$page_id = absint( Settings::get()['tasks_page_id'] ?? 0 );
		if ( $page_id <= 0 || get_the_ID() !== $page_id ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post || self::page_has_tasks_block( $post ) ) {
			return $content;
		}

		$mount = Blocks\Tasks_Block::render_mount_markup();
		if ( '' === $mount ) {
			return $content;
		}

		if ( '' === trim( $content ) ) {
			return $mount;
		}

		return $content . $mount;
	}

	/**
	 * One-time migration for already configured pages.
	 *
	 * @return void
	 */
	public static function maybe_seed_configured_page(): void {
		if ( get_option( self::SEED_OPTION ) ) {
			return;
		}

		$page_id = absint( Settings::get()['tasks_page_id'] ?? 0 );
		if ( $page_id > 0 ) {
			self::ensure_page_has_tasks_block( $page_id );
		}

		update_option( self::SEED_OPTION, 1, false );
	}

	/**
	 * Insert the tasks block when the page has no list yet.
	 *
	 * @param int $page_id Page ID.
	 * @return bool True when content was updated.
	 */
	public static function ensure_page_has_tasks_block( int $page_id ): bool {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 ) {
			return false;
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return false;
		}

		if ( self::page_has_tasks_block( $post ) ) {
			return false;
		}

		$block       = '<!-- wp:forwp/todo-tasks /-->';
		$content     = trim( (string) $post->post_content );
		$new_content = '' !== $content ? $content . "\n\n" . $block : $block;

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $new_content,
			)
		);

		return true;
	}

	/**
	 * @param \WP_Post|null $post Post object.
	 * @return bool
	 */
	public static function page_has_tasks_block( $post ): bool {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( self::BLOCK_NAME, $post );
	}
}
