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
 */

namespace Cheevos;

use ArrayAccess;

class CheevosModel implements ArrayAccess {
	/**
	 * Associative array for storing property values
	 *
	 * @var array
	 */
	protected array $container = [];

	/**
	 * Sometimes data might have to be munged for display purposes only.
	 * Setting this objec to read only will prevent it from being saved.
	 *
	 * @var	bool
	 */
	private bool $readOnly = false;

	/**
	 * Magic call method for:
	 * $this->get{Property}()
	 * $this->set{Property}(mixed value)
	 * $this->get{Property}()
	 * $this->has{Property}()
	 *
	 * @param string $name
	 * @param array  $arguments
	 */
	public function __call( $name, $arguments ) {
		// Getter and Setter
		if ( str_starts_with( $name, "get" ) || str_starts_with( $name, "set" ) ) {
			$prop = $this->snipPropName( $name, 3 );
			$act = substr( $name, 0, 3 );
			if ( array_key_exists( $prop, $this->container ) ) {
				if ( $act == "get" ) {
					return $this->container[$prop];
				} else {
					$value = $arguments[0];
					if ( gettype( $value ) !== gettype( $this->container[$prop] ) ) {
						throw new CheevosException(
							"[" . get_class( $this ) . "->{$act}{$prop}()] The type " . gettype( $value ) .
							" is not valid for " . gettype( $this->container[$prop] ) . "."
						);
					}
					$this->container[$prop] = $value;
					return null;
				}
			} else {
				throw new CheevosException(
					"[" . get_class( $this ) .
					"->{$act}{$prop}()] The property {$prop} is not a valid property for this class."
				);
			}
		} elseif ( str_starts_with( $name, "is" ) ) {
			$prop = $this->snipPropName( $name, 2 );
			if ( array_key_exists( $prop, $this->container ) ) {
				$evaluate = $this->container[$prop];
				// @TODO: this should be smarter. May not behave as expected in cases checking other stuff.
				return $evaluate;
			} else {
				throw new CheevosException(
					"[" . get_class( $this ) .
					"->is{$prop}()] The property {$prop} is not a valid property for this class."
				);
			}
		} elseif ( str_starts_with( $name, "has" ) ) {
			$prop = $this->snipPropName( $name, 3 );
			if ( array_key_exists( $prop, $this->container ) ) {
				return true;
			}
			return false;
		} else {
			throw new CheevosException(
				"No idea what method you thought you wanted, but {$name} isn't a valid one."
			);
		}
	}

	/**
	 * Snip property name off a function call.
	 *
	 * @param string $prop Unsnipped string.
	 * @param int $length Amount to snip.
	 *
	 * @return string Property name.
	 */
	private function snipPropName( string $prop, int $length ): string {
		$prop = substr( $prop, $length );
		return strtolower( $prop );
	}

	/**
	 * Magic getter for container properties.
	 *
	 * @param string $property Property
	 *
	 * @return mixed Request property or null if it does not exist.
	 */
	public function __get( string $property ) {
		if ( isset( $this->container[$property] ) ) {
			return $this->container[$property];
		}
		return null;
	}

	/**
	 * Magic setter for container properties.
	 * Will only set the property if it was created during the child classes constructor setup.
	 *
	 * @param string $property Property
	 * @param mixed $value Value to set.
	 *
	 * @return object This object.
	 */
	public function __set( string $property, mixed $value ) {
		if ( isset( $this->container[$property] ) ) {
			$this->container[$property] = $value;
		}
		return $this;
	}

	/**
	 * Sets the value at the specified index to newval
	 *
	 * @param int $offset
	 * @param int $value
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value ): void {
		if ( $offset === null ) {
			$this->container[] = $value;
		} else {
			$this->container[$offset] = $value;
		}
	}

	/**
	 * Returns whether the requested index exists
	 *
	 * @param int $offset
	 *
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->container[$offset] );
	}

	/**
	 * Unsets the value at the specified index
	 *
	 * @param int $offset
	 *
	 * @return void
	 */
	public function offsetUnset( $offset ): void {
		unset( $this->container[$offset] );
	}

	/**
	 * Returns the value at the specified index
	 *
	 * @param int $offset
	 *
	 * @return void
	 */
	public function offsetGet( $offset ) {
		return $this->container[$offset] ?? null;
	}

	/**
	 * Convert model to array
	 *
	 * @return array
	 */
	public function toArray(): array {
		$container = $this->container;
		foreach ( $container as $key => $value ) {
			if ( $value instanceof CheevosModel ) {
				$container[$key] = $value->toArray();
			}
		}
		return $container;
	}

	/**
	 * Wrapper for MediaWiki's API serializer.
	 *
	 * @return array
	 */
	public function serializeForApiResult(): array {
		return $this->toArray();
	}

	/**
	 * Convert model to string
	 *
	 * @return string
	 */
	public function __toString() {
		return json_encode( $this->toArray() );
	}

	/**
	 * Is this read only?
	 *
	 * @return bool
	 */
	public function isReadOnly(): bool {
		return $this->readOnly;
	}

	/**
	 * Set this object to read only.
	 *
	 * @return void
	 */
	public function setReadOnly(): void {
		$this->readOnly = true;
	}

	/**
	 * Does this model roughly equal another model?
	 * Such as criteria, points to be earned, etc. Ignores fields such as created and updated timestamps.
	 *
	 * @return bool
	 */
	public function sameAs( CheevosModel $model ): bool {
		if ( get_class( $this ) != get_class( $model ) ) {
			return false;
		}

		foreach ( $this->container as $field ) {
			if ( $this->container[$field] instanceof CheevosModel ) {
				if (
					!( $model->container[$field] instanceof CheevosModel ) ||
					!$this->container[$field]->sameAs( $model->container[$field] )
				) {
					return false;
				}
				continue;
			}
			if ( $this->container[$field] !== $model[$field] ) {
				return false;
			}
		}

		return true;
	}
}
