<?php
/**
 * Map native editor notes (block metadata.noteId) to frontend block targets.
 *
 * @package ForWP\Todo
 */

namespace ForWP\Todo;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves which rendered block on the frontend belongs to an editor note.
 */
final class Editor_Note_Locator {

	/** @var array<int, array<string, mixed>>|null */
	private static ?array $cache = null;

	/** @var int */
	private static int $cache_post_id = 0;

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array{block_name: string, block_index: int}>
	 */
	public static function map_for_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		if ( self::$cache_post_id === $post_id && null !== self::$cache ) {
			return self::$cache;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		$blocks  = parse_blocks( $content );
		$counts  = array();
		$map     = array();

		self::walk_blocks(
			$blocks,
			static function ( array $block ) use ( &$counts, &$map ): void {
				$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
				if ( '' === $name ) {
					return;
				}

				$index = $counts[ $name ] ?? 0;
				$counts[ $name ] = $index + 1;

				$note_ids = self::note_ids_from_block( $block );
				foreach ( $note_ids as $note_id ) {
					$map[ $note_id ] = array(
						'block_name'  => $name,
						'block_index' => $index,
					);
				}
			}
		);

		self::$cache_post_id = $post_id;
		self::$cache         = $map;

		return $map;
	}

	/**
	 * @param int $note_id  Note comment ID.
	 * @param int $post_id  Parent post ID.
	 * @return array{block_name: string, block_index: int}|null
	 */
	public static function locate( int $note_id, int $post_id ): ?array {
		$map = self::map_for_post( $post_id );

		return $map[ $note_id ] ?? null;
	}

	/**
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<int>
	 */
	private static function note_ids_from_block( array $block ): array {
		$metadata = $block['attrs']['metadata'] ?? null;
		if ( ! is_array( $metadata ) || empty( $metadata['noteId'] ) ) {
			return array();
		}

		$raw   = $metadata['noteId'];
		$items = is_array( $raw ) ? $raw : array( $raw );
		$ids   = array();

		foreach ( $items as $value ) {
			$id = absint( $value );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * @param list<array<string, mixed>> $blocks Parsed blocks.
	 * @param callable                   $callback Receives each block.
	 * @return void
	 */
	private static function walk_blocks( array $blocks, callable $callback ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$callback( $block );

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'], $callback );
			}
		}
	}
}
