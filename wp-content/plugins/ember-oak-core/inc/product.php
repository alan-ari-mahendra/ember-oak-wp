<?php
defined( 'ABSPATH' ) || exit;

function eo_cur() { global $product; if ( ! is_a( $product, 'WC_Product' ) ) { $product = wc_get_product( get_the_ID() ); } return $product; }
function eo_pf( $k, $p = null ) { $p = $p ?: eo_cur(); return $p && function_exists( 'get_field' ) ? get_field( $k, $p->get_id() ) : ''; }

/* Breadcrumb + gallery + info + purchase form + accordion */
add_shortcode( 'eo_pdp', function () {
	$p = eo_cur(); if ( ! $p ) { return ''; }
	$ids = array_filter( array_merge( [ $p->get_image_id() ], $p->get_gallery_image_ids() ) );
	$notes = array_filter( array_map( 'trim', explode( ',', (string) eo_pf( 'tasting_notes' ) ) ) );
	$roast = max( 1, min( 4, (int) eo_pf( 'roast_level' ) ?: 1 ) );
	$rl = [ 'Light', 'Medium-light', 'Medium', 'Dark' ][ $roast - 1 ];
	$avg = (float) $p->get_average_rating(); $cnt = (int) $p->get_review_count();
	$vars = $p->is_type( 'variable' ) ? $p->get_available_variations() : [];
	$sizes = $p->is_type( 'variable' ) ? ( $p->get_variation_attributes()['Size'] ?? [] ) : [];
	$grinds = $p->is_type( 'variable' ) ? ( $p->get_variation_attributes()['Grind'] ?? [] ) : [];
	$subscribable = 'yes' === get_post_meta( $p->get_id(), '_eo_subscribable', true );
	$cat = wp_get_post_terms( $p->get_id(), 'product_cat' )[0] ?? null;
	ob_start(); ?>
	<div class="wrap crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span>/</span><a href="<?php echo esc_url( home_url( '/#shop' ) ); ?>">Coffee</a><?php if ( $cat ) : ?><span>/</span><a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a><?php endif; ?><span>/</span><span aria-current="page" style="color:var(--bean)"><?php echo esc_html( $p->get_name() ); ?></span></div>
	<section class="pdp"><div class="wrap">
	<div class="gallery"><div class="gmain" id="gmain">
		<?php if ( $b = eo_pf( 'badge' ) ) : ?><span class="badge"><?php echo esc_html( $b ); ?></span><?php endif; ?>
		<?php if ( $ids ) { foreach ( $ids as $i => $id ) { echo wp_get_attachment_image( $id, 'large', false, [ 'class' => 'gimg' . ( 0 === $i ? ' on' : '' ), 'loading' => 0 === $i ? 'eager' : 'lazy', 'data-i' => $i, 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) ] ); } } ?>
		<?php if ( $s = eo_pf( 'cupping_score' ) ) : ?><div class="score"><b><?php echo esc_html( $s ); ?></b><small>Cupping</small></div><?php endif; ?>
	</div>
	<div class="thumbs" role="group" aria-label="Product images"><?php foreach ( $ids as $i => $id ) { echo '<button class="thumb" type="button" data-i="' . $i . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '" aria-label="' . esc_attr( 'Image ' . ( $i + 1 ) ) . '">' . wp_get_attachment_image( $id, 'thumbnail', false, [ 'alt' => '', 'loading' => 'lazy' ] ) . '</button>'; } ?></div></div>

	<div class="info">
		<div class="origin"><i aria-hidden="true"></i><?php echo esc_html( eo_pf( 'origin' ) ); ?></div>
		<h1 class="disp"><?php echo esc_html( $p->get_name() ); ?></h1>
		<div class="rating"><?php echo eo_stars( round( $avg ) ); ?><b style="color:var(--bean)"><?php echo $cnt ? esc_html( number_format( $avg, 1 ) ) : '–'; ?></b><a href="#reviews"><?php echo (int) $cnt; ?> reviews</a><?php if ( $l = eo_pf( 'lot' ) ) { echo '<span>·</span><span>' . esc_html( $l ) . '</span>'; } ?></div>
		<p class="desc"><?php echo wp_kses_post( $p->get_short_description() ); ?></p>
		<div class="notes" aria-label="Tasting notes"><?php foreach ( $notes as $n ) { echo '<span>' . esc_html( ucfirst( $n ) ) . '</span>'; } ?></div>
		<div class="specs">
			<div><small>Roast</small><b><?php echo esc_html( $rl ); ?></b><div class="roast-scale" aria-label="Roast level <?php echo (int) $roast; ?> of 4"><?php for ( $i = 1; $i <= 4; $i++ ) { echo '<span class="' . ( $i <= $roast ? 'on' : '' ) . '"></span>'; } ?></div></div>
			<div><small>Process</small><b><?php echo esc_html( eo_pf( 'processing' ) ); ?></b></div>
			<div><small>Altitude</small><b><?php echo esc_html( eo_pf( 'altitude' ) ); ?></b></div>
			<div><small>Variety</small><b><?php echo esc_html( eo_pf( 'variety' ) ); ?></b></div></div>

		<?php if ( $p->is_type( 'variable' ) ) : ?>
		<form id="pform" class="cart variations_form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>"
			data-product_id="<?php echo (int) $p->get_id(); ?>" data-name="<?php echo esc_attr( $p->get_name() ); ?>" data-disc="<?php echo esc_attr( EO_SUB_DISCOUNT ); ?>" data-variations="<?php echo esc_attr( wp_json_encode( $vars ) ); ?>">
			<div class="opt"><div class="lbl"><span>Size</span><small id="sizeHint"></small></div><div class="seg c2" role="radiogroup" aria-label="Size">
				<?php foreach ( $sizes as $i => $v ) { echo '<label><input type="radio" name="attribute_size" value="' . esc_attr( $v ) . '"' . checked( 0, $i, false ) . '><span>' . esc_html( $v ) . '<small data-size-price="' . esc_attr( $v ) . '"></small></span></label>'; } ?></div></div>
			<div class="opt"><div class="lbl"><span>Grind</span><small>Whole bean stays fresh longest</small></div><div class="seg c5" role="radiogroup" aria-label="Grind">
				<?php foreach ( $grinds as $i => $v ) { echo '<label><input type="radio" name="attribute_grind" value="' . esc_attr( $v ) . '"' . checked( 0, $i, false ) . '><span>' . esc_html( $v ) . '</span></label>'; } ?></div></div>
			<?php if ( $subscribable ) : ?>
			<div class="opt"><div class="lbl"><span>Purchase</span></div><div class="purchase" id="purchase" role="radiogroup" aria-label="Purchase option">
				<label class="on"><input type="radio" name="eo_mode" value="sub" checked><div class="t"><b>Subscribe &amp; save 15% <span class="save">Best value</span></b><small>Free shipping, pause or cancel anytime</small></div><div class="p" id="pSub"></div></label>
				<div class="cad show seg" id="cad" role="radiogroup" aria-label="Delivery frequency"><label><input type="radio" name="eo_cadence" value="1"><span>Every 1 week</span></label><label><input type="radio" name="eo_cadence" value="2" checked><span>Every 2 weeks</span></label><label><input type="radio" name="eo_cadence" value="4"><span>Every 4 weeks</span></label></div>
				<label><input type="radio" name="eo_mode" value="once"><div class="t"><b>One-time purchase</b><small>Ships within 48h of roasting</small></div><div class="p" id="pOnce"></div></label></div></div>
			<?php endif; ?>
			<div class="cta"><div class="qty"><button type="button" aria-label="Decrease quantity" id="qdec">−</button><input type="number" name="quantity" id="qty" value="1" min="1" aria-label="Quantity" readonly><button type="button" aria-label="Increase quantity" id="qinc">+</button></div>
				<input type="hidden" name="add-to-cart" value="<?php echo (int) $p->get_id(); ?>"><input type="hidden" name="product_id" value="<?php echo (int) $p->get_id(); ?>"><input type="hidden" name="variation_id" value="">
				<button type="submit" class="btn btn-terra single_add_to_cart_button" id="eo-atc">Add to cart <span class="pr" id="ctaPrice"></span></button></div>
			<div class="ship"><span><?php echo EO_ICON_CHECK; ?>In stock, roasting Monday</span><span><?php echo EO_ICON_CHECK; ?>Free shipping over $40</span><span><?php echo EO_ICON_CHECK; ?>Compostable bag</span></div>
		</form>
		<?php else : woocommerce_template_single_add_to_cart(); endif; ?>

		<div class="acc">
			<details open><summary>Product details<i aria-hidden="true">+</i></summary><ul><?php foreach ( (array) eo_pf( 'details' ) as $r ) { echo '<li><b>' . esc_html( $r['label'] ) . '</b><span>' . esc_html( $r['value'] ) . '</span></li>'; } ?></ul></details>
			<details><summary>Origin &amp; processing<i aria-hidden="true">+</i></summary><p><?php echo nl2br( esc_html( eo_pf( 'origin_processing' ) ) ); ?></p></details>
			<details><summary>Shipping information<i aria-hidden="true">+</i></summary><p><?php echo nl2br( esc_html( eo_pf( 'shipping_info' ) ) ); ?></p></details>
			<details><summary>Storage instructions<i aria-hidden="true">+</i></summary><p><?php echo nl2br( esc_html( eo_pf( 'storage_info' ) ) ); ?></p></details>
		</div>
	</div></div></section>
	<div class="stickybar" id="stickybar" aria-hidden="true"><div><b id="stickyPrice"></b><small><?php echo esc_html( $p->get_name() ); ?> · <span id="stickyVar"></span></small></div><button type="button" class="btn btn-terra" id="stickyAtc" tabindex="-1">Add to cart</button></div>
	<?php return eo_wrap( ob_get_clean() );
} );

