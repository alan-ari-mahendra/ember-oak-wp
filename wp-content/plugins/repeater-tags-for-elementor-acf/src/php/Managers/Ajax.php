<?php

namespace Arts\RepeaterTags\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Base\Manager as BaseManager;

/**
 * @phpstan-import-type RepeaterEntry from \Arts\RepeaterTags\Services\Schema
 */
class Ajax extends BaseManager {

	/** @param \Elementor\Core\Common\Modules\Ajax\Module $ajax */
	public function register_ajax_actions( $ajax ): void {
		$ajax->register_ajax_action( 'arts_repeater_tags_get_rows', array( $this, 'handle_get_rows' ) );
	}

	/**
	 * Row enumeration for both picker tiers. Additive child params on the SAME action:
	 * when `child_path` is present the response enumerates that nested repeater's rows
	 * within `parent_row_index` instead of the top-tier rows.
	 *
	 * @param array<string, mixed> $data
	 * @return array{options: array<int, array{id: int, text: string}>}
	 */
	public function handle_get_rows( $data ): array {
		$repeater_key  = isset( $data['repeater_key'] ) && is_string( $data['repeater_key'] ) ? $data['repeater_key'] : '';
		$document_type = isset( $data['document_type'] ) && is_string( $data['document_type'] ) ? $data['document_type'] : '';
		$plugin        = \Arts\RepeaterTags\Plugin::instance();
		$repeater      = $plugin->schema()->get_entry( $repeater_key );

		// Whitelist against the enumerated schema (stronger than any format regex).
		if ( null === $repeater ) {
			return array( 'options' => array() );
		}

		$context = $this->resolve_request_context( $data, $repeater );

		if ( null === $context ) {
			return array( 'options' => array() );
		}

		$child_path = isset( $data['child_path'] ) && is_string( $data['child_path'] ) ? $data['child_path'] : '';

		if ( '' !== $child_path ) {
			// Child enumeration: the path must be a registered nested repeater of the
			// whitelisted parent — flex entries and unknown/deep paths fail closed. No
			// loop sentinel at this tier (frozen: child pickers have no -2).
			if ( ! isset( $repeater['children'][ $child_path ] ) ) {
				return array( 'options' => array() );
			}

			$parent_row_index = isset( $data['parent_row_index'] ) && is_numeric( $data['parent_row_index'] ) ? (int) $data['parent_row_index'] : 0;

			return array( 'options' => $plugin->rows()->get_child_row_options( $repeater_key, $child_path, $parent_row_index, $context ) );
		}

		return array( 'options' => $this->maybe_prepend_loop_sentinel( $plugin->rows()->get_row_options( $repeater_key, $context ), $document_type ) );
	}

	/**
	 * Resolve the editor preview payload into the ACF read context, enforcing the same
	 * capability model per branch as Elementor's own render_tags: options page capability /
	 * edit_term / edit_user / (post_type_archive → newest post →) edit_post. Null = no
	 * target or capability denied.
	 *
	 * @param array<string, mixed> $data
	 * @param RepeaterEntry        $repeater
	 * @return int|string|null
	 */
	private function resolve_request_context( array $data, array $repeater ): int|string|null {
		// Options-scoped repeaters read a site-wide store: the options page's own capability
		// replaces edit_post, and the preview post id is irrelevant.
		if ( '' !== $repeater['options_post_id'] ) {
			return current_user_can( $repeater['options_capability'] ) ? $repeater['options_post_id'] : null;
		}

		$post_id      = isset( $data['post_id'] ) && is_numeric( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		$preview_type = isset( $data['preview_type'] ) && is_string( $data['preview_type'] ) ? $data['preview_type'] : '';

		// Theme Builder archive templates: the editor's "Preview Dynamic Content as" is
		// preview_type ('{category}/{object}') + preview_id — translate it into the same
		// context the frontend ladder resolves, so the picker enumerates what will render.
		if ( str_starts_with( $preview_type, 'taxonomy/' ) && $post_id > 0 ) {
			return current_user_can( 'edit_term', $post_id ) ? 'term_' . $post_id : null;
		}

		// Author archives nest under the 'archive' category — Pro stores 'archive/author',
		// NOT a bare 'author' prefix (unlike the top-level 'taxonomy/' / 'post_type_archive/').
		if ( str_starts_with( $preview_type, 'archive/author' ) ) {
			$user_id = $post_id > 0 ? $post_id : get_current_user_id();

			return $user_id > 0 && current_user_can( 'edit_user', $user_id ) ? 'user_' . $user_id : null;
		}

		if ( str_starts_with( $preview_type, 'post_type_archive/' ) ) {
			// Mirror the render-time fallback (first post of the archive's default query) —
			// approximate by the newest post of that type.
			$post_type = substr( $preview_type, strlen( 'post_type_archive/' ) );
			$first     = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			$post_id   = isset( $first[0] ) ? (int) $first[0]->ID : 0;
		}

		// Same capability model as Elementor's own render_tags. Singular previews land here
		// directly: their preview_id IS a post id.
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}

		return $post_id;
	}

	/**
	 * "Current loop row" is offered only while editing a loop-item template — the one place
	 * the sentinel resolves. Unlike "Last row" it is prepended even to empty enumerations:
	 * a loop card is designed independently of the preview target's data, and the sentinel
	 * is the primary binding there.
	 *
	 * @param array<int, array{id: int, text: string}> $options
	 * @return array<int, array{id: int, text: string}>
	 */
	private function maybe_prepend_loop_sentinel( array $options, string $document_type ): array {
		if ( 'loop-item' !== $document_type ) {
			return $options;
		}

		array_unshift(
			$options,
			array(
				'id'   => -2,
				'text' => esc_html__( 'Current loop row', 'repeater-tags-for-elementor-acf' ),
			)
		);

		return $options;
	}
}
