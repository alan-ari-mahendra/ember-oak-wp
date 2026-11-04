<?php

namespace Arts\RepeaterTags\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Plugin;

/**
 * "Repeat by ACF Repeater" for Elementor Pro's Loop Grid/Carousel: expands each queried post
 * into one loop item per repeater row (post-SQL query-object surgery — Pro's own
 * alternate-templates feature reshapes the query object post-run too, though on its pagination
 * counts rather than the posts array), and resolves the "Current loop row" picker sentinel
 * during per-item renders. Every entry point is a Pro-only hook — inert on free.
 *
 * Pagination stays post-based by design: found_posts/max_num_pages are left untouched, so a
 * page can show more cards than posts_per_page. Documented in the readme.
 */
class LoopRepeat {

	/** Setting key injected into the loop widgets' Query section (storage contract — frozen). */
	const CONTROL_KEY = 'arts_repeater_tags_repeat_field';

	/**
	 * Expanded queries this request, keyed by spl_object_id($query). Each entry keeps the
	 * query handle (its live current_post/in_the_loop drive resolution) and the item→row map.
	 *
	 * @var array<int, array{query: \WP_Query, map: array<int, int>}>
	 */
	private $registry = array();

	/**
	 * Injected at the end of loop-grid / loop-carousel's Query section via before_section_end
	 * (the section is still OPEN there — after_section_end aborts via wp_die() ("Cannot add a
	 * control outside of a section"); the skins add their query controls on after_section_start,
	 * so this control lands last either way).
	 *
	 * @param \Elementor\Controls_Stack $element
	 */
	public function register_repeat_control( $element ): void {
		$options = Plugin::instance()->schema()->get_repeater_options();

		$element->add_control(
			self::CONTROL_KEY,
			array(
				'label'       => esc_html__( 'Repeat by ACF Repeater', 'repeater-tags-for-elementor-acf' ),
				'description' => esc_html__( 'Render one item per row of the chosen repeater. Inside the loop template, bind Repeater Tags to "Current loop row".', 'repeater-tags-for-elementor-acf' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => array( '' => esc_html__( 'Off', 'repeater-tags-for-elementor-acf' ) ) + $options,
				'default'     => '',
				'separator'   => 'before',
			)
		);
	}

	/**
	 * elementor/query/query_results: fires post-SQL, pre-iteration, once per query build with
	 * a fresh WP_Query (a widget using alternate templates builds more than one). Rendering
	 * iterates posts/post_count exclusively, so the expansion is authoritative; found_posts is
	 * only a pre-loop empty-gate and needs no adjustment here (an original result set is never
	 * empty when there is something to expand).
	 *
	 * @param \WP_Query              $query
	 * @param \Elementor\Widget_Base $widget
	 */
	public function expand_query_results( $query, $widget ): void {
		if ( ! $query instanceof \WP_Query || ! $widget instanceof \Elementor\Widget_Base ) {
			return;
		}

		$repeater_key = $widget->get_settings( self::CONTROL_KEY );

		if ( ! is_string( $repeater_key ) || '' === $repeater_key ) {
			return;
		}

		$plugin = Plugin::instance();
		$entry  = $plugin->schema()->get_entry( $repeater_key );

		if ( null === $entry || empty( $query->posts ) ) {
			return;
		}

		// Options-scoped repeaters hold their rows in the site-wide store — every queried
		// post expands by the same global row set.
		$options_post_id = $entry['options_post_id'];

		$expanded = array();
		$map      = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				return; // Unexpected fields=>ids shape — leave the query untouched.
			}

			$rows = $plugin->rows()->get( $repeater_key, '' !== $options_post_id ? $options_post_id : $post->ID );

			foreach ( array_keys( $rows ) as $row_index ) {
				$map[ count( $expanded ) ] = (int) $row_index;
				$expanded[]                = $post;
			}
		}

		$query->posts      = $expanded;
		$query->post_count = count( $expanded );

		$this->registry[ spl_object_id( $query ) ] = array(
			'query' => $query,
			'map'   => $map,
		);
	}

	/**
	 * The row this loop item was expanded for, or null outside a repeat-mode loop (then the
	 * sentinel falls back to row 0 — a useful editor preview beats an empty card). LIFO scan:
	 * with nested/multiple grids the innermost iterating query is the most recently expanded
	 * one whose in_the_loop flag is up (core raises it on every the_post() and drops it when
	 * have_posts() exhausts).
	 */
	public function resolve_current_row_index(): ?int {
		foreach ( array_reverse( $this->registry, true ) as $entry ) {
			$query = $entry['query'];

			if ( $query->in_the_loop && isset( $entry['map'][ $query->current_post ] ) ) {
				return $entry['map'][ $query->current_post ];
			}
		}

		return null;
	}
}