add_shortcode( 'eo_farm', function () {
	$p = eo_cur(); $meta = '';
	foreach ( (array) eo_pf( 'farm_meta' ) as $r ) { $meta .= '<div><small>' . esc_html( $r['label'] ) . '</small>' . esc_html( $r['value'] ) . '</div>'; }
	return eo_wrap( '<section class="farm"><div class="wrap"><div class="inner"><div class="img ph dk">' . eo_img( eo_pf( 'farm_image' ), 'large', 'Coffee cherries drying on raised beds' ) . '</div><div class="body"><div class="eyebrow">From the farm</div><h2 class="disp">' . wp_kses( eo_pf( 'farm_title' ), [ 'em' => [] ] ) . '</h2>' . wp_kses_post( eo_pf( 'farm_story' ) ) . '<div class="meta">' . $meta . '</div></div></div></div></section>' );
} );

add_shortcode( 'eo_brew', function () {
	$c = ''; foreach ( (array) eo_pf( 'brew_guide' ) as $b ) {
		$c .= '<div class="bcard"><h3>' . esc_html( $b['method'] ) . '</h3><dl><dt>Dose</dt><dd>' . esc_html( $b['dose'] ) . '</dd><dt>' . esc_html( $b['water_label'] ?: 'Water' ) . '</dt><dd>' . esc_html( $b['water'] ) . '</dd><dt>Temp</dt><dd>' . esc_html( $b['temp'] ) . '</dd><dt>Time</dt><dd>' . esc_html( $b['time'] ) . '</dd></dl><p class="tip">' . esc_html( $b['tip'] ) . '</p></div>'; }
	return eo_wrap( '<section class="brew"><div class="wrap"><div class="sec-head"><div><div class="eyebrow">Brew guide</div><h2 class="disp">How we brew <em>this one.</em></h2></div><p>Starting points from our bar. Adjust grind first, then ratio.</p></div><div class="bgrid">' . $c . '</div></div></section>' );
} );

