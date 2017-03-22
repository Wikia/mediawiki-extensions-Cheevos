<?php

namespace Cheevos;

class CheevosAchievement extends CheevosModel {
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) ? $data['id'] : null;
		$this->container['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : 0;
		$this->container['site_id'] = isset($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) ? $data['site_key'] : "";
		$this->container['name'] = isset($data['name']) ? $data['name'] : [];
		$this->container['description'] = isset($data['description']) ? $data['description'] : [];
		$this->container['image'] = isset($data['image']) ? $data['image'] : null;
		$this->container['category'] = isset($data['category']) ? new CheevosAchievementCategory($data['category']) : new CheevosAchievementCategory();
		$this->container['points'] = isset($data['points']) ? $data['points'] : null;
		$this->container['global'] = isset($data['global']) ? $data['global'] : null;
		$this->container['protected'] = isset($data['protected']) ? $data['protected'] : null;
		$this->container['secret'] = isset($data['secret']) ? $data['secret'] : null;
		$this->container['created_at'] = isset($data['created_at']) ? $data['created_at'] : 0;
		$this->container['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : 0;
		$this->container['deleted_at'] = isset($data['deleted_at']) ? $data['deleted_at'] : 0;
		$this->container['created_by'] = isset($data['created_by']) ? $data['created_by'] : 0;
		$this->container['updated_by'] = isset($data['updated_by']) ? $data['updated_by'] : 0;
		$this->container['deleted_by'] = isset($data['deleted_by']) ? $data['deleted_by'] : 0;
		$this->container['criteria'] = isset($data['criteria']) ? new CheevosAchievementCriteria($data['criteria']) : new CheevosAchievementCriteria();
	}

	/**
	 * Save achievement up to the service.
	 *
	 * @access	public
	 * @param	boolean	Force create instead of save.  Typically used when copying from a global parent to a child.
	 * @return	array	Success Result
	 */
	public function save($forceCreate = false) {
		if ($this->getId() !== null && !$forceCreate) {
			$result = Cheevos::updateAchievement($this->getId(), $this->toArray());
		} else {
			$result = Cheevos::createAchievement($this->toArray());
		}
		return $result;
	}

	public function exists() {
		if ($this->getId() !== null) {
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

	public function isMega() {
		return false; //No no no... you buy.
	}

	public function getName() {
		if ($this->container['name'] == null || !count($this->container['name'])) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['name']) && isset($this->container['name'][$code])) {
			return $this->container['name'][$code];
		} else {
			return reset($this->container['name']);
		}
	}

	public function setName($name) {
		$code = CheevosHelper::getUserLanguage();
		if (!is_array($this->container['name'])) {
			$this->container['name'] = [$code => $name];
		} else {
			$this->container['name'][$code] = $name;
		}
	}


	public function getHash() {
		// @TODO Decide if this is a bad idea.
		return md5($this->container['id']);
	}

	public function getCategoryId() {
		return $this->container['category']['id'];
	}

	public function getCategory() {
		if ($this->container['category'] instanceof CheevosAchievementCategory) {
			return $this->container['category'];
		}
		$category = new CheevosAchievementCategory($this->container['category']);
		return $category;
	}

	public function getDescription() {
		if ($this->container['description'] == null || !count($this->container['description'])) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['description']) && isset($this->container['description'][$code])) {
			return $this->container['description'][$code];
		} else {
			return reset($this->container['description']);
		}
	}

	public function setDescription($desc) {
		$code = CheevosHelper::getUserLanguage();
		if (!is_array($this->container['description'])) {
			$this->container['description'] = [$code => $desc];
		} else {
			$this->container['description'][$code] = $desc;
		}
	}

	/**
	 * Returns the image article name.
	 * "File:ExampleAchievement.png"
	 *
	 * @access	public
	 * @return	string	Image Article Name - If available
	 */
	public function getImage() {
		$image = $this->container['image'];
		if (empty($image)) {
			return null;
		}
		return $image;
	}

	/**
	 * Returns the image HTTP(S) URL.
	 *
	 * @access	public
	 * @return	mixed	Image URL; false if unable to locate the file.
	 */
	public function getImageUrl() {
		$title = \Title::newFromText($this->getImage());
		$file = wfFindFile($title);
		if ($file) {
			$url = $file->getCanonicalUrl();
			return $url;
		}
		return false;
	}

	/**
	 * Sets this achievement as global.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Set to global.
	 * @return	void
	 */
	public function setGlobal($global = true) {
		$this->container['global'] = boolval($global);
		if ($this->container['global']) {
			$this->container['site_id'] = 0;
			$this->container['site_key'] = '';
		}
	}

	// quick fix for legacy code calls for now.
	public function isDeleted() {
		return boolval($this->container['deleted_at']);
	}
}
