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

class Cheevos {
	/**
	 * Constructor sets up the Swagger API Client for Cheevos
	 */
	public function __construct() {
		global $wgCheevosHost;

		\Swagger\Client\Configuration::getDefaultConfiguration()->setHost($wgCheevosHost);
		\Swagger\Client\Configuration::getDefaultConfiguration()->setSSLVerification(false);
		$this->api = new \Swagger\Client\Api\DefaultApi();
	}

	/**
	 * [getAchievement description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function getAchievement($id=null) {
		$siteId = 0; // Integer | The site id to use for locally overridden achievements.

		$achievements = [];
		try {
		    $result = is_numeric($id) ? $this->api->achievementIdGet($id) : $this->api->achievementsAllGet($siteId);
			if (!is_null($result->achievements)) {
				foreach ($result->achievements as $_achievement) {
					$achievements[$_achievement->getId()] = $_achievement;
				}
			}
		} catch (Exception $e) {
		    wfErrorLog('Exception in getAchievement: '. $e->getMessage(). PHP_EOL);
		}

		return $achievements;
	}

	/**
	 * [getAchievements description]
	 * @return [type] [description]
	 */
	public function getAchievements() {
		return self::getAchievement();
	}

	/**
	 * [deleteAchievement description]
	 * @param  [type]  $id     [description]
	 * @param  integer $userId [description]
	 * @return [type]          [description]
	 */
	public function deleteAchievement($id, $userId=0) {
		$result = false;
		try {
		    $result = $this->api->achievementIdDelete($id, $userId);
		} catch (Exception $e) {
			wfErrorLog('Exception deleteAchievement: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	/**
	 * [putAchievement description]
	 * @param  [type] $body [description]
	 * @param  [type] $id   [description]
	 * @return [type]       [description]
	 */
	public function putAchievement($body,$id=null) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$achievement = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement passed.
			}
		}

		$result = false;
		try {
		    $result = is_numeric($id) ? $this->api->achievementPut($id, $body) : $this->api->achievementsPut($body);
		} catch (Exception $e) {
		    wfErrorLog('Exception in putAchievement: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	/**
	 * [updateAchievement description]
	 * @param  [type] $id   [description]
	 * @param  [type] $body [description]
	 * @return [type]       [description]
	 */
	public function updateAchievement($id,$body) {
		return $this->putAchievement($body, $id);
	}

	/**
	 * [createAchievement description]
	 * @param  [type] $body [description]
	 * @return [type]       [description]
	 */
	public function createAchievement($body) {
		return $this->putAchievement($body);
	}

	/**
	 * [getCategory description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function getCategory($id=null) {
		$categorys = [];
		try {
			$result = is_numeric($id) ? $this->api->categoryIdGet($id) : $this->api->categorysAllGet();
			if (!is_null($result->categorys)) {
				foreach ($result->categorys as $_category) {
					$categorys[$_category->getId()] = $_category;
				}
			}
		} catch (Exception $e) {
			wfErrorLog('Exception in getCategory: '. $e->getMessage(). PHP_EOL);
		}

		return $categorys;
	}

	/**
	 * [getCategories description]
	 * @return [type] [description]
	 */
	public function getCategories() {
		return self::getCategory();
	}

	/**
	 * [deleteCategory description]
	 * @param  [type]  $id     [description]
	 * @param  integer $userId [description]
	 * @return [type]          [description]
	 */
	public function deleteCategory($id, $userId=0) {
		$result = false;
		try {
			$result = $this->api->categoryIdDelete($id, $userId);
		} catch (Exception $e) {
			wfErrorLog('Exception deleteCategory: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	/**
	 * [putCategory description]
	 * @param  [type] $body [description]
	 * @param  [type] $id   [description]
	 * @return [type]       [description]
	 */
	public function putCategory($body,$id=null) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$category = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid category passed.
			}
		}

		$result = false;
		try {
			$result = is_numeric($id) ? $this->api->categoryPut($id, $body) : $this->api->categorysPut($body);
		} catch (Exception $e) {
			wfErrorLog('Exception in putCategory: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	/**
	 * [updateCategory description]
	 * @param  [type] $id   [description]
	 * @param  [type] $body [description]
	 * @return [type]       [description]
	 */
	public function updateCategory($id,$body) {
		return $this->putCategory($body, $id);
	}

	/**
	 * [createCategory description]
	 * @param  [type] $body [description]
	 * @return [type]       [description]
	 */
	public function createCategory($body) {
		return $this->putCategory($body);
	}

	/**
	 * [increment description]
	 * @param  [type] $body [description]
	 * @return [type]       [description]
	 */
	public function increment($body) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$achievement = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement passed.
			}
		}

		$result = false;
		try {
			$result = $this->api->incrementPost($body);
		} catch (Exception $e) {
			wfErrorLog('Exception in increment: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	/**
	 * [stats description]
	 * @param  [type] $userId [description]
	 * @param  [type] $siteId [description]
	 * @param  [type] $global [description]
	 * @param  [type] $stat   [description]
	 * @param  [type] $limit  [description]
	 * @param  [type] $offset [description]
	 * @return [type]         [description]
	 */
	public function stats($userId, $siteId, $global, $stat, $limit=null, $offset=null) {
		$result = false;
		try {
		    $result = $this->api->statsGet($userId, $siteId, $global, $stat, $limit, $offset);
		} catch (Exception $e) {
		    echo 'Exception when stats: ', $e->getMessage(), PHP_EOL;
		}
		return $result;
	}

}