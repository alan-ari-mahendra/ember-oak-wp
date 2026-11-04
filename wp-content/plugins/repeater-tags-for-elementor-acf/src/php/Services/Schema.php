<?php

namespace Arts\RepeaterTags\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide ACF container enumeration (same no-post-context trick as Elementor Pro's own
 * ACF picker). Pickable entries are repeaters and flexible content fields — each group's
 * direct fields plus ONE hop inside a top-level group field (entry name becomes a dot
 * path). Sub-field maps are keyed by depth-1 PATHS within a row ('sub' or 'grp.sub');
 * nested repeaters inside a parent row become child entries (the second picker tier);
 * anything deeper is excluded by construction. Clone fields need no handling of their own:
 * ACF resolves them at field-LOAD time, so a 'seamless' clone arrives here already spliced
 * into the group as the real repeater (enumerating like any other, under a synthetic
 * composite key), while a 'group' clone stays a `clone`-typed container — not a repeater, so
 * it drops out with every other non-container type.
 *
 * @phpstan-type SubFieldMeta array{label: string, type: string, return_format: string}
 * @phpstan-type ChildMeta array{key: string, label: string, label_sub_field: string, sub_fields: array<string, SubFieldMeta>, text_sub_fields: array<int, string>}
 * @phpstan-type LayoutMeta array{label: string, sub_fields: array<string, SubFieldMeta>}
 * @phpstan-type RepeaterEntry array{label: string, name: string, kind: 'repeater'|'flexible_content', options_post_id: int|string, options_capability: string, label_sub_field: string, sub_fields: array<string, SubFieldMeta>, text_sub_fields: array<int, string>, children: array<string, ChildMeta>, layouts: array<string, LayoutMeta>}
 */
class Schema {

	/** @var array<string, RepeaterEntry>|null */
	private $repeaters = null;

	/**
	 * Pickable containers keyed by ACF field KEY. Entry semantics:
	 * - 'name' is a dot PATH from the context root ('rep' or 'grp.rep' — one group hop).
	 * - 'sub_fields' is keyed by leaf paths within a row; for flexible content it is the
	 *   layout-deduped UNION (per-row truth lives in 'layouts').
	 * - 'children' (repeater kind only) is keyed by the child repeater's path within a row.
	 * - 'label_sub_field' is a path too (in practice ACF's Collapsed always points at a
	 *   direct sub; matching is by sub KEY at any scanned depth).
	 *
	 * @return array<string, RepeaterEntry>
	 */
	public function get_repeaters(): array {
		if ( null !== $this->repeaters ) {
			return $this->repeaters;
		}

		$entries = array();

		// The one dependency combo WP can't express (Requires Plugins is conjunctive and
		// matches installed directory slugs — the repeater provider may be ACF Pro or the
		// SCF fork under different slugs): Elementor active, ACF missing. Fail closed
		// to an empty enumeration — the picker shows "No ACF repeater fields found", and
		// Rows/Ajax key off enumerated keys so no other ACF call site is reachable.
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$this->repeaters = $entries;

			return $this->repeaters;
		}

