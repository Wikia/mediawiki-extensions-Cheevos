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

class Progress {
	/**
	 * Curse ID
	 *
	 * @var		integer
	 */
	protected $curseId = -1;

	/**
	 * Object Loaded
	 *
	 * @var		boolean
	 */
	protected $isLoaded = false;

	/**
	 * Progress Information of $achievementHashes => $info straight from the database.
	 *
	 * @var		array
	 */
	protected $progress = [];

	/**
	 * Achievement hashes of earned achievements.
	 *
	 * @var		array
	 */
	protected $earned = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct($curseId) {
		$this->curseId = intval($curseId);
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
		$DB = wfGetDB(DB_MASTER);

		if (!$this->isLoaded) {
			$result = $DB->select(
				[
					'achievement_earned',
					'achievement'
				],
				[
					'achievement_earned.*',
					'achievement.unique_hash'
				],
				['achievement_earned.curse_id' => $this->curseId],
				__METHOD__,
				['ORDER BY' => 'achievement_earned.date DESC'],
				[
					'achievement' => [
						'INNER JOIN', 'achievement.aid = achievement_earned.achievement_id'
					]
				]
			);

			$progress = [];
			while ($row = $result->fetchRow()) {
				$this->progress[$row['unique_hash']] = $row;
				if ($row['date'] > 0) {
					$this->earned[$row['unique_hash']] = $row['date'];
				}
			}
			$this->isLoaded = true;
		}

		return true;
	}

	/**
	 * Is the given achievement hash earned?
	 *
	 * @access	public
	 * @param	string	Achievement Hash
	 * @return	boolean	Earned
	 */
	public function isEarned($hash) {
		return array_key_exists($hash, $this->earned);
	}

	/**
	 * Return all progress information for a given achievement hash.
	 *
	 * @access	public
	 * @param	string	Achievement Hash
	 * @return	array	Achievement Progress
	 */
	public function forAchievement($hash) {
		if (isset($this->progress[$hash])) {
			$data = $this->progress[$hash];
			$data['earned_date'] = $this->getEarnedUserDate($hash);
			return $data;
		} else {
			return [];
		}
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
	 * Return all the achievement hashes of earned achievements.
	 *
	 * @access	public
	 * @return	array	Earned Achievement Hashes
	 */
	public function getEarnedHashes() {
		return array_keys($this->earned);
	}

	/**
	 * Return the total number of earned achievements.
	 *
	 * @access	public
	 * @return	integer	Total
	 */
	public function getTotalEarned() {
		return count($this->earned);
	}

	/**
	 * Get a limited number of recently earned achievement hashes.
	 *
	 * @access	public
	 * @param	mixed	Integer total to return or false for no limit.  Defaults to 10.
	 * @return	array	Achievement hashes of recently earned achievements.
	 */
	public function getRecentlyEarnedHashes($limit = 10) {
		$recent = $this->earned;
		arsort($recent, SORT_NUMERIC);
		if ($limit !== false && is_int($limit)) {
			$recent = array_slice($recent, 0, $limit, true);
		}
		return array_keys($recent);
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
			$achievement = Achievement::newFromHash($hash);
			if ($achievement !== false) {
				$achievements[$hash] = $achievement;
			}
		}
		return $achievements;
	}
}
