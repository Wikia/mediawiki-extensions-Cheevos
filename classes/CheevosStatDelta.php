<?php
/**
 * Cheevos
 * Cheevos Stat Monthly Count Model
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosStatDelta extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {
		$this->container['stat'] = isset($data['stat']) && is_string($data['stat']) ? $data['stat'] : '';
		$this->container['delta'] = isset($data['delta']) && is_int($data['delta']) ? $data['delta'] : 0;
	}
}
