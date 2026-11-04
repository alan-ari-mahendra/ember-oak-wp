<?php
defined( 'ABSPATH' ) || exit;

/** Tidy My Account menu labels; endpoints (incl. Subscriptions for WooCommerce) are untouched. */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$rename = [ 'dashboard' => 'Overview', 'edit-account' => 'Account details', 'customer-logout' => 'Log out' ];
	foreach ( $rename as $k => $l ) { if ( isset( $items[ $k ] ) ) { $items[ $k ] = $l; } }
	unset( $items['downloads'], $items['wps_memberships'] ); // downloads + memberships are not used by this store
	return $items;
} );

/** Wrap the account area in our page shell with a Fraunces heading (the page title is hidden by CSS). */
add_action( 'woocommerce_before_account_navigation', function () {
	if ( ! is_user_logged_in() ) { return; }
	echo '<div class="eo-acc-head"><div class="eyebrow">My account</div><h1 class="disp">Hello, <em>' . esc_html( wp_get_current_user()->display_name ) . '.</em></h1></div>';
} );
