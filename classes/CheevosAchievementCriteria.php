<?php
/**
 * Cheevos
 * Cheevos Achievement Criteria Model
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosAchievementCriteria extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['stats'] = isset($data['stats']) && is_array($data['stats']) ? $data['stats'] : [];
		$this->container['value'] = isset($data['value']) && is_int($data['value']) ? $data['value'] : 0;
		$this->container['streak'] = isset($data['streak']) && is_string($data['streak']) ? $data['streak'] : '';
		$this->container['streak_progress_required'] = isset($data['streak_progress_required']) && is_int($data['streak_progress_required']) ? $data['streak_progress_required'] : 0;
		$this->container['streak_reset_to_zero'] = isset($data['streak_reset_to_zero']) && is_bool($data['streak_reset_to_zero']) ? $data['streak_reset_to_zero'] : false;
		$this->container['per_site_progress_maximum'] = isset($data['per_site_progress_maximum']) && is_int($data['per_site_progress_maximum']) ? $data['per_site_progress_maximum'] : 0;
		$this->container['date_range_start'] = isset($data['date_range_start']) && is_int($data['date_range_start']) ? $data['date_range_start'] : 0;
		$this->container['date_range_end'] = isset($data['date_range_end']) && is_int($data['date_range_end']) ? $data['date_range_end'] : 0;
		$this->container['category_id'] = isset($data['category_id']) && is_int($data['category_id']) ? $data['category_id'] : 0;
		$this->container['achievement_ids'] = isset($data['achievement_ids']) && is_array($data['achievement_ids']) ? $data['achievement_ids'] : [];
	}

	/**
	 * Does this criteria roughly equal another criteria?
	 *
	 * @access	public
	 * @param	object	CheevosAchievementCriteria
	 * @return	boolean
	 */
	public function sameAs($criteria) {
		foreach (['stats', 'value', 'streak', 'streak_progress_required', 'streak_reset_to_zero', 'per_site_progress_maximum', 'date_range_start', 'date_range_end', 'category_id', 'achievement_ids'] as $field) {
			if ($this->container[$field] instanceof CheevosModel) {
				if (!$this->container[$field]->sameAs($criteria[$field])) {
					return false;
				}
				continue;
			}
			if ($this->container[$field] !== $criteria[$field]) {
				return false;
			}
		}
		return true;
	}
}
