<?php
/**
 * Core Gutenberg note resolution (read/write _wp_note_status only).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Maps native note open/close — we do not invent parallel open/done flags.
 */
final class Task_Status {

	public const CORE_META = '_wp_note_status';

	public const REOPEN   = 'reopen';
	public const RESOLVED = 'resolved';

	/**
	 * @param int $comment_id Note comment ID.
	 * @return bool True when core note is resolved/closed.
	 */
	public static function is_resolved( int $comment_id ): bool {
		return self::RESOLVED === self::get_core_status( $comment_id );
	}

	/**
	 * @param int $comment_id Note comment ID.
	 * @return string reopen|resolved
	 */
	public static function get_core_status( int $comment_id ): string {
		$status = (string) get_comment_meta( $comment_id, self::CORE_META, true );

		return self::RESOLVED === $status ? self::RESOLVED : self::REOPEN;
	}

	/**
	 * @param int    $comment_id Note comment ID.
	 * @param string $status     reopen|resolved
	 * @return bool
	 */
	public static function set_core_status( int $comment_id, string $status ): bool {
		$status = self::RESOLVED === sanitize_key( $status ) ? self::RESOLVED : self::REOPEN;

		return (bool) update_comment_meta( $comment_id, self::CORE_META, $status );
	}

	/**
	 * @param string $filter open|closed|'' for list queries.
	 * @return string
	 */
	public static function label_for_filter( string $filter ): string {
		return 'closed' === $filter
			? __( 'Closed', '4wp-todo' )
			: __( 'Open', '4wp-todo' );
	}
}
