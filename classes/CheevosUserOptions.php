<?php
/**
 * Cheevos
 * Cheevos User Options Model
 *
 * @package   Cheevos
 * @author    Alexia E. Smtih
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

namespace Cheevos;

class CheevosUserOptions extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @param array $data Associated array of property values initializing the model.
	 *
	 * @return void
	 */
	public function __construct(array $data = null) {
		$this->container['user_id'] = isset($data['user_id']) && is_int($data['user_id']) ? $data['user_id'] : 0;
		$this->container['user_name'] = isset($data['user_name']) && is_string($data['user_name']) ? $data['user_name'] : '';
		$this->container['options'] = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
	}
}
