<?php

namespace Arts\RepeaterTags\Base;

use ArtsRepeaterTags\Arts\Base\Plugins\BasePlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Plugin extends BasePlugin {

	/** @var ManagersContainer */
	protected $managers;
}
