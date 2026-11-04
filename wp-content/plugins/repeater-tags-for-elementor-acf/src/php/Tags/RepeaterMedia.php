<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a media sub-field (image or file) from an ACF repeater row.
 * Returns ['id'=>int, 'url'=>string] — the shape both the IMAGE and MEDIA categories
 * expect. ACF Image and File fields in array format both carry `id` and `url` keys.
 */
class RepeaterMedia extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-media';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: Media', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::IMAGE_CATEGORY, TagsModule::MEDIA_CATEGORY );
	}

	/** @return array<int, string> */
	protected function get_accepted_sub_field_types(): array {
		return array( 'image', 'file' );
	}

	/**
	 * Handles all three ACF return formats for image/file sub-fields:
	 * array ({id,url}), id (int attachment ID), url (string).
	 *
	 * @param array<string, mixed> $options
	 * @return array{id: int, url: string}
	 */
	public function get_value( array $options = array() ): array {
		return $this->normalize_media_value( $this->resolve_cell() ) ?? array(
			'id'  => 0,
			'url' => '',
		);
	}
}
