<?php

namespace ArtsRepeaterTags\Arts\Base\Containers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Container for manager instances. Extends `ArrayObject` so callers can both iterate
 * (`foreach`) and reach managers via property syntax (`$container->some_manager`).
 *
 * @extends \ArrayObject<string, object>
 */
class ManagersContainer extends \ArrayObject {

	/**
	 * @param string $name
	 * @return object|null `null` when the manager isn't registered (vs. raising a notice).
	 */
	public function __get( $name ) {
		return $this->offsetExists( $name ) ? $this->offsetGet( $name ) : null;
	}

	/**
	 * @param string $name
	 * @param object $value
	 * @return void
	 */
	public function __set( $name, $value ) {
		$this->offsetSet( $name, $value );
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset( $name ) {
		return $this->offsetExists( $name );
	}
}
