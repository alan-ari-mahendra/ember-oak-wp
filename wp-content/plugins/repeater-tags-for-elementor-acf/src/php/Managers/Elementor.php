<?php

namespace Arts\RepeaterTags\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Base\Manager as BaseManager;
use Arts\RepeaterTags\Conditions\RowCount;
use Arts\RepeaterTags\Controls\RowPicker;
use Arts\RepeaterTags\Tags\RepeaterColor;
use Arts\RepeaterTags\Tags\RepeaterDate;
use Arts\RepeaterTags\Tags\RepeaterGallery;
use Arts\RepeaterTags\Tags\RepeaterMedia;
use Arts\RepeaterTags\Tags\RepeaterNumber;
use Arts\RepeaterTags\Tags\RepeaterText;
use Arts\RepeaterTags\Tags\RepeaterUrl;

class Elementor extends BaseManager {

	/** @param \Elementor\Core\DynamicTags\Manager $manager */
	public function register_tags( $manager ): void {
		$manager->register_group(
			'arts-repeater-tags',
			array( 'title' => esc_html__( 'Repeater Tags', 'repeater-tags-for-elementor-acf' ) )
		);

		$manager->register( new RepeaterText() );
		$manager->register( new RepeaterMedia() );
		$manager->register( new RepeaterUrl() );
		$manager->register( new RepeaterGallery() );
		$manager->register( new RepeaterNumber() );
		$manager->register( new RepeaterColor() );
		$manager->register( new RepeaterDate() );
	}

	/** @param \Elementor\Controls_Manager $controls_manager */
	public function register_controls( $controls_manager ): void {
		$controls_manager->register( new RowPicker() );
	}

	/**
	 * Fires only when Elementor Pro's Display Conditions module runs — inert on free.
	 *
	 * @param \ElementorPro\Modules\DisplayConditions\Classes\Conditions_Manager $conditions_manager
	 */
	public function register_display_conditions( $conditions_manager ): void {
		$conditions_manager->register_condition_instance( new RowCount() );
	}
}
