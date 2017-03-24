<?php
/**
 * Cheevos
 * Cheevos Achievement Status Model
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosAchievementStatus extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['achievement_id'] = isset($data['achievement_id']) && is_int($data['achievement_id']) ? $data['achievement_id'] : 0;
		$this->container['user_id'] = isset($data['user_id']) && is_int($data['user_id']) ? $data['user_id'] : 0;
		$this->container['site_id'] = isset($data['site_id']) && is_int($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) && is_string($data['site_id']) ? $data['site_key'] : '';
		$this->container['earned'] = isset($data['earned']) && is_bool($data['earned']) ? $data['earned'] : false;
		$this->container['progress'] = isset($data['progress']) && is_int($data['progress']) ? $data['progress'] : 0;
		$this->container['total'] = isset($data['total']) && is_int($data['total']) ? $data['total'] : 0;
	}
}