<?php
defined( 'ABSPATH' ) || exit;

/** Products-only global search. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_search() ) {
		$q->set( 'post_type', 'product' );
		$q->set( 'posts_per_page', 12 );
		$q->set( 'tax_query', array_merge( (array) $q->get( 'tax_query' ), [ [ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => [ 'exclude-from-search' ], 'operator' => 'NOT IN' ] ] ) );
	}
} );

/** Reusable pill search form. $panel = header dropdown variant. */
function eo_search_form( $id = 'eo-search', $panel = false ) {
	$val = $panel ? '' : get_search_query();
	$uid = $id . '-q';
	ob_start(); ?>
	<div class="<?php echo $panel ? 'eo-search-panel' : 'eo-search-inline'; ?>" id="<?php echo esc_attr( $id ); ?>" <?php echo $panel ? 'hidden' : ''; ?>>
		<div class="wrap"><form role="search" method="get" class="eo-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label for="<?php echo esc_attr( $uid ); ?>" class="screen-reader-text">Search coffee</label>
			<input type="search" id="<?php echo esc_attr( $uid ); ?>" name="s" value="<?php echo esc_attr( $val ); ?>" placeholder="Search coffee" autocomplete="off">
			<button class="btn btn-terra" type="submit">Search</button>
		</form></div>
	</div>
	<?php return ob_get_clean();
}

add_shortcode( 'eo_search', function () {
	global $wp_query;
	$term  = get_search_query();
	$total = (int) $wp_query->found_posts;
	$cards = '';
	if ( have_posts() ) {
		while ( have_posts() ) { the_post(); $p = wc_get_product( get_the_ID() ); if ( $p ) { $cards .= eo_product_card( $p ); } }
		wp_reset_postdata();
	}
	ob_start(); ?>
	<section class="spage"><div class="wrap">
		<div class="eyebrow">Search</div>
		<h1 class="disp"><?php echo '' === $term ? 'Find your <em>coffee.</em>' : 'Results for <em>“' . esc_html( $term ) . '”</em>'; ?></h1>
		<?php echo eo_search_form( 'eo-search-page' ); ?>
		<?php if ( $cards ) : ?>
			<p class="scount" role="status"><?php echo esc_html( sprintf( _n( '%d coffee found', '%d coffees found', $total, 'ember-oak' ), $total ) ); ?></p>
			<div class="pgrid"><?php echo $cards; ?></div>
			<?php $links = paginate_links( [ 'total' => $wp_query->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ) ), 'type' => 'list', 'prev_text' => '←', 'next_text' => '→' ] ); if ( $links ) { echo '<nav class="spager" aria-label="Pagination">' . $links . '</nav>'; } ?>
		<?php else : ?>
			<div class="sempty"><h2 class="disp"><?php echo $term ? 'No coffee found' : 'What are you craving?'; ?></h2>
				<p><?php echo $term ? 'Try a different word, or browse by roast.' : 'Search by name, origin or tasting note, or browse by roast.'; ?></p>
				<div class="filters"><?php foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'exclude' => [ get_option( 'default_product_cat' ) ] ] ) as $t ) { if ( 'bundles' === $t->slug ) { continue; } echo '<a class="chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>'; } ?></div></div>
		<?php endif; ?>
	</div></section>
	<?php return eo_wrap( ob_get_clean() );
} );
