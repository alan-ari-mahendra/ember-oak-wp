<?php

namespace Arts\RepeaterTags\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Plugin;
use Elementor\Controls_Manager;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker;
use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;

/**
 * Pro Display Condition: show/hide an element by an ACF repeater's row count, resolved in
 * the current context (post, loop item, term/user archive, options page — the same ladder
 * the tags use, so it evaluates per Loop Grid item by construction). The class references
 * Pro classes but is only ever loaded from the elementor/display_conditions/register hook,
 * which never fires without Pro.
 *
 * Frozen contract: name arts-repeater-row-count · setting keys repeater_field (ACF field
 * KEY), comparator, rows_number. Condition instances are stateless singletons — saved
 * per-instance settings arrive whole as $args (keys = control names).
 */
class RowCount extends Condition_Base {

	public function get_name(): string {
		return 'arts-repeater-row-count';
	}

	public function get_label(): string {
		return esc_html__( 'Repeater Row Count', 'repeater-tags-for-elementor-acf' );
	}

	/** @param mixed $args */
	public function check( $args ): bool {
		if ( ! is_array( $args ) ) {
			return true;
		}

		$repeater_key = isset( $args['repeater_field'] ) && is_string( $args['repeater_field'] ) ? $args['repeater_field'] : '';

		// An unconfigured condition never hides content (mirrors Pro's in_categories).
		if ( '' === $repeater_key || ! Plugin::instance()->schema()->is_known_repeater( $repeater_key ) ) {
			return true;
		}

		$comparator  = isset( $args['comparator'] ) && is_string( $args['comparator'] ) ? $args['comparator'] : Comparator_Provider::COMPARATOR_IS;
		$rows_number = isset( $args['rows_number'] ) && is_numeric( $args['rows_number'] ) ? (int) $args['rows_number'] : 0;
		$post_id     = Plugin::instance()->context()->resolve_post_id( $repeater_key );
		$count       = count( Plugin::instance()->rows()->get( $repeater_key, $post_id ) );

		return Comparators_Checker::check_numeric_constraints( $comparator, $rows_number, $count );
	}

	public function get_options(): void {
		$options     = Plugin::instance()->schema()->get_repeater_options();
		$comparators = Comparator_Provider::get_comparators(
			array(
				Comparator_Provider::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
				Comparator_Provider::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
				Comparator_Provider::COMPARATOR_IS,
				Comparator_Provider::COMPARATOR_IS_NOT,
			)
		);

		$this->add_control(
			'repeater_field',
			array(
				'label'       => esc_html__( 'Repeater Field', 'repeater-tags-for-elementor-acf' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => empty( $options )
					? array( '' => esc_html__( 'No ACF repeater fields found', 'repeater-tags-for-elementor-acf' ) )
					: array( '' => esc_html__( 'Select…', 'repeater-tags-for-elementor-acf' ) ) + $options,
				'default'     => '',
				'required'    => true,
			)
		);

		$this->add_control(
			'comparator',
			array(
				'type'    => Controls_Manager::SELECT,
				'options' => $comparators,
				'default' => Comparator_Provider::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
			)
		);

		$this->add_control(
			'rows_number',
			array(
				'type'        => Controls_Manager::TEXT,
				'input_type'  => 'number',
				'variant'     => 'number',
				'placeholder' => esc_html__( 'Type a number…', 'repeater-tags-for-elementor-acf' ),
				'step'        => 1,
				'min'         => 0,
				'default'     => 1,
			)
		);
	}
}
