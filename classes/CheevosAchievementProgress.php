<?php

namespace Cheevos;

class CheevosAchievementProgress extends CheevosModel {
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) ? $data['id'] : null;
		$this->container['achievement_id'] = isset($data['achievement_id']) ? $data['achievement_id'] : null;
		$this->container['user_id'] = isset($data['user_id']) ? $data['user_id'] : null;
		$this->container['site_id'] = isset($data['site_id']) ? $data['site_id'] : null;
		$this->container['site_key'] = isset($data['site_key']) ? $data['site_key'] : null;
		$this->container['site_key'] = isset($data['site_key']) ? $data['site_key'] : null;
		$this->container['earned'] = isset($data['earned']) ? $data['earned'] : null; 
		$this->container['manual_award'] = isset($data['manual_award']) ? $data['manual_award'] : null;
		$this->container['awarded_at'] = isset($data['awarded_at']) ? $data['awarded_at'] : null;
		$this->container['notified'] = isset($data['notified']) ? $data['notified'] : null;
	}
}