<?php

namespace Arts\RepeaterTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin extends Base\Plugin {

	/** @var Services\Schema|null */
	private $schema = null;

	/** @var Services\Rows|null */
	private $rows = null;

	/** @var Services\Context|null */
	private $context = null;

	/** @var Services\LoopRepeat|null */
	private $loop_repeat = null;

	public function schema(): Services\Schema {
		if ( null === $this->schema ) {
			$this->schema = new Services\Schema();
		}

		return $this->schema;
	}

	public function rows(): Services\Rows {
		if ( null === $this->rows ) {
			$this->rows = new Services\Rows();
		}

		return $this->rows;
	}

	public function context(): Services\Context {
		if ( null === $this->context ) {
			$this->context = new Services\Context();
		}

		return $this->context;
	}

	public function loop_repeat(): Services\LoopRepeat {
		if ( null === $this->loop_repeat ) {
			$this->loop_repeat = new Services\LoopRepeat();
		}

		return $this->loop_repeat;
	}

	/** @return array<string, mixed> */
	protected function get_default_config(): array {
		return array();
	}

	/** @return array<string, mixed> */
	protected function get_default_strings(): array {
		return array();
	}

	protected function get_default_run_action(): string {
		return 'plugins_loaded';
	}

	protected function get_run_action_priority(): int {
		return 20;
	}

	/** @return array<string, class-string> */
	protected function get_managers_classes(): array {
		return array(
			'elementor' => Managers\Elementor::class,
			'ajax'      => Managers\Ajax::class,
			'assets'    => Managers\Assets::class,
		);
	}

	protected function add_actions(): void {
		add_action( 'elementor/dynamic_tags/register', array( $this->managers->elementor, 'register_tags' ) );
		add_action( 'elementor/controls/register', array( $this->managers->elementor, 'register_controls' ) );
		add_action( 'elementor/ajax/register_actions', array( $this->managers->ajax, 'register_ajax_actions' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this->managers->assets, 'enqueue_editor_js' ) );
		add_action( 'elementor/display_conditions/register', array( $this->managers->elementor, 'register_display_conditions' ) );
		add_action( 'elementor/element/loop-grid/section_query/before_section_end', array( $this->loop_repeat(), 'register_repeat_control' ) );
		add_action( 'elementor/element/loop-carousel/section_query/before_section_end', array( $this->loop_repeat(), 'register_repeat_control' ) );
		add_action( 'elementor/query/query_results', array( $this->loop_repeat(), 'expand_query_results' ), 10, 2 );
	}
}
