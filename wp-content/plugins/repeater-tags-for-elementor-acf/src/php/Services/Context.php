<?php

namespace Arts\RepeaterTags\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Plugin;

/**
 * Resolves WHICH object a repeater read targets. Mirrors Elementor Pro's own ACF context
 * behavior (dynamic-value-provider.php) without referencing any Pro class: the loop-item
 * document type is compared as a plain string, and term/user contexts use ACF's
 * "term_{id}"/"user_{id}" post_id formats.
 */
class Context {

	/**
	 * Resolution ladder:
	 * 0. options-scoped repeater → its options-page post_id (site-wide store, no post context);
	 * 1. loop-item template render → get_the_ID() (Loop Grid iterates the_post(), so the
	 *    global post IS the current card);
	 * 2. frontend archive → queried WP_Term/WP_User as ACF term_/user_ id;
	 * 3. get_the_ID() — singular frontend AND every editor ajax_render_tags preview
	 *    (switch_to_post() sets the global post but never the queried object, so rung 2
	 *    self-skips in admin-ajax).
	 */
	public function resolve_post_id( string $repeater_key ): int|string {
		$entry = Plugin::instance()->schema()->get_entry( $repeater_key );

		if ( null !== $entry && '' !== $entry['options_post_id'] ) {
			return $entry['options_post_id'];
		}

		if ( $this->is_loop_item_render() ) {
			return (int) get_the_ID();
		}

		$queried = get_queried_object();

		if ( $queried instanceof \WP_Term ) {
			return 'term_' . $queried->term_id;
		}

		if ( $queried instanceof \WP_User ) {
			return 'user_' . $queried->ID;
		}

		return (int) get_the_ID();
	}

	/** No Pro class involved: get_current() is free core; 'loop-item' is Pro's document type STRING. */
	private function is_loop_item_render(): bool {
		$document = \Elementor\Plugin::$instance->documents->get_current();

		return ! empty( $document ) && 'loop-item' === $document::get_type();
	}
}
