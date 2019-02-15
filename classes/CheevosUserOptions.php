<?php
/**
 * Cheevos
 * Cheevos User Options Model
 *
 * @author		Alexia E. Smtih
 * @copyright	(c) 2017 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		Cheevos
 * @link		https://gitlab.com/hydrawiki
 *
 **/

namespace Cheevos;

class CheevosUserOptions extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['user_id'] = isset($data['user_id']) && is_int($data['user_id']) ? $data['user_id'] : 0;
		$this->container['user_name'] = isset($data['user_name']) && is_string($data['user_name']) ? $data['user_name'] : '';
		$this->container['options'] = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
	}
}
