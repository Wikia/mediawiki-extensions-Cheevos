<?php
/**
 * Cheevos
 * Cheevos Stat Monthly Count Model
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class StatMonthlyCount extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['count'] = isset($data['count']) && is_int($data['count']) ? $data['count'] : 0;
		$this->container['month'] = isset($data['month']) && is_int($data['month']) ? $data['month'] : 0;
		$this->container['site_id'] = isset($data['site_id']) && is_int($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) && is_string($data['site_key']) ? $data['site_key'] : '';
		$this->container['stat'] = isset($data['stat']) && is_string($data['stat']) ? $data['stat'] : '';
		$this->container['stat_id'] = isset($data['stat_id']) && is_int($data['stat_id']) ? $data['stat_id'] : 0;
		$this->container['user_id'] = isset($data['user_id']) && is_int($data['user_id']) ? $data['user_id'] : 0;
	}
}
