<?php

namespace ArtsRepeaterTags\Arts\Base\Managers;

use ArtsRepeaterTags\Arts\Base\Containers\ManagersContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for plugin managers. Constructed by `BasePlugin::add_managers()` with the plugin's
 * `$args`, `$config`, `$strings`; peer managers are wired in later via `init($managers)`.
 */
abstract class BaseManager {
	/**
	 * Plugin-level args injected by `BasePlugin` (`dir_path`, `dir_url`, `ajax_url`).
	 *
	 * @var array<string, mixed>
	 */
	protected $args;

	/**
	 * Plugin config, filtered upstream via `{filter_portion}/config`.
	 *
	 * @var array<string, mixed>
	 */
	protected $config;

	/**
	 * Plugin strings, filtered upstream via `{filter_portion}/strings`.
	 *
	 * @var array<string, string>
	 */
	protected $strings;

	/**
	 * Peer managers (self excluded). `null` until `init()` runs.
	 *
	 * @var ManagersContainer|null
	 */
	protected $managers;

	/** @var string */
	protected $plugin_dir_path;

	/** @var string */
	protected $plugin_dir_url;

	/** @var string */
	protected $plugin_ajax_url;

	/**
	 * @param array<string, mixed>  $args
	 * @param array<string, mixed>  $config
	 * @param array<string, string> $strings
	 */
	public function __construct( $args = array(), $config = array(), $strings = array() ) {
		$this->args = $args;

		if ( isset( $args['dir_path'] ) && is_string( $args['dir_path'] ) ) {
			$this->plugin_dir_path = $args['dir_path'];
		}

		if ( isset( $args['dir_url'] ) && is_string( $args['dir_url'] ) ) {
			$this->plugin_dir_url = $args['dir_url'];
		}

		if ( isset( $args['ajax_url'] ) && is_string( $args['ajax_url'] ) ) {
			$this->plugin_ajax_url = $args['ajax_url'];
		}

		$this->config  = $config;
		$this->strings = $strings;
	}

	/**
	 * Two-phase lifecycle hook called by `BasePlugin::init_managers()` once all managers exist:
	 * first run subclass property init, then copy peer managers into `$this->managers`.
	 *
	 * @param ManagersContainer $managers Full container from the plugin (includes self).
	 * @return void
	 */
	public function init( $managers ): void {
		$this->init_properties();
		$this->add_managers( $managers );
	}

	/**
	 * Copy peers into `$this->managers`, skipping self so a manager never holds a handle to itself.
	 *
	 * @param ManagersContainer $managers Full container from the plugin.
	 * @return void
	 */
	protected function add_managers( $managers ): void {
		if ( $this->managers === null ) {
			$this->managers = new ManagersContainer();
		}

		foreach ( $managers as $key => $manager ) {
			if ( $manager !== $this ) {
				$this->managers->$key = $manager;
			}
		}
	}

	/**
	 * Extension point invoked before peers are wired in. No-op by default.
	 *
	 * @return void
	 */
	protected function init_properties(): void {
	}

	/**
	 * Copy `$this->config[$property]` to `$this->$property` if the key is set (any value, incl. null/false).
	 *
	 * @param string $property
	 * @return void
	 */
	protected function init_property( $property ): void {
		if ( isset( $this->config[ $property ] ) ) {
			$this->$property = $this->config[ $property ];
		}
	}

	/**
	 * Copy `$this->config[$property]` to `$this->$property` only if it's a non-empty array.
	 *
	 * @param string $property
	 * @return void
	 */
	protected function init_array_property( $property ): void {
		if ( isset( $this->config[ $property ] ) && is_array( $this->config[ $property ] ) && ! empty( $this->config[ $property ] ) ) {
			$this->$property = $this->config[ $property ];
		}
	}

	/**
	 * Return `apply_filters($key, $default)`, memoized in a method-local static.
	 *
	 * The cache is shared across all instances and persists for the request, so the very first
	 * caller's `$default` is what subsequent callers see — later `$default` values are ignored.
	 *
	 * @param non-empty-string $key Filter name used as both the cache key and the filter tag.
	 * @param mixed            $default Default passed to `apply_filters` on the first call only.
	 * @return mixed
	 */
	protected function get_config( $key, $default = null ): mixed {
		/** @var array<non-empty-string, mixed> $config_cache */
		static $config_cache = array();

		if ( ! isset( $config_cache[ $key ] ) ) {
			$config_cache[ $key ] = apply_filters( $key, $default );
		}

		return $config_cache[ $key ];
	}
}
