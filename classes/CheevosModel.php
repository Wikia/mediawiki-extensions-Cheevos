<?php

namespace Cheevos;
use \ArrayAccess;

class CheevosModel implements ArrayAccess
{

/**
	 * Associative array for storing property values
	 * @var mixed[]
	 */
	protected $container = [];

	public function __call($name, $arguments) {
		// Getter and Setter
		if (substr($name, 0, 3) == "get" || substr($name, 0, 3) == "set") {
			$getProp = substr($name, 3);
			$prop = strtolower($getProp);
			$act = substr($name, 0, 3);
			if (array_key_exists($prop, $this->container)) {
				if ($act == "get") {
					return $this->container[$prop];
				} else {
					$value = $arguments[0];
					$this->container[$prop] = $value;
					return;
				}
			} else {
				throw new CheevosException("[".get_class($this)."->{$act}{$getProp}()] The property {$prop} is not a valid property for this class.");
			}
		} elseif (substr($name,0,2) == "is") {
			$getProp = substr($name, 2);
			$prop = strtolower($getProp);
			if (array_key_exists($prop, $this->container)) {
				$evaluate = $this->container[$prop];
				// @TODO: this should be smarter. May not behave as expected in cases checking other stuff.
				if ($evaluate) {
					return true;
				} else {
					return false;
				}
			} else {
				throw new CheevosException("[".get_class($this)."->is{$getProp}()] The property {$prop} is not a valid property for this class.");
			}
		} else {
			throw new CheevosException("No idea what method you thought you wanted, but {$name} isn't a valid one.");
		}
	}


	public function __get($property) {
		if (isset($this->container[$property])) {
	  		return $this->container[$property];
		}
		return null;
  	}

  	public function __set($property, $value) {
		if (isset($this->container[$property])) {
			$this->container[$property] = $value;
		}
		return $this;
  	}

	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			$this->container[] = $value;
		} else {
			$this->container[$offset] = $value;
		}
	}

	public function offsetExists($offset) {
		return isset($this->container[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->container[$offset]);
	}

	public function offsetGet($offset) {
		return isset($this->container[$offset]) ? $this->container[$offset] : null;
	}

	public function toArray() {
		return $this->container;
	}

	public function __toString() {
		return json_encode($this->container);
	}

}


