<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Controls\RowPicker;
use Arts\RepeaterTags\Plugin;
use Arts\RepeaterTags\Services\Rows;
use Elementor\Controls_Manager;

/**
 * Shared picker cascade + cell resolver for the repeater row tags.
 *
 * Settings contract (frozen): repeater_field = ACF field KEY (repeater or flexible
 * content, incl. group-hosted); row_index = 0-based int (-1 last row, -2 current loop
 * row); sub_field_{$repeater_key} = depth-1 PATH within a row ('name' or 'grp.name' —
 * plain names are valid paths) or a nested repeater's path, which engages the child tier;
 * child_row_index_{$child_key} = 0-based int (-1 last row, NO -2);
 * child_sub_field_{$child_key} = path within the child row. Sub-field options are known
 * at registration time (one conditioned select per entry); the child tier's control pair
 * is revealed by Elementor's native multi-key AND condition.
 *
 * @phpstan-import-type SubFieldMeta from \Arts\RepeaterTags\Services\Schema
 * @phpstan-import-type ChildMeta from \Arts\RepeaterTags\Services\Schema
 */
trait BaseRepeaterTag {

	/**
	 * ACF sub-field TYPES this tag can render — only compatible sub-fields appear in
	 * its Sub-field select. Conservative by design: a type is listed only when its
	 * FORMATTED value is reliably normalizable by this tag's get_value()/render()
	 * (a pickable-but-empty-rendering option reads as breakage).
	 *
	 * @return array<int, string>
	 */
	abstract protected function get_accepted_sub_field_types(): array;

	public function get_group(): string {
		return 'arts-repeater-tags';
	}

	protected function register_controls(): void {
		$this->register_repeater_controls();
	}

