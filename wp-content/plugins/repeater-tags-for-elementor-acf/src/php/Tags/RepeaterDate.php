<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a date_time_picker sub-field from an ACF repeater row for DATETIME
 * consumers (Pro's Countdown). Exact parity with Pro's acf-date-time: the ACF-formatted
 * value (which follows the field's display return_format) is re-parsed with that same
 * return_format and always emitted as machine-readable 'Y-m-d H:i:s'; parse failure fails
 * closed to ''. date_time_picker only — mirrors Pro's own whitelist.
 */
class RepeaterDate extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-date';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Date', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::DATETIME_CATEGORY );
	}

	/** @return array<int, string> */
	protected function get_accepted_sub_field_types(): array {
		return array( 'date_time_picker' );
	}

	/** @param array<string, mixed> $options */
	public function get_value( array $options = array() ): string {
		$resolved = $this->resolve_cell_with_meta();
		$value    = $resolved['value'];

		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		if ( '' === $resolved['return_format'] ) {
			return '';
		}

		$date = \DateTime::createFromFormat( $resolved['return_format'], $value );

		return $date instanceof \DateTime ? $date->format( 'Y-m-d H:i:s' ) : '';
	}
}
