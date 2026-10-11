<?php
/**
 * Minimal WordPress function stubs for PHPUnit (global namespace).
 *
 * @package ForWP\Todo
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- PHPUnit stubs mirroring core APIs; excluded from plugin ZIP.

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ): string {
		$key = strtolower( (string) $key );

		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * @param string $path Admin path.
	 * @return string
	 */
	function admin_url( $path = '' ): string {
		return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param mixed $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ): string {
		return (string) filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ): string {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ): string {
		return preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ?? '';
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Minimal WP_Term stub for unit tests.
	 */
	class WP_Term {
		/** @var int */
		public $term_id = 0;

		/** @var string */
		public $slug = '';

		/** @var string */
		public $name = '';
	}
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
