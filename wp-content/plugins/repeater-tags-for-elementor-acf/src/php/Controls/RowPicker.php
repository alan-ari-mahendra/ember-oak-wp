<?php

namespace Arts\RepeaterTags\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Select2-based row picker. PHP side inherits everything from the native Select2 control;
 * the custom JS view (same type key) does the AJAX population. get_type() must EXACTLY
 * match the JS addControlView() key — a mismatch fails silently.
 *
 * One control type serves both picker tiers, config-driven: a child-tier instance is
 * registered with two extra add_control() args — 'parent_control' (the parent Sub-field
 * setting id) and 'child_path' (the nested repeater's path within a row) — which Elementor
 * forwards to the JS control model as-is. Absent args = top-tier behavior.
 */
class RowPicker extends \Elementor\Control_Select2 {

	const TYPE = 'arts-repeater-tags-row-picker';

	public function get_type(): string {
		return self::TYPE;
	}
}
