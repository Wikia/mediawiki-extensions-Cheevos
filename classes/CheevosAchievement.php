<?php

namespace Cheevos;

class CheevosAchievement extends CheevosModel
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
		$this->container['criteria'] = isset($data['criteria']) ? $data['criteria'] : NULL;
	}

	public function exists() {
		if ($this->getId() !== NULL) {
			$return = true;
			try {
				// Throws an error if it doesn't exist.
				$test = Cheevos::getAchievement($this->getId());
			} catch (CheevosException $e) {
				$return = false;
			}
			return $return;
		} else {
			return false; // no ID on this. Can't exist?
		}
	}

	public function isManuallyAwarded() {
		$crit = $this->getCriteria();
		if (!isset($crit['category_id']) && !$crit['stats'] && !$crit['achievement_ids']) {
			return true;
		} else {
			return false;
		}
	}

	public function isPartOfDefaultMega() {
		return false;
	}

	public function getRequires() {
		return [];
	}


	public function getName() {
		if ($this->container['name'] == NULL || !count($this->container['name'])) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['name']) && isset($this->container['name'][$code])) {
			return $this->container['name'][$code];
		} else {
			return reset($this->container['name']);
		}
	}

	public function getHash() {
		// @TODO Decide if this is a bad idea. 
		return md5($this->container['id']);
	}

	public function getCategoryId() {
		return $this->container['category']['id'];
	}

	public function getDescription() {
		if ($this->container['description'] == NULL || !count($this->container['description'])) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['description']) && isset($this->container['description'][$code])) {
			return $this->container['description'][$code];
		} else {
			return reset($this->container['description']);
		}
	}

	public function getImageUrl(){
		// @TODO: Get the image the "MediaWiki" way.
		$image = $this->container['image'];
		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
			// drop the placeholder.
			$image = "https://hydra-media.cursecdn.com/commons.gamepedia.com/c/c4/Placeholder-Achievement.png";
		}
		return $image;
	}

	// quick fix for legacy code calls for now.
	public function isDeleted() {
		return false;
	}
}