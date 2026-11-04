<?php

namespace Arts\RepeaterTags\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Arts\RepeaterTags\Base\Manager as BaseManager;

class Assets extends BaseManager {

	public function enqueue_editor_js(): void {
		$relative = 'libraries/repeater-tags-for-elementor-acf/repeater-tags-for-elementor-acf.js';
		// filemtime suffix busts browser/proxy caches on every bundle change (dev syncs + plugin updates alike).
		$mtime    = filemtime( $this->plugin_dir_path . $relative );

		wp_enqueue_script(
			'arts-repeater-tags-editor',
			untrailingslashit( $this->plugin_dir_url ) . '/' . $relative,
			array( 'elementor-editor' ),
			ARTS_REPEATER_TAGS_PLUGIN_VERSION . '.' . ( false !== $mtime ? (string) $mtime : '0' ),
			true
		);
	}
}
