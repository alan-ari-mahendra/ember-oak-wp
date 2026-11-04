<?php
defined( 'ABSPATH' ) || exit;

function eo_f( $name, $label, $type = 'text', $extra = [] ) {
	return array_merge( [ 'key' => 'field_eo_' . $name, 'name' => $name, 'label' => $label, 'type' => $type ], $extra );
}
function eo_rep( $name, $label, $subs, $extra = [] ) {
	foreach ( $subs as &$s ) { $s['key'] = 'field_eo_' . $name . '_' . $s['name']; }
	return array_merge( [ 'key' => 'field_eo_' . $name, 'name' => $name, 'label' => $label, 'type' => 'repeater', 'layout' => 'row', 'button_label' => 'Add row', 'sub_fields' => $subs ], $extra );
}
function eo_s( $name, $label, $type = 'text' ) { return [ 'name' => $name, 'label' => $label, 'type' => $type ]; }

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }

	acf_add_local_field_group( [
		'key' => 'group_eo_home', 'title' => 'Ember & Oak — Homepage', 'position' => 'normal',
		'location' => [ [ [ 'param' => 'page_type', 'operator' => '==', 'value' => 'front_page' ] ] ],
		'fields' => [
			eo_f( 'announce', 'Announcement bar (HTML allowed: <b>)' ),
			eo_f( 'hero_eyebrow', 'Hero eyebrow' ),
			eo_f( 'hero_title', 'Hero title (wrap italic part in <em>)' ),
			eo_f( 'hero_desc', 'Hero description', 'textarea' ),
			eo_f( 'hero_sticker', 'Sticker title' ), eo_f( 'hero_sticker_sub', 'Sticker sub text' ),
			eo_f( 'hero_bag_origin', 'Hero bag — origin' ), eo_f( 'hero_bag_name', 'Hero bag — name' ),
			eo_f( 'hero_bag_notes', 'Hero bag — notes' ), eo_f( 'hero_bag_lot', 'Hero bag — lot line' ),
			eo_f( 'float_a_title', 'Floating card A — title' ), eo_f( 'float_a_sub', 'Floating card A — sub' ),
			eo_f( 'float_b_big', 'Floating card B — big' ), eo_f( 'float_b_sub', 'Floating card B — sub' ),
			eo_rep( 'trust', 'Trust indicators', [ eo_s( 'html', 'Text (HTML: <b>)' ) ] ),
			eo_rep( 'ticker', 'Ticker items', [ eo_s( 'text', 'Text' ) ] ),
			eo_f( 'values_title', 'Values title', 'text', [ 'key' => 'field_eo_values_heading' ] ), eo_f( 'values_desc', 'Values description', 'textarea' ),
			eo_rep( 'values', 'Brand values (4)', [ eo_s( 'icon', 'Icon (farm|clock|score|leaf)' ), eo_s( 'title', 'Title' ), eo_s( 'text', 'Text', 'textarea' ) ] ),
			eo_f( 'shop_title', 'Shop title' ), eo_f( 'shop_desc', 'Shop description', 'textarea' ),
			eo_f( 'bundle_title', 'Bundle title' ), eo_f( 'bundle_desc', 'Bundle description', 'textarea' ),
			eo_rep( 'bundle_steps', 'Bundle steps', [ eo_s( 'title', 'Title' ), eo_s( 'text', 'Text' ) ] ),
			eo_f( 'sub_title', 'Subscription title' ), eo_f( 'sub_desc', 'Subscription description', 'textarea' ),
			eo_rep( 'sub_perks', 'Subscription perks', [ eo_s( 'text', 'Text' ) ] ),
			eo_f( 'story_title', 'Roastery title' ), eo_f( 'story_text', 'Roastery story (HTML)', 'wysiwyg' ),
			eo_f( 'story_image', 'Roastery image', 'image', [ 'return_format' => 'id' ] ),
			eo_f( 'story_image2', 'Roastery image 2', 'image', [ 'return_format' => 'id' ] ),
			eo_rep( 'stats', 'Roastery statistics (3)', [ eo_s( 'value', 'Value' ), eo_s( 'label', 'Label' ) ] ),
			eo_rep( 'testimonials', 'Customer reviews (3)', [ eo_s( 'quote', 'Quote', 'textarea' ), eo_s( 'name', 'Name' ), eo_s( 'meta', 'Detail' ), eo_s( 'rating', 'Rating 1-5', 'number' ) ] ),
			eo_f( 'cafe_title', 'Cafe title' ),
			eo_f( 'cafe_image', 'Cafe image', 'image', [ 'return_format' => 'id' ] ),
			eo_f( 'cafe_address', 'Address', 'textarea' ), eo_f( 'cafe_hours', 'Opening hours', 'textarea' ),
			eo_f( 'cafe_cuppings', 'Cuppings', 'textarea' ), eo_f( 'cafe_directions', 'Directions URL', 'url' ),
			eo_f( 'cafe_contact', 'Contact URL', 'url' ),
			eo_rep( 'faq', 'FAQ items', [ eo_s( 'q', 'Question' ), eo_s( 'a', 'Answer', 'textarea' ) ] ),
			eo_f( 'newsletter_text', 'Newsletter text', 'textarea' ),
			eo_f( 'instagram', 'Instagram URL', 'url' ), eo_f( 'tiktok', 'TikTok URL', 'url' ),
		],
	] );

	acf_add_local_field_group( [
		'key' => 'group_eo_product', 'title' => 'Ember & Oak — Coffee details', 'position' => 'normal',
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'product' ] ] ],
		'fields' => [
			eo_f( 'origin', 'Origin line' ), eo_f( 'badge', 'Badge (e.g. New harvest)' ),
			eo_f( 'tasting_notes', 'Tasting notes (comma separated)' ),
			eo_f( 'roast_level', 'Roast level (1-4)', 'number', [ 'min' => 1, 'max' => 4 ] ),
			eo_f( 'processing', 'Processing method' ), eo_f( 'altitude', 'Altitude' ), eo_f( 'variety', 'Variety' ),
			eo_f( 'bag_bg', 'Card bag colour', 'color_picker' ), eo_f( 'bag_fg', 'Card bag text colour', 'color_picker' ), eo_f( 'cupping_score', 'Cupping score' ), eo_f( 'lot', 'Lot' ),
			eo_rep( 'details', 'Coffee details list', [ eo_s( 'label', 'Label' ), eo_s( 'value', 'Value' ) ] ),
			eo_f( 'shipping_info', 'Shipping & freshness', 'textarea' ),
			eo_f( 'storage_info', 'Storage instructions', 'textarea' ),
			eo_f( 'origin_processing', 'Origin & processing', 'textarea' ),
			eo_f( 'farm_title', 'Farm title' ), eo_f( 'farm_story', 'Farm story (HTML)', 'wysiwyg' ),
			eo_f( 'farm_image', 'Farm image', 'image', [ 'return_format' => 'id' ] ),
			eo_rep( 'farm_meta', 'Farm origin details', [ eo_s( 'label', 'Label' ), eo_s( 'value', 'Value' ) ] ),
			eo_rep( 'brew_guide', 'Brew guide cards', [ eo_s( 'method', 'Method' ), eo_s( 'dose', 'Dose' ), eo_s( 'water_label', 'Water label' ), eo_s( 'water', 'Water / ratio' ), eo_s( 'temp', 'Temperature' ), eo_s( 'time', 'Time' ), eo_s( 'tip', 'Tip', 'textarea' ) ] ),
		],
	] );
} );

/** Get a homepage field regardless of which page renders the shortcode. */
function eo_home( $name ) {
	$id = (int) get_option( 'page_on_front' );
	return function_exists( 'get_field' ) && $id ? get_field( $name, $id ) : null;
}
