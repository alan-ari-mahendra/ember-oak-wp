<?php
defined( 'ABSPATH' ) || exit;

const EO_SUB_DISCOUNT    = 0.15;
const EO_BUNDLE_DISCOUNT = 0.10;
const EO_CADENCES        = [ 1, 2, 4 ];

/** Capture purchase mode + cadence from the product form. */
add_filter( 'woocommerce_add_cart_item_data', function ( $data, $product_id ) {
	if ( ( $_REQUEST['eo_mode'] ?? '' ) === 'sub' && get_post_meta( $product_id, '_eo_subscribable', true ) === 'yes' ) {
		$cad = (int) ( $_REQUEST['eo_cadence'] ?? 2 );
		$cad = in_array( $cad, EO_CADENCES, true ) ? $cad : 2;
		$data['eo_sub']                       = $cad;
		// Keys consumed by Subscriptions for WooCommerce → real recurring orders.
		$data['wps_sfw_subscription_number']   = $cad;
		$data['wps_sfw_subscription_interval'] = 'week';
	}
	return $data;
}, 30, 2 );

/** Keep one-time and subscription lines separate in cart. */
add_filter( 'woocommerce_get_cart_item_from_session', function ( $item, $values ) {
	foreach ( [ 'eo_sub', 'wps_sfw_subscription_number', 'wps_sfw_subscription_interval' ] as $k ) {
		if ( isset( $values[ $k ] ) ) { $item[ $k ] = $values[ $k ]; }
	}
	return $item;
}, 30, 2 );

/** Apply 15% / 10% after the subscription plugin has run (priority 999). */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
	foreach ( $cart->get_cart() as $item ) {
		$product = $item['data'];
		if ( ! isset( $item['eo_base'] ) ) {
			$cart->cart_contents[ $item['key'] ]['eo_base'] = (float) $product->get_regular_price() ?: (float) $product->get_price();
		}
		$base = $cart->cart_contents[ $item['key'] ]['eo_base'];
		if ( ! empty( $item['eo_sub'] ) ) {
			$product->set_price( round( $base * ( 1 - EO_SUB_DISCOUNT ), 2 ) );
		} elseif ( get_post_meta( $item['product_id'], '_eo_bundle', true ) === 'yes' ) {
			$product->set_price( round( $base * ( 1 - EO_BUNDLE_DISCOUNT ), 2 ) );
		}
	}
}, 1000 );

add_filter( 'woocommerce_get_item_data', function ( $out, $item ) {
	if ( ! empty( $item['eo_sub'] ) ) {
		$out[] = [ 'key' => __( 'Subscription', 'ember-oak' ), 'value' => sprintf( __( 'Every %d week(s) · save 15%%', 'ember-oak' ), $item['eo_sub'] ) ];
	}
	return $out;
}, 10, 2 );

/** Admin checkbox: product can be sold as subscription. */
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_checkbox( [ 'id' => '_eo_subscribable', 'label' => 'Subscribable (15%)', 'description' => 'Offer subscribe & save (1/2/4 weeks).' ] );
	woocommerce_wp_checkbox( [ 'id' => '_eo_bundle', 'label' => 'Bundle (10% off)', 'description' => 'Treat price as 3-bag bundle with 10% discount.' ] );
} );
add_action( 'woocommerce_process_product_meta', function ( $id ) {
	foreach ( [ '_eo_subscribable', '_eo_bundle' ] as $k ) {
		update_post_meta( $id, $k, isset( $_POST[ $k ] ) ? 'yes' : 'no' );
	}
} );

/** Helper: price HTML of bundle for sections. */
function eo_bundle_product() {
	$q = get_posts( [ 'post_type' => 'product', 'meta_key' => '_eo_bundle', 'meta_value' => 'yes', 'numberposts' => 1 ] );
	return $q ? wc_get_product( $q[0]->ID ) : null;
}

/** Cart count fragment for header badge. */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$n = WC()->cart->get_cart_contents_count();
	$f['span.eo-count'] = '<span class="count eo-count">' . $n . '</span>';
	return $f;
} );

/** AJAX add-to-cart for the variable-product form (uses WC cart API incl. our cart-item filters). */
function eo_ajax_add_to_cart() {
	$pid = absint( $_POST['product_id'] ?? 0 ); $vid = absint( $_POST['variation_id'] ?? 0 ); $qty = max( 1, absint( $_POST['quantity'] ?? 1 ) );
	$vars = [];
	foreach ( $_POST as $k => $v ) { if ( 0 === strpos( $k, 'attribute_' ) ) { $vars[ sanitize_title( $k ) ] = wc_clean( wp_unslash( $v ) ); } }
	if ( $pid && WC()->cart->add_to_cart( $pid, $qty, $vid, $vars ) ) {
		WC_AJAX::get_refreshed_fragments();
	} else {
		wp_send_json( [ 'error' => true, 'product_url' => get_permalink( $pid ) ] );
	}
}
add_action( 'wc_ajax_eo_add_to_cart', 'eo_ajax_add_to_cart' );

/** Handle non-JS POST fallback: variation attributes → WC native handler already reads attribute_* fields. */

/**
 * Bridge to Subscriptions for WooCommerce: it decides "is subscription" and reads the cadence from
 * product/variation meta. Cadence is chosen per cart line, so answer those reads from the cart item.
 */
function eo_cart_sub_for( $product_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || empty( WC()->cart->cart_contents ) ) { return 0; }
	foreach ( WC()->cart->cart_contents as $item ) {
		if ( ! empty( $item['eo_sub'] ) && in_array( (int) $product_id, [ (int) $item['product_id'], (int) $item['variation_id'] ], true ) ) { return (int) $item['eo_sub']; }
	}
	return 0;
}
add_filter( 'wps_sfw_check_subscription_product_type', function ( $is, $product ) {
	return $is || ( is_object( $product ) && eo_cart_sub_for( $product->get_id() ) );
}, 10, 2 );
add_filter( 'get_post_metadata', function ( $v, $id, $key ) {
	if ( null !== $v || ! in_array( $key, [ '_wps_sfw_product', 'wps_sfw_subscription_number', 'wps_sfw_subscription_interval' ], true ) ) { return $v; }
	$cad = eo_cart_sub_for( $id );
	if ( ! $cad ) { return $v; }
	return [ '_wps_sfw_product' => 'yes', 'wps_sfw_subscription_number' => $cad, 'wps_sfw_subscription_interval' => 'week' ][ $key ];
}, 10, 3 );
