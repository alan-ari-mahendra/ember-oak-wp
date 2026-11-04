<?php
defined( 'ABSPATH' ) || exit;

const EO_ICON_LOGO = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"><path d="M8 3c0 3-3 4-3 7a7 7 0 0 0 14 0c0-3-3-4-3-7"/><path d="M12 3c0 3-2 4-2 7a2 2 0 0 0 4 0c0-3-2-4-2-7"/></svg>';
const EO_ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>';

function eo_icons() {
	return [
		'farm'  => '<svg viewBox="0 0 24 24"><path d="M12 3v18M5 10c0 4 3 7 7 7s7-3 7-7"/><path d="M8 6c1 2 2.5 3 4 3s3-1 4-3"/></svg>',
		'clock' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg>',
		'score' => '<svg viewBox="0 0 24 24"><path d="M4 20l4-4M14 4l6 6-9 9H5v-6z"/></svg>',
		'leaf'  => '<svg viewBox="0 0 24 24"><path d="M12 21s-7-5-7-11a7 7 0 0 1 14 0c0 6-7 11-7 11z"/><path d="M9 10c1 2 2 3 3 3s2-1 3-3"/></svg>',
	];
}
function eo_img( $id, $size, $alt, $lazy = true, $cls = '' ) {
	if ( ! $id ) { return '<div class="ph" role="img" aria-label="' . esc_attr( $alt ) . '"></div>'; }
	return wp_get_attachment_image( $id, $size, false, [ 'alt' => $alt, 'loading' => $lazy ? 'lazy' : 'eager', 'class' => $cls ] );
}
function eo_logo() {
	return '<a href="' . esc_url( home_url( '/' ) ) . '" class="logo"><i aria-hidden="true">' . EO_ICON_LOGO . '</i><span>Ember &amp; Oak<small>Coffee Roasters</small></span></a>';
}
function eo_stars( $n ) { return '<span class="stars" role="img" aria-label="' . (int) $n . ' out of 5 stars">' . str_repeat( '★', (int) $n ) . str_repeat( '☆', 5 - (int) $n ) . '</span>'; }
function eo_wrap( $html, $cls = '' ) { return '<div class="eo ' . esc_attr( $cls ) . '">' . $html . '</div>'; }

/* ---------- Header / announcement ---------- */
add_shortcode( 'eo_announce', function () {
	$t = eo_home( 'announce' ) ?: 'Free US shipping on orders over $40 &middot; <b>Roasted Monday to Thursday, shipped within 48h</b>';
	return eo_wrap( '<div class="announce">' . wp_kses( $t, [ 'b' => [] ] ) . '</div>' );
} );

add_shortcode( 'eo_header', function () {
	$home = home_url( '/' );
	$links = [ 'Shop' => '#shop', 'Bundles' => '#bundle', 'Subscribe' => '#subscribe', 'Roastery' => '#story', 'Visit' => '#cafe' ];
	$nav = '';
	foreach ( $links as $l => $h ) { $nav .= '<a href="' . esc_url( $home . $h ) . '">' . esc_html( $l ) . '</a>'; }
	$count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$acct  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '#';
	$cart  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '#';
	return eo_wrap( '<header class="eo-header"><div class="wrap"><nav class="eo-nav" aria-label="Main">' . eo_logo() .
		'<div class="links" id="eo-links">' . $nav . '</div><div class="nav-r">' .
		'<button class="icon-btn" type="button" id="eo-search-toggle" aria-label="Search" aria-expanded="false" aria-controls="eo-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>' .
		'<a class="icon-btn hide-m" href="' . esc_url( $acct ) . '" aria-label="Account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></a>' .
		'<a class="icon-btn" href="' . esc_url( $cart ) . '" aria-label="Cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 13H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><span class="count eo-count">' . (int) $count . '</span></a>' .
		'<button class="icon-btn burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="eo-links"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>' .
		'</div></nav></div>' . eo_search_form( 'eo-search', true ) . '</header>' );
} );

