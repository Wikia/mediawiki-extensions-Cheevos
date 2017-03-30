<?php
/**
 * Cheevos
 * Cheevos Achievement Model
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosAchievement extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * Nearly every property is type constrained to check for data integrity.  However, those that initialize submodels support taking an already initialized object or an array for their container model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) && is_int($data['id']) ? $data['id'] : 0;
		$this->container['parent_id'] = isset($data['parent_id']) && is_int($data['parent_id']) ? $data['parent_id'] : 0;
		$this->container['site_id'] = isset($data['site_id']) && is_int($data['site_id']) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset($data['site_key']) && is_string($data['site_key']) ? $data['site_key'] : "";
		$this->container['name'] = isset($data['name']) && is_array($data['name']) ? $data['name'] : [];
		$this->container['description'] = isset($data['description']) && is_array($data['description']) ? $data['description'] : [];
		$this->container['image'] = isset($data['image']) && is_string($data['image']) ? $data['image'] : '';
		$this->container['category'] = isset($data['category']) && $data['category'] instanceof CheevosAchievementCategory ? $data['category'] : (is_array($data['category']) ? new CheevosAchievementCategory($data['category']) : new CheevosAchievementCategory());
		$this->container['points'] = isset($data['points']) && is_int($data['points']) ? $data['points'] : 0;
		$this->container['global'] = isset($data['global']) && is_bool($data['global']) ? $data['global'] : false;
		$this->container['protected'] = isset($data['protected']) && is_bool($data['protected']) ? $data['protected'] : false;
		$this->container['secret'] = isset($data['secret']) && is_bool($data['secret']) ? $data['secret'] : false;
		$this->container['special'] = isset($data['special']) && is_bool($data['special']) ? $data['special'] : false;
		$this->container['show_on_all_sites'] = isset($data['show_on_all_sites']) && is_bool($data['show_on_all_sites']) ? $data['show_on_all_sites'] : false;
		$this->container['created_at'] = isset($data['created_at']) && is_int($data['created_at']) ? $data['created_at'] : 0;
		$this->container['updated_at'] = isset($data['updated_at']) && is_int($data['updated_at']) ? $data['updated_at'] : 0;
		$this->container['deleted_at'] = isset($data['deleted_at']) && is_int($data['deleted_at']) ? $data['deleted_at'] : 0;
		$this->container['created_by'] = isset($data['created_by']) && is_int($data['created_by']) ? $data['created_by'] : 0;
		$this->container['updated_by'] = isset($data['updated_by']) && is_int($data['updated_by']) ? $data['updated_by'] : 0;
		$this->container['deleted_by'] = isset($data['deleted_by']) && is_int($data['deleted_by']) ? $data['deleted_by'] : 0;
		$this->container['criteria'] = isset($data['criteria']) && $data['criteria'] instanceof CheevosAchievementCriteria ? $data['criteria'] : (is_array($data['criteria']) ?  new CheevosAchievementCriteria($data['criteria']) : new CheevosAchievementCriteria());
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
		if ($this->getId() > 0) {
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
		global $wgSitename;

		if ($this->container['name'] == null || !count($this->container['name'])) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['name']) && isset($this->container['name'][$code])) {
			$name = $this->container['name'][$code];
		} else {
			$name = reset($this->container['name']);
		}

		return str_replace("%1", $wgSitename, $name);
	}

	/**
	 * Set the name for this achievement with automatic language code selection.
	 *
	 * @access	public
	 * @param	string	Name
	 * @return	void
	 */
	public function setName($name) {
		$code = CheevosHelper::getUserLanguage();
		if (!is_array($this->container['name'])) {
			$this->container['name'] = [$code => $name];
		} else {
			$this->container['name'][$code] = $name;
		}
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

	/**
	 * Set the description for this achievement with automatic language code selection.
	 *
	 * @access	public
	 * @param	string	Description
	 * @return	void
	 */
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
