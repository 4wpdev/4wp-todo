<?php
/**
 * Registers 4WP ToDo comment meta on native notes.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes task fields on comment_type=note without duplicating storage.
 */
final class Task_Comment_Meta {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * @return void
	 */
	public static function register_meta(): void {
		$string_fields = array(
			Task_Repository::META_CATEGORY    => 'content',
			Task_Repository::META_CONTEXT_URL => '',
			Task_Repository::META_DUE_DATE    => '',
			Task_Repository::META_SOURCE      => 'note',
			Task_Repository::META_VIEWPORT    => '',
			Task_Repository::META_SELECTOR    => '',
			Task_Repository::META_ANCHOR      => '',
			Task_Workflow::META_KEY           => '',
		);

		foreach ( $string_fields as $key => $default ) {
			register_meta(
				'comment',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => $default,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'can_edit_note_meta' ),
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						if ( Task_Workflow::META_KEY === $key ) {
							return sanitize_key( (string) $value );
						}
						if ( Task_Repository::META_CONTEXT_URL === $key ) {
							return esc_url_raw( (string) $value );
						}
						return sanitize_text_field( (string) $value );
					},
				)
			);
		}

		register_meta(
			'comment',
			Task_Repository::META_ASSIGNEE,
			array(
				'type'           => 'integer',
				'single'         => true,
				'default'        => 0,
				'show_in_rest'   => true,
				'auth_callback'  => array( __CLASS__, 'can_edit_note_meta' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_edit_note_meta(): bool {
		return Settings::user_can_access_todo();
	}
}
