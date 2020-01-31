<?php
/**
 * Cheevos
 * Achievements API
 *
 * @package   Cheevos
 * @author    Alexia E. Smith, Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

use Cheevos\Cheevos;
use DynamicSettings\Sites;
use DynamicSettings\Wiki;

class CheevosStatsAPI extends ApiBase {
	/**
	 * API Initialized
	 *
	 * @var boolean
	 */
	private $initialized = false;

	/**
	 * Main Executor
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute() {
		$this->params = $this->extractRequestParams();

		switch ($this->params['do']) {
			case 'getGlobalStats':
				$response = $this->getGlobalStats();
				break;
			case 'getWikiStats':
				$response = $this->getWikiStats();
				break;
			case 'getWikiStatsTable':
				$response = $this->getWikiStatsTable();
				break;
			case 'getMegasTable':
				$response = $this->getMegasTable();
				break;
			case 'getAchievementUsers':
				$response = $this->getAchievementUsers();
				break;
			default:
				$this->dieWithError(['invaliddo', $this->params['do']]);
				break;
		}

		foreach ($response as $key => $value) {
			$this->getResult()->addValue(null, $key, $value);
		}
	}

	/**
	 * Get global stats data
	 *
	 * @return array
	 */
	public function getGlobalStats() {
		global $wgCheevosAchievementEngagementId, $wgCheevosMasterAchievementId;

		$achievements = Cheevos::getAchievements();
		$categories = Cheevos::getCategories();
		$wikis = Wiki::loadAll();

		$progressCount = Cheevos::getProgressCount();
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos::getProgressCount(null, $wgCheevosMasterAchievementId);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";

		$progressCountEngaged = Cheevos::getProgressCount(null, $wgCheevosAchievementEngagementId);
		$totalEarnedAchievementsEngaged = isset($progressCountEngaged['total']) ? $progressCountEngaged['total'] : "N/A";

		$customAchievements = [];

		foreach ($achievements as $a) {
			if ($a->getParent_Id() !== 0) {
				$customAchievements[$a->getSite_Key()][] = $a;
			}
		}

		$topAchieverCall = Cheevos::getProgressTop();
		$topUser = isset($topAchieverCall['counts'][0]['user_id']) ? $topAchieverCall['counts'][0]['user_id'] : false;

		if (!$topUser) {
			$topAchiever = [
				'name' => "API RETURNED NO USER",
				'img' => 'https://placehold.it/96x96'
			];
		} else {
			$user = Cheevos::getUserForServiceUserId($topUser);
			if ($user) {
				$topAchiever = [
					'name' => $user->getName(),
					'img' => "//www.gravatar.com/avatar/" . md5(strtolower(trim($user->getEmail()))) . "?d=mm&amp;s=96"
				];
			} else {
				$topAchiever = [
					'name' => "UNABLE TO LOOKUP USER ($topUser)",'img' => 'https://placehold.it/96x96'
				];
			}
		}

		$wikiManagerGlobalIds = [];

		$wikiManagers = Sites::getAllManagers();
		foreach ($wikiManagers as $wikiManager) {
			$wikiManagerGlobalIds[] = Cheevos::getUserIdForService($wikiManager['user']);
		}

		$topNonCurseAchieverCall = Cheevos::getProgressTop(null, $wikiManagerGlobalIds);
		$topNonCurseUser = isset($topNonCurseAchieverCall['counts'][0]['user_id']) ? $topNonCurseAchieverCall['counts'][0]['user_id'] : false;

		if (!$topNonCurseUser) {
			$topNonCurseAchiever = ['name' => "API RETURNED NO USER", 'img' => 'https://placehold.it/96x96'];
		} else {

			$userNonCurse = Cheevos::getUserForServiceUserId($topNonCurseUser);
			if ($user) {
				$topNonCurseAchiever = ['name' => $userNonCurse->getName(), 'img' => "//www.gravatar.com/avatar/" . md5(strtolower(trim($userNonCurse->getEmail()))) . "?d=mm&amp;s=96"];
			} else {
				$topNonCurseAchiever = ['name' => "UNABLE TO LOOKUP USER ($topNonCurseUser)", 'img' => 'https://placehold.it/96x96'];
			}
		}

		$data = [
			'wikisWithCustomAchievements' => count($customAchievements),
			'totalWikis' => count($wikis),
			'totalAchievements' => count($achievements),
			'averageAchievementsPerWiki' => "N/I",
			'totalEarnedAchievements' => number_format($totalEarnedAchievements),
			'totalEarnedMegaAchievements' => number_format($totalEarnedAchievementsMega),
			'engagedUsers' => $totalEarnedAchievementsEngaged,
			'topAchiever' => $topAchiever,
			'topAchieverNonCurse' => $topNonCurseAchiever,
		];

		return ['success' => true, 'data' => $data];
	}

	/**
	 * Get stats data for specific wiki
	 *
	 * @return array
	 */
	public function getWikiStats() {
		$this->params = $this->extractRequestParams();
		$siteKey = $this->params['wiki'];

		$achievements = Cheevos::getAchievements($siteKey);

		$progressCount = Cheevos::getProgressCount($siteKey);
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos::getProgressCount($siteKey, 96);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";

		$topAchieverCall = Cheevos::getProgressTop($siteKey);
		$topUser = isset($topAchieverCall['counts'][0]['user_id']) ? $topAchieverCall['counts'][0]['user_id'] : false;

		if (!$topUser) {
			$topAchiever = ['name' => "API RETURNED NO USER", 'img' => 'https://placehold.it/96x96'];
		} else {
			$user = Cheevos::getUserForServiceUserId($topUser);
			if ($user) {
				$topAchiever = ['name' => $user->getName(), 'img' => "//www.gravatar.com/avatar/" . md5(strtolower(trim($user->getEmail()))) . "?d=mm&amp;s=96"];
			} else {
				$topAchiever = ['name' => "UNABLE TO LOOKUP USER ($topUser)", 'img' => 'https://placehold.it/96x96'];
			}
		}

		$data = [
			'totalAchievements' => count($achievements),
			'totalEarnedAchievements' => number_format($totalEarnedAchievements),
			'totalEarnedMegaAchievements' => number_format($totalEarnedAchievementsMega),
			'topAchiever' => $topAchiever,
		];

		return ['success' => true, 'data' => $data];
	}

	/**
	 * Get stats data for specific wiki table
	 *
	 * @return array
	 */
	public function getWikiStatsTable() {
		$this->params = $this->extractRequestParams();
		$siteKey = $this->params['wiki'];
		$data = [];

		$db = wfGetDB(DB_MASTER);
		$userCount = $db->selectRow(
			'user',
			['COUNT(*) as `count`'],
			'',
			__METHOD__
		);
		$userCount = $userCount->count;

		$achievements = Cheevos::getAchievements($siteKey);
		foreach ($achievements as $a) {

			$earned = Cheevos::getProgressCount($siteKey, $a->getId());
			$totalEarned = isset($earned['total']) ? $earned['total'] : 0;
			$userPercent = ($totalEarned > 0) ? (($totalEarned / $userCount) * 100) : 0;

			$data[] = [
				"id" => $a->getId(),
				"name" => $a->getName(),
				"description" => $a->getDescription(),
				"category" => $a->getCategory()->getName(),
				"earned" => $totalEarned,
				"userpercent" => $userPercent
			];
		}

		return ['success' => true, 'data' => $data];
	}

	/**
	 * Get users who earned an achievemnet on a specific wiki
	 *
	 * @return array
	 */
	public function getAchievementUsers() {
		$this->params = $this->extractRequestParams();
		$siteKey = $this->params['wiki'];
		$achievementId = $this->params['achievementId'];
		$earned = Cheevos::getProgressCount($siteKey, $achievementId);
		$currentProgress = Cheevos::getAchievementProgress([
			'achievement_id' => $achievementId,
			'site_key' => $siteKey,
			'earned' => true,
			'user_id' => 0
		]);

		$data = [];
		foreach ($currentProgress as $cp) {
			$user = Cheevos::getUserForServiceUserId($cp->getUser_Id());
			if ($user) {
				$userName = $user->getName();
			} else {
				$userName = null;
			}
			$cp['user_name'] = $userName;
			$data[] = $cp;
		}
		return ['success' => true, 'data' => $data];
	}

	/**
	 * Get data for Mega Achievements Table
	 *
	 * @return array
	 */
	public function getMegasTable() {
		global $wgCheevosMasterAchievementId;

		$achievementStore = [];
		$data = [];

		$progress = Cheevos::getAchievementProgress([
			'user_id' => 0,
			'achievement_id' => $wgCheevosMasterAchievementId,
			'earned' => 1,
			'limit' => 0
		]);

		foreach ($progress as $p) {
			$achievementId = $p->getAchievement_Id();
			if (!isset($achievementStore[$achievementId])) {
				$achievementStore[$achievementId] = Cheevos::getAchievement($achievementId);
			}
			$achievement = $achievementStore[$achievementId];

			$user = Cheevos::getUserForServiceUserId($p->getUser_Id());
			$userName = ($user) ? $user->getName() : "User #" . $p->getUser_Id();

			$data[] = [
				'user' => $userName,
				'mega' => $achievement->getName(),
				'awarded' => date("m/d/Y h:i A", $p->getAwarded_At())
			];
		}

		if (empty($data)) {
			$data[] = [
				'user' => "N/A",
				'mega' => "N/A",
				'awarded' => "N/A"
			];
		}

		return ['success' => true, 'data' => $data];
	}

	/**
	 * Requirements for API call parameters.
	 *
	 * @return array	Merged array of parameter requirements.
	 */
	public function getAllowedParams() {
		return [
			'do' => [
				ApiBase::PARAM_TYPE		=> 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'wiki' => [
				ApiBase::PARAM_TYPE		=> 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'achievementId' => [
				ApiBase::PARAM_TYPE		=> 'string',
				ApiBase::PARAM_REQUIRED => false
			]
		];
	}

	/**
	 * Descriptions for API call parameters.
	 *
	 * @return array	Merged array of parameter descriptions.
	 */
	public function getParamDescription() {
		return [
			'do'		=> 'Action to take.',
			'wiki'		=> 'The wiki to filter by'
		];
	}

	/**
	 * Get version of this API Extension.
	 *
	 * @return string	API Extension Version
	 */
	public function getVersion() {
		return '1.0';
	}

	/**
	 * Return a ApiFormatJson format object.
	 *
	 * @return object	ApiFormatJson
	 */
	public function getCustomPrinter() {
		return $this->getMain()->createPrinterByName('json');
	}
}
