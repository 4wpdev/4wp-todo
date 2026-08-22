<?php
/**
 * WordPress taxonomies for TODO grouping (Category, Status, Workflow).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

use ForWP\Todo\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and seeds classic taxonomies on the internal forwp_todo CPT.
 */
final class Task_Taxonomies {

	public const CATEGORY = 'forwp_todo_category';

	public const STATUS = 'forwp_todo_status';

	public const WORKFLOW = 'forwp_todo_workflow';

	public const WORKFLOW_PHASE_META = 'forwp_todo_phase';

	public const PHASE_OPEN   = 'open';

	public const PHASE_CLOSED = 'closed';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 9 );
		add_action( 'init', array( __CLASS__, 'register_term_meta' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_seed_defaults' ), 20 );
		add_action( 'created_' . self::WORKFLOW, array( __CLASS__, 'save_workflow_phase_from_request' ), 10, 2 );
		add_action( 'edited_' . self::WORKFLOW, array( __CLASS__, 'save_workflow_phase_from_request' ), 10, 2 );
		add_action( self::WORKFLOW . '_add_form_fields', array( __CLASS__, 'render_workflow_phase_field' ) );
		add_action( self::WORKFLOW . '_edit_form_fields', array( __CLASS__, 'render_workflow_phase_field_edit' ), 10, 2 );
		add_filter( 'manage_edit-' . self::WORKFLOW . '_columns', array( __CLASS__, 'workflow_columns' ) );
		add_filter( 'manage_' . self::WORKFLOW . '_custom_column', array( __CLASS__, 'workflow_column_content' ), 10, 3 );
	}

	/**
	 * Seed default terms on existing installs (not only activation).
	 *
	 * @return void
	 */
	public static function maybe_seed_defaults(): void {
		if ( get_option( 'forwp_todo_taxonomies_seeded', false ) ) {
			return;
		}

		$existing = get_terms(
			array(
				'taxonomy'   => self::CATEGORY,
				'hide_empty' => false,
				'number'     => 1,
			)
		);

		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			update_option( 'forwp_todo_taxonomies_seeded', true, false );
			return;
		}

		self::seed_defaults();
		update_option( 'forwp_todo_taxonomies_seeded', true, false );
	}

	/**
	 * Save workflow phase from the term add/edit form (defaults to open).
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy ID.
	 * @return void
	 */
	public static function save_workflow_phase_from_request( int $term_id, int $tt_id ): void {
		unset( $tt_id );

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core taxonomy form nonce.
		if ( isset( $_POST['forwp_todo_workflow_phase'] ) ) {
			$phase = self::sanitize_phase( wp_unslash( $_POST['forwp_todo_workflow_phase'] ) );
			update_term_meta( $term_id, self::WORKFLOW_PHASE_META, $phase );
			return;
		}

		if ( ! get_term_meta( $term_id, self::WORKFLOW_PHASE_META, true ) ) {
			update_term_meta( $term_id, self::WORKFLOW_PHASE_META, self::PHASE_OPEN );
		}
	}

	/**
	 * @return void
	 */
	public static function render_workflow_phase_field(): void {
		?>
		<div class="form-field forwp-todo-workflow-phase-wrap">
			<label for="forwp-todo-workflow-phase"><? esc_html_e( 'Note phase', '4wp-todo' ); ?></label>
			<select name="forwp_todo_workflow_phase" id="forwp-todo-workflow-phase">
				<option value="<?php echo esc_attr( self::PHASE_OPEN ); ?>">
					<? esc_html_e( 'While note is open', '4wp-todo' ); ?>
				</option>
				<option value="<?php echo esc_attr( self::PHASE_CLOSED ); ?>">
					<? esc_html_e( 'When note is closed', '4wp-todo' ); ?>
				</option>
			</select>
			<p class="description">
				<? esc_html_e( 'Workflow lifts apply only while the core Gutenberg note matches this phase.', '4wp-todo' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * @param \WP_Term $term Term object.
	 * @return void
	 */
	public static function render_workflow_phase_field_edit( $term ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$phase = self::sanitize_phase( get_term_meta( $term->term_id, self::WORKFLOW_PHASE_META, true ) );
		?>
		<tr class="form-field forwp-todo-workflow-phase-wrap">
			<th scope="row">
				<label for="forwp-todo-workflow-phase"><? esc_html_e( 'Note phase', '4wp-todo' ); ?></label>
			</th>
			<td>
				<select name="forwp_todo_workflow_phase" id="forwp-todo-workflow-phase">
					<option value="<?php echo esc_attr( self::PHASE_OPEN ); ?>" <? selected( self::PHASE_OPEN, $phase ); ?>>
						<? esc_html_e( 'While note is open', '4wp-todo' ); ?>
					</option>
					<option value="<?php echo esc_attr( self::PHASE_CLOSED ); ?>" <? selected( self::PHASE_CLOSED, $phase ); ?>>
						<? esc_html_e( 'When note is closed', '4wp-todo' ); ?>
					</option>
				</select>
				<p class="description">
					<? esc_html_e( 'Workflow lifts apply only while the core Gutenberg note matches this phase.', '4wp-todo' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param array<string, string> $columns Column headers.
	 * @return array<string, string>
	 */
	public static function workflow_columns( array $columns ): array {
		$columns['forwp_todo_phase'] = __( 'Note phase', '4wp-todo' );

		return $columns;
	}

	/**
	 * @param string $content     Column content.
	 * @param string $column_name   Column key.
	 * @param int    $term_id       Term ID.
	 * @return string
	 */
	public static function workflow_column_content( string $content, string $column_name, int $term_id ): string {
		unset( $content );

		if ( 'forwp_todo_phase' !== $column_name ) {
			return '';
		}

		$phase = self::sanitize_phase( get_term_meta( $term_id, self::WORKFLOW_PHASE_META, true ) );

		return self::PHASE_CLOSED === $phase
			? esc_html__( 'When closed', '4wp-todo' )
			: esc_html__( 'While open', '4wp-todo' );
	}

	/**
	 * @return void
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			self::CATEGORY,
			Task_Post_Type::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Task categories', '4wp-todo' ),
					'singular_name' => __( 'Task category', '4wp-todo' ),
					'menu_name'     => __( 'Categories', '4wp-todo' ),
					'all_items'     => __( 'All categories', '4wp-todo' ),
					'edit_item'     => __( 'Edit category', '4wp-todo' ),
					'view_item'     => __( 'View category', '4wp-todo' ),
					'update_item'   => __( 'Update category', '4wp-todo' ),
					'add_new_item'  => __( 'Add new category', '4wp-todo' ),
					'new_item_name' => __( 'New category name', '4wp-todo' ),
					'search_items'  => __( 'Search categories', '4wp-todo' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => Menu::PAGE_SLUG,
				'show_admin_column' => false,
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);

		register_taxonomy(
			self::STATUS,
			Task_Post_Type::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Task statuses', '4wp-todo' ),
					'singular_name' => __( 'Task status', '4wp-todo' ),
					'menu_name'     => __( 'Statuses', '4wp-todo' ),
					'all_items'     => __( 'All statuses', '4wp-todo' ),
					'edit_item'     => __( 'Edit status', '4wp-todo' ),
					'view_item'     => __( 'View status', '4wp-todo' ),
					'update_item'   => __( 'Update status', '4wp-todo' ),
					'add_new_item'  => __( 'Add new status', '4wp-todo' ),
					'new_item_name' => __( 'New status name', '4wp-todo' ),
					'search_items'  => __( 'Search statuses', '4wp-todo' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => Menu::PAGE_SLUG,
				'show_admin_column' => false,
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);

		register_taxonomy(
			self::WORKFLOW,
			Task_Post_Type::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Task workflows', '4wp-todo' ),
					'singular_name' => __( 'Task workflow', '4wp-todo' ),
					'menu_name'     => __( 'Workflows', '4wp-todo' ),
					'all_items'     => __( 'All workflows', '4wp-todo' ),
					'edit_item'     => __( 'Edit workflow', '4wp-todo' ),
					'view_item'     => __( 'View workflow', '4wp-todo' ),
					'update_item'   => __( 'Update workflow', '4wp-todo' ),
					'add_new_item'  => __( 'Add new workflow', '4wp-todo' ),
					'new_item_name' => __( 'New workflow name', '4wp-todo' ),
					'search_items'  => __( 'Search workflows', '4wp-todo' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => Menu::PAGE_SLUG,
				'show_admin_column' => false,
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_term_meta(): void {
		register_term_meta(
			self::WORKFLOW,
			self::WORKFLOW_PHASE_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => self::PHASE_OPEN,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_phase' ),
			)
		);
	}

	/**
	 * @param mixed $phase Raw phase value.
	 * @return string
	 */
	public static function sanitize_phase( $phase ): string {
		$phase = sanitize_key( (string) $phase );

		return self::PHASE_CLOSED === $phase ? self::PHASE_CLOSED : self::PHASE_OPEN;
	}

	/**
	 * Seed default terms once (activation or first load).
	 *
	 * @return void
	 */
	public static function seed_defaults(): void {
		$defaults = array(
			'category' => array(
				'qa'      => __( 'QA', '4wp-todo' ),
				'dev'     => __( 'Development', '4wp-todo' ),
				'seo'     => __( 'SEO', '4wp-todo' ),
				'content' => __( 'Content', '4wp-todo' ),
				'client'  => __( 'For client', '4wp-todo' ),
			),
			'status'   => array(
				'open'   => __( 'Open', '4wp-todo' ),
				'closed' => __( 'Closed', '4wp-todo' ),
			),
			'workflow' => array(
				'in_work'         => array(
					'label' => __( 'Take in work', '4wp-todo' ),
					'phase' => self::PHASE_OPEN,
				),
				'in_progress'     => array(
					'label' => __( 'In progress', '4wp-todo' ),
					'phase' => self::PHASE_OPEN,
				),
				'on_qa'           => array(
					'label' => __( 'On QA', '4wp-todo' ),
					'phase' => self::PHASE_OPEN,
				),
				'passed_reported' => array(
					'label' => __( 'Passed and reported', '4wp-todo' ),
					'phase' => self::PHASE_CLOSED,
				),
				'client_approved' => array(
					'label' => __( 'Client approved', '4wp-todo' ),
					'phase' => self::PHASE_CLOSED,
				),
			),
		);

		/**
		 * Filter default taxonomy terms seeded on install / first load.
		 *
		 * @param array<string, mixed> $defaults category, status, workflow arrays.
		 */
		$defaults = apply_filters( 'forwp_todo_default_taxonomy_terms', $defaults );

		if ( ! empty( $defaults['category'] ) && is_array( $defaults['category'] ) ) {
			self::seed_terms( self::CATEGORY, $defaults['category'] );
		}

		if ( ! empty( $defaults['status'] ) && is_array( $defaults['status'] ) ) {
			self::seed_terms( self::STATUS, $defaults['status'] );
		}

		if ( ! empty( $defaults['workflow'] ) && is_array( $defaults['workflow'] ) ) {
			self::seed_workflow_terms( $defaults['workflow'] );
		}
	}

	/**
	 * @param string               $taxonomy Taxonomy slug.
	 * @param array<string,string> $terms    slug => label.
	 * @return void
	 */
	private static function seed_terms( string $taxonomy, array $terms ): void {
		foreach ( $terms as $slug => $label ) {
			if ( term_exists( $slug, $taxonomy ) ) {
				continue;
			}

			wp_insert_term(
				$label,
				$taxonomy,
				array(
					'slug' => sanitize_key( $slug ),
				)
			);
		}
	}

	/**
	 * @param array<string, array{label: string, phase: string}> $terms slug => data.
	 * @return void
	 */
	private static function seed_workflow_terms( array $terms ): void {
		foreach ( $terms as $slug => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$label = isset( $data['label'] ) ? (string) $data['label'] : '';
			$phase = isset( $data['phase'] ) ? self::sanitize_phase( $data['phase'] ) : self::PHASE_OPEN;

			if ( '' === $label ) {
				continue;
			}

			if ( term_exists( $slug, self::WORKFLOW ) ) {
				continue;
			}

			$result = wp_insert_term(
				$label,
				self::WORKFLOW,
				array(
					'slug' => sanitize_key( (string) $slug ),
				)
			);

			if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
				continue;
			}

			update_term_meta( (int) $result['term_id'], self::WORKFLOW_PHASE_META, $phase );
		}
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<string, string> slug => label
	 */
	public static function choices( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$choices = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$choices[ $term->slug ] = $term->name;
		}

		return $choices;
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $slug     Term slug.
	 * @return string Sanitized slug or empty.
	 */
	public static function sanitize_slug( string $taxonomy, string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return '';
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );

		return ( $term instanceof \WP_Term ) ? $term->slug : '';
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $slug     Term slug.
	 * @return string
	 */
	public static function label( string $taxonomy, string $slug ): string {
		$term = get_term_by( 'slug', sanitize_key( $slug ), $taxonomy );

		return ( $term instanceof \WP_Term ) ? $term->name : '';
	}

	/**
	 * @param string $phase open|closed
	 * @return list<string>
	 */
	public static function workflow_slugs_for_phase( string $phase ): array {
		$phase = self::sanitize_phase( $phase );
		$terms = get_terms(
			array(
				'taxonomy'   => self::WORKFLOW,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$term_phase = self::sanitize_phase( get_term_meta( $term->term_id, self::WORKFLOW_PHASE_META, true ) );
			if ( $term_phase === $phase ) {
				$slugs[] = $term->slug;
			}
		}

		return $slugs;
	}

	/**
	 * @param string $slug Term slug.
	 * @return string open|closed
	 */
	public static function workflow_phase_for_slug( string $slug ): string {
		$term = get_term_by( 'slug', sanitize_key( $slug ), self::WORKFLOW );
		if ( ! $term instanceof \WP_Term ) {
			return self::PHASE_OPEN;
		}

		return self::sanitize_phase( get_term_meta( $term->term_id, self::WORKFLOW_PHASE_META, true ) );
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return list<array{slug: string, label: string, count: int}>
	 */
	public static function registry_terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$items[] = array(
				'slug'  => $term->slug,
				'label' => $term->name,
				'count' => (int) $term->count,
			);
		}

		return $items;
	}
}