add_shortcode( 'eo_pdp_reviews', function () {
	$p = eo_cur(); $id = $p->get_id(); $cnt = (int) $p->get_review_count(); $avg = (float) $p->get_average_rating(); $counts = $p->get_rating_counts();
	$bars = ''; for ( $s = 5; $s >= 1; $s-- ) { $n = (int) ( $counts[ $s ] ?? 0 ); $w = $cnt ? round( $n / $cnt * 100, 1 ) : 0; $bars .= '<div><span>' . $s . '★</span><i style="--w:' . $w . '%"></i><span>' . $n . '</span></div>'; }
	$cards = ''; foreach ( get_comments( [ 'post_id' => $id, 'status' => 'approve', 'type' => 'review', 'number' => 3 ] ) as $c ) {
		$r = (int) get_comment_meta( $c->comment_ID, 'rating', true );
		$cards .= '<div class="rcard">' . eo_stars( $r ) . '<p>' . esc_html( $c->comment_content ) . '</p><div class="who"><b>' . esc_html( $c->comment_author ) . '</b>' . ( wc_review_is_from_verified_owner( $c->comment_ID ) ? '<span class="ver">Verified</span>' : '' ) . '</div></div>'; }
	return eo_wrap( '<section class="prev" id="reviews"><div class="wrap"><div class="top"><div><div class="eyebrow">Reviews</div><h2 class="disp">' . $cnt . ' people <em>rated this.</em></h2></div><div class="summary"><div><span class="big">' . ( $cnt ? number_format( $avg, 1 ) : '–' ) . '</span>' . eo_stars( round( $avg ) ) . '<small>' . $cnt . ' verified reviews</small></div><div class="bars">' . $bars . '</div></div></div><div class="rgrid">' . $cards . '</div></div></section>' );
} );

add_shortcode( 'eo_related', function () {
	$p = eo_cur(); $ids = wc_get_related_products( $p->get_id(), 4 );
	if ( count( $ids ) < 4 ) { $more = wc_get_products( [ 'limit' => 8, 'return' => 'ids', 'exclude' => array_merge( [ $p->get_id() ], $ids, array_filter( [ ( $b = eo_bundle_product() ) ? $b->get_id() : 0 ] ) ), 'orderby' => 'menu_order', 'order' => 'ASC' ] ); $ids = array_merge( $ids, $more ); }
	$ids = array_slice( $ids, 0, 4 ); $o = '';
	foreach ( $ids as $i ) { $o .= eo_product_card( wc_get_product( $i ) ); }
	return eo_wrap( '<section class="related"><div class="wrap"><div class="eyebrow">You might also like</div><h2 class="disp">More from <em>Ethiopia and beyond.</em></h2><div class="pgrid">' . $o . '</div></div></section>' );
} );
