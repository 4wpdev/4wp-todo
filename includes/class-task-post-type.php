<?php
/**
 * Internal CPT — taxonomy object type for TODO terms (tasks stay native notes).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers forwp_todo post type (no public task posts in v0.3).
 */
final class Task_Post_Type {

	public const POST_TYPE = 'forwp_todo';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 8 );
	}

	/**
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'TODO tasks', '4wp-todo' ),
					'singular_name' => __( 'TODO task', '4wp-todo' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'taxonomies'          => array(
					Task_Taxonomies::CATEGORY,
					Task_Taxonomies::STATUS,
					Task_Taxonomies::WORKFLOW,
				),
			)
		);
	}
}
