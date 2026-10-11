<?php
/**
 * PHPUnit bootstrap for 4wp-todo.
 *
 * @package ForWP\Todo
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- PHPUnit bootstrap only; excluded from plugin ZIP.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'FORWP_TODO_PATH' ) ) {
	define( 'FORWP_TODO_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'FORWP_TODO_VERSION' ) ) {
	define( 'FORWP_TODO_VERSION', '1.0.0' );
}

$forwp_todo_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $forwp_todo_autoload ) ) {
	exit( "Run: composer install (requires 4wp-dev-toolkit path repo).\n" );
}

require_once $forwp_todo_autoload;

require_once __DIR__ . '/wp-functions.php';

require_once FORWP_TODO_PATH . 'includes/load.php';

if ( ! ForWP\Todo\load_required_includes( FORWP_TODO_PATH ) ) {
	exit( "Could not load required plugin includes.\n" );
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