	protected function register_repeater_controls(): void {
		$schema  = Plugin::instance()->schema();
		$options = $schema->get_repeater_options();

		$this->add_control(
			'repeater_field',
			array(
				'label'       => esc_html__( 'Repeater Field', 'repeater-tags-for-elementor-acf' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => empty( $options )
					? array( '' => esc_html__( 'No ACF repeater fields found', 'repeater-tags-for-elementor-acf' ) )
					: array( '' => esc_html__( 'Select…', 'repeater-tags-for-elementor-acf' ) ) + $options,
				'default'     => '',
			)
		);

		$this->add_control(
			'row_index',
			array(
				'label'       => esc_html__( 'Row', 'repeater-tags-for-elementor-acf' ),
				'type'        => RowPicker::TYPE,
				'label_block' => true,
				'options'     => array(),
				'default'     => '0',
				'condition'   => array( 'repeater_field!' => '' ),
			)
		);

		$accepted = $this->get_accepted_sub_field_types();

		foreach ( $schema->get_repeaters() as $key => $entry ) {
			// Nested repeaters pickable FOR THIS TAG: a child qualifies only when it holds
			// at least one accepted sub-field — the compat-map promise (no pickable
			// dead-ends) applied to the second tier. Filtered-out children register no
			// controls on this tag at all.
			$pickable_children = array();

			foreach ( $entry['children'] as $child_path => $child ) {
				$child_compatible = $this->filter_compatible_sub_fields( $child['sub_fields'], $accepted );

				if ( array() !== $child_compatible ) {
					$pickable_children[ $child_path ] = $child_compatible;
				}
			}

			$compatible = $this->filter_compatible_sub_fields( $entry['sub_fields'], $accepted );

			foreach ( $pickable_children as $child_path => $child_compatible ) {
				/* translators: %s: nested repeater label */
				$compatible[ $child_path ] = sprintf( esc_html__( '%s (nested repeater)', 'repeater-tags-for-elementor-acf' ), $entry['children'][ $child_path ]['label'] );
			}

			$this->add_control(
				'sub_field_' . $key,
				array(
					'label'       => esc_html__( 'Sub-field', 'repeater-tags-for-elementor-acf' ),
					'type'        => Controls_Manager::SELECT,
					'label_block' => true,
					'options'     => empty( $compatible )
						? array( '' => esc_html__( 'No compatible sub-fields', 'repeater-tags-for-elementor-acf' ) )
						: array( '' => esc_html__( 'Select…', 'repeater-tags-for-elementor-acf' ) ) + $compatible,
					'default'     => '',
					'condition'   => array( 'repeater_field' => $key ),
				)
			);

			// Second tier: one (row picker, sub-field select) pair per pickable nested
			// repeater. The picker carries instance args the JS view reads to switch into
			// child mode; Elementor forwards custom add_control() args to the control
			// model as-is.
			foreach ( $pickable_children as $child_path => $child_compatible ) {
				$child     = $entry['children'][ $child_path ];
				$condition = array(
					'repeater_field'    => $key,
					'sub_field_' . $key => $child_path,
				);

				$this->add_control(
					'child_row_index_' . $child['key'],
					array(
						'label'          => esc_html__( 'Nested Row', 'repeater-tags-for-elementor-acf' ),
						'type'           => RowPicker::TYPE,
						'label_block'    => true,
						'options'        => array(),
						'default'        => '0',
						'condition'      => $condition,
						'parent_control' => 'sub_field_' . $key,
						'child_path'     => $child_path,
					)
				);

				$this->add_control(
					'child_sub_field_' . $child['key'],
					array(
						'label'       => esc_html__( 'Nested Sub-field', 'repeater-tags-for-elementor-acf' ),
						'type'        => Controls_Manager::SELECT,
						'label_block' => true,
						'options'     => array( '' => esc_html__( 'Select…', 'repeater-tags-for-elementor-acf' ) ) + $child_compatible,
						'default'     => '',
						'condition'   => $condition,
					)
				);
			}
		}
	}

	/**
	 * @param array<string, SubFieldMeta> $sub_fields
	 * @param array<int, string>          $accepted
	 * @return array<string, string> Path ⇒ label, filtered to accepted sub-field types.
	 */
	private function filter_compatible_sub_fields( array $sub_fields, array $accepted ): array {
		$compatible = array();

		foreach ( $sub_fields as $path => $sub ) {
			if ( in_array( $sub['type'], $accepted, true ) ) {
				$compatible[ $path ] = $sub['label'];
			}
		}

		return $compatible;
	}

	/**
	 * Resolve the bound cell PLUS its schema meta in one walk. Type/return_format
	 * resolution is tier-aware (the child tier reads the child registry) and, for
	 * flexible content, row-aware — the addressed row's acf_fc_layout picks its layout's
	 * meta, so a layout-mismatched or orphaned row yields type '' (the tags' default
	 * normalization) after the name-keyed walk already missed.
	 *
	 * @return array{value: mixed, type: string, return_format: string}
	 */
	protected function resolve_cell_with_meta(): array {
		$miss = array(
			'value'         => null,
			'type'          => '',
			'return_format' => '',
		);

		$repeater_key = $this->get_settings( 'repeater_field' );

		if ( ! is_string( $repeater_key ) || '' === $repeater_key ) {
			return $miss;
		}

		// Unknown keys stay resolvable: the dev rows filter can synthesize rows for them
		// (field name ''), so proceed with a null entry instead of bailing.
		$entry = Plugin::instance()->schema()->get_entry( $repeater_key );

		$post_id       = Plugin::instance()->context()->resolve_post_id( $repeater_key );
		$valid_post_id = is_int( $post_id ) ? $post_id > 0 : '' !== $post_id;

		if ( ! $valid_post_id ) {
			return $miss;
		}

		$row_setting = $this->get_settings( 'row_index' );
		$row_index   = is_scalar( $row_setting ) ? (int) $row_setting : 0;

		// "Current loop row" sentinel: inside a repeat-mode Loop Grid/Carousel item this is
		// the row the card was expanded for; outside one (editing the card template, tag
		// preview) fall back to row 0 — a useful preview beats an empty card.
		if ( -2 === $row_index ) {
			$row_index = Plugin::instance()->loop_repeat()->resolve_current_row_index() ?? 0;
		}

		$sub_path = $this->get_settings( 'sub_field_' . $repeater_key );

		if ( ! is_string( $sub_path ) || '' === $sub_path ) {
			return $miss;
		}

		$rows_service = Plugin::instance()->rows();
		$rows         = $rows_service->get( $repeater_key, $post_id );

		// The "Last row" sentinel: -1 resolves to the final row of the CURRENT context
		// (0 rows → -1 → null, same fail-closed path as any missing index).
		if ( -1 === $row_index ) {
			$row_index = count( $rows ) - 1;
		}

		$row = $rows[ $row_index ] ?? null;

		if ( ! is_array( $row ) ) {
			return $miss;
		}

		$child = null !== $entry ? ( $entry['children'][ $sub_path ] ?? null ) : null;

		// Child tier — engaged iff the stored sub path addresses a nested repeater.
		if ( null !== $child ) {
			return $this->resolve_child_cell( $rows_service, $row, $sub_path, $child, $miss );
		}

		$value = $rows_service->walk_row_path( $row, $sub_path );

		if ( null !== $entry && 'flexible_content' === $entry['kind'] ) {
			$layout_name = isset( $row['acf_fc_layout'] ) && is_string( $row['acf_fc_layout'] ) ? $row['acf_fc_layout'] : '';
			$meta        = $entry['layouts'][ $layout_name ]['sub_fields'][ $sub_path ] ?? null;
		} else {
			$meta = null !== $entry ? ( $entry['sub_fields'][ $sub_path ] ?? null ) : null;
		}

		return array(
			'value'         => $value,
			'type'          => null !== $meta ? $meta['type'] : '',
			'return_format' => null !== $meta ? $meta['return_format'] : '',
		);
	}

	/**
	 * Child-tier cell resolution: the stored sub path addresses a nested repeater, so the
	 * row/index/sub-field triad it selects lives one level down, within the parent row.
	 *
	 * @param array<string, mixed>                                       $parent_row
	 * @param ChildMeta                                                  $child
	 * @param array{value: mixed, type: string, return_format: string}   $miss
	 * @return array{value: mixed, type: string, return_format: string}
	 */
	private function resolve_child_cell( Rows $rows_service, array $parent_row, string $sub_path, array $child, array $miss ): array {
		$child_sub = $this->get_settings( 'child_sub_field_' . $child['key'] );

		if ( ! is_string( $child_sub ) || '' === $child_sub ) {
			return $miss;
		}

		$child_rows    = $rows_service->get_child_rows( $parent_row, $sub_path );
		$index_setting = $this->get_settings( 'child_row_index_' . $child['key'] );
		$child_index   = is_scalar( $index_setting ) ? (int) $index_setting : 0;

		// "Last row" applies per tier; there is NO -2 on this tier (the loop sentinel
		// is top-tier-only) — a hand-saved -2 just misses.
		if ( -1 === $child_index ) {
			$child_index = count( $child_rows ) - 1;
		}

		$child_row = $child_rows[ $child_index ] ?? null;

		if ( ! is_array( $child_row ) ) {
			return $miss;
		}

		$meta = $child['sub_fields'][ $child_sub ] ?? null;

		return array(
			'value'         => $rows_service->walk_row_path( $child_row, $child_sub ),
			'type'          => null !== $meta ? $meta['type'] : '',
			'return_format' => null !== $meta ? $meta['return_format'] : '',
		);
	}

	/** @return mixed|null */
	protected function resolve_cell() {
		return $this->resolve_cell_with_meta()['value'];
	}

	/**
	 * One media cell across all three ACF return formats: an attachment ARRAY, an attachment
	 * ID, or a plain URL STRING. Shared by the Media tag (one cell) and the Gallery tag (one
	 * per item) — the shapes are identical, a gallery just arrives as a list of them.
	 *
	 * ACF's own attachment arrays carry the id as BOTH 'ID' and 'id'; rows synthesized through
	 * the `arts_repeater_tags/rows` filter may carry only one, so both are accepted.
	 *
	 * @param mixed $value
	 * @return array{id: int, url: string}|null Null when the value carries neither an id nor a
	 *                                          url — the Media tag renders its empty shape,
	 *                                          the Gallery tag drops the item.
	 */
	protected function normalize_media_value( $value ): ?array {
		if ( is_array( $value ) ) {
			$id = 0;

			if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) {
				$id = (int) $value['ID'];
			} elseif ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
				$id = (int) $value['id'];
			}

			return array(
				'id'  => $id,
				'url' => isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '',
			);
		}

		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return array(
				'id'  => (int) $value,
				'url' => (string) wp_get_attachment_url( (int) $value ),
			);
		}

		if ( is_string( $value ) && '' !== $value ) {
			return array(
				'id'  => 0,
				'url' => $value,
			);
		}

		return null;
	}

	/**
	 * color_picker values across both return formats: the default 'string' format passes
	 * through as saved (hex or rgba() string); the RGBA-'array' format ({red, green,
	 * blue, alpha}) becomes a CSS rgba() string. Anything else drops to ''.
	 *
	 * @param mixed $value
	 */
	protected function normalize_color_value( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$red   = isset( $value['red'] ) && is_numeric( $value['red'] ) ? (int) $value['red'] : null;
		$green = isset( $value['green'] ) && is_numeric( $value['green'] ) ? (int) $value['green'] : null;
		$blue  = isset( $value['blue'] ) && is_numeric( $value['blue'] ) ? (int) $value['blue'] : null;

		if ( null === $red || null === $green || null === $blue ) {
			return '';
		}

		$alpha = isset( $value['alpha'] ) && is_numeric( $value['alpha'] ) ? (float) $value['alpha'] : 1.0;

		return sprintf( 'rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha );
	}
}