/* ---------- Footer ---------- */
add_shortcode( 'eo_footer', function () {
	$home = home_url( '/' );
	$col = function ( $h, $items ) { $o = '<div><h5>' . esc_html( $h ) . '</h5><ul>'; foreach ( $items as $l => $u ) { $o .= '<li>' . ( $u ? '<a href="' . esc_url( $u ) . '">' . esc_html( $l ) . '</a>' : esc_html( $l ) ) . '</li>'; } return $o . '</ul></div>'; };
	$ig = eo_home( 'instagram' ) ?: '#'; $tk = eo_home( 'tiktok' ) ?: '#';
	$txt = eo_home( 'newsletter_text' ) ?: 'New lots, roast notes and the occasional brewing guide. One email every two weeks, nothing else.';
	ob_start(); ?>
	<footer class="eo-footer"><div class="wrap"><div class="fgrid">
		<div class="news"><?php echo eo_logo(); ?><p><?php echo esc_html( $txt ); ?></p>
			<form method="post" action="<?php echo esc_url( $home ); ?>" data-eo-newsletter><label for="eo-nl" class="screen-reader-text">Email</label><input id="eo-nl" name="email" type="email" required placeholder="Your email"><button class="btn btn-terra" type="submit">Subscribe</button></form></div>
		<?php
		echo $col( 'Shop', [ 'All coffee' => $home . '#shop', 'Bundles' => $home . '#bundle', 'Subscriptions' => $home . '#subscribe' ] );
		echo $col( 'Company', [ 'Our roastery' => $home . '#story', 'Visit' => $home . '#cafe' ] );
		echo $col( 'Help', [ 'Shipping & returns' => get_privacy_policy_url() ?: $home, 'My account' => wc_get_page_permalink( 'myaccount' ), 'FAQ' => $home . '#faq', 'hello@emberandoak.coffee' => '' ] ); ?>
	</div><div class="fbot"><span>© <?php echo date( 'Y' ); ?> Ember &amp; Oak Coffee Roasters. Portland, Oregon.</span>
		<div class="pay" aria-label="Accepted payments"><span>VISA</span><span>MC</span><span>AMEX</span><span>APPLE PAY</span><span>PAYPAL</span></div>
		<div class="soc"><a href="<?php echo esc_url( $ig ); ?>" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></a>
		<a href="<?php echo esc_url( $tk ); ?>" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.5 2.5 2.5 4.5 5 5"/></svg></a></div></div></div></footer>
	<div class="cart-toast" id="eo-toast" role="status" aria-live="polite"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D9A531" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg><div><b id="eo-toast-name"></b> &middot; <a href="<?php echo esc_url( wc_get_cart_url() ); ?>">View cart</a></div></div>
	<?php return eo_wrap( ob_get_clean() );
} );

