<?php

namespace ArtsRepeaterTags\Arts\Base\Plugins;

use ArtsRepeaterTags\Arts\Base\Containers\ManagersContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @template TManagers of ManagersContainer
 * @phpstan-consistent-constructor
 */
abstract class BasePlugin {
	/**
	 * Per-class singleton storage, keyed by `static::class` so subclasses each get their own instance.
	 *
	 * @var array<class-string, static>
	 */
	private static $instances = array();

	/**
	 * URL of `admin-ajax.php`, shared across all subclasses; lazily set on the first `instance()` call.
	 *
	 * @var string|null
	 */
	private static $ajax_url;

	/**
	 * Common arguments exposed to managers (dir_path, dir_url, ajax_url).
	 *
	 * @var array<string, mixed>
	 */
	protected $args;

	/**
	 * Plugin options. Empty by default; populated when subclasses override `add_options()`.
	 *
	 * @var array<string, mixed>
	 */
	protected $options;

	/**
	 * Plugin config; filtered via `{filter_portion}/config` during `apply_filters()`.
	 *
	 * @var array<string, mixed>
	 */
	protected $config;

	/**
	 * Plugin strings; filtered via `{filter_portion}/strings` during `apply_filters()`.
	 *
	 * Values may nest arbitrarily deep when paired with a string lookup that traverses
	 * dot-notation paths (see `\Arts\Store\Shared\Managers\Traits\Strings::get_string()`).
	 *
	 * @var array<string, mixed>
	 */
	protected $strings;

	/**
	 * WordPress action on which `run()` executes; filtered via `{filter_portion}/run_action`.
	 *
	 * @var string
	 */
	protected $run_action;

	/**
	 * Container of instantiated managers. Subclasses may narrow `TManagers` to a custom
	 * `ManagersContainer` subclass for typed peer access.
	 *
	 * @var TManagers
	 */
	protected $managers;

	/**
	 * Per-class singleton accessor. Also lazily initializes the shared `$ajax_url` on first call.
	 *
	 * @return static
	 */
	public static function instance(): static {
		$cls = static::class;

		if ( ! isset( self::$instances[ $cls ] ) ) {
			self::$instances[ $cls ] = new static();
		}

		if ( self::$ajax_url === null ) {
			self::$ajax_url = admin_url( 'admin-ajax.php' );
		}

		return self::$instances[ $cls ];
	}

	/**
	 * @return void
	 */
	final protected function __construct() {
		$this->init();
	}

	/** Singletons must not be cloneable. */
	private function __clone(): void { }

	/** Singletons must not be restorable from a serialized payload. */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Bootstrap sequence: init properties → apply config filters → add+init managers →
	 * `do_after_init_managers` → init options → register run action → `do_after_run_action`.
	 *
	 * Filters and actions registered by `add_filters()` / `add_actions()` are added later, inside `run()`.
	 *
	 * @return void
	 */
	protected function init(): void {
		$this->init_properties();
		$this->apply_filters();
		$this->add_managers();
		$this->init_managers();
		$this->do_after_init_managers();
		$this->add_options();
		$this->add_run_action();
		$this->do_after_run_action();
	}

	/**
	 * Initialize the managers container and seed `$args`, `$config`, `$strings`, `$run_action`
	 * from subclass defaults.
	 *
	 * @return void
	 */
	private function init_properties(): void {
		$this->init_managers_container();
		$this->args       = array(
			'dir_path' => $this->get_plugin_dir_path(),
			'dir_url'  => $this->get_plugin_dir_url(),
			'ajax_url' => self::$ajax_url,
		);
		$this->config     = $this->get_default_config();
		$this->strings    = $this->get_default_strings();
		$this->run_action = $this->get_default_run_action();
	}

	/**
	 * Instantiate the managers container. Subclasses may override to substitute a typed
	 * `ManagersContainer` subclass (see `@template TManagers`) for IDE/static-analysis support.
	 *
	 * @return void
	 */
	protected function init_managers_container(): void {
		/** @var TManagers $managers */
		$managers       = new ManagersContainer();
		$this->managers = $managers;
	}

	/**
	 * Default config; filterable via `{filter_portion}/config`.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function get_default_config(): array;

	/**
	 * Default strings; filterable via `{filter_portion}/strings`.
	 *
	 * Returns a map of localized strings. Implementations may nest values arbitrarily
	 * deep when paired with a string lookup that traverses dot-notation paths (see
	 * `\Arts\Store\Shared\Managers\Traits\Strings::get_string()`).
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function get_default_strings(): array;

	/**
	 * Map of container key → manager class-string. Instantiated in declared order during `init()`.
	 *
	 * @return array<string, class-string>
	 */
	abstract protected function get_managers_classes(): array;

