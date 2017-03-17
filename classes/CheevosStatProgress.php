<?php

namespace Cheevos;


class CheevosStatProgress extends CheevosModel
{
    /**
     * Constructor
     * @param mixed[] $data Associated array of property values initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['stat'] = isset($data['stat']) ? $data['stat'] : null;
        $this->container['user_id'] = isset($data['user_id']) ? $data['user_id'] : null;
        $this->container['site_id'] = isset($data['site_id']) ? $data['site_id'] : null;
        $this->container['streak_type'] = isset($data['streak_type']) ? $data['streak_type'] : null;
        $this->container['count'] = isset($data['count']) ? $data['count'] : null;
        $this->container['last_incremented'] = isset($data['last_incremented']) ? $data['last_incremented'] : null;
    }
}