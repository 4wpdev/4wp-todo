<?php
/**
 * TODO taxonomy registry — WP taxonomies + dynamic Page dimension for admin UI and list filters.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Registry for task grouping dimensions.
 */
final class Task_Taxonomy {

	/**
	 * @param array<string, mixed> $dynamic Dynamic dimension data (e.g. posts).
	 * @return array<string, mixed>
	 */
	public static function registry( array $dynamic = array() ): array {
		$posts = isset( $dynamic['posts'] ) && is_array( $dynamic['posts'] ) ? $dynamic['posts'] : array();

		return array(
			'label'       => __( 'TODO', '4wp-todo' ),
			'description' => __(
				'Category, Status, and Workflow are WordPress taxonomies. Page is built from posts that have notes.',
				'4wp-todo'
			),
			'dimensions'  => array(
				self::dimension_category(),
				self::dimension_status(),
				self::dimension_workflow(),
				self::dimension_page( $posts ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function dimension_category(): array {
		return array(
			'id'           => 'category',
			'label'        => __( 'Category', '4wp-todo' ),
			'description'  => __( 'Team or department (QA, SEO, Content…). Managed under Categories.', '4wp-todo' ),
			'taxonomy'     => Task_Taxonomies::CATEGORY,
			'manage_url'   => self::manage_url( Task_Taxonomies::CATEGORY ),
			'filterable'   => true,
			'filter_param' => 'category',
			'all_label'    => __( 'All categories', '4wp-todo' ),
			'terms'        => Task_Taxonomies::registry_terms( Task_Taxonomies::CATEGORY ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function dimension_status(): array {
		return array(
			'id'           => 'status',
			'label'        => __( 'Status', '4wp-todo' ),
			'description'  => __( 'Open/close maps to core Gutenberg note state (_wp_note_status). Managed under Statuses.', '4wp-todo' ),
			'taxonomy'     => Task_Taxonomies::STATUS,
			'manage_url'   => self::manage_url( Task_Taxonomies::STATUS ),
			'filterable'   => true,
			'filter_param' => 'note_state',
			'all_label'    => __( 'All statuses', '4wp-todo' ),
			'terms'        => Task_Taxonomies::registry_terms( Task_Taxonomies::STATUS ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function dimension_workflow(): array {
		$open_terms   = array();
		$closed_terms = array();

		foreach ( Task_Taxonomies::registry_terms( Task_Taxonomies::WORKFLOW ) as $term ) {
			if ( Task_Taxonomies::PHASE_CLOSED === Task_Taxonomies::workflow_phase_for_slug( $term['slug'] ) ) {
				$closed_terms[] = $term;
			} else {
				$open_terms[] = $term;
			}
		}

		return array(
			'id'           => 'workflow',
			'label'        => __( 'Workflow', '4wp-todo' ),
			'description'  => __(
				'Optional lift on top of open/close — never replaces core note status. Managed under Workflows.',
				'4wp-todo'
			),
			'taxonomy'     => Task_Taxonomies::WORKFLOW,
			'manage_url'   => self::manage_url( Task_Taxonomies::WORKFLOW ),
			'filterable'   => true,
			'filter_param' => 'workflow',
			'all_label'    => __( 'All workflows', '4wp-todo' ),
			'terms'        => array_merge( $open_terms, $closed_terms ),
			'groups'       => array(
				array(
					'label' => __( 'While open', '4wp-todo' ),
					'terms' => $open_terms,
				),
				array(
					'label' => __( 'When closed', '4wp-todo' ),
					'terms' => $closed_terms,
				),
			),
		);
	}

	/**
	 * @param array<int, array{id: int, title: string}> $posts Posts with notes.
	 * @return array<string, mixed>
	 */
	private static function dimension_page( array $posts ): array {
		$terms = array();

		foreach ( $posts as $post ) {
			if ( empty( $post['id'] ) ) {
				continue;
			}

			$terms[] = array(
				'slug'  => (string) absint( $post['id'] ),
				'label' => isset( $post['title'] ) ? (string) $post['title'] : '',
			);
		}

		return array(
			'id'           => 'page',
			'label'        => __( 'Page', '4wp-todo' ),
			'description'  => __( 'Built dynamically from posts and pages that have notes — not a taxonomy.', '4wp-todo' ),
			'filterable'   => true,
			'filter_param' => 'post_id',
			'all_label'    => __( 'All pages', '4wp-todo' ),
			'dynamic'      => true,
			'terms'        => $terms,
		);
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private static function manage_url( string $taxonomy ): string {
		return admin_url(
			'edit-tags.php?taxonomy=' . rawurlencode( $taxonomy ) . '&post_type=' . rawurlencode( Task_Post_Type::POST_TYPE )
		);
	}

	/**
	 * @param string $workflow Raw workflow slug from a list filter.
	 * @return string Sanitized slug or empty when invalid.
	 */
	public static function sanitize_workflow( string $workflow ): string {
		return Task_Taxonomies::sanitize_slug( Task_Taxonomies::WORKFLOW, $workflow );
	}
}
