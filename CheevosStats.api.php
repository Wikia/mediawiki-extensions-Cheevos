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
			default:
				$this->dieUsageMsg(['invaliddo', $this->params['do']]);
				break;
		}

		foreach ($response as $key => $value) {
			$this->getResult()->addValue(null, $key, $value);
		}
	}


	public function getGlobalStats() {
		$achievements = Cheevos\Cheevos::getAchievements();
		$categories = Cheevos\Cheevos::getCategories();
		$wikis = \DynamicSettings\Wiki::loadAll();

		$progressCount = Cheevos\Cheevos::getProgressCount();
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos\Cheevos::getProgressCount(null,96);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";


		$customAchievements = [];

		foreach($achievements as $a) {
			if ($a->getParent_Id() !== 0) {
				$customAchievements[$a->getSite_Key()][] = $a;
			}
		}

		$lookup = CentralIdLookup::factory();

		$topAchieverCall = Cheevos\Cheevos::getProgressTop();
		$topUser = isset($topAchieverCall['user_id']) ? $topAchieverCall['user_id'] : false;

		if (!$topUser) {
			$topAchiever = ['name'=>"API RETURNED NO USER",'img'=>'https://placehold.it/96x96'];
		} else {
			$user = $lookup->localUserFromCentralId($topUser);
			if ($user) {
				$topAchiever = ['name'=>$user->getName(),'img'=>"//www.gravatar.com/avatar/".md5(strtolower(trim($user->getEmail())))."?d=mm&amp;s=96"];
			} else {
				$topAchiever = ['name'=>"UNABLE TO LOOKUP USER ($topUser)",'img'=>'https://placehold.it/96x96'];
			}
		}


		$curse_global_ids = [];

		$curseAccounts = \DynamicSettings\DS::getWikiManagers();
		foreach ($curseAccounts as $key => $localUser) {
			$user_global_ids[] = $lookup->centralIdFromLocalUser($localUser);
		}

		$topNonCurseAchieverCall = Cheevos\Cheevos::getProgressTop(null,$curse_global_ids);
		$topNonCurseUser = isset($topNonCurseAchieverCall['user_id']) ? $topNonCurseAchieverCall['user_id'] : false;

		if (!$topNonCurseUser) {
			$topNonCurseAchiever = ['name'=>"API RETURNED NO USER",'img'=>'https://placehold.it/96x96'];
		} else {

			$userNonCurse = $lookup->localUserFromCentralId($topNonCurseUser);
			if ($user) {
				$topNonCurseAchiever = ['name'=>$userNonCurse->getName(),'img'=>"//www.gravatar.com/avatar/".md5(strtolower(trim($userNonCurse->getEmail())))."?d=mm&amp;s=96"];
			} else {
				$topNonCurseAchiever = ['name'=>"UNABLE TO LOOKUP USER ($topNonCurseUser)",'img'=>'https://placehold.it/96x96'];
			}
		}

		$data = [
			'wikisWithCustomAchievements' => count($customAchievements),
			'totalWikis' => count($wikis),
			'totalAchievements' => count($achievements),
			'averageAchievementsPerWiki' => 0,
			'totalEarnedAchievements' => number_format($totalEarnedAchievements),
			'totalEarnedMegaAchievements' => number_format($totalEarnedAchievementsMega),
			'engagedUsers' => 0,
			'topAchiever' => $topAchiever,
			'topAchieverNonCurse' => $topNonCurseAchiever,
		];

		return ['success' => true, 'data' => $data];
	}

	public function getWikiStats() {
		$this->params = $this->extractRequestParams();
		$site_key = $this->params['wiki'];

		$achievements = Cheevos\Cheevos::getAchievements($site_key);

		$progressCount = Cheevos\Cheevos::getProgressCount($site_key);
		$totalEarnedAchievements = isset($progressCount['total']) ? $progressCount['total'] : "N/A";

		$progressCountMega = Cheevos\Cheevos::getProgressCount($site_key,96);
		$totalEarnedAchievementsMega = isset($progressCountMega['total']) ? $progressCountMega['total'] : "N/A";

		$topAchieverCall = Cheevos\Cheevos::getProgressTop($site_key);
		$topUser = isset($topAchieverCall['user_id']) ? $topAchieverCall['user_id'] : false;

		if (!$topUser) {
			$topAchiever = ['name'=>"API RETURNED NO USER",'img'=>'https://placehold.it/96x96'];
		} else {
			$lookup = CentralIdLookup::factory();
			$user = $lookup->localUserFromCentralId($topUser);
			if ($user) {
				$topAchiever = ['name'=>$user->getName(),'img'=>"//www.gravatar.com/avatar/".md5(strtolower(trim($user->getEmail())))."?d=mm&amp;s=96"];
			} else {
				$topAchiever = ['name'=>"UNABLE TO LOOKUP USER ($topUser)",'img'=>'https://placehold.it/96x96'];
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
		$site_key = $this->params['wiki'];
		$data = [];

		$achievements = Cheevos\Cheevos::getAchievements($site_key);
		foreach($achievements as $a) {
			$data[] = [
				"name" => $a->getName(),
				"description" => $a->getDescription(),
				"category" => $a->getCategory()->getName(),
				"earned" => "9000",
				"userpercent" => "1.23"
			];
		}

		return ['success' => true, 'data' => $data, 'extra_data' => $extra];
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
