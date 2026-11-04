<?php

namespace Arts\RepeaterTags\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Plugin;

/**
 * Default rows producer. Reads formatted rows (keyed by sub-field NAME at every level) via
 * get_field() with direct indexing — never have_rows()/the_row(), which mutate ACF's global
 * loop stack (unsafe inside render callbacks). Entry names are dot paths, so group-hosted
 * containers descend from the first segment. Wrapped by the dev-API filter.
 *
 * @phpstan-import-type RepeaterEntry from Schema
 * @phpstan-import-type LayoutMeta from Schema
 */
class Rows {

	const LABEL_MAX_LENGTH = 40;

	/** @var array<string, array<int, array<string, mixed>>> */
	private $memo = array();

	/**
	 * @param int|string $post_id Post ID, or an ACF context id ('options', 'term_5', 'user_3').
	 * @return array<int, array<string, mixed>>
	 */
	public function get( string $repeater_key, int|string $post_id ): array {
		$memo_key = $repeater_key . ':' . $post_id;

		if ( isset( $this->memo[ $memo_key ] ) ) {
			return $this->memo[ $memo_key ];
		}

		$entry         = Plugin::instance()->schema()->get_entry( $repeater_key );
		$field_path    = null !== $entry ? $entry['name'] : '';
		$valid_post_id = is_int( $post_id ) ? $post_id > 0 : '' !== $post_id;
		$acf_rows      = array();

		if ( '' !== $field_path && $valid_post_id ) {
			$acf_rows = $this->normalize_rows( $this->read_rows_at_path( $field_path, $post_id ) );
		}

		/**
		 * Dev API: override or synthesize rows from any source. The built-in ACF read is
		 * the default value; returning it untouched costs nothing. Fires ONCE per top-tier
		 * read — nested child-repeater rows ride INSIDE the parent rows this filter
		 * returns, so nested data inherits the override (no child-tier filter exists).
		 *
		 * @param array<int, array<string, mixed>> $acf_rows
		 * @param string                           $field_name The entry's dot PATH from the context
		 *                                                     root ('rep' or 'grp.rep'; '' if key
		 *                                                     unknown) — a plain name for direct fields.
		 * @param int|string                       $post_id    Post ID or ACF context id ('options', 'term_5', …).
		 */
		$rows = apply_filters( 'arts_repeater_tags/rows', $acf_rows, $field_path, $post_id );

		$this->memo[ $memo_key ] = $this->normalize_rows( $rows );

		return $this->memo[ $memo_key ];
	}

	/**
	 * Read the value at a dot path from the context root: get_field() on the FIRST segment
	 * (ACF formats the whole tree in that one call), then name-keyed descent. False or
	 * missing at any hop fails closed — an empty repeater/flexible content field reads as
	 * false; an unsaved GROUP still reads as an array of empty sub-values (the contained
	 * repeater is then false).
	 *
	 * @param int|string $post_id
	 * @return mixed
	 */
	private function read_rows_at_path( string $path, int|string $post_id ) {
		$segments = explode( '.', $path );
		$value    = get_field( $segments[0], $post_id, true );

		foreach ( array_slice( $segments, 1 ) as $segment ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$value = $value[ $segment ] ?? null;
		}

		return $value;
	}

