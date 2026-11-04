<?php
defined( 'ABSPATH' ) || exit;

/** Homepage: FAQPage from SCF + Store entity, merged into Rank Math's graph (no second <script>). */
add_filter( 'rank_math/json_ld', function ( $data, $jsonld ) {
	if ( ! is_front_page() ) { return $data; }
	$faq = [];
	foreach ( (array) eo_home( 'faq' ) as $r ) {
		$faq[] = [ '@type' => 'Question', 'name' => $r['q'], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $r['a'] ] ];
	}
	if ( $faq ) { $data['eo_faq'] = [ '@type' => 'FAQPage', '@id' => home_url( '/#faq' ), 'mainEntity' => $faq ]; }
	$data['eo_store'] = [
		'@type' => [ 'Store', 'CafeOrCoffeeShop' ], '@id' => home_url( '/#store' ), 'name' => 'Ember & Oak Coffee Roasters', 'url' => home_url( '/' ),
		'description' => 'Small-batch specialty coffee roaster in Portland, Oregon.', 'telephone' => '+1-503-555-0188', 'priceRange' => '$$',
		'address' => [ '@type' => 'PostalAddress', 'streetAddress' => '2214 SE Division St', 'addressLocality' => 'Portland', 'addressRegion' => 'OR', 'postalCode' => '97202', 'addressCountry' => 'US' ],
	];
	return $data;
}, 99, 2 );