/* ---------- Hero / ticker ---------- */
add_shortcode( 'eo_hero', function () {
	$g = 'eo_home';
	ob_start(); ?>
	<section class="hero"><div class="wrap"><div>
		<div class="eyebrow"><?php echo esc_html( $g( 'hero_eyebrow' ) ); ?></div>
		<h1 class="disp"><?php echo wp_kses( $g( 'hero_title' ), [ 'em' => [], 'br' => [] ] ); ?></h1>
		<p class="lead"><?php echo esc_html( $g( 'hero_desc' ) ); ?></p>
		<div class="ctas"><a href="#shop" class="btn btn-terra">Shop fresh roasts <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a><a href="#subscribe" class="btn btn-line">Start a subscription</a></div>
		<div class="trust"><?php foreach ( (array) $g( 'trust' ) as $r ) { echo '<span>' . wp_kses( $r['html'], [ 'b' => [], 'span' => [ 'class' => [], 'aria-hidden' => [] ] ] ) . '</span>'; } ?></div>
	</div>
	<div class="hero-visual"><div class="blob" aria-hidden="true"></div>
		<div class="beans" aria-hidden="true" style="left:6%;top:58%"><span style="--r:30deg"></span><span style="left:26px;top:14px;--r:-20deg"></span><span style="left:8px;top:30px;--r:70deg"></span></div>
		<div class="beans" aria-hidden="true" style="right:12%;top:22%"><span style="--r:-40deg"></span><span style="left:22px;top:12px;--r:15deg"></span></div>
		<div class="sticker" aria-hidden="true"><?php echo esc_html( $g( 'hero_sticker' ) ); ?><small><?php echo esc_html( $g( 'hero_sticker_sub' ) ); ?></small></div>
		<div class="bag-hero" role="img" aria-label="Bag of <?php echo esc_attr( $g( 'hero_bag_name' ) ); ?> coffee">
			<div class="ring"><svg viewBox="0 0 24 24" fill="none" stroke="#C8572A" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M8 3c0 3-3 4-3 7a7 7 0 0 0 14 0c0-3-3-4-3-7"/><path d="M12 3c0 3-2 4-2 7a2 2 0 0 0 4 0c0-3-2-4-2-7"/></svg></div>
			<small><?php echo esc_html( $g( 'hero_bag_origin' ) ); ?></small><b><?php echo wp_kses( nl2br( $g( 'hero_bag_name' ) ), [ 'br' => [] ] ); ?></b>
			<small style="color:#8A7A6B"><?php echo esc_html( $g( 'hero_bag_notes' ) ); ?></small><span class="lot"><?php echo esc_html( $g( 'hero_bag_lot' ) ); ?></span></div>
		<div class="float a"><i><?php echo EO_ICON_CHECK; ?></i><div><b><?php echo esc_html( $g( 'float_a_title' ) ); ?></b><small><?php echo esc_html( $g( 'float_a_sub' ) ); ?></small></div></div>
		<div class="float b"><span class="big"><?php echo esc_html( $g( 'float_b_big' ) ); ?></span><small><?php echo esc_html( $g( 'float_b_sub' ) ); ?></small></div>
	</div></div></section>
	<?php return eo_wrap( ob_get_clean() );
} );

add_shortcode( 'eo_ticker', function () {
	$items = array_column( (array) eo_home( 'ticker' ), 'text' );
	$span = ''; foreach ( array_merge( $items, $items ) as $t ) { $span .= '<span>' . esc_html( $t ) . '</span>'; }
	return eo_wrap( '<div class="ticker" aria-hidden="true"><div class="track">' . $span . '</div></div>' );
} );

/* ---------- Values ---------- */
add_shortcode( 'eo_values', function () {
	$ic = eo_icons(); $cards = '';
	foreach ( (array) eo_home( 'values' ) as $v ) { $cards .= '<div class="vcard"><i aria-hidden="true">' . ( $ic[ $v['icon'] ] ?? $ic['farm'] ) . '</i><h3>' . esc_html( $v['title'] ) . '</h3><p>' . esc_html( $v['text'] ) . '</p></div>'; }
	return eo_wrap( '<section class="values"><div class="wrap"><div><div class="eyebrow">Why Ember &amp; Oak</div><h2 class="disp">' . wp_kses( eo_home( 'values_title' ), [ 'em' => [] ] ) . '</h2><p class="lead">' . esc_html( eo_home( 'values_desc' ) ) . '</p></div><div class="vgrid">' . $cards . '</div></div></section>' );
} );

