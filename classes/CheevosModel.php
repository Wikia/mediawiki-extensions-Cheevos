<?php

namespace Cheevos;
use \ArrayAccess;

class CheevosModel implements ArrayAccess {
	/**
	 * Associative array for storing property values
	 * @var mixed[]
	 */
	protected $container = [];

	/**
	 * Undocumented function
	 *
	 * @param [type] $name
	 * @param [type] $arguments
	 * @return void
	 */
	public function __call($name, $arguments) {
		// Getter and Setter
		if (substr($name, 0, 3) == "get" || substr($name, 0, 3) == "set") {
			$prop = $this->snipPropName($name, 3);
			$act = substr($name, 0, 3);
			if (array_key_exists($prop, $this->container)) {
				if ($act == "get") {
					return $this->container[$prop];
				} else {
					$value = $arguments[0];
					if (gettype($value) !== gettype($this->container[$prop])) {
						throw new CheevosException("[".get_class($this)."->{$act}{$getProp}()] The type ".gettype($value)." is not valid for ".gettype($this->container[$prop]).".");
					}
					$this->container[$prop] = $value;
					return;
				}
			} else {
				throw new CheevosException("[".get_class($this)."->{$act}{$getProp}()] The property {$prop} is not a valid property for this class.");
			}
		} elseif (substr($name, 0, 2) == "is") {
			$prop = $this->snipPropName($name, 2);
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
		} elseif (substr($name, 0, 3) == "has") {
			$prop = $this->snipPropName($name, 3);
			if (array_key_exists($prop, $this->container)) {
				return true;
			}
			return false;
		} else {
			throw new CheevosException("No idea what method you thought you wanted, but {$name} isn't a valid one.");
		}
	}

	/**
	 * Snip property name off a function call.
	 *
	 * @access	private
	 * @param	string	Unsnipped string.
	 * @param	integer	Amount to snip.
	 * @return	string	Property name.
	 */
	private function snipPropName($prop, $length) {
		$prop = substr($prop, $length);
		return strtolower($prop);
	}

	/**
	 * Magic getter for container properties.
	 *
	 * @access public
	 * @param	string	Property
	 * @return	mixed	Request property or null if it does not exist.
	 */
	public function __get($property) {
		if (isset($this->container[$property])) {
	  		return $this->container[$property];
		}
		return null;
  	}

	/**
	 * Magic setter for container properties.
	 * Will only set the property if it was created during the child classes constructor setup.
	 *
	 * @access public
	 * @param	string	Property
	 * @param	mixed	Value to set.
	 * @return	object	This object.
	 */
  	public function __set($property, $value) {
		if (isset($this->container[$property])) {
			$this->container[$property] = $value;
		}
		return $this;
  	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $offset
	 * @param [type] $value
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			$this->container[] = $value;
		} else {
			$this->container[$offset] = $value;
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $offset
	 * @return void
	 */
	public function offsetExists($offset) {
		return isset($this->container[$offset]);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $offset
	 * @return void
	 */
	public function offsetUnset($offset) {
		unset($this->container[$offset]);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $offset
	 * @return void
	 */
	public function offsetGet($offset) {
		return isset($this->container[$offset]) ? $this->container[$offset] : null;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function toArray() {
		foreach ($this->container as $key => $value) {
			if ($value instanceof CheevosModel) {
				$this->container[$key] = $value->toArray();
			}
		}
		return $this->container;
	}

	/**
	 * Undocumented function
	 *
	 * @return string
	 */
	public function __toString() {
		return json_encode($this->toArray());
	}

}


