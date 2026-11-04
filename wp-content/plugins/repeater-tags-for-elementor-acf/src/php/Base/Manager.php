<?php

namespace Arts\RepeaterTags\Base;

use ArtsRepeaterTags\Arts\Base\Managers\BaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Manager extends BaseManager {

	/** @var ManagersContainer|null */
	protected $managers;
}
