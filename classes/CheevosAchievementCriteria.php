<?php

namespace Cheevos;


class CheevosAchievementCriteria extends CheevosModel
{
    /**
     * Constructor
     * @param mixed[] $data Associated array of property values initializing the model
     */
    public function __construct(array $data = null) {
        $this->container['stats'] = isset($data['stats']) ? $data['stats'] : null;
        $this->container['value'] = isset($data['value']) ? $data['value'] : null;
        $this->container['streak'] = isset($data['streak']) ? $data['streak'] : null;
        $this->container['streak_progress_required'] = isset($data['streak_progress_required']) ? $data['streak_progress_required'] : null;
        $this->container['streak_reset_to_zero'] = isset($data['streak_reset_to_zero']) ? $data['streak_reset_to_zero'] : null;
        $this->container['per_site_progress_maximum'] = isset($data['per_site_progress_maximum']) ? $data['per_site_progress_maximum'] : null;
        $this->container['category_id'] = isset($data['category_id']) ? $data['category_id'] : null;
        $this->container['achievement_ids'] = isset($data['achievement_ids']) ? $data['achievement_ids'] : null;
    }
}