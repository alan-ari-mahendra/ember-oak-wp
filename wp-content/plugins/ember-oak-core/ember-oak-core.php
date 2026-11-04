<?php
/**
 * Plugin Name: Ember & Oak Core
 * Description: Design system assets, SCF fields, shortcodes, bundle & subscription glue for Ember & Oak Coffee Roasters.
 * Version: 1.0.0
 * Text Domain: ember-oak
 */
defined( 'ABSPATH' ) || exit;

define( 'EO_VER', '1.0.0' );
define( 'EO_DIR', plugin_dir_path( __FILE__ ) );
define( 'EO_URL', plugin_dir_url( __FILE__ ) );

require_once EO_DIR . 'inc/fields.php';
require_once EO_DIR . 'inc/commerce.php';
require_once EO_DIR . 'inc/sections.php';
require_once EO_DIR . 'inc/product.php';
require_once EO_DIR . 'inc/seo.php';
require_once EO_DIR . 'inc/search.php';
require_once EO_DIR . 'inc/account.php';

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'eo-fonts', 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400;1,9..144,600&family=Work+Sans:wght@400;500;600&display=swap', [], null );
	wp_enqueue_style( 'eo', EO_URL . 'assets/css/ember-oak.css', [], filemtime( EO_DIR . 'assets/css/ember-oak.css' ) );
	wp_enqueue_script( 'eo', EO_URL . 'assets/js/ember-oak.js', [ 'jquery' ], filemtime( EO_DIR . 'assets/js/ember-oak.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_localize_script( 'eo', 'EO', [
		'cartUrl' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
		'i18n'    => [ 'added' => __( 'added to cart', 'ember-oak' ), 'view' => __( 'View cart', 'ember-oak' ) ],
	] );
} );

add_filter( 'body_class', fn( $c ) => array_merge( $c, [ 'eo-site' ] ) );

// Add to toast: prevent WC "added to cart" notice duplication on shop UI.
add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