	/**
	 * WordPress action hook on which `run()` executes; filterable via `{filter_portion}/run_action`.
	 *
	 * @return string
	 */
	abstract protected function get_default_run_action(): string;

	/**
	 * @return string Filesystem path of the directory containing the concrete subclass file.
	 */
	protected function get_plugin_dir_path(): string {
		$reflection = new \ReflectionClass( static::class );
		$file_name  = $reflection->getFileName();

		if ( $file_name === false ) {
			return '';
		}

		return plugin_dir_path( $file_name );
	}

	/**
	 * Resolve the public URL of the plugin/theme directory the subclass lives in.
	 *
	 * Tries direct path prefixes first, then falls back to realpath comparison (see symlink note below).
	 *
	 * @return string URL of the directory, or empty string if outside `WP_PLUGIN_DIR` and `get_theme_root()`.
	 */
	protected function get_plugin_dir_url(): string {
		$reflection = new \ReflectionClass( static::class );
		$file_name  = $reflection->getFileName();

		if ( $file_name === false ) {
			return '';
		}

		$dir_path = plugin_dir_path( $file_name );

		if ( strpos( $dir_path, WP_PLUGIN_DIR ) === 0 ) {
			$relative_path = str_replace( WP_PLUGIN_DIR, '', $dir_path );
			return plugins_url( $relative_path );
		}

		// Resolve symlinks — some hosts (e.g. Hostinger) use symlinked document roots,
		// causing ReflectionClass::getFileName() to return a realpath that doesn't match WP_PLUGIN_DIR.
		$real_dir_path   = wp_normalize_path( (string) realpath( dirname( $file_name ) ) ) . '/';
		$real_plugin_dir = wp_normalize_path( (string) realpath( WP_PLUGIN_DIR ) );

		if ( $real_plugin_dir && strpos( $real_dir_path, $real_plugin_dir ) === 0 ) {
			$relative_path = substr( $real_dir_path, strlen( $real_plugin_dir ) );
			return plugins_url( $relative_path );
		}

		$theme_root = get_theme_root();

		if ( strpos( $dir_path, $theme_root ) === 0 ) {
			$relative_path = str_replace( $theme_root, '', $dir_path );
			return get_theme_root_uri() . $relative_path;
		}

		$real_theme_root = wp_normalize_path( (string) realpath( $theme_root ) );

		if ( $real_theme_root && strpos( $real_dir_path, $real_theme_root ) === 0 ) {
			$relative_path = substr( $real_dir_path, strlen( $real_theme_root ) );
			return get_theme_root_uri() . $relative_path;
		}

		return '';
	}

	/**
	 * Run `{filter_portion}/{args,config,strings,run_action}` filters. Filtered values that fail
	 * runtime validation are silently discarded; the property keeps its prior value.
	 *
	 * @return void
	 */
	protected function apply_filters(): void {
		$name = $this->get_plugin_filters_portion_name();

		$args = apply_filters( "{$name}/args", $this->args );
		if ( $this->is_string_keyed_array( $args ) ) {
			$this->args = $args;
		}

		$config = apply_filters( "{$name}/config", $this->config );
		if ( $this->is_string_keyed_array( $config ) ) {
			$this->config = $config;
		}

		$strings = apply_filters( "{$name}/strings", $this->strings );
		if ( $this->is_string_array( $strings ) ) {
			$this->strings = $strings;
		}

		$run_action = apply_filters( "{$name}/run_action", $this->run_action );
		if ( is_string( $run_action ) ) {
			$this->run_action = $run_action;
		}
	}

	/**
	 * Runtime: just `is_array()`. The `@phpstan-assert-if-true` narrows the type for static
	 * analysis under the assumption that filter callbacks return string-keyed arrays.
	 *
	 * @param mixed $value
	 * @return bool
	 * @phpstan-assert-if-true array<string, mixed> $value
	 */
	private function is_string_keyed_array( $value ): bool {
		return is_array( $value );
	}