/* ---------- Product card (WooCommerce-driven) ---------- */
function eo_product_card( $product ) {
	$id   = $product->get_id();
	$f    = fn( $k ) => function_exists( 'get_field' ) ? get_field( $k, $id ) : '';
	$cats = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'slugs' ] );
	$roast = max( 1, min( 4, (int) $f( 'roast_level' ) ?: 2 ) );
	$rl   = [ 'Light', 'Medium-light', 'Medium', 'Dark' ][ $roast - 1 ];
	$bars = ''; for ( $i = 1; $i <= 4; $i++ ) { $bars .= '<span class="' . ( $i <= $roast ? 'on' : '' ) . '"></span>'; }
	$bg = $f( 'bag_bg' ) ?: '#F2E8D5'; $fg = $f( 'bag_fg' ) ?: '#2B1D14';
	$origin = $f( 'origin' ); $short = trim( explode( '·', (string) $origin )[0] );
	$badge = $f( 'badge' );
	$price = $product->is_type( 'variable' ) ? $product->get_variation_price( 'min' ) : $product->get_price();
	$link = get_permalink( $id );
	ob_start(); ?>
	<article class="pcard" data-c="<?php echo esc_attr( implode( ' ', $cats ) ); ?>">
		<?php if ( $badge ) : ?><span class="tag<?php echo 'New' === $badge ? ' new' : ''; ?>"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
		<a href="<?php echo esc_url( $link ); ?>" class="img" aria-label="<?php echo esc_attr( $product->get_name() ); ?>"><div class="bag" style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>"><span class="ring"></span><small><?php echo esc_html( $short ); ?></small><b><?php echo esc_html( $product->get_name() ); ?></b></div></a>
		<div class="meta"><span><?php echo esc_html( $origin ); ?></span><span>340 g</span></div>
		<h3><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
		<p class="notes"><?php echo esc_html( $f( 'tasting_notes' ) ?: wp_trim_words( $product->get_short_description(), 12 ) ); ?></p>
		<div class="roast" aria-label="Roast level <?php echo esc_attr( $rl ); ?>"><?php echo $bars; ?><small><?php echo esc_html( $rl ); ?></small></div>
		<div class="buy"><span class="price"><?php echo wc_price( $price ); ?><small>/ 340 g</small></span>
		<?php if ( $product->is_type( 'simple' ) ) : ?>
			<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1" data-product_id="<?php echo (int) $id; ?>" data-product_name="<?php echo esc_attr( $product->get_name() ); ?>" class="add add_to_cart_button ajax_add_to_cart" rel="nofollow" aria-label="Add <?php echo esc_attr( $product->get_name() ); ?> to cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></a>
		<?php else : ?>
			<a href="<?php echo esc_url( $link ); ?>" class="add" aria-label="Choose options for <?php echo esc_attr( $product->get_name() ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></a>
		<?php endif; ?></div>
	</article>
	<?php return ob_get_clean();
}

add_shortcode( 'eo_shop', function () {
	$q = wc_get_products( [ 'status' => 'publish', 'limit' => 8, 'orderby' => 'menu_order', 'order' => 'ASC', 'exclude' => array_filter( [ ( $b = eo_bundle_product() ) ? $b->get_id() : 0 ] ) ] );
	$chips = [ 'all' => 'All coffees', 'light' => 'Light', 'medium' => 'Medium', 'dark' => 'Dark', 'decaf' => 'Decaf', 'blend' => 'Blends' ];
	$c = ''; foreach ( $chips as $k => $l ) { $c .= '<button class="chip" type="button" aria-pressed="' . ( 'all' === $k ? 'true' : 'false' ) . '" data-f="' . $k . '">' . $l . '</button>'; }
	$cards = ''; foreach ( $q as $p ) { $cards .= eo_product_card( $p ); }
	return eo_wrap( '<section class="shop" id="shop"><div class="wrap"><div class="sec-head"><div><div class="eyebrow">This week\'s roasts</div><h2 class="disp">' . wp_kses( eo_home( 'shop_title' ), [ 'em' => [] ] ) . '</h2></div><p>' . esc_html( eo_home( 'shop_desc' ) ) . '</p></div><div class="filters" role="group" aria-label="Filter by roast">' . $c . '</div><div class="pgrid" id="pgrid">' . $cards . '</div><div class="shop-foot"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="btn btn-line">View all coffees</a></div></div></section>' );
} );

