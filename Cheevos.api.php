<?php
/**
 * Cheevos
 * Cheevos API
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class CheevosAPI extends ApiBase {
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

		parent::$messageMap['invalidcurseid'] = [
			'code'	=> 'invalidcurseid',
			'info'	=> 'Could not find an user by Curse ID "$1"'
		];

		$this->params = $this->extractRequestParams();

		switch ($this->params['do']) {
			case 'acknowledgeAwards':
				$response = $this->acknowledgeAwards();
				break;
			case 'getAchievements':
				$response = $this->getAchievements();
				break;
			case 'getAchievementProgress':
				$response = $this->getAchievementProgress(intval($this->params['curse_id']));
				break;
			case 'getAchievementEarned':
				$response = $this->getAchievementProgress(intval($this->params['curse_id']), true, $this->params['limit']);
				break;
			default:
				$this->dieUsageMsg(['invaliddo', $this->params['do']]);
				break;
		}

		foreach ($response as $key => $value) {
			$this->getResult()->addValue(null, $key, $value);
		}
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
			'curse_id' => [
				ApiBase::PARAM_TYPE		=> 'integer',
				ApiBase::PARAM_REQUIRED => false
			],
			'limit' => [
				ApiBase::PARAM_TYPE		=> 'integer',
				ApiBase::PARAM_REQUIRED => false
			],
			'hashes' => [
				ApiBase::PARAM_TYPE		=> 'string', //Actually a JSON string of a single dimensional array.
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
			'curse_id'	=> 'Curse ID of the user in the database for data retrieval.',
			'limit'		=> 'Limit the number of achievements return.',
			'hashes'	=> 'Hashes to acknowledge awards for.'
		];
	}

	/**
	 * Get information on an user by Curse ID.
	 *
	 * @access	public
	 * @return	array
	 */
	public function acknowledgeAwards() {
		global $wgServer;

		$success = false;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->wgUser, CentralIdLookup::AUDIENCE_RAW);
		if ($this->wgUser->getId() < 1 || User::isIP($this->wgUser->getName()) || !$globalId) {
			$this->dieUsageMsg(['notloggedin', $this->params['do']]);
		}

		$redisKey = 'achievement:display:'.$globalId;
		$hashes = $this->wgRequest->getVal('hashes');
		$hashes = @json_decode($hashes, true);

		if (count($hashes)) {
			array_unshift($hashes, $redisKey);

			$success = (bool) call_user_func_array([$this->redis, 'hDel'], $hashes);
		}

		return ['success' => $success];
	}

	/**
	 * Return basic list of achievements.
	 *
	 * @access	public
	 * @return	array
	 */
	public function getAchievements() {
		$achievements = CheevosHooks::getAchievement();

		if ($achievements === false) {
			return ['success' => false];
		} else {
			return ['success' => true, 'data' => $achievements];
		}
	}

	/**
	 * Return achievements progress for the Curse ID.
	 * @TODO: This function is not currently used is left over from the v2.0 rework.  However, it may still be useful for the future.
	 *
	 * @access	public
	 * @param	integer	Curse ID
	 * @param	boolean	[Optional] Earned Only Achievements
	 * @param	integer	[Optional] Limit total amount of results for earned achievements to the X most recent.
	 * @return	array
	 */
	public function getAchievementProgress($curseId, $earnedOnly = false, $limit = null) {
		if ($curseId < 1) {
			$this->dieUsageMsg(['invalidcurseid', $curseId]);
			return ['success' => false];
		}

		//@TODO: Have this handle loading necessary achievements.
		$progress = \Cheevos\Progress::newFromGlobalId($curseId);

		if ($earnedOnly === true && $limit > 0) {
			$progress = array_slice($progress, 0, intval($limit));
		}

		if (empty($progress)) {
			return ['success' => false];
		} else {
			return ['success' => true, 'data' => $progress];
		}
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
