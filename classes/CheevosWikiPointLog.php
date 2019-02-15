<?php
/**
 * Cheevos
 * Cheevos Wiki Point Log Model
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		Cheevos
 * @link		https://gitlab.com/hydrawiki
 *
 **/

namespace Cheevos;

class CheevosWikiPointLog extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['achievement_id'] = isset($data['achievement_id']) && is_int($data['achievement_id']) ? $data['achievement_id'] : 0;
		$this->container['page_id'] = isset($data['page_id']) && is_int($data['page_id']) ? $data['page_id'] : 0;
		$this->container['revision_id'] = isset($data['revision_id']) && is_int($data['revision_id']) ? $data['revision_id'] : 0;
		$this->container['points'] = isset($data['points']) && is_int($data['points']) ? $data['points'] : 0;
		$this->container['site_id'] = isset($data['site_id']) && is_int($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) && is_string($data['site_key']) ? $data['site_key'] : '';
		$this->container['size'] = isset($data['size']) && is_int($data['size']) ? $data['size'] : 0;
		$this->container['size_diff'] = isset($data['size_diff']) && is_int($data['size_diff']) ? $data['size_diff'] : 0;
		$this->container['timestamp'] = isset($data['timestamp']) && is_int($data['timestamp']) ? $data['timestamp'] : 0;
		$this->container['user_id'] = isset($data['user_id']) && is_int($data['user_id']) ? $data['user_id'] : 0;
	}
}
