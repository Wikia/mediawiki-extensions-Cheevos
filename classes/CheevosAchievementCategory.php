<?php

namespace Cheevos;


class CheevosAchievementCategory extends CheevosModel {
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) ? $data['id'] : null;
		$this->container['name'] = isset($data['name']) ? $data['name'] : [];
		$this->container['slug'] = isset($data['slug']) ? $data['slug'] : null;
		$this->container['created_at'] = isset($data['created_at']) ? $data['created_at'] : null;
		$this->container['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : null;
		$this->container['created_by'] = isset($data['created_by']) ? $data['created_by'] : null;
		$this->container['updated_by'] = isset($data['updated_by']) ? $data['updated_by'] : null;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function save() {
		if ($this->getId() !== null) {
			Cheevos::updateCategory($this->getId(), $this->toArray());
		} else {
			Cheevos::createCategory($this->toArray());
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function exists() {
		if ($this->getId() !== null) {
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
	 * Undocumented function
	 *
	 * @return void
	 */
	public function getTitle() {
		return $this->getName();
	}
}
