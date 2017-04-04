<?php
/**
 * Achievements
 * Cheevos Class
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Achievements
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosHelper {
	/**
	 * Return the language code the current user.
	 *
	 * @access	public
	 * @return	string	Language Code
	 */
	public static function getUserLanguage() {
		try {
			$user = \RequestContext::getMain()->getUser();
			$code = $user->getOption('language');
		} catch (\Exception $e) {
			$code = "en"; //"faulure? English is best anyway."  --Cameron Chunn, 2017-03-02 15:37:33 -0600
		}
		return $code;
	}

	/**
	 * Return the language for the wiki.
	 *
	 * @access	public
	 * @return	string	Language Code
	 */
	static public function getWikiLanuage() {
		global $wgLanguageCode;
		return $wgLanguageCode;
	}

	/**
	 * Turns an array of CheevosStatProgress objects into an array that is easier to consume.
	 *
	 * @access	public
	 * @param	array	Flat array.
	 * @return	array	Nice array.
	 */
	static public function makeNiceStatProgressArray($stats) {
		$nice = [];
		foreach ($stats as $stat) {
			$_data = [
				'stat_id' => $stat['stat_id'],
				'count' => $stat['count'],
				'last_incremented' => $stat['last_incremented'],
			];
			if (isset($stat['site_key'])) {
				$nice[$stat['site_key']][$stat['user_id']][$stat['stat']] = $_data;
			} else {
				$nice[$stat['user_id']][$stat['stat']] = $_data;
			}
		}
		return $nice;
	}

	/**
	 * Removes achievement parents when a child is presents.
	 *
	 * @access	public
	 * @param	array	CheevosAchievement objects.
	 * @return	array	CheevosAchievement objects.
	 */
	static public function pruneAchievements($achievements, $removeParents = true, $removeDeleted = true) {
		if (count($achievements)) {
			$removeParents = [];
			foreach ($achievements as $key => $achievement) {
				if ($achievement->getParent_Id() > 0 && $achievement->getDeleted_At() == 0) {
					$removeParents[] = $achievement->getParent_Id();
				}
			}
			if ($removeParents && count($removeParents)) {
				foreach ($achievements as $key => $achievement) {
					if (in_array($achievement->getId(), $removeParents)) {
						unset($achievements[$key]);
					}
				}
			}
		}
		return $achievements;
	}
}
