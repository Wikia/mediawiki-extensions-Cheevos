<?php
/**
 * Cheevos
 * Cheevos Stat Monthly Count Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

class CheevosStatDelta extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @param array|null $data Associated array of property values initializing the model
	 */
	public function __construct( array $data = null ) {
		$this->container['stat'] = isset( $data['stat'] ) && is_string( $data['stat'] ) ? $data['stat'] : '';
		$this->container['delta'] = isset( $data['delta'] ) && is_int( $data['delta'] ) ? $data['delta'] : 0;
	}
}
