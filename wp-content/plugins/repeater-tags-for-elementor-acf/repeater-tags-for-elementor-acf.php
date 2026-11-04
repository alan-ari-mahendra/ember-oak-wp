<?php
/**
 * Plugin Name: Arts Repeater Tags for Elementor and ACF
 * Description: ACF repeater sub-fields as native Elementor dynamic tags with direct row addressing — no loops, no templates.
 * Version: 1.0.1
 * Author: Artem Semkin
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: elementor
 * Text Domain: repeater-tags-for-elementor-acf
 * Plugin URI: https://artemsemkin.com/plugins/repeater-tags-for-elementor-acf/
 * Author URI: https://artemsemkin.com
 * Tested up to: 7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARTS_REPEATER_TAGS_PLUGIN_VERSION', '1.0.1' );

require_once __DIR__ . '/vendor/autoload.php';

// No dependency guard needed: "Requires Plugins: elementor" is enforced by WP 6.5+,
// every entry point below is an elementor/* hook (inert without Elementor), and the
// single ACF soft-check lives in Services\Schema (fails closed to empty enumeration).
// Plugin extends Base\Plugin (arts/base BasePlugin), which itself schedules run() on
// the hook/priority from Plugin::get_default_run_action()/get_run_action_priority().
\Arts\RepeaterTags\Plugin::instance();
