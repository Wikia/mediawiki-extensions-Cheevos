<?php
/**
 * Cheevos
 * Cheevos Site Edit Points Promotion Model
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosSiteEditPointsPromotion extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) && is_int($data['id']) ? $data['id'] : 0;
		$this->container['begins'] = isset($data['begins']) && is_int($data['begins']) ? $data['begins'] : 0;
		$this->container['expires'] = isset($data['expires']) && is_int($data['expires']) ? $data['expires'] : 0;
		$this->container['multiplier'] = isset($data['multiplier']) && is_numeric($data['multiplier']) ? $data['multiplier'] : 0;
		$this->container['site_id'] = isset($data['site_id']) && is_int($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) && is_string($data['site_key']) ? $data['site_key'] : '';
	}
}
