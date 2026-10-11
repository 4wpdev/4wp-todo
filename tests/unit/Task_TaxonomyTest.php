<?php
/**
 * @package ForWP\Todo
 */

declare( strict_types=1 );

namespace ForWP\Todo\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ForWP\Todo\Task_Category;
use ForWP\Todo\Task_Taxonomies;
use ForWP\Todo\Task_Taxonomy;
use ForWP\Todo\Task_Workflow;
use PHPUnit\Framework\TestCase;

/**
 * Category and workflow helper tests.
 */
class Task_TaxonomyTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );

		Functions\when( 'get_term_by' )->alias(
			function ( $field, $value, $taxonomy ) {
				if ( 'slug' !== $field ) {
					return false;
				}

				$known = array(
					Task_Taxonomies::CATEGORY => array(
						'seo' => 'SEO',
					),
					Task_Taxonomies::WORKFLOW => array(
						'on_qa'           => 'On QA',
						'client_approved' => 'Client approved',
					),
				);

				if ( ! isset( $known[ $taxonomy ][ $value ] ) ) {
					return false;
				}

				$term          = new \WP_Term();
				$term->term_id = 1;
				$term->slug    = $value;
				$term->name    = $known[ $taxonomy ][ $value ];

				return $term;
			}
		);

		Functions\when( 'get_term_meta' )->alias(
			function ( $term_id, $key ) {
				unset( $term_id );

				if ( Task_Taxonomies::WORKFLOW_PHASE_META !== $key ) {
					return '';
				}

				return 'on_qa' === 'on_qa' ? Task_Taxonomies::PHASE_OPEN : Task_Taxonomies::PHASE_CLOSED;
			}
		);

		Functions\when( 'get_terms' )->justReturn( array() );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_category_sanitize_accepts_registered_terms(): void {
		$this->assertSame( 'seo', Task_Category::sanitize( 'seo' ) );
		$this->assertSame( '', Task_Category::sanitize( 'unknown' ) );
	}

	/**
	 * @return void
	 */
	public function test_workflow_sanitize_respects_open_state(): void {
		Functions\when( 'get_term_meta' )->alias(
			function ( $term_id, $key ) {
				unset( $term_id, $key );
				return Task_Taxonomies::PHASE_OPEN;
			}
		);

		$this->assertSame( 'on_qa', Task_Workflow::sanitize( 'on_qa', true ) );

		Functions\when( 'get_term_meta' )->alias(
			function ( $term_id, $key ) {
				unset( $term_id, $key );
				return Task_Taxonomies::PHASE_CLOSED;
			}
		);

		$this->assertSame( '', Task_Workflow::sanitize( 'on_qa', true ) );
	}

	/**
	 * @return void
	 */
	public function test_taxonomy_registry_has_filterable_dimensions(): void {
		$registry = Task_Taxonomy::registry();

		$this->assertSame( 'TODO', $registry['label'] );
		$this->assertNotEmpty( $registry['dimensions'] );

		$ids = array_column( $registry['dimensions'], 'id' );
		$this->assertContains( 'category', $ids );
		$this->assertContains( 'status', $ids );
		$this->assertContains( 'workflow', $ids );
		$this->assertContains( 'page', $ids );

		foreach ( $registry['dimensions'] as $dimension ) {
			$this->assertTrue( $dimension['filterable'] );
			$this->assertNotEmpty( $dimension['filter_param'] );
		}

		$category = $registry['dimensions'][0];
		$this->assertSame( Task_Taxonomies::CATEGORY, $category['taxonomy'] );
		$this->assertNotEmpty( $category['manage_url'] );

		$page = $registry['dimensions'][3];
		$this->assertTrue( $page['dynamic'] );
		$this->assertArrayNotHasKey( 'taxonomy', $page );
	}

	/**
	 * @return void
	 */
	public function test_taxonomy_sanitize_workflow_filter(): void {
		$this->assertSame( 'on_qa', Task_Taxonomy::sanitize_workflow( 'on_qa' ) );
		$this->assertSame( '', Task_Taxonomy::sanitize_workflow( 'invalid' ) );
	}
}
