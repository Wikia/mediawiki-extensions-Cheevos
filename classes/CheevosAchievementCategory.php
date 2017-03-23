<?php
/**
 * Cheevos
 * Cheevos Achievement Category Model
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosAchievementCategory extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	$data Associated array of property values initializing the model.
	 * @return	void
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) && is_int($data['id']) ? $data['id'] : 0;
		$this->container['name'] = isset($data['name']) && is_array($data['name']) ? $data['name'] : [];
		$this->container['slug'] = isset($data['slug']) && is_string($data['slug']) ? $data['slug'] : '';
		$this->container['created_at'] = isset($data['created_at']) && is_int($data['created_at']) ? $data['created_at'] : 0;
		$this->container['updated_at'] = isset($data['updated_at']) && is_int($data['updated_at']) ? $data['updated_at'] : 0;
		$this->container['deleted_at'] = isset($data['deleted_at']) && is_int($data['deleted_at']) ? $data['deleted_at'] : 0;
		$this->container['created_by'] = isset($data['created_by']) && is_int($data['created_by']) ? $data['created_by'] : 0;
		$this->container['updated_by'] = isset($data['updated_by']) && is_int($data['updated_by']) ? $data['updated_by'] : 0;
		$this->container['deleted_by'] = isset($data['deleted_by']) && is_int($data['deleted_by']) ? $data['deleted_by'] : 0;
	}

	/**
	 * Save category up to the service.
	 *
	 * @access	public
	 * @return	array	Success Result
	 */
	public function save() {
		if ($this->getId() !== null) {
			$result = Cheevos::updateCategory($this->getId(), $this->toArray());
		} else {
			$result = Cheevos::createCategory($this->toArray());
		}
		return $result;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function exists() {
		if ($this->getId() > 0) {
			$return = true;
			try {
				// Throws an error if it doesn't exist.
				$test = Cheevos::getCategory($this->getId());
			} catch (CheevosException $e) {
				$return = false;
			}
			return $return;
		} else {
			return false; // no ID on this. Can't exist?
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
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

	/**
	 * Set the name for this category with automatic language code selection.
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

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function getTitle() {
		return $this->getName();
	}
}
