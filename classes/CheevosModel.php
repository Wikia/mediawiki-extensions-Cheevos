<?php
/**
 * Cheevos
 * Cheevos Base Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

namespace Cheevos;

use \ArrayAccess;

class CheevosModel implements ArrayAccess {
	/**
	 * Associative array for storing property values
	 *
	 * @var	mixed[]
	 */
	protected $container = [];

	/**
	 * Sometimes data might have to be munged for display purposes only.  Setting this objec to read only will prevent it from being saved.
	 *
	 * @var	boolean
	 */
	private $readOnly = false;

	/**
	 * Magic call method for:
	 * $this->get{Property}()
	 * $this->set{Property}(mixed value)
	 * $this->get{Property}()
	 * $this->has{Property}()
	 *
	 * @param  string $name
	 * @param  array  $arguments
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
						throw new CheevosException("[" . get_class($this) . "->{$act}{$prop}()] The type " . gettype($value) . " is not valid for " . gettype($this->container[$prop]) . ".");
					}
					$this->container[$prop] = $value;
					return;
				}
			} else {
				throw new CheevosException("[" . get_class($this) . "->{$act}{$prop}()] The property {$prop} is not a valid property for this class.");
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
				throw new CheevosException("[" . get_class($this) . "->is{$prop}()] The property {$prop} is not a valid property for this class.");
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
	 * @access private
	 * @param  string	Unsnipped string.
	 * @param  integer	Amount to snip.
	 * @return string	Property name.
	 */
	private function snipPropName($prop, $length) {
		$prop = substr($prop, $length);
		return strtolower($prop);
	}

	/**
	 * Magic getter for container properties.
	 *
	 * @access public
	 * @param  string	Property
	 * @return mixed	Request property or null if it does not exist.
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
	 * @param  string	Property
	 * @param  mixed	Value to set.
	 * @return object	This object.
	 */
	public function __set($property, $value) {
		if (isset($this->container[$property])) {
			$this->container[$property] = $value;
		}
		return $this;
	}

	/**
	 * Sets the value at the specified index to newval
	 *
	 * @param  int $offset
	 * @param  int $value
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
	 * Returns whether the requested index exists
	 *
	 * @param  int $offset
	 * @return void
	 */
	public function offsetExists($offset) {
		return isset($this->container[$offset]);
	}

	/**
	 * Unsets the value at the specified index
	 *
	 * @param  int $offset
	 * @return void
	 */
	public function offsetUnset($offset) {
		unset($this->container[$offset]);
	}

	/**
	 *Returns the value at the specified index
	 *
	 * @param  int $offset
	 * @return void
	 */
	public function offsetGet($offset) {
		return isset($this->container[$offset]) ? $this->container[$offset] : null;
	}

	/**
	 * Convert model to array
	 *
	 * @return void
	 */
	public function toArray() {
		$container = $this->container;
		foreach ($container as $key => $value) {
			if ($value instanceof CheevosModel) {
				$container[$key] = $value->toArray();
			}
		}
		return $container;
	}

	/**
	 * Wrapper for MediaWiki's API serializer.
	 *
	 * @access public
	 * @return array
	 */
	public function serializeForApiResult() {
		return $this->toArray();
	}

	/**
	 * Convert model to string
	 *
	 * @return string
	 */
	public function __toString() {
		return json_encode($this->toArray());
	}

	/**
	 * Is this only read only?
	 *
	 * @access public
	 * @return boolean
	 */
	public function isReadOnly() {
		return $this->readOnly;
	}

	/**
	 * Set this object to read only.
	 *
	 * @access public
	 * @return void
	 */
	public function setReadOnly() {
		$this->readOnly = true;
	}

	/**
	 * Does this model roughly equal another model?
	 * Such as criteria, points to be earned, ecterera.  Ignores fields such as created and updated timestamps.
	 *
	 * @access public
	 * @param  object	CheevosModel
	 * @return boolean
	 */
	public function sameAs($model) {
		if (get_class($this) != get_class($model)) {
			return false;
		}

		foreach ($this->container as $field) {
			if ($this->container[$field] instanceof CheevosModel) {
				if (!$this->container[$field]->sameAs($criteria[$field])) {
					return false;
				}
				continue;
			}
			if ($this->container[$field] !== $model[$field]) {
				return false;
			}
		}

		return true;
	}
}
