<?php
/**
 * Load plugin PHP dependencies with graceful failure on incomplete installs.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required include paths relative to the plugin root.
 *
 * @return list<string>
 */
function get_required_include_files(): array {
	return array(
		'includes/functions.php',
		'includes/class-task-category.php',
		'includes/class-task-status.php',
		'includes/class-task-workflow.php',
		'includes/class-task-post-type.php',
		'includes/class-task-taxonomies.php',
		'includes/class-task-taxonomy.php',
		'includes/class-integration-api.php',
		'includes/class-task-comment-meta.php',
		'includes/class-task-repository.php',
		'includes/class-task-integration.php',
		'includes/class-editor-note-locator.php',
		'includes/class-settings.php',
		'includes/class-task-page-display.php',
		'includes/Blocks/class-tasks-block.php',
		'includes/class-plugin.php',
		'includes/Admin/class-menu.php',
		'includes/Frontend/class-overlay.php',
		'includes/Rest/class-tasks-rest.php',
	);
}

/**
 * Require plugin files or register an admin notice when the install is incomplete.
 *
 * @param string $plugin_dir Absolute plugin directory path.
 * @return bool True when every required file was loaded.
 */
function load_required_includes( string $plugin_dir ): bool {
	$missing = array();

	foreach ( get_required_include_files() as $relative ) {
		$path = $plugin_dir . $relative;
		if ( ! is_readable( $path ) ) {
			$missing[] = $relative;
			continue;
		}

		require_once $path;
	}

	if ( empty( $missing ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function () use ( $missing ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$list = implode( ', ', array_map( 'esc_html', $missing ) );
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
				esc_html__( '4WP ToDo is missing required files.', '4wp-todo' ),
				esc_html__( 'Re-upload the full plugin package. The site stayed online because the plugin stopped loading safely.', '4wp-todo' ),
				esc_html( $list )
			);
		}
	);

	return false;
}