/* ---------- Bundle ---------- */
add_shortcode( 'eo_bundle', function () {
	$b = eo_bundle_product(); if ( ! $b ) { return ''; }
	$orig = (float) $b->get_regular_price(); $deal = round( $orig * ( 1 - EO_BUNDLE_DISCOUNT ), 2 ); $sub = round( $deal * ( 1 - EO_SUB_DISCOUNT ), 2 );
	$steps = ''; $i = 1; foreach ( (array) eo_home( 'bundle_steps' ) as $s ) { $steps .= '<div class="step"><i>' . $i++ . '</i><div><b>' . esc_html( $s['title'] ) . '</b><small>' . esc_html( $s['text'] ) . '</small></div></div>'; }
	$cart = esc_url( add_query_arg( 'add-to-cart', $b->get_id(), wc_get_cart_url() ) );
	ob_start(); ?>
	<section class="bundle" id="bundle"><div class="wrap"><div>
		<div class="eyebrow">Build a bundle</div><h2 class="disp"><?php echo wp_kses( eo_home( 'bundle_title' ), [ 'em' => [] ] ); ?></h2>
		<p class="lead"><?php echo esc_html( eo_home( 'bundle_desc' ) ); ?></p><div class="steps"><?php echo $steps; ?></div>
		<a href="<?php echo esc_url( get_permalink( $b->get_id() ) ); ?>" class="btn btn-terra">Start building</a></div>
		<div class="bundle-visual"><div class="bags" aria-hidden="true"><div class="bag" style="background:#F2E8D5"><small>Ethiopia</small><b>Guji Natural</b></div><div class="bag" style="background:#E8A58A"><small>Colombia</small><b>Huila Honey</b></div><div class="bag" style="background:#8FA68A"><small>Blend</small><b>Hearth</b></div></div>
		<div class="pricebox"><div class="row"><span>3 × 340 g bags</span><span class="save">Save 10%</span></div>
		<div class="row"><span>About 66 cups of coffee</span><span><s><?php echo wc_price( $orig ); ?></s><b><?php echo wc_price( $deal ); ?></b></span></div>
		<div class="row"><span>Subscribe and save a further 15%</span><b style="font-size:18px"><?php echo wc_price( $sub ); ?></b></div></div>
		<a href="<?php echo $cart; ?>" data-product_id="<?php echo (int) $b->get_id(); ?>" data-quantity="1" data-product_name="<?php echo esc_attr( $b->get_name() ); ?>" class="btn btn-terra add_to_cart_button ajax_add_to_cart">Add 3-bag bundle</a></div>
	</div></section>
	<?php return eo_wrap( ob_get_clean() );
} );

/* ---------- Subscription ---------- */
add_shortcode( 'eo_subscription', function () {
	$g = eo_product_by_slug( 'guji-natural' ); $base = $g ? (float) ( $g->is_type( 'variable' ) ? $g->get_variation_regular_price( 'min' ) : $g->get_regular_price() ) : 19;
	$perks = ''; foreach ( (array) eo_home( 'sub_perks' ) as $p ) { $perks .= '<li>' . EO_ICON_CHECK . esc_html( $p['text'] ) . '</li>'; }
	ob_start(); ?>
	<section class="sub" id="subscribe"><div class="wrap"><div class="inner"><div class="deco" aria-hidden="true"></div><div>
		<div class="eyebrow">Subscribe &amp; save</div><h2 class="disp"><?php echo wp_kses( eo_home( 'sub_title' ), [ 'em' => [] ] ); ?></h2>
		<p class="lead"><?php echo esc_html( eo_home( 'sub_desc' ) ); ?></p><ul><?php echo $perks; ?></ul>
		<a href="<?php echo esc_url( $g ? get_permalink( $g->get_id() ) . '#pform' : '#' ); ?>" class="btn btn-cream">Build my subscription</a></div>
		<div class="cadence" data-base="<?php echo esc_attr( $base ); ?>" data-disc="<?php echo esc_attr( EO_SUB_DISCOUNT ); ?>"><h4>How often?</h4>
		<div class="opts" role="radiogroup" aria-label="Delivery frequency">
			<label><input type="radio" name="cad" value="1"><span>Weekly<small>Heavy drinker</small></span></label>
			<label><input type="radio" name="cad" value="2" checked><span>Every 2 weeks<small>Most popular</small></span></label>
			<label><input type="radio" name="cad" value="4"><span>Every 4 weeks<small>Light drinker</small></span></label></div>
		<div class="total"><div><small>Per 340 g bag</small><br><b><s><?php echo wc_price( $base ); ?></s><span data-sub-price><?php echo wc_price( $base * ( 1 - EO_SUB_DISCOUNT ) ); ?></span></b></div><small data-next>Next delivery in 2 weeks</small></div></div>
	</div></div></section>
	<?php return eo_wrap( ob_get_clean() );
} );

