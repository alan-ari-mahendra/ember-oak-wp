<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a color_picker sub-field from an ACF repeater row. COLOR is one of
 * the two always-on dynamic categories — this tag appears on every style-tab color control.
 * ACF formats color_picker to a hex or rgba() string under its default 'string' return
 * format; the RGBA-'array' variant is normalized back to a CSS rgba() string.
 */
class RepeaterColor extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-color';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Color', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::COLOR_CATEGORY );
	}

	/** @return array<int, string> */
	protected function get_accepted_sub_field_types(): array {
		return array( 'color_picker' );
	}

	/** @param array<string, mixed> $options */
	public function get_value( array $options = array() ): string {
		return $this->normalize_color_value( $this->resolve_cell() );
	}
}