	/**
	 * Runtime: array whose values are all strings (keys are not inspected). The phpstan assertion
	 * narrows callers to `array<string, string>` on a true return.
	 *
	 * @param mixed $value
	 * @return bool
	 * @phpstan-assert-if-true array<string, string> $value
	 */
	private function is_string_array( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Priority used when registering `run()` against the run action.
	 *
	 * @return int
	 */
	protected function get_run_action_priority(): int {
		return 10;
	}

	/**
	 * Number of args passed from the run action hook into `run()`.
	 *
	 * @return int
	 */
	protected function get_run_action_accepted_args(): int {
		return 1;
	}

	/**
	 * Register `run()` on the configured action. If that action has already fired
	 * (subclass instantiated late), invoke `run()` directly so it isn't silently skipped.
	 *
	 * @return void
	 */
	protected function add_run_action(): void {
		if ( is_string( $this->run_action ) && ! empty( $this->run_action ) ) {
			if ( did_action( $this->run_action ) ) {
				$this->run();
			} else {
				$priority      = $this->get_run_action_priority();
				$accepted_args = $this->get_run_action_accepted_args();
				add_action( $this->run_action, array( $this, 'run' ), $priority, $accepted_args );
			}
		}
	}

	/**
	 * Run-action callback: add filters, add actions, then invoke the `do_run()` extension point.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->add_filters();
		$this->add_actions();
		$this->do_run();
	}

	/**
	 * Extension point invoked at the end of `run()`. No-op by default.
	 *
	 * @return void
	 */
	protected function do_run(): void {
	}

	/**
	 * Default: reset `$this->options` to an empty array. Subclasses override to populate it
	 * (e.g., from the database) before `run()` is scheduled.
	 *
	 * @return void
	 */
	protected function add_options(): void {
		$this->options = array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Extension point: register WordPress actions. Called from `run()`. No-op by default.
	 *
	 * @return void
	 */
	protected function add_actions(): void {
	}

	/**
	 * Extension point: register WordPress filters. Called from `run()`. No-op by default.
	 *
	 * @return void
	 */
	protected function add_filters(): void {
	}

	/**
	 * Extension point invoked in `init()` after all managers have been instantiated and initialized,
	 * before `add_options()`. No-op by default.
	 *
	 * @return void
	 */
	protected function do_after_init_managers(): void {
	}

	/**
	 * Extension point invoked as the final step of `init()`, after `add_run_action()`. No-op by default.
	 *
	 * @return void
	 */
	protected function do_after_run_action(): void {
	}

	/**
	 * Instantiate each manager from `get_managers_classes()` and store it in the managers container
	 * under its declared key. Pre-init: each manager only knows about itself at this point.
	 *
	 * @return void
	 */
	private function add_managers(): void {
		$manager_classes = $this->get_managers_classes();

		if ( empty( $manager_classes ) ) {
			return;
		}

		foreach ( $manager_classes as $key => $class ) {
			$this->managers->$key = $this->get_manager_instance( $class );
		}
	}

	/**
	 * Call `init($managers)` on each registered manager, passing the full container so each one
	 * can reach its peers. Managers without an `init()` method are skipped.
	 *
	 * @return void
	 */
	private function init_managers(): void {
		foreach ( $this->managers as $manager ) {
			if ( method_exists( $manager, 'init' ) ) {
				$manager->init( $this->managers );
			}
		}
	}

	/**
	 * Instantiate a manager with the plugin's `$args`, `$config`, `$strings`. Falls back to the
	 * no-arg constructor if reflection-based instantiation fails (e.g., reflection exception).
	 *
	 * @param class-string $class
	 * @return object
	 */
	private function get_manager_instance( $class ): object {
		try {
			$reflection = new \ReflectionClass( $class );
			return $reflection->newInstanceArgs( array( $this->args, $this->config, $this->strings ) );
		} catch ( \ReflectionException $e ) {
			return new $class();
		}
	}

	/**
	 * Build the filter-name prefix used by `apply_filters()`: namespace + class short name, lowercased,
	 * with `\` replaced by `/`. E.g. `Arts\Base\Plugins\BasePlugin` → `arts/base/plugins/baseplugin`.
	 *
	 * @return string
	 */
	protected function get_plugin_filters_portion_name(): string {
		$fully_qualified_class_name = static::class;

		$last_separator = strrpos( $fully_qualified_class_name, '\\' );

		if ( $last_separator === false ) {
			$namespace = '';
		} else {
			$namespace = substr( $fully_qualified_class_name, 0, $last_separator );
		}

		$class_name = ( new \ReflectionClass( static::class ) )->getShortName();

		return strtolower( str_replace( '\\', '/', $namespace ) ) . '/' . strtolower( $class_name );
	}
}