function eo_product_by_slug( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'product' );
	return $p ? wc_get_product( $p->ID ) : null;
}

/* ---------- Story / reviews / cafe / faq ---------- */
add_shortcode( 'eo_story', function () {
	$stats = ''; foreach ( (array) eo_home( 'stats' ) as $s ) { $stats .= '<div><b>' . esc_html( $s['value'] ) . '</b><small>' . esc_html( $s['label'] ) . '</small></div>'; }
	return eo_wrap( '<section class="story" id="story"><div class="wrap"><div class="visual"><div class="img ph">' . eo_img( eo_home( 'story_image' ), 'large', 'Roaster checking beans at the drum roaster' ) . '</div><div class="img2 ph">' . eo_img( eo_home( 'story_image2' ), 'medium_large', 'Green coffee beans in a sack' ) . '</div></div><div><div class="eyebrow">The roastery</div><h2 class="disp">' . wp_kses( eo_home( 'story_title' ), [ 'em' => [] ] ) . '</h2>' . wp_kses_post( eo_home( 'story_text' ) ) . '<div class="stats">' . $stats . '</div></div></div></section>' );
} );

add_shortcode( 'eo_reviews', function () {
	$c = ''; foreach ( (array) eo_home( 'testimonials' ) as $t ) {
		$c .= '<div class="rcard">' . eo_stars( $t['rating'] ?: 5 ) . '<p>“' . esc_html( $t['quote'] ) . '”</p><div class="who"><span></span><div><b>' . esc_html( $t['name'] ) . '</b><small>' . esc_html( $t['meta'] ) . '</small></div><span class="ver">Verified</span></div></div>'; }
	return eo_wrap( '<section class="reviews"><div class="wrap"><div class="sec-head"><div><div class="eyebrow">Reviews</div><h2 class="disp">From the <em>mug.</em></h2></div><p>Verified reviews from customers who bought the coffee they are talking about.</p></div><div class="rgrid">' . $c . '</div></div></section>' );
} );

add_shortcode( 'eo_cafe', function () {
	$dir = eo_home( 'cafe_directions' ) ?: '#'; $con = eo_home( 'cafe_contact' ) ?: '#';
	ob_start(); ?>
	<section class="cafe" id="cafe"><div class="wrap"><div>
		<div class="eyebrow">Visit the roastery</div><h2 class="disp"><?php echo wp_kses( eo_home( 'cafe_title' ), [ 'em' => [] ] ); ?></h2>
		<div class="card"><div class="img ph"><?php echo eo_img( eo_home( 'cafe_image' ), 'large', 'Ember and Oak cafe counter' ); ?></div><div class="body"><div class="row">
			<div><b>Address</b><?php echo nl2br( esc_html( eo_home( 'cafe_address' ) ) ); ?></div><div><b>Hours</b><?php echo nl2br( esc_html( eo_home( 'cafe_hours' ) ) ); ?></div>
			<div><b>Cuppings</b><?php echo nl2br( esc_html( eo_home( 'cafe_cuppings' ) ) ); ?></div>
			<div><b>Get here</b><a href="<?php echo esc_url( $dir ); ?>" style="text-decoration:underline">Directions</a> · <a href="<?php echo esc_url( $con ); ?>" style="text-decoration:underline">Contact</a></div></div></div></div></div>
		<?php echo eo_faq_html(); ?></div></section>
	<?php return eo_wrap( ob_get_clean() );
} );

function eo_faq_html() {
	$rows = (array) eo_home( 'faq' ); $o = '<div class="faq" id="faq"><div class="eyebrow">Questions</div><h2 class="disp" style="font-size:clamp(34px,3.6vw,48px);margin:18px 0 26px">Before you order</h2>';
	foreach ( $rows as $i => $r ) {
		$o .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $r['q'] ) . '<i aria-hidden="true">+</i></summary><p>' . esc_html( $r['a'] ) . '</p></details>';
	}
	return $o . '</div>';
}
