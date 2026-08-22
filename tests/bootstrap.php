<?php
/**
 * PHPUnit bootstrap for 4wp-todo.
 *
 * @package ForWP\Todo
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "Run: composer install (requires 4wp-dev-toolkit path repo).\n" );
	exit( 1 );
}

require_once $autoload;

require_once dirname( __DIR__ ) . '/includes/class-task-category.php';
require_once dirname( __DIR__ ) . '/includes/class-task-status.php';
require_once dirname( __DIR__ ) . '/includes/class-task-workflow.php';
