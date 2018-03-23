<?php
/**
 * Cheevos
 * Achievements API
 *
 * @author		Alexia E. Smith, Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Achievements
 * @link		http://www.curse.com/
 *
 **/

class CheevosStatsAPI extends ApiBase {
	/**
	 * API Initialized
	 *
	 * @var		boolean
	 */
	private $initialized = false;

	/**
	 * Initiates some needed classes.
	 *
	 * @access	public
	 * @return	void
	 */
	private function init() {
		if (!$this->initialized) {
			global $wgUser, $wgRequest;
			$this->wgUser		= $wgUser;
			$this->wgRequest	= $wgRequest;
			$this->language		= $this->getLanguage();
			$this->redis = RedisCache::getClient('cache');
			$this->initialized = true;
		}
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function execute() {
		$this->init();
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
				$this->dieUsageMsg(['invaliddo', $this->params['do']]);
				break;
		}

		foreach ($response as $key => $value) {
			$this->getResult()->addValue(null, $key, $value);
		}
	}

	public function getGlobalStats() {
		global $wgCheevosAchievementEngagementId, $wgCheevosMasterAchievementId;

		$achievements = Cheevos\Cheevos::getAchievements();
		$categories = Cheevos\Cheevos::getCategories();
		$wikis = \DynamicSettings\Wiki::loadAll();

		$progressCount = Cheevos\Cheevos::getProgressCount();
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos\Cheevos::getProgressCount(null, $wgCheevosMasterAchievementId);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";

		$progressCountEngaged = Cheevos\Cheevos::getProgressCount(null, $wgCheevosAchievementEngagementId);
		$totalEarnedAchievementsEngaged = isset($progressCountEngaged['total']) ? $progressCountEngaged['total'] : "N/A";

		$customAchievements = [];

		foreach($achievements as $a) {
			if ($a->getParent_Id() !== 0) {
				$customAchievements[$a->getSite_Key()][] = $a;
			}
		}

		$lookup = CentralIdLookup::factory();

		$topAchieverCall = Cheevos\Cheevos::getProgressTop();
		$topUser = isset($topAchieverCall['counts'][0]['user_id']) ? $topAchieverCall['counts'][0]['user_id'] : false;

		if (!$topUser) {
			$topAchiever = [
				'name' => "API RETURNED NO USER",
				'img' => 'https://placehold.it/96x96'
			];
		} else {
			$user = $lookup->localUserFromCentralId($topUser);
			if ($user) {
				$topAchiever = [
					'name' => $user->getName(),
					'img' => "//www.gravatar.com/avatar/".md5(strtolower(trim($user->getEmail())))."?d=mm&amp;s=96"
				];
			} else {
				$topAchiever = [
					'name' => "UNABLE TO LOOKUP USER ($topUser)",'img' => 'https://placehold.it/96x96'
				];
			}
		}




		$curse_global_ids = [];

		$curseAccounts = \DynamicSettings\DS::getWikiManagers();
		foreach ($curseAccounts as $key => $localUser) {
			$curse_global_ids[] = $lookup->centralIdFromLocalUser($localUser);
		}

		$topNonCurseAchieverCall = Cheevos\Cheevos::getProgressTop(null, $curse_global_ids);
		$topNonCurseUser = isset($topNonCurseAchieverCall['counts'][0]['user_id']) ? $topNonCurseAchieverCall['counts'][0]['user_id'] : false;

		if (!$topNonCurseUser) {
			$topNonCurseAchiever = ['name' => "API RETURNED NO USER", 'img' => 'https://placehold.it/96x96'];
		} else {

			$userNonCurse = $lookup->localUserFromCentralId($topNonCurseUser);
			if ($user) {
				$topNonCurseAchiever = ['name' => $userNonCurse->getName(), 'img' => "//www.gravatar.com/avatar/".md5(strtolower(trim($userNonCurse->getEmail())))."?d=mm&amp;s=96"];
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

	public function getWikiStats() {
		$this->params = $this->extractRequestParams();
		$siteKey = $this->params['wiki'];

		$achievements = Cheevos\Cheevos::getAchievements($siteKey);

		$progressCount = Cheevos\Cheevos::getProgressCount($siteKey);
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos\Cheevos::getProgressCount($siteKey, 96);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";

		$topAchieverCall = Cheevos\Cheevos::getProgressTop($siteKey);
		$topUser = isset($topAchieverCall['counts'][0]['user_id']) ? $topAchieverCall['counts'][0]['user_id'] : false;

		if (!$topUser) {
			$topAchiever = ['name' => "API RETURNED NO USER", 'img' => 'https://placehold.it/96x96'];
		} else {
			$lookup = CentralIdLookup::factory();
			$user = $lookup->localUserFromCentralId($topUser);
			if ($user) {
				$topAchiever = ['name' => $user->getName(), 'img' => "//www.gravatar.com/avatar/".md5(strtolower(trim($user->getEmail())))."?d=mm&amp;s=96"];
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

		$achievements = Cheevos\Cheevos::getAchievements($siteKey);
		foreach($achievements as $a) {

			$earned = Cheevos\Cheevos::getProgressCount($siteKey, $a->getId());
			$totalEarned = isset($earned['total']) ? $earned['total'] : 0;
			$userPercent = ($totalEarned > 0) ? ( ($totalEarned / $userCount) * 100 ) : 0;

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

	public function getAchievementUsers() {
		$this->params = $this->extractRequestParams();
		$siteKey = $this->params['wiki'];
		$achievementId = $this->params['achievementId'];
		$earned = Cheevos\Cheevos::getProgressCount($siteKey, $achievementId);
		$currentProgress = \Cheevos\Cheevos::getAchievementProgress([
			'achievement_id' => $achievementId,
			'site_key' => $siteKey,
			'earned' => true,
			'user_id' => 0
		]);
		$lookup = CentralIdLookup::factory();
		$data = [];
		foreach ($currentProgress as $cp) {
			$user = $lookup->localUserFromCentralId($cp->getUser_Id());
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

	public function getMegasTable() {
		global $wgCheevosMasterAchievementId;

		$lookup = CentralIdLookup::factory();
		$achievementStore = [];
		$data = [];

		$progress = Cheevos\Cheevos::getAchievementProgress([
			'user_id' => 0,
			'achievement_id' => $wgCheevosMasterAchievementId,
			'earned' => 1,
			'limit' => 0
		]);

		foreach ($progress as $p) {
			$achievementId = $p->getAchievement_Id();
			if (!isset($achievementStore[$achievementId])) {
				$achievementStore[$achievementId] = Cheevos\Cheevos::getAchievement($achievementId);
			}
			$achievement = $achievementStore[$achievementId];

			$user = $lookup->localUserFromCentralId($p->getUser_Id());
			$userName = ($user) ? $user->getName() : "User #".$p->getUser_Id();

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
	 * @access	public
	 * @return	array	Merged array of parameter requirements.
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
	 * @access	public
	 * @return	array	Merged array of parameter descriptions.
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
	 * @access	public
	 * @return	string	API Extension Version
	 */
	public function getVersion() {
		return '1.0';
	}

	/**
	 * Return a ApiFormatJson format object.
	 *
	 * @access	public
	 * @return	object	ApiFormatJson
	 */
	public function getCustomPrinter() {
		return $this->getMain()->createPrinterByName('json');
	}
}
