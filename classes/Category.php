<?php
/**
 * Cheevos
 * Category Class
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class Category {
	/**
	 * Object fully loaded with data.
	 *
	 * @var		boolean
	 */
	protected $isLoaded = false;

	/**
	 * Create a new instance of this class from an Category database identification number.
	 *
	 * @access	public
	 * @param	integer	Category database identification number.
	 * @return	mixed	Category object or false on error.
	 */
	static public function newFromId($id) {
		if ($id < 1) {
			return false;
		}

		$category = new self;
		$category->setId(intval($id));

		$category->newFrom = 'id';

		$success = $category->load();

		return ($success ? $category : false);
	}

	/**
	 * Create a new instance of this class from the title text.
	 *
	 * @access	public
	 * @param	string	Category title text.
	 * @return	mixed	Category object or false on error.
	 */
	static public function newFromText($text) {
		if (empty($text)) {
			return false;
		}

		$category = new self;
		$category->setTitle($text);

		$category->newFrom = 'text';

		$category->load();

		return $category;
	}

	/**
	 * Load a new Category object from a database row.
	 *
	 * @access	public
	 * @param	array	Database Row
	 * @return	mixed	Category or false on error.
	 */
	static public function newFromRow($row) {
		$category = new self;

		$category->newFrom = 'row';

		$category->load($row);

		if (!$category->getId()) {
			return false;
		}

		return $category;
	}

	/**
	 * Get all categories.
	 *
	 * @access	public
	 * @return	array	Category objects
	 */
	static public function getAll() {
		$DB = wfGetDB(DB_MASTER);

		$results = $DB->select(
			['achievement_category'],
			['*'],
			null,
			__METHOD__,
			[
				'ORDER BY'	=> 'title ASC'
			]
		);

		$categories = [];
		while ($row = $results->fetchRow()) {
			$_category = self::newFromRow($row);
			if ($_category !== false) {
				$categories[$_category->getId()] = $_category;
			}
		}
		return $categories;
	}

	/**
	 * Load from the database.
	 *
	 * @access	public
	 * @param	array	[Optional] Database row to load from.
	 * @return	boolean	Success
	 */
	public function load($row = null) {
		$DB = wfGetDB(DB_MASTER);

		if (!$this->isLoaded) {
			if ($this->newFrom != 'row') {
				switch ($this->newFrom) {
					case 'id':
						$where = [
							'acid' => $this->getId()
						];
						break;
					case 'text':
						$where = [
							'title' => $this->getTitle()
						];
						break;
				}

				$result = $DB->select(
					['achievement_category'],
					['*'],
					$where,
					__METHOD__
				);

				$row = $result->fetchRow();
			}

			if (is_array($row)) {
				$this->data = $row;

				$this->isLoaded = true;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Save Category to the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		$DB = wfGetDB(DB_MASTER);

		$success = false;

		$save = $this->data;
		unset($save['acid']);

		$categoryId = $this->getId();

		$dbPending = $DB->writesOrCallbacksPending();
		if (!$dbPending) {
			$DB->begin();
		}

		if ($categoryId > 0) {
			//Do the update.
			$result = $DB->update(
				'achievement_category',
				$save,
				['acid' => $categoryId],
				__METHOD__
			);
		} else {
			//Do the insert.
			$result = $DB->insert(
				'achievement_category',
				$save,
				__METHOD__
			);
			$categoryId = $DB->insertId();
		}

		if ($result !== false) {
			$this->setId($categoryId);

			if (!$dbPending) {
				$DB->commit();
			}
			//Enforce sanity on data.
			$this->data['acid']		= $categoryId;

			$success = true;
		} else {
			if (!$dbPending) {
				$DB->rollback();
			}
		}

		return $success;
	}

	/**
	 * Set the Category ID
	 *
	 * @access	public
	 * @param	integer	Category ID
	 * @return	boolean	True on success, false if the ID is already set.
	 */
	public function setId($id) {
		if (!$this->data['acid']) {
			$this->data['acid'] = intval($id);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the database identification number for this Category.
	 *
	 * @access	public
	 * @return	integer	Category ID
	 */
	public function getId() {
		return intval($this->data['acid']);
	}

	/**
	 * Return if this category exists.
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function exists() {
		return $this->data['acid'] > 0;
	}

	/**
	 * Set the title.
	 *
	 * @access	public
	 * @param	string	Title
	 * @return	void
	 */
	public function setTitle($title) {
		$this->data['title'] = substr($title, 0, 50);
		$this->data['title_slug'] = $this->makeCanonicalTitle($title);
	}

	/**
	 * Return the title.
	 *
	 * @access	public
	 * @return	string	Title
	 */
	public function getTitle() {
		return $this->data['title'];
	}

	/**
	 * Return the title slug.
	 *
	 * @access	public
	 * @return	string	Title Slug
	 */
	public function getTitleSlug() {
		return $this->data['title_slug'];
	}

	/**
	 * Transforms text into canonical versions safe for usage in URLs and Javascript data attributes.
	 *
	 * @access	private
	 * @param	integer	Text to filter
	 * @param	boolean	[Optional] Ignore spaces
	 * @return	integer	Generated Canonical Title
	 */
	private function makeCanonicalTitle($text, $ignoreSpaces = false) {
		$text = html_entity_decode(rawurldecode(trim($text)), ENT_QUOTES, 'UTF-8');
		$text = mb_strtolower($text, 'UTF-8');

		if (!$ignoreSpaces) {
			$text = str_replace(' ', '-', $text);
		}

		//Replace non-alpha numeric characters that would be bad for SEO
        $text = preg_replace('#(?![a-zA-Z0-9_\-|\\\|/|\+]).*?#is', '', $text);

		//Replace other separators with dashes.
		$text = preg_replace("#(_+|/+|\\\+|\+)#is", "-", $text);

		//Remove excess dashes.
		$text = preg_replace("#(-+)#is", "-", $text);

		//Remove trailing dashes.
		$text = trim($text, '-');

		return $text;
	}
}
