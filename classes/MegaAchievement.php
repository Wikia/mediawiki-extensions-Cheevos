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

class MegaAchievement extends Achievement {
	/**
	 * Achievement Service Object
	 *
	 * @var		object
	 */
	static private $service;

	/**
	 * Cache of Raw Mega Achievement Information
	 *
	 * @var		array
	 */
	static private $megaAchievementsCache = [];

	/**
	 * Redis object holder.
	 *
	 * @var		object
	 */
	static private $redis = null;

	/**
	 * Redis Cache Key
	 *
	 * @var		string
	 */
	static private $cacheKey = 'achievement:cache:megas';

	/**
	 * Last error return by the service.
	 *
	 * @var		string
	 */
	private $lastError = false;

	/**
	 * Object fully loaded with data.
	 *
	 * @var		boolean
	 */
	protected $isLoaded = false;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		self::setup();
	}

	/**
	 * Setup the mega achievement service.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function setup() {
		$initializeCache = false;
		if (!self::$service instanceOf MegaService) {
			self::$service = MegaService::getInstance();
			self::$redis = \RedisCache::getClient('cache');
			$initializeCache = true;
		}

		if ($initializeCache) {
			$achievements = [];
			$_achievements = [];
			$usingRedis = false;
			if (self::$redis !== false) {
				try {
					$_achievements = self::$redis->hGetAll(self::$cacheKey);
					$usingRedis = true;
				} catch (RedisException $e) {
					wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
					return;
				}
			}
			$redisEmpty = false;
			if (empty($_achievements)) {
				$redisEmpty = true;
				$_achievements = self::$service->_getAchievementsForHydra();
			}

			foreach ($_achievements as $achievement) {
				if ($usingRedis && !is_array($achievement)) {
					$achievement = unserialize($achievement);
				}
				if (is_array($achievement) && array_key_exists('id', $achievement)) {
					$achievements[$achievement['id']] = $achievement;
				}
			}
			self::$megaAchievementsCache = $achievements;

			if ($usingRedis && $redisEmpty && count($achievements)) {
				$args = [];
				foreach ($achievements as $key => $data) {
					$args[$key] = serialize($data);
				}
				try {
					self::$redis->hMSet(self::$cacheKey, $args);
				} catch (RedisException $e) {
					wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
					return;
				}
			}
		}
	}

	/**
	 * Create a new instance of this class from a mega achievement ID.
	 *
	 * @access	public
	 * @param	integer	Mega Achievement ID
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	mixed	MegaAchievement object or false on error.
	 */
	static public function newFromId($id, $useCache = false) {
		self::setup();

		if ($id < 1) {
			return false;
		}

		$achievement = new self;
		$achievement->setId(intval($id));

		if ($useCache && isset(self::$megaAchievementsCache[$id])) {
			$achievement->newFrom = 'cache';
		} else {
			$achievement->newFrom = 'id';
		}

		$success = $achievement->load();

		return ($success ? $achievement : false);
	}

	/**
	 * Get all mega achievements.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	array	MegaAchievement objects
	 */
	static public function getAll($useCache = false) {
		self::setup();
		foreach (self::$megaAchievementsCache as $achievementId => $achievement) {
			$_achievement = self::newFromId($achievementId, $useCache);
			if ($_achievement !== false) {
				$achievements[$achievementId] = $_achievement;
			}
		}

		return $achievements;
	}

	/**
	 * Get all mega achievements for a given site key.
	 *
	 * @access	public
	 * @param	string	Site Hash Key
	 * @param	boolean	[Optional] Use cache if possible.
	 * @return	array	MegaAchievement objects
	 */
	static public function getAllForSite($siteKey, $useCache = false) {
		self::setup();
		foreach (self::$megaAchievementsCache as $achievementId => $achievement) {
			if ($achievement['subSiteKey'] != $siteKey) {
				continue;
			}
			$_achievement = self::newFromId($achievementId, $useCache);
			if ($_achievement !== false) {
				$achievements[$achievementId] = $_achievement;
			}
		}

		return $achievements;
	}

	/**
	 * Load data for the mega.
	 *
	 * @access	public
	 * @param	array	[Unused] Database row to load from.
	 * @return	boolean	Success
	 */
	public function load($row = null) {
		$DB = wfGetDB(DB_MASTER);

		if (!$this->isLoaded) {
			switch ($this->newFrom) {
				case 'id':
					//Call out to service.
					$achievement = self::$service->getAchievementByID(['achievementID' => $this->getId()]);
					if (is_array($achievement) && $achievement['id'] == $this->getId()) {
						$this->data = $achievement;
					} else {
						return false;
					}
					break;
				case 'cache':
					$this->data = self::$megaAchievementsCache[$this->getId()];
					break;
				default:
					return false;
					break;
			}

			$this->data['requires'] = @json_decode($this->data['requires'], true);
			if (!is_array($this->data['requires'])) {
				$this->data['requires'] = [];
			}

			$this->isLoaded = true;
		}

		return true;
	}

	/**
	 * Save MegaAchievement to the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		$DB = wfGetDB(DB_MASTER);

		$success = false;

		$save = $this->data;
		$save['requires'] = json_encode($save['requires']);
		$site = \DynamicSettings\Wiki::loadFromHash($save['subSiteKey']);
		if ($site !== false) {
			$save['hostName'] = $site->getDomains()->getDomain();
		} else {
			global $wgServer;
			$save['hostName'] = parse_url($wgServer, PHP_URL_HOST);
			$save['subSiteKey'] = '';
		}

		if ($this->getId()) {
			//Okay.  JSON data keys returned are in TitleCase.  Argument/Array keys in JSON sent have to be in camelCase.  Which means an achievement pulled down could not be immediately/programmatically sent back up easily.  The keys for achievement IDs returned(ID) are different than what is used to send(achievementID) them up.  That is why this line exists here along with other various hard codes to fix issues with the service per the naming conventions in .NET.
			$save['achievementID'] = $save['id'];

			$return = self::$service->updateAchievement($save, $megaAchievement);
			if (is_array($return) && !$return['errorCode']) {
				$success = true;
			}
		} else {
			$return = self::$service->addAchievement($save);
			if (is_array($return) && !$return['errorCode']) {
				$success = true;
			}
			$this->setId($return['id']);
		}

		if (!$success) {
			$this->lastError = $return['errorCode'];
		}

		if (self::$redis !== false) {
			try {
				//self::$redis->hSet(self::$cacheKey, $this->getId(), serialize($save));
				self::$redis->del(self::$cacheKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		return $success;
	}

	/**
	 * Set the mega achievement ID.
	 *
	 * @access	public
	 * @param	integer	MegaAchievement ID
	 * @return	boolean	True on success, false if the ID is already set.
	 */
	public function setId($id) {
		if (!isset($this->data['id'])) {
			$this->data['id'] = intval($id);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the database identification number for this MegaAchievement.
	 *
	 * @access	public
	 * @return	integer	MegaAchievement ID
	 */
	public function getId() {
		return intval($this->data['id']);
	}

	/**
	 * Return if this achievement exists.
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function exists() {
		return $this->data['id'] > 0;
	}

	/**
	 * Return a prefixed ID.
	 *
	 * @access	public
	 * @return	string	Prefixed ID
	 */
	public function getHash() {
		return 'mega-'.$this->getId();
	}

	/**
	 * Set the name.
	 *
	 * @access	public
	 * @param	string	Name
	 * @return	void
	 */
	public function setName($name) {
		$this->data['name'] = substr($name, 0, 255);
	}

	/**
	 * Set the description.
	 *
	 * @access	public
	 * @param	string	Description
	 * @return	void
	 */
	public function setDescription($description) {
		$this->data['description'] = substr($description, 0, 255);
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
			$this->data['imagePath'] = $imageUrl;
			return true;
		} else {
			$this->data['imagePath'] = '';
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
		return $this->data['imagePath'];
	}

	/**
	 * Return the number of points.
	 *
	 * @access	public
	 * @return	integer	Points
	 */
	public function getPoints() {
		return 0;
	}

	/**
	 * Mark this achievement as deleted
	 *
	 * @access	public
	 * @param	boolean	[Optional] Is Deleted
	 * @return	void
	 */
	public function setDeleted($deleted = true) {
		$this->data['status'] = ($deleted ? 2 : 1);
	}

	/**
	 * Is this a deleted achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Deleted
	 */
	public function isDeleted() {
		return ($this->data['status'] == 2 ? true : false);
	}

	/**
	 * Is this part of the default site mega achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Part of Default Mega
	 */
	public function isPartOfDefaultMega() {
		return false;
	}

	/**
	 * Is this a manually awarded achievement?
	 *
	 * @access	public
	 * @return	boolean	Is Manually Awarded
	 */
	public function isManuallyAwarded() {
		return false;
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
		$this->data['requires'] = (array) $requires;
	}

	/**
	 * Return the achievement(s) this achievement requires.
	 *
	 * @access	public
	 * @return	array	Achievement IDs
	 */
	public function getRequires() {
		if (!is_array($this->data['requires'])) {
			$this->data['requires'] = [];
		}
		return $this->data['requires'];
	}

	/**
	 * Megas can not be required by others.
	 *
	 * @access	public
	 * @return	array	Blank
	 */
	public function getRequiredBy() {
		return [];
	}

	/**
	 * Return the increment amount.
	 *
	 * @access	public
	 * @return	integer	Increment
	 */
	public function getIncrement() {
		return 0;
	}

	/**
	 * Set the site key.
	 *
	 * @access	public
	 * @param	string	Site Key
	 * @return	boolean	True on success, false if the hash is already set.
	 */
	public function setSiteKey($hash) {
		if (!$this->data['subSiteKey'] && strlen($hash) === 32) {
			$this->data['subSiteKey'] = $hash;
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
	public function getSiteKey() {
		return $this->data['subSiteKey'];
	}

	/**
	 * Is this a mega achievement?
	 *
	 * @access	public
	 * @return	boolean	True
	 */
	public function isMega() {
		return true;
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
		global $dsSiteKey;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
		if (!$this->getId() || !$globalId) {
			return false;
		}

		$progress = \Cheevos\MegaProgress::newFromGlobalId($globalId);
		if ($progress === false) {
			return false;
		}
		if ($progress->isEarned($this->getId())) {
			//Already earned.
			return false;
		}

		$return = self::$service->awardAchievement(
			[
				'userID'		=> $globalId,
				'achievementID'	=> $this->getId()
			],
			true
		);

		if (is_array($return) && $return['status'] == 1) {
			wfRunHooks('AchievementAwarded', [$this, $user]);
			return true;
		} else {
			$this->lastError = $return['errorMessage'];
			return false;
		}
	}

	/**
	 * Unawards this achievement from an user.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	boolean	[Unused] Bypass Date Check, Default False - Does not apply to megas.
	 * @return	boolean	Successfully Awarded
	 */
	public function unaward($user, $bypassDateCheck = false) {
		global $dsSiteKey;

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
		if (!$this->getId() || !$globalId) {
			return false;
		}

		$progress = MegaProgress::newFromGlobalId($globalId);
		if ($progress === false) {
			return false;
		}

		$earnedAchievement = $progress->forAchievement($this->getId());

		$return = self::$service->unawardAchievement(
			[
				'userID'				=> $globalId,
				'earnedAchievementID'	=> $earnedAchievement['id']
			],
			true
		);

		if (is_array($return) && $return['returnCode'] == true) {
			wfRunHooks('AchievementUnawarded', [$this, $user]);
			return true;
		} else {
			$this->lastError = $return['errorMessage'];
			return false;
		}
	}

	/**
	 * Not implemented on megas.
	 *
	 * @access	public
	 * @return	array	Blank Array
	 */
	static public function getKnownHooks() {
		return [];
	}

	/**
	 * Return the last error from the service.
	 *
	 * @access	public
	 * @return	string	Last error returned from the service.
	 */
	public function getLastError() {
		return $this->lastError;
	}
}
