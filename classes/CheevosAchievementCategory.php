<?php

namespace Cheevos;


class CheevosAchievementCategory extends CheevosModel
{
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null) {
		$this->container['id'] = isset($data['id']) ? $data['id'] : null;
		$this->container['name'] = isset($data['name']) ? $data['name'] : null;
		$this->container['slug'] = isset($data['slug']) ? $data['slug'] : null;
		$this->container['created_at'] = isset($data['created_at']) ? $data['created_at'] : null;
		$this->container['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : null;
		$this->container['created_by'] = isset($data['created_by']) ? $data['created_by'] : null;
		$this->container['updated_by'] = isset($data['updated_by']) ? $data['updated_by'] : null;
	}

	public function save() {
		if ($this->getId() !== NULL) {
			Cheevos::updateCategory($this->getId(),$this->container);
		} else {
			Cheevos::createCategory($this->container);
		}
	}

	public function exists() {
		if ($this->getId() !== NULL) {
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

	public function getName() {
		$code = CheevosHelper::getUserLanguage();
		if (array_key_exists($code, $this->container['name']) && isset($this->container['name'][$code])) {
			return $this->container['name'][$code];
		} else {
			return reset($this->container['name']);
		}
	}

	// Legacy Naming
	public function getTitle() {
		return $this->getName();
	}
}
