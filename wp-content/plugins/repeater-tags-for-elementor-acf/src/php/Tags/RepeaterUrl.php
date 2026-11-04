<?php

namespace Arts\RepeaterTags\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\DynamicTags\Module as TagsModule;

/**
 * Dynamic tag resolving a URL sub-field from an ACF repeater row. Handles both plain
 * URL strings and array values with a `url` key (ACF Link field in array return format).
 */
class RepeaterUrl extends \Elementor\Core\DynamicTags\Data_Tag {

	use BaseRepeaterTag;

	public function get_name(): string {
		return 'arts-repeater-url';
	}

	public function get_title(): string {
		return esc_html__( 'Repeater Row: URL', 'repeater-tags-for-elementor-acf' );
	}

	/** @return array<int, string> */
	public function get_categories(): array {
		return array( TagsModule::URL_CATEGORY );
	}

	/**
	 * url (string), link (string or {url,…} array), page_link (URL string — its
	 * 'multiple' variant yields an array of URL strings, first one taken), file (any
	 * return format resolves to the file URL). Relational types resolve to the linked
	 * object's URL — permalink, term link or author archive — taking the FIRST item of
	 * multi-value fields (post and user selection order is preserved by ACF — requeried
	 * results are re-sorted to match).
	 *
	 * @return array<int, string>
	 */
	protected function get_accepted_sub_field_types(): array {
		return array( 'url', 'link', 'page_link', 'file', 'post_object', 'relationship', 'taxonomy', 'user' );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public function get_value( array $options = array() ): string {
		$resolved = $this->resolve_cell_with_meta();
		$value    = $resolved['value'];

		switch ( $resolved['type'] ) {
			case 'post_object':
			case 'relationship':
				return $this->post_url( $this->first_item( $value ) );

			case 'taxonomy':
				return $this->term_url( $this->first_item( $value ) );

			case 'user':
				return $this->user_url( $this->first_item( $value ) );

			case 'page_link':
				$value = $this->first_item( $value );
				break;
		}

		if ( is_array( $value ) ) {
			return isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '';
		}

		// A file sub-field in 'id' return format: resolve the attachment URL.
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return (string) wp_get_attachment_url( (int) $value );
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * First element of a numeric-indexed list; associative arrays (a user field's 'array'
	 * return format) and scalars/objects pass through. Empty formatted values arrive as
	 * false/''/[] depending on the field type — all fall through to '' in the resolvers.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function first_item( $value ) {
		if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
			return $value[0];
		}

		return $value;
	}

	/** @param mixed $item */
	private function post_url( $item ): string {
		if ( $item instanceof \WP_Post ) {
			$link = get_permalink( $item );

			return is_string( $link ) ? $link : '';
		}

		if ( is_numeric( $item ) && (int) $item > 0 ) {
			$link = get_permalink( (int) $item );

			return is_string( $link ) ? $link : '';
		}

		return '';
	}

	/** @param mixed $item Stale term ids survive ACF's 'id' format — get_term_link() then returns WP_Error, dropped here. */
	private function term_url( $item ): string {
		if ( ! $item instanceof \WP_Term && ! ( is_numeric( $item ) && (int) $item > 0 ) ) {
			return '';
		}

		$link = get_term_link( $item instanceof \WP_Term ? $item : (int) $item );

		return is_string( $link ) ? $link : '';
	}

	/** @param mixed $item WP_User, the 'array' format's associative shape (uppercase ID key), or a bare id. */
	private function user_url( $item ): string {
		if ( $item instanceof \WP_User ) {
			return get_author_posts_url( $item->ID );
		}

		if ( is_array( $item ) && isset( $item['ID'] ) && is_numeric( $item['ID'] ) ) {
			return get_author_posts_url( (int) $item['ID'] );
		}

		if ( is_numeric( $item ) && (int) $item > 0 ) {
			return get_author_posts_url( (int) $item );
		}

		return '';
	}
}
