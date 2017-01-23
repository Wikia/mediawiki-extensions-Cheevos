<?php
/**
 * Cheevos
 * Achievement Class
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class Achievement {
	/**
	 * Raw achievement information.
	 *
	 * @var		array
	 */
	protected $data = [];

	/**
	 * Loaded and Cached Achievement Objects
	 *
	 * @var		array
	 */
	static protected $achievements = [];

	/**
	 * Achievement hash => id array.
	 *
	 * @var		array
	 */
	static private $achievementHashes = [];

	/**
	 * What achievements this achievement requires.
	 *
	 * @var		array
	 */
	private $requires = [];

	/**
	 * What achievements this achievement is required by.
	 *
	 * @var		array
	 */
	private $requiredBy = [];

	/**
	 * Known Mediawiki hooks.
	 *
	 * @var		array
	 */
	static private $knownHooks = [];

	/**
	 * Used to signal a mega update is required.
	 *
	 * @var		boolean
	 */
	private $queueMegaUpdate = false;

	/**
	 * Object fully loaded with data.
	 *
	 * @var		boolean
	 */
	protected $isLoaded = false;

	/**
	 * Create a new instance of this class from an Achievement database identification number.
	 *
	 * @access	public
	 * @param	integer	Achievement database identification number.
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	mixed	Achievement object or false on error.
	 */
	static public function newFromId($id, $useCache = false) {
		if ($id < 1) {
			return false;
		}

		if ($useCache && isset(self::$achievements[$id]) && self::$achievements[$id]->isLoaded()) {
			return self::$achievements[$id];
		}

		$achievement = new self;
		$achievement->setId(intval($id));

		$achievement->newFrom = 'id';

		$success = $achievement->load();

		return ($success ? $achievement : false);
	}

	/**
	 * Create a new instance of this class from an Achievement unique hash.
	 *
	 * @access	public
	 * @param	string	32 character long unique Achievement hash.
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	mixed	Achievement object or false on error.
	 */
	static public function newFromHash($hash, $useCache = false) {
		if (strlen($hash) !== 32) {
			return false;
		}

		if ($useCache && isset(self::$achievementHashes[$hash]) && isset(self::$achievements[self::$achievementHashes[$hash]]) && self::$achievements[self::$achievementHashes[$hash]]->isLoaded()) {
			return self::$achievements[self::$achievementHashes[$hash]];
		}

		$achievement = new self;
		if (!$achievement->setHash($hash)) {
			return false;
		}

		$achievement->newFrom = 'hash';

		$success = $achievement->load();

		return ($success ? $achievement : false);
	}

	/**
	 * Load a new Achievement object from a database row.
	 *
	 * @access	public
	 * @param	array	Database Row
	 * @return	mixed	Achievement or false on error.
	 */
	static public function newFromRow($row) {
		$achievement = new self;

		$achievement->newFrom = 'row';

		$achievement->load($row);

		if (!$achievement->getId()) {
			return false;
		}

		return $achievement;
	}

	/**
	 * Get all achievements.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	array	Achievement objects
	 */
	static public function getAll($useCache = false) {
		$db = wfGetDB(DB_MASTER);

		if ($useCache && count(self::$achievements)) {
			return self::$achievements;
		}

		$results = $db->select(
			['achievement'],
			['*'],
			null,
			__METHOD__
		);

		$achievements = [];
		while ($row = $results->fetchRow()) {
			$_achievement = self::newFromRow($row);
			if ($_achievement !== false) {
				$achievements[$_achievement->getId()] = $_achievement;
			}
		}
		return $achievements;
	}

	/**
	 * Load from the database.
	 *
	 * @access	public
	 * @param	array	[Optional] Database row to load from.
	 * @return	boolean	Success
	 */
	public function load($row = null) {
		$db = wfGetDB(DB_MASTER);

		if (!$this->isLoaded) {
			if ($this->newFrom != 'row') {
				switch ($this->newFrom) {
					case 'id':
						$where = [
							'aid' => $this->getId()
						];
						break;
					case 'hash':
						$where = [
							'unique_hash' => $this->getHash()
						];
						break;
				}

				$result = $db->select(
					[
						'achievement',
						'achievement_link'
					],
					[
						'achievement.*',
						'achievement_link.requires',
						'achievement_link.achievement_id AS required_by'
					],
					$where,
					__METHOD__,
					[],
					[
						'achievement_link' => [
							'LEFT JOIN', 'achievement_link.achievement_id = achievement.aid OR achievement_link.requires = achievement.aid'
						]
					]
				);
				while ($row = $result->fetchRow()) {
					$rows[] = $row;
				}
			} else {
				$rows[] = $row;
			}

			$achievement = false;
			foreach ($rows as $row) {
				if ($achievement === false) {
					$achievement = $row;
					$achievement['rules'] = @json_decode($row['rules'], true);
					if (isset($achievement['rules']['triggers'])) {
						$achievement['rules']['triggers'] = (array) $achievement['rules']['triggers'];
					} else {
						$achievement['rules']['triggers'] = [];
					}

					self::$achievementHashes[$achievement['unique_hash']] = $achievement['aid'];
				}

				//Requires
				if ($row['required_by'] == $achievement['aid']) {
					$this->requires[] = $row['requires'];
				}

				//Required By
				//This required by information should be not be saved by this object instance later as it is controlled by another achievement object.  It is loaded only for faster look up.
				if ($row['requires'] == $achievement['aid']) {
					$this->requiredBy[] = $row['required_by'];
				}
			}
			unset($achievement['requires']);
			unset($achievement['required_by']);

			$this->requires = array_unique($this->requires);
			$this->requiredBy = array_unique($this->requiredBy);

			$this->data = $achievement;

			//Cache this object on to the global static object for fast access later.
			self::$achievements[$achievement['aid']] = $this;

			$this->isLoaded = true;
		}

		return true;
	}

	/**
	 * Save Achievement to the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		global $dsSiteKey;

		$db = wfGetDB(DB_MASTER);

		$success = false;

		$save = $this->data;
		unset($save['aid']);
		$save['edited'] = time();
		if (!is_array($save['rules'])) {
			//Enforce sanity on rules array to prevent issues later.
			$save['rules'] = [];
		}
		$save['rules'] = json_encode($save['rules']);

		$achievementId = $this->getId();

		$dbPending = $db->writesOrCallbacksPending();
		if (!$dbPending) {
			$db->begin();
		}

		if ($achievementId > 0) {
			//Do the update.
			$result = $db->update(
				'achievement',
				$save,
				['aid' => $achievementId],
				__METHOD__
			);
		} else {
			$save['created'] = time();
			$this->data['created'] = $save['created'];
			$save['unique_hash'] = md5($save['name'].$save['description'].$save['created']);
			$this->data['unique_hash'] = $save['unique_hash'];
			//Do the insert.
			$result = $db->insert(
				'achievement',
				$save,
				__METHOD__
			);
			$achievementId = $db->insertId();
		}

		if ($result !== false) {
			$this->setId($achievementId);

			global $wgUser;
			if ($wgUser->isAllowed('edit_meta_achievements')) {
				$db->delete(
					'achievement_link',
					['achievement_id' => $achievementId],
					__METHOD__
				);

				if (count($this->requires)) {
					foreach ($this->requires as $requires) {
						$db->insert(
							'achievement_link',
							[
								'achievement_id'	=> $achievementId,
								'requires'			=> $requires
							],
							__METHOD__
						);
					}
				}
			}

			if (!$dbPending) {
				$db->commit();
			}

			//Enforce sanity on data.
			$this->data['aid']		= $achievementId;
			$this->data['edited']	= $save['edited'];

			if ($this->queueMegaUpdate) {
				\Cheevos\SiteMegaUpdate::queue(['site_key' => $dsSiteKey]);
			}

			$success = true;
		} else {
			if (!$dbPending) {
				$db->rollback();
			}
		}

		return $success;
	}

	/**
	 * Set the Achievement ID
	 *
	 * @access	public
	 * @param	integer	Achievement ID
	 * @return	boolean	True on success, false if the ID is already set.
	 */
	public function setId($id) {
		if (!isset($this->data['aid'])) {
			$this->data['aid'] = intval($id);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the database identification number for this Achievement.
	 *
	 * @access	public
	 * @return	integer	Achievement ID
	 */
	public function getId() {
		return intval($this->data['aid']);
	}

	/**
	 * Return if this achievement exists.
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function exists() {
		return $this->data['aid'] > 0;
	}

	/**
	 * Return if this object is loaded.
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isLoaded() {
		return $this->isLoaded;
	}

	/**
	 * Set the Achievement Hash
	 *
	 * @access	private
	 * @param	string	Hash Key
	 * @return	boolean	True on success, false if the hash is already set.
	 */
	private function setHash($hash) {
		if (!isset($this->data['unique_hash']) && strlen($hash) === 32) {
			$this->data['unique_hash'] = $hash;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the hash for this Achievement.
	 *
	 * @access	public
	 * @return	string	Achievement Hash
	 */
	public function getHash() {
		return $this->data['unique_hash'];
	}

	/**
	 * Set the name.
	 *
	 * @access	public
	 * @param	string	Name
	 * @return	void
	 */
	public function setName($name) {
		$this->data['name'] = substr($name, 0, 50);
	}

	/**
	 * Return the name.
	 *
	 * @access	public
	 * @return	string	Name
	 */
	public function getName() {
		return $this->data['name'];
	}

	/**
	 * Set the description.
	 *
	 * @access	public
	 * @param	string	Description
	 * @return	void
	 */
	public function setDescription($description) {
		$this->data['description'] = substr($description, 0, 150);
	}

	/**
	 * Return the description.
	 *
	 * @access	public
	 * @return	string	Description
	 */
	public function getDescription() {
		return $this->data['description'];
	}

	/**
	 * Validates and sets the image URL.
	 *
	 * @access	public
	 * @param	string	Image URL
	 * @return	boolean	Validated Successfully
	 */
	public function setImageUrl($imageUrl) {
		$imageUrl = filter_var($imageUrl, FILTER_VALIDATE_URL);
		if ($imageUrl !== false) {
			$this->data['image_url'] = $imageUrl;
			return true;
		} else {
			$this->data['image_url'] = '';
			return false;
		}
	}

	/**
	 * Return the image URL.
	 *
	 * @access	public
	 * @return	string	Image URL
	 */
	public function getImageUrl() {
		return $this->data['image_url'];
	}

	/**
	 * Set the number of points.
	 *
	 * @access	public
	 * @param	integer	Points
	 * @return	void
	 */
	public function setPoints($points) {
		$this->data['points'] = abs(intval($points));
	}

	/**
	 * Return the number of points.
	 *
	 * @access	public
	 * @return	integer	Points
	 */
	public function getPoints() {
		return intval($this->data['points']);
	}

	/**
	 * Mark this achievement as deleted
	 *
	 * @access	public
	 * @param	boolean	[Optional] Is Deleted
	 * @return	void
	 */
	public function setDeleted($deleted = true) {
		$this->data['deleted'] = ($deleted ? 1 : 0);
	}

	/**
	 * Is this a deleted achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Deleted
	 */
	public function isDeleted() {
		return (bool) $this->data['deleted'];
	}

	/**
	 * Set to be a manually awarded achievement.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Is Manually Awarded
	 * @return	void
	 */
	public function setManuallyAwarded($manual = true) {
		$this->data['manual_award'] = ($manual ? 1 : 0);
	}

	/**
	 * Is this a manually awarded achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Manually Awarded
	 */
	public function isManuallyAwarded() {
		return (bool) $this->data['manual_award'];
	}

	/**
	 * Set the category ID.
	 *
	 * @access	public
	 * @param	integer	Category ID
	 * @return	void
	 */
	public function setCategoryId($categoryId) {
		$this->data['category_id'] = intval($categoryId);
	}

	/**
	 * Return the category
	 *
	 * @access	public
	 * @return	integer	Points
	 */
	public function getCategoryId() {
		return intval($this->data['category_id']);
	}

	/**
	 * Return the AchievementCategory object this achievement is registered to.
	 *
	 * @access	public
	 * @return	mixed	AchievementCategory object or false if it does not belong to a category.
	 */
	public function getCategory() {
		if ($this->data['category_id'] > 0) {
			return AchievementCategory::newFromId($this->data['category_id']);
		} else {
			return false;
		}
	}

	/**
	 * Set to be a secret achievement.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Is Secret
	 * @return	void
	 */
	public function setSecret($secret = true) {
		$this->data['secret'] = ($secret ? 1 : 0);
	}

	/**
	 * Is this a secret achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Secret
	 */
	public function isSecret() {
		return (bool) $this->data['secret'];
	}

	/**
	 * Set to be part of the default site mega achievement.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Is Part of Default Mega
	 * @return	void
	 */
	public function setPartOfDefaultMega($default = true) {
		if (intval($default) !== $this->data['part_of_default_mega']) {
			$this->queueMegaUpdate = true;
		}
		$this->data['part_of_default_mega'] = ($default ? 1 : 0);
	}

	/**
	 * Is this part of the default site mega achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Part of Default Mega
	 */
	public function isPartOfDefaultMega() {
		return (bool) $this->data['part_of_default_mega'];
	}

	/**
	 * Set what achievements this one requires.
	 * Overrides any existing; manipulation must be done before calling this function.
	 *
	 * @access	public
	 * @param	array	Achievement IDs
	 * @return	boolean	True on success, false if the hash is already set.
	 */
	public function setRequires($requires) {
		$this->requires = (array) $requires;
	}

	/**
	 * Return the achievement(s) this achievement requires.
	 *
	 * @access	public
	 * @return	array	Achievement IDs
	 */
	public function getRequires() {
		return $this->requires;
	}

	/**
	 * Return the achievement(s) this achievement is required by.
	 * There is no corresponding setRequiredBy().  This function is only for informational purposes.
	 *
	 * @access	public
	 * @return	array	Achievement IDs
	 */
	public function getRequiredBy() {
		return $this->requiredBy;
	}

	/**
	 * Set the number of times an achievement trigger must be triggered.
	 *
	 * @access	public
	 * @param	integer	Increment
	 * @return	void
	 */
	public function setIncrement($points) {
		$this->data['rules']['increment'] = abs(intval($points));
	}

	/**
	 * Return the increment amount.
	 *
	 * @access	public
	 * @return	integer	Increment
	 */
	public function getIncrement() {
		return (isset($this->data['rules']['increment']) ? intval($this->data['rules']['increment']) : 0);
	}

	/**
	 * Set the achievement triggers(hooks).
	 *
	 * @access	public
	 * @param	array	Triggers
	 * @return	void
	 */
	public function setTriggers($triggers) {
		//@TODO: addTrigger(), validation.
		$this->data['rules']['triggers'] = $triggers;
	}

	/**
	 * Return the achievement triggers(hooks).
	 *
	 * @access	public
	 * @return	integer	Triggers
	 */
	public function getTriggers() {
		return (array) $this->data['rules']['triggers'];
	}

	/**
	 * Is this a mega achievement?
	 *
	 * @access	public
	 * @return	boolean	False
	 */
	public function isMega() {
		return false;
	}

	/**
	 * Awards this achievement to an user.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	integer	[Optional] Override the default increment amount.
	 * @return	boolean	Successfully Awarded
	 */
	public function award($user, $incrementAmount = null) {
		$db = wfGetDB(DB_MASTER);

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
		if (!$this->getId() || $globalId < 1) {
			return false;
		}

		$result = $db->select(
			['achievement_earned'],
			['*'],
			[
				'achievement_id'	=> $this->getId(),
				'curse_id'			=> $globalId
			],
			__METHOD__
		);

		$exists = $result->fetchRow();

		if ($exists['date'] > 0) {
			//They have it already.
			return false;
		} elseif (empty($exists)) {
			$exists = [
				'achievement_id'	=> $this->getId(),
				'curse_id'			=> $globalId
			];
		}

		if ($this->getIncrement()) {
			if ($incrementAmount !== null && ($incrementAmount > 0 || $incrementAmount < 0)) {
				$exists['increment'] = $exists['increment'] + $incrementAmount;
			} else {
				if (!isset($exists['increment'])) {
					$exists['increment'] = 0;
				}
				$exists['increment']++;
			}
			if ($exists['increment'] >= $this->getIncrement()) {
				$exists['date'] = time();
				//Make sure the final increment amount matches the maximum.  Going over the maximum occurs with $incrementAmount causing an overage.
				$exists['increment'] = $this->getIncrement();
			}
		} else {
			$exists['date'] = time();
		}

		if (isset($exists['aeid'])) {
			$success = $db->update(
				'achievement_earned',
				$exists,
				['aeid' => $exists['aeid']],
				__METHOD__
			);
		} else {
			$success = $db->insert(
				'achievement_earned',
				$exists,
				__METHOD__
			);
		}

		//$exists['date'] does not mean it already exists.  It was set a few lines up after verifying increments and now this is to verify it was just earned this moment.
		if ($success && isset($exists['date']) && $exists['date'] > 0) {
			if (count($this->getRequiredBy())) {
				//If this achievement is required by a meta achievement lets check that and award it also if all those achievements the meta requires has been met.
				foreach ($this->getRequiredBy() as $requiredByAId) {
					$_achievementTemp = self::newFromId($requiredByAId, true);
					//Safety checks just in case something goes wrong loading the necessary achievement.
					if ($_achievementTemp !== false && count($_achievementTemp->getRequires())) {
						$result = $db->select(
							['achievement_earned'],
							['count(*) as total'],
							[
								'achievement_id'	=> $_achievementTemp->getRequires(),
								'curse_id'			=> $globalId,
								'date > 0'
							],
							__METHOD__
						);

						$total = $result->fetchRow();

						if ($total['total'] == count($_achievementTemp->getRequires())) {
							$_achievementTemp->award($user);
						}
					}
				}
			}

			wfRunHooks('AchievementAwarded', [$this, $user]);
		}

		return ($success && isset($exists['date']) && $exists['date'] > 0);
	}

	/**
	 * Unawards this achievement from an user.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	boolean	[Optional] Bypass Date Check, Default False
	 * @return	boolean	Successfully Awarded
	 */
	public function unaward($user, $bypassDateCheck = false) {
		$db = wfGetDB(DB_MASTER);

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
		if (!$this->getId() || $globalId < 1) {
			return false;
		}

		$parameters = [
			'achievement_id' => $this->getId(),
			'curse_id'	=> $globalId
		];

		$result = $db->select(
			['achievement_earned'],
			['*'],
			$parameters,
			__METHOD__
		);

		$exists = $result->fetchRow();

		if ($bypassDateCheck === false) {
			if ($exists['date'] > 0) {
				return true;
			}
			$parameters[] = 'date < 1';
		}

		$success = $db->delete(
			'achievement_earned',
			$parameters,
			__METHOD__
		);
		$db->commit();

		if ($success && $db->affectedRows() && $exists['date'] > 0) {
			//Only trigger the unawarded hook if it the achievement was fully earned otherwise the user will get negative points and never have earned the achievement.
			wfRunHooks('AchievementUnawarded', [$this, $user]);
		}

		return $success;
	}

	/**
	 * Gets the known hook information for display.  This is mainly used as reference on the admin achievement form and not intended to be used for programatic purposes.
	 *
	 * @access	public
	 * @return	array	Hook information from the database.
	 */
	static public function getKnownHooks() {
		if (!count(self::$knownHooks)) {
			$db = wfGetDB(DB_MASTER);

			$results = $db->select(
				['achievement_hook'],
				['*'],
				null,
				__METHOD__,
				[
					'ORDER BY'	=> 'hook ASC'
				]
			);

			while ($row = $results->fetchRow()) {
				self::$knownHooks[$row['category']][] = $row['hook'];
			}
		}

		return self::$knownHooks;
	}

	/**
	 * Get all registered rules(triggers, increments) from existing achievements.
	 *
	 * @access	public
	 * @return	array	Achievement Rules
	 */
	static public function getAllRules() {
		$rules = [];
		$db = wfGetDB(DB_MASTER);

		$results = $db->select(
			['achievement'],
			[
				'unique_hash',
				'rules'
			],
			[
				'deleted'		=> 0,
				'manual_award'	=> 0
			],
			__METHOD__
		);

		while ($row = $results->fetchRow()) {
			$row['rules'] = @json_decode($row['rules'], true);
			$row['rules']['triggers'] = (isset($row['rules']['triggers']) ? (array) $row['rules']['triggers'] : []);
			$rules[$row['unique_hash']] = $row['rules'];
		}

		return $rules;
	}

	/**
	 * Tests if the rule condition passes.
	 *
	 * @access	public
	 * @param	mixed	Data to test
	 * @param	array	Condition test
	 * @return	boolean	Successfully Passes
	 */
	static public function testCondition($data, $test) {
		$_conditionPasses = false;
		$operator = $test[0];
		$validator = $test[1];
		$otherValidator = $validator;
		if (!is_numeric($validator) && is_string($validator)) {
			$otherValidator = strlen($validator);
		}
		switch ($operator) {
			case '==':
				$_conditionPasses = ($data == $validator);
				break;
			case '!=':
				$_conditionPasses = ($data != $validator);
				break;
			case '>=':
				$_conditionPasses = ($data >= $otherValidator);
				break;
			case '<=':
				$_conditionPasses = ($data <= $otherValidator);
				break;
			case '>':
				$_conditionPasses = ($data > $otherValidator);
				break;
			case '<':
				$_conditionPasses = ($data < $otherValidator);
				break;
		}
		return $_conditionPasses;
	}
}

/*
 * This class is to be used when an Achievement needs to be display containing information that may be present on a different wiki's database that is not accessible.
 * Using this class will prevent issues with loading a cached database row from a remote database that could somehow accidentally be saved to the local database with a conflicting ID.
 * Used by mega achievements on the master when pulling down remote achievements.
*/
class FakeAchievement extends Achievement {
	/**
	 * Create a new instance of this class from an Achievement database identification number.
	 *
	 * @access	public
	 * @param	integer	Achievement database identification number.
	 * @param	boolean	Use cache if possible.
	 * @return	mixed	Achievement object or false on error.
	 */
	static public function newFromId($id, $useCache = false) {
		return false;
	}

	/**
	 * Create a new instance of this class from an Achievement unique hash.
	 *
	 * @access	public
	 * @param	string	32 character long unique Achievement hash.
	 * @param	boolean	Use cache if possible.
	 * @return	mixed	Achievement object or false on error.
	 */
	static public function newFromHash($hash, $useCache = false) {
		return false;
	}

	/**
	 * Load a new Achievement object from a database row.
	 *
	 * @access	public
	 * @param	array	Database Row
	 * @return	mixed	Achievement or false on error.
	 */
	static public function newFromRow($row) {
		$achievement = new self;

		$achievement->newFrom = 'row';

		$achievement->load($row);

		if (!$achievement->getId()) {
			return false;
		}

		return $achievement;
	}

	/**
	 * Get all achievements.
	 *
	 * @access	public
	 * @param	boolean	Use cache if possible.
	 * @return	array	Achievement objects
	 */
	static public function getAll($useCache = false) {
		return false;
	}

	/**
	 * Load from the database.
	 *
	 * @access	public
	 * @param	array	[Optional] Database row to load from.
	 * @return	boolean	Success
	 */
	public function load($row = null) {
		if (is_array($row)) {
			if (!is_array($row['rules'])) {
				$row['rules'] = @json_decode($row['rules'], true);
				$row['rules']['triggers'] = (array) $row['rules']['triggers'];
			}

			if (is_array($row['requires'])) {
				$this->requires[] = $row['requires'];
				$this->requires = array_unique($this->requires);
			}

			if (is_array($row['required_by'])) {
				$this->requiredBy[] = $row['required_by'];
				$this->requiredBy = array_unique($this->requiredBy);
			}

			$this->data = $row;

			$this->isLoaded = true;
		} else {
			return false;
		}
	}

	/**
	 * Save Achievement to the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		return false;
	}
}
