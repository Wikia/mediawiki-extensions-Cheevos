<?php

namespace Cheevos;

class Achievement extends Model
{

	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {

		$this->container['id'] = isset($data['id']) ? $data['id'] : null;
		$this->container['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : null;
		$this->container['site_id'] = isset($data['site_id']) ? $data['site_id'] : null;
		$this->container['site_key'] = isset($data['site_key']) ? $data['site_key'] : null;
		$this->container['name'] = isset($data['name']) ? $data['name'] : null;
		$this->container['description'] = isset($data['description']) ? $data['description'] : null;
		$this->container['image'] = isset($data['image']) ? $data['image'] : null;
		$this->container['category'] = isset($data['category']) ? $data['category'] : null;
		$this->container['points'] = isset($data['points']) ? $data['points'] : null;
		$this->container['global'] = isset($data['global']) ? $data['global'] : null;
		$this->container['protected'] = isset($data['protected']) ? $data['protected'] : null;
		$this->container['secret'] = isset($data['secret']) ? $data['secret'] : null;
		$this->container['created_at'] = isset($data['created_at']) ? $data['created_at'] : null;
		$this->container['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : null;
		$this->container['created_by'] = isset($data['created_by']) ? $data['created_by'] : null;
		$this->container['updated_by'] = isset($data['updated_by']) ? $data['updated_by'] : null;
		$this->container['criteria'] = isset($data['criteria']) ? $data['criteria'] : null;
	}

	// quick fix for legacy code calls for now.
	public function isDeleted() {
		return false;
	}
}