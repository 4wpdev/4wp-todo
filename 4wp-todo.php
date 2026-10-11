<?php
/**
 * Plugin Name:       4WP TODO
 * Plugin URI:        https://github.com/4wpdev/4wp-todo
 * Description:       Team tasks and checklists for the 4WP plugin ecosystem — capture notes, assign categories, and track completion across projects.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            4wp.dev
 * Author URI:        https://4wp.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       4wp-todo
 *
 * @package ForWP\Todo
 */

defined( 'ABSPATH' ) || exit;

define( 'FORWP_TODO_VERSION', '1.0.2' );
define( 'FORWP_TODO_FILE', __FILE__ );
define( 'FORWP_TODO_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_TODO_URL', plugin_dir_url( __FILE__ ) );

$forwp_todo_loader = FORWP_TODO_PATH . 'includes/load.php';
if ( ! is_readable( $forwp_todo_loader ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p><strong>' . esc_html__( '4WP Todo is missing includes/load.php.', '4wp-todo' ) . '</strong> ' . esc_html__( 'Re-upload the complete plugin package.', '4wp-todo' ) . '</p></div>';
		}
	);
	return;
}

require_once $forwp_todo_loader;

if ( ! ForWP\Todo\load_required_includes( FORWP_TODO_PATH ) ) {
	return;
}

add_action( 'plugins_loaded', array( 'ForWP\Todo\Plugin', 'init' ) );

register_activation_hook( __FILE__, array( 'ForWP\Todo\Plugin', 'on_activation' ) );
