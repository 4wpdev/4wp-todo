<?php
/**
 * Task category — backed by forwp_todo_category taxonomy (comment meta stores slug).
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Category helpers — terms are managed in wp-admin under 4WP TODO → Categories.
 */
final class Task_Category {

	public const QA      = 'qa';
	public const DEV     = 'dev';
	public const SEO     = 'seo';
	public const CONTENT = 'content';
	public const CLIENT  = 'client';

	/**
	 * Default category slug when none is set on a note.
	 *
	 * @return string
	 */
	public static function default_slug(): string {
		$slug = self::sanitize( self::CONTENT );

		return '' !== $slug ? $slug : self::CONTENT;
	}

	/**
	 * @param string $category Raw category slug.
	 * @return string Sanitized slug or empty when invalid.
	 */
	public static function sanitize( string $category ): string {
		return Task_Taxonomies::sanitize_slug( Task_Taxonomies::CATEGORY, $category );
	}

	/**
	 * @param string $category Category slug.
	 * @return string Localized label.
	 */
	public static function label( string $category ): string {
		$label = Task_Taxonomies::label( Task_Taxonomies::CATEGORY, $category );

		return '' !== $label ? $label : __( 'Uncategorized', '4wp-todo' );
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function choices(): array {
		return Task_Taxonomies::choices( Task_Taxonomies::CATEGORY );
	}
}
