<?php

namespace Cheevos;


class CheevosAchievementCriteria extends CheevosModel
{
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
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
}