	/**
	 * Value at a depth-1 path WITHIN one formatted row ('sub' or 'grp.sub'). Per-hop
	 * isset-equivalent semantics — parity with plain `$row[$sub] ?? null` indexing for
	 * single-segment paths.
	 *
	 * @param array<string, mixed> $row
	 * @return mixed Null when a segment is missing or a non-array intermediate is hit.
	 */
	public function walk_row_path( array $row, string $path ) {
		$value = $row;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) ) {
				return null;
			}

			$value = $value[ $segment ] ?? null;
		}

		return $value;
	}

	/**
	 * Child-repeater rows within an already-resolved parent row. ACF returns boolean FALSE
	 * (not an empty array) for an empty child repeater — normalize_rows absorbs it. Pure
	 * array walking over the memoized parent read: no extra memo, no ACF call, and parent
	 * filtering is inherited by construction.
	 *
	 * @param array<string, mixed> $parent_row
	 * @return array<int, array<string, mixed>>
	 */
	public function get_child_rows( array $parent_row, string $child_path ): array {
		return $this->normalize_rows( $this->walk_row_path( $parent_row, $child_path ) );
	}

	/**
	 * Child-tier picker options: same label derivation and "Last row" mode as the top tier,
	 * NO "-2 current loop row" (the sentinel is top-tier-only, frozen). Parent addressing
	 * uses editor-enumeration semantics: -1 = last parent row; -2 previews against row 0 —
	 * the loop sentinel only resolves per-item at render.
	 *
	 * @param int|string $post_id Post ID, or an ACF context id ('options', 'term_5', 'user_3').
	 * @return array<int, array{id: int, text: string}>
	 */
	public function get_child_row_options( string $repeater_key, string $child_path, int $parent_row_index, int|string $post_id ): array {
		$entry = Plugin::instance()->schema()->get_entry( $repeater_key );
		$child = null !== $entry ? ( $entry['children'][ $child_path ] ?? null ) : null;

		if ( null === $child ) {
			return array();
		}

		$rows = $this->get( $repeater_key, $post_id );

		if ( -2 === $parent_row_index ) {
			$parent_row_index = 0;
		}

		if ( -1 === $parent_row_index ) {
			$parent_row_index = count( $rows ) - 1;
		}

		$parent_row = $rows[ $parent_row_index ] ?? null;

		if ( ! is_array( $parent_row ) ) {
			return array();
		}

		$candidates = '' !== $child['label_sub_field']
			? array_merge( array( $child['label_sub_field'] ), $child['text_sub_fields'] )
			: $child['text_sub_fields'];

		return $this->build_row_options( $this->get_child_rows( $parent_row, $child_path ), $candidates );
	}

	/**
	 * Coerce an untrusted rows value (ACF read or filter return) into a clean
	 * list of string-keyed row arrays; anything else drops out.
	 *
	 * @param mixed $rows
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_rows( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = array();

			foreach ( $row as $sub_name => $sub_value ) {
				$clean[ (string) $sub_name ] = $sub_value;
			}

			$normalized[] = $clean;
		}

		return $normalized;
	}

	/**
	 * Row-picker options derived from the SAME rows the render resolves — what the picker
	 * shows is by construction what the tag will output. Repeater rows label by the
	 * sub-field the admin chose in ACF's "Collapsed" setting, then the first non-empty
	 * text/textarea; flexible content rows label by their layout. Display is 1-based,
	 * stored id is the 0-based index.
	 *
	 * @param int|string $post_id Post ID, or an ACF context id ('options', 'term_5', 'user_3').
	 * @return array<int, array{id: int, text: string}>
	 */
	public function get_row_options( string $repeater_key, int|string $post_id ): array {
		$rows  = $this->get( $repeater_key, $post_id );
		$entry = Plugin::instance()->schema()->get_entry( $repeater_key );

		if ( null !== $entry && 'flexible_content' === $entry['kind'] ) {
			return $this->build_flex_row_options( $rows, $entry['layouts'] );
		}

		$label_sub  = null !== $entry ? $entry['label_sub_field'] : '';
		$text_subs  = null !== $entry ? $entry['text_sub_fields'] : array();
		$candidates = '' !== $label_sub ? array_merge( array( $label_sub ), $text_subs ) : $text_subs;

		return $this->build_row_options( $rows, $candidates );
	}

	/**
	 * Shared option builder for the top repeater tier and the child tier. Candidates are
	 * depth-1 paths, read via walk_row_path in priority order.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<int, string>               $candidates Label sources in priority order.
	 * @return array<int, array{id: int, text: string}>
	 */
	private function build_row_options( array $rows, array $candidates ): array {
		$options = array();

		// "Last row" is a MODE, not a phantom index — offered only alongside real rows so
		// the empty state stays honest. Render maps -1 to count-1 in the current context.
		if ( array() !== $rows ) {
			$options[] = array(
				'id'   => -1,
				'text' => esc_html__( 'Last row', 'repeater-tags-for-elementor-acf' ),
			);
		}

		foreach ( $rows as $index => $row ) {
			$label = '';

			foreach ( $candidates as $path ) {
				$value = $this->walk_row_path( $row, $path );

				if ( is_string( $value ) && '' !== trim( $value ) ) {
					$label = trim( $value );
					break;
				}
			}

			$label = $this->truncate_label( $label );

			$options[] = array(
				'id'   => (int) $index,
				'text' => '' !== $label
					? sprintf( '%d — %s', $index + 1, $label )
					/* translators: %d: 1-based row number */
					: sprintf( esc_html__( 'Row %d', 'repeater-tags-for-elementor-acf' ), $index + 1 ),
			);
		}

		return $options;
	}

	/**
	 * Flexible content rows label by LAYOUT: "{n} — {Layout}" plus the row's first
	 * non-empty text/textarea OF ITS OWN LAYOUT as a snippet ("{n} — {Layout}: {snippet}").
	 * Orphaned-layout rows (stored layout no longer defined) show the raw acf_fc_layout
	 * name and never a snippet — ACF loads such a row as acf_fc_layout ALONE (no sub-field
	 * data at all), so name-keyed reads miss.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, LayoutMeta>        $layouts
	 * @return array<int, array{id: int, text: string}>
	 */
	private function build_flex_row_options( array $rows, array $layouts ): array {
		$options = array();

		if ( array() !== $rows ) {
			$options[] = array(
				'id'   => -1,
				'text' => esc_html__( 'Last row', 'repeater-tags-for-elementor-acf' ),
			);
		}

		foreach ( $rows as $index => $row ) {
			$layout_name = isset( $row['acf_fc_layout'] ) && is_string( $row['acf_fc_layout'] ) ? $row['acf_fc_layout'] : '';
			$layout      = $layouts[ $layout_name ] ?? null;
			$base        = null !== $layout ? $layout['label'] : $layout_name;
			$snippet     = '';

			if ( null !== $layout ) {
				foreach ( $layout['sub_fields'] as $path => $meta ) {
					if ( ! in_array( $meta['type'], array( 'text', 'textarea' ), true ) ) {
						continue;
					}

					$value = $this->walk_row_path( $row, $path );

					if ( is_string( $value ) && '' !== trim( $value ) ) {
						$snippet = $this->truncate_label( trim( $value ) );
						break;
					}
				}
			}

			if ( '' === $base ) {
				/* translators: %d: 1-based row number */
				$text = sprintf( esc_html__( 'Row %d', 'repeater-tags-for-elementor-acf' ), $index + 1 );
			} elseif ( '' !== $snippet ) {
				$text = sprintf( '%d — %s: %s', $index + 1, $base, $snippet );
			} else {
				$text = sprintf( '%d — %s', $index + 1, $base );
			}

			$options[] = array(
				'id'   => (int) $index,
				'text' => $text,
			);
		}

		return $options;
	}

	private function truncate_label( string $label ): string {
		if ( '' !== $label && mb_strlen( $label ) > self::LABEL_MAX_LENGTH ) {
			return mb_substr( $label, 0, self::LABEL_MAX_LENGTH ) . '…';
		}

		return $label;
	}
}
