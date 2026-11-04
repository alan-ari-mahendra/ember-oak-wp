<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a numeric sub-field from an ACF repeater row. NUMBER category
 * surfaces it on numeric controls (Counter, Progress); POST_META mirrors Pro's acf-number
 * (meta-curated fields accept it).
 */
class RepeaterNumber extends \Elementor\Core\DynamicTags\Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-number';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Number', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::NUMBER_CATEGORY, TagsModule::POST_META_CATEGORY );
	}

	/**
	 * number and range both format to plain numerics across all their settings —
	 * no return_format variant yields anything non-scalar.
	 *
	 * @return array<int, string>
	 */
	protected function get_accepted_sub_field_types(): array {
		return array( 'number', 'range' );
	}

	public function render(): void {
		$value = $this->resolve_cell();

		echo esc_html( is_scalar( $value ) ? (string) $value : '' );
	}
}