		foreach ( acf_get_field_groups() as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$fields = acf_get_fields( $group );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			$group_title     = $this->read_string( $group, 'title' );
			$options_context = $this->resolve_options_context( $group );

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['type'] ) || ! is_string( $field['type'] ) ) {
					continue;
				}

				$field_name  = $this->read_string( $field, 'name' );
				$field_label = $this->read_label( $field, $field_name );

				if ( 'repeater' === $field['type'] || 'flexible_content' === $field['type'] ) {
					$built = $this->build_entry( $field, '', sprintf( '%s — %s', $group_title, $field_label ), $options_context );

					if ( null !== $built ) {
						$entries[ $built['key'] ] = $built['entry'];
					}

					continue;
				}

				// ONE group hop from the root: containers inside a top-level group become
				// entries with a dot-path name. Group leaves themselves are not addressable
				// (tags address rows), and deeper nesting is out of scope by contract.
				if ( 'group' !== $field['type'] || '' === $field_name || str_contains( $field_name, '.' ) ) {
					continue;
				}

				foreach ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array() as $sub ) {
					if ( ! is_array( $sub ) || ! isset( $sub['type'] ) || ( 'repeater' !== $sub['type'] && 'flexible_content' !== $sub['type'] ) ) {
						continue;
					}

					$sub_name  = $this->read_string( $sub, 'name' );
					$sub_label = $this->read_label( $sub, $sub_name );
					$built     = $this->build_entry( $sub, $field_name, sprintf( '%s — %s → %s', $group_title, $field_label, $sub_label ), $options_context );

					if ( null !== $built ) {
						$entries[ $built['key'] ] = $built['entry'];
					}
				}
			}
		}

		$this->repeaters = $entries;

		return $this->repeaters;
	}

	/**
	 * Build one pickable container entry (a direct field or one hop inside a top-level
	 * group). The entry key is the container's ACF field KEY; its name is the dot path
	 * Rows reads from the context root.
	 *
	 * @param array<mixed>                                   $field
	 * @param string                                         $name_prefix     '' for direct fields; the enclosing group's name for hopped ones.
	 * @param string                                         $label           Composed picker label (pre-"(Options)").
	 * @param array{post_id: int|string, capability: string} $options_context
	 * @return array{key: string, entry: RepeaterEntry}|null Null when the field is not enumerable.
	 */
	private function build_entry( array $field, string $name_prefix, string $label, array $options_context ): ?array {
		$key  = $this->read_string( $field, 'key' );
		$name = $this->read_string( $field, 'name' );
		$type = $this->read_string( $field, 'type' );

		// A dotted NAME would corrupt path addressing — skipped at every level, fail closed.
		if ( '' === $key || '' === $name || str_contains( $name, '.' ) ) {
			return null;
		}

		if ( 'repeater' !== $type && 'flexible_content' !== $type ) {
			return null;
		}

		if ( '' !== $options_context['post_id'] ) {
			/* translators: %s: repeater label ("Group — Field") */
			$label = sprintf( esc_html__( '%s (Options)', 'repeater-tags-for-elementor-acf' ), $label );
		}

		$entry = array(
			'label'              => $label,
			'name'               => '' !== $name_prefix ? $name_prefix . '.' . $name : $name,
			'kind'               => $type,
			'options_post_id'    => $options_context['post_id'],
			'options_capability' => $options_context['capability'],
			'label_sub_field'    => '',
			'sub_fields'         => array(),
			'text_sub_fields'    => array(),
			'children'           => array(),
			'layouts'            => array(),
		);

		if ( 'repeater' === $type ) {
			// ACF's "Collapsed" presentation setting stores the sub-field KEY the admin
			// chose as the row title — the picker prefers the same sub-field.
			$collapsed = $this->read_string( $field, 'collapsed' );
			$scan      = $this->scan_row_fields(
				isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array(),
				$collapsed,
				true
			);

			$entry['label_sub_field'] = $scan['label_sub_field'];
			$entry['sub_fields']      = $scan['sub_fields'];
			$entry['text_sub_fields'] = $scan['text_sub_fields'];
			$entry['children']        = $scan['children'];
		} else {
			$built = $this->build_layouts( $field );

			$entry['sub_fields'] = $built['union'];
			$entry['layouts']    = $built['layouts'];
		}

		return array(
			'key'   => $key,
			'entry' => $entry,
		);
	}

	/**
	 * Walk ONE row level of ACF sub-field definitions into pickable leaf paths. Exactly one
	 * group hop: a group at depth 0 recurses once with a path/label prefix, a group met
	 * inside the hop is skipped. A repeater becomes a child entry only on the parent tier
	 * ($with_children) and is dropped otherwise (child rows / flex layouts — depth stop);
	 * flexible content below the top tier is always dropped. Dotted names are skipped
	 * everywhere (path separator collision).
	 *
	 * @param array<int|string, mixed> $fields        One level of ACF sub-field definitions.
	 * @param string                   $collapsed_key Owning repeater's 'collapsed' setting (sub KEY, '' unset).
	 * @param bool                     $with_children Collect nested repeaters as child entries.
	 * @param string                   $path_prefix   '' at depth 0; the group's name inside the hop.
	 * @param string                   $label_prefix  '' at depth 0; the group's label inside the hop.
	 * @return array{label_sub_field: string, sub_fields: array<string, SubFieldMeta>, text_sub_fields: array<int, string>, children: array<string, ChildMeta>}
	 */
	private function scan_row_fields( array $fields, string $collapsed_key, bool $with_children, string $path_prefix = '', string $label_prefix = '' ): array {
		$result = array(
			'label_sub_field' => '',
			'sub_fields'      => array(),
			'text_sub_fields' => array(),
			'children'        => array(),
		);

		foreach ( $fields as $sub ) {
			if ( ! is_array( $sub ) ) {
				continue;
			}

			$sub_key    = $this->read_string( $sub, 'key' );
			$sub_name   = $this->read_string( $sub, 'name' );
			$sub_label  = $this->read_label( $sub, $sub_name );
			$sub_type   = $this->read_string( $sub, 'type' );
			$sub_format = $this->read_string( $sub, 'return_format' );

			if ( '' === $sub_name || str_contains( $sub_name, '.' ) ) {
				continue;
			}

			$path  = '' !== $path_prefix ? $path_prefix . '.' . $sub_name : $sub_name;
			$label = '' !== $label_prefix ? sprintf( '%s → %s', $label_prefix, $sub_label ) : $sub_label;

			if ( 'group' === $sub_type ) {
				// The one allowed hop — a group met while already prefixed is out of scope.
				if ( '' !== $path_prefix ) {
					continue;
				}

				$inner = $this->scan_row_fields(
					isset( $sub['sub_fields'] ) && is_array( $sub['sub_fields'] ) ? $sub['sub_fields'] : array(),
					$collapsed_key,
					$with_children,
					$sub_name,
					$sub_label
				);

				$result['sub_fields']      = array_merge( $result['sub_fields'], $inner['sub_fields'] );
				$result['text_sub_fields'] = array_merge( $result['text_sub_fields'], $inner['text_sub_fields'] );
				$result['children']        = array_merge( $result['children'], $inner['children'] );

				if ( '' === $result['label_sub_field'] && '' !== $inner['label_sub_field'] ) {
					$result['label_sub_field'] = $inner['label_sub_field'];
				}

				continue;
			}

			if ( 'repeater' === $sub_type ) {
				// Never a leaf: a nested repeater is either a child entry (second picker
				// tier) or out of scope.
				if ( $with_children && '' !== $sub_key ) {
					$result['children'][ $path ] = $this->build_child_meta( $sub, $sub_key, $label );
				}

				continue;
			}

			if ( 'flexible_content' === $sub_type ) {
				continue;
			}

			if ( '' !== $collapsed_key && $sub_key === $collapsed_key ) {
				$result['label_sub_field'] = $path;
			}

			$result['sub_fields'][ $path ] = array(
				'label'         => $label,
				'type'          => $sub_type,
				'return_format' => $sub_format,
			);

			if ( in_array( $sub_type, array( 'text', 'textarea' ), true ) ) {
				$result['text_sub_fields'][] = $path;
			}
		}

		return $result;
	}

	/**
	 * Child-repeater meta for the second picker tier. Sub-field paths are within the CHILD
	 * row (one group hop allowed there too); repeaters inside the child are out of scope.
	 *
	 * @param array<mixed> $field ACF child-repeater definition.
	 * @return ChildMeta
	 */
	private function build_child_meta( array $field, string $key, string $label ): array {
		$collapsed = $this->read_string( $field, 'collapsed' );
		$scan      = $this->scan_row_fields(
			isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array(),
			$collapsed,
			false
		);

		return array(
			'key'             => $key,
			'label'           => $label,
			'label_sub_field' => $scan['label_sub_field'],
			'sub_fields'      => $scan['sub_fields'],
			'text_sub_fields' => $scan['text_sub_fields'],
		);
	}

	/**
	 * Per-layout sub-field maps plus the layout-deduped UNION that feeds the Sub-field
	 * select. Union values are plain paths: a picked path renders on any row whose layout
	 * has it and fails closed otherwise; type/return_format in the union come from the
	 * first layout defining the path (pickability only — render resolves the real type per
	 * row via acf_fc_layout). A path defined by several layouts keeps ONE option with
	 * merged layout prefixes ("Hero, Quote → Heading").
	 *
	 * @param array<mixed> $field ACF flexible_content definition.
	 * @return array{layouts: array<string, LayoutMeta>, union: array<string, SubFieldMeta>}
	 */
	private function build_layouts( array $field ): array {
		$layouts = array();
		$collect = array();

		foreach ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array() as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$layout_name  = $this->read_string( $layout, 'name' );
			$layout_label = $this->read_label( $layout, $layout_name );

			if ( '' === $layout_name ) {
				continue;
			}

			$scan = $this->scan_row_fields(
				isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array(),
				'',
				false
			);

			$layouts[ $layout_name ] = array(
				'label'      => $layout_label,
				'sub_fields' => $scan['sub_fields'],
			);

			foreach ( $scan['sub_fields'] as $path => $meta ) {
				if ( ! isset( $collect[ $path ] ) ) {
					$collect[ $path ] = array(
						'meta'    => $meta,
						'sources' => array(),
					);
				}

				$collect[ $path ]['sources'][] = $layout_label;
			}
		}

		$union = array();

		foreach ( $collect as $path => $collected ) {
			$meta          = $collected['meta'];
			$meta['label'] = sprintf( '%s → %s', implode( ', ', $collected['sources'] ), $meta['label'] );

			$union[ $path ] = $meta;
		}

		return array(
			'layouts' => $layouts,
			'union'   => $union,
		);
	}

	/**
	 * A field group is options-scoped when any location rule is `options_page == {menu_slug}`
	 * (the param is unambiguous — only ACF's options-page location registers it). The page's
	 * post_id is resolved per request via acf_get_valid_post_id() — never persisted, because
	 * multilingual suffixing happens inside that resolver.
	 *
	 * @param array<mixed> $group
	 * @return array{post_id: int|string, capability: string}
	 */
	private function resolve_options_context( array $group ): array {
		$none = array(
			'post_id'    => '',
			'capability' => '',
		);

		// acf_get_options_page() needs the Pro feature set (ACF Pro ships it, the SCF fork
		// bundles it too) — free ACF lacks it, so this scope simply never matches there.
		if ( ! function_exists( 'acf_get_options_page' ) || ! function_exists( 'acf_get_valid_post_id' ) ) {
			return $none;
		}

		$location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : array();

		foreach ( $location as $rule_group ) {
			if ( ! is_array( $rule_group ) ) {
				continue;
			}

			foreach ( $rule_group as $rule ) {
				if ( ! is_array( $rule )
					|| ! isset( $rule['param'], $rule['operator'], $rule['value'] )
					|| 'options_page' !== $rule['param']
					|| '==' !== $rule['operator']
					|| ! is_string( $rule['value'] ) ) {
					continue;
				}

				$page = acf_get_options_page( $rule['value'] );

				if ( ! is_array( $page ) || ! isset( $page['post_id'] ) ) {
					continue;
				}

				$post_id = acf_get_valid_post_id( $page['post_id'] );

				$is_valid = ( is_int( $post_id ) && $post_id > 0 ) || ( is_string( $post_id ) && '' !== $post_id );

				if ( ! $is_valid ) {
					continue;
				}

				return array(
					'post_id'    => $post_id,
					// 'edit_posts' is ACF's own options-page default capability.
					'capability' => isset( $page['capability'] ) && is_string( $page['capability'] ) && '' !== $page['capability']
						? $page['capability']
						: 'edit_posts',
				);
			}
		}

		return $none;
	}

	/** @return RepeaterEntry|null */
	public function get_entry( string $key ): ?array {
		return $this->get_repeaters()[ $key ] ?? null;
	}

	public function is_known_repeater( string $key ): bool {
		return null !== $this->get_entry( $key );
	}

	/** @param array<mixed> $arr */
	private function read_string( array $arr, string $key ): string {
		return isset( $arr[ $key ] ) && is_string( $arr[ $key ] ) ? $arr[ $key ] : '';
	}

	/** @param array<mixed> $arr */
	private function read_label( array $arr, string $fallback ): string {
		return isset( $arr['label'] ) && is_string( $arr['label'] ) && '' !== $arr['label'] ? $arr['label'] : $fallback;
	}

	/** @return array<string, string> Control options: key ⇒ "Group — Field label". */
	public function get_repeater_options(): array {
		$options = array();

		foreach ( $this->get_repeaters() as $key => $repeater ) {
			$options[ $key ] = $repeater['label'];
		}

		return $options;
	}
}
