<?php
/**
 * Cheevos
 * Progress Class
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class MegaProgress extends Progress {
	/**
	 * Achievement Service Object
	 *
	 * @var		object
	 */
	static private $service;

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
	public function __construct($curseId) {
		if (!self::$service instanceOf MegaService) {
			self::$service = MegaService::getInstance();
		}

		parent::__construct($curseId);
	}

	/**
	 * Setup the mega achievement service.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function setup() {
		if (!self::$service instanceOf MegaService) {
			self::$service = MegaService::getInstance();
		}
	}

	/**
	 * Create a new instance from a Global ID.
	 *
	 * @access	public
	 * @param	integer	Global ID
	 * @return	mixed	Progress object or false on error.
	 */
	static public function newFromGlobalId($globalId) {
		if ($globalId < 1) {
			return false;
		}

		$progress = new self($globalId);
		$success = $progress->load();

		return ($success ? $progress : false);
	}

	/**
	 * Load from the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function load() {
		if (!$this->isLoaded) {
			$megaAchievements = self::$service->getAllAchievementsByUserID(['userID' => $this->curseId]);
			if (isset($megaAchievements['errorMessage'])) {
				$this->lastError = $megaAchievements['errorMessage'];
				return false;
			}
			if (is_array($megaAchievements) && count($megaAchievements)) {
				foreach ($megaAchievements as $index => $megaAchievement) {
					if ($megaAchievement['statusMessage'] != 'Awarded') {
						continue;
					}
					$megaAchievement['date'] = self::$service->convertDotNetDate($megaAchievement['dateModified']);
					$this->progress[$megaAchievement['achievementID']] = $megaAchievement;
					if ($megaAchievement['date'] > 0 && $megaAchievement['status'] === 1) {
						$this->earned[$megaAchievement['achievementID']] = $megaAchievement['date'];
					}
				}
				$this->isLoaded = true;
			}
		}

		return true;
	}

	/**
	 * Return the earned date in the stored UNIX style timestamp.
	 *
	 * @access	public
	 * @param	string	Achievement Hash
	 * @return	integer	Timestamp
	 */
	public function getEarnedTimestamp($hash) {
		return intval($this->progress[$hash]['date']);
	}

	/**
	 * Return the earned date transformed to the user's date preferences.
	 *
	 * @access	public
	 * @param	string	Achievement Hash
	 * @return	string	User Date
	 */
	public function getEarnedUserDate($hash) {
		global $wgLang, $wgUser;

		return $wgLang->userDate($this->progress[$hash]['date'], $wgUser);
	}

	/**
	 * Return an optionally limited number of recently earned achievements.
	 *
	 * @access	public
	 * @param	mixed	Integer total to return or false for no limit.  Defaults to 10.
	 * @return	array	Recently earned Achievement objects.
	 */
	public function getRecentlyEarnedAchievements($limit = 10) {
		$hashes = $this->getRecentlyEarnedHashes($limit);
		$achievements = [];
		foreach ($hashes as $hash) {
			$achievement = MegaAchievement::newFromId($hash);
			if ($achievement !== false) {
				$achievements[$hash] = $achievement;
			}
		}
		return $achievements;
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
