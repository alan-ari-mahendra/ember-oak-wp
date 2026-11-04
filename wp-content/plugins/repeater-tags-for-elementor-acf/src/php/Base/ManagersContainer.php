<?php

namespace Arts\RepeaterTags\Base;

use ArtsRepeaterTags\Arts\Base\Containers\ManagersContainer as BaseManagersContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @property \Arts\RepeaterTags\Managers\Elementor $elementor
 * @property \Arts\RepeaterTags\Managers\Ajax      $ajax
 * @property \Arts\RepeaterTags\Managers\Assets    $assets
 */
class ManagersContainer extends BaseManagersContainer {
}
