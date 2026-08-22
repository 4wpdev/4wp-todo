<?php
/**
 * Optional workflow lifts — backed by forwp_todo_workflow taxonomy (comment meta stores slug).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Micro-status while a note stays open or after it is resolved.
 */
final class Task_Workflow {

	public const META_KEY = '_forwp_todo_workflow';

	public const IN_WORK        = 'in_work';
	public const IN_PROGRESS    = 'in_progress';
	public const ON_QA          = 'on_qa';
	public const PASSED_REPORTED = 'passed_reported';
	public const CLIENT_APPROVED = 'client_approved';

	/**
	 * @return list<string>
	 */
	public static function open_lifts(): array {
		return Task_Taxonomies::workflow_slugs_for_phase( Task_Taxonomies::PHASE_OPEN );
	}

	/**
	 * @return list<string>
	 */
	public static function closed_lifts(): array {
		return Task_Taxonomies::workflow_slugs_for_phase( Task_Taxonomies::PHASE_CLOSED );
	}

	/**
	 * @param string $workflow Raw slug.
	 * @param bool   $is_open  Whether core note is open (not resolved).
	 * @return string Sanitized slug or empty.
	 */
	public static function sanitize( string $workflow, bool $is_open ): string {
		$workflow = Task_Taxonomies::sanitize_slug( Task_Taxonomies::WORKFLOW, $workflow );
		if ( '' === $workflow ) {
			return '';
		}

		$phase     = Task_Taxonomies::workflow_phase_for_slug( $workflow );
		$expected  = $is_open ? Task_Taxonomies::PHASE_OPEN : Task_Taxonomies::PHASE_CLOSED;

		return $phase === $expected ? $workflow : '';
	}

	/**
	 * @param string $workflow Workflow slug.
	 * @return string
	 */
	public static function label( string $workflow ): string {
		return Task_Taxonomies::label( Task_Taxonomies::WORKFLOW, $workflow );
	}

	/**
	 * @param bool $is_open Core note open state.
	 * @return array<string, string>
	 */
	public static function choices( bool $is_open ): array {
		$phase   = $is_open ? Task_Taxonomies::PHASE_OPEN : Task_Taxonomies::PHASE_CLOSED;
		$choices = array();

		foreach ( Task_Taxonomies::choices( Task_Taxonomies::WORKFLOW ) as $slug => $label ) {
			if ( Task_Taxonomies::workflow_phase_for_slug( $slug ) === $phase ) {
				$choices[ $slug ] = $label;
			}
		}

		return $choices;
	}
}
