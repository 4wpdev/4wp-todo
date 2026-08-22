<?php
/**
 * Plugin bootstrap.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

use ForWP\Todo\Admin\Menu;
use ForWP\Todo\Frontend\Overlay;
use ForWP\Todo\Rest\Tasks_Rest;
use ForWP\Todo\Settings;
use ForWP\Todo\Task_Post_Type;
use ForWP\Todo\Task_Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin orchestrator.
 */
final class Plugin {

	/**
	 * @return void
	 */
	public static function init(): void {
		Task_Post_Type::register();
		Task_Taxonomies::register();
		Task_Integration::register();
		Task_Comment_Meta::register();
		Settings::register();
		Tasks_Rest::register();
		Task_Page_Display::register();
		Blocks\Tasks_Block::register();
		Overlay::register();

		if ( is_admin() ) {
			Menu::register();
		}
	}

	/**
	 * @return void
	 */
	public static function on_activation(): void {
		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			update_option( Settings::OPTION_KEY, Settings::defaults(), false );
		}

		Task_Post_Type::register_post_type();
		Task_Taxonomies::register_taxonomies();
		Task_Taxonomies::register_term_meta();
		Task_Taxonomies::seed_defaults();

		flush_rewrite_rules();
	}
}
