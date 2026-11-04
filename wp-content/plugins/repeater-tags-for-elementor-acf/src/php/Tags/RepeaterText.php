<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a text sub-field from an ACF repeater row.
 * Extends Tag (not Data_Tag) so Elementor's Before/After/Fallback affixes are available.
 */
class RepeaterText extends \Elementor\Core\DynamicTags\Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-text';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Text', 'repeater-tags-for-elementor-acf' );
	}

	/**
	 * POST_META alongside TEXT: widgets curate some fields to meta-backed tags only
	 * (Google Maps address is POST_META-exclusive; video/audio URL fields co-declare it).
	 * Repeater cells are meta-backed by definition — mirrors Pro's acf-text.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( TagsModule::TEXT_CATEGORY, TagsModule::POST_META_CATEGORY );
	}

	/**
	 * Scalar-string types render as-is; the rest get type-aware normalization in
	 * render(): wysiwyg (kses'd HTML), choice fields (labels, lists joined), google_map
	 * (its address), color_picker (the RGBA-'array' return format becomes an rgba()
	 * string). oembed stays excluded — its formatted value is embed HTML that kses
	 * would gut; a plain URL sub-field serves that case.
	 *
	 * @return array<int, string>
	 */
	protected function get_accepted_sub_field_types(): array {
		return array( 'text', 'textarea', 'email', 'url', 'number', 'range', 'date_picker', 'date_time_picker', 'time_picker', 'color_picker', 'wysiwyg', 'select', 'checkbox', 'radio', 'button_group', 'google_map' );
	}

	public function render(): void {
		$resolved = $this->resolve_cell_with_meta();
		$value    = $resolved['value'];

		switch ( $resolved['type'] ) {
			case 'wysiwyg':
				// Formatted value is HTML (ACF runs its the_content-like filter chain).
				// wp_kses_post is Pro's own treatment; plain types keep stricter esc_html.
				echo wp_kses_post( is_string( $value ) ? $value : '' );
				return;

			case 'google_map':
				// Formatted value is {address, lat, lng, …} — or boolean false when unset.
				echo esc_html( is_array( $value ) && isset( $value['address'] ) && is_string( $value['address'] ) ? $value['address'] : '' );
				return;

			case 'color_picker':
				echo esc_html( $this->normalize_color_value( $value ) );
				return;

			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				echo esc_html( $this->normalize_choice( $value ) );
				return;
		}

		echo esc_html( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Choice values across every return_format: 'value'/'label' are scalars, 'array' is
	 * {value, label}, multi-select/checkbox wrap any of those in a list ([] when empty).
	 * Labels win over values; lists join with ", "; anything unrecognized drops to ''.
	 *
	 * @param mixed $value
	 */
	private function normalize_choice( $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		if ( isset( $value['label'] ) && is_scalar( $value['label'] ) ) {
			return (string) $value['label'];
		}

		if ( isset( $value['value'] ) && is_scalar( $value['value'] ) ) {
			return (string) $value['value'];
		}

		$parts = array();

		foreach ( $value as $item ) {
			$part = $this->normalize_choice( $item );

			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}

		return implode( ', ', $parts );
	}
}
