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
			default:
				$this->dieUsageMsg(['invaliddo', $this->params['do']]);
				break;
		}

		foreach ($response as $key => $value) {
			$this->getResult()->addValue(null, $key, $value);
		}
	}


	public function getGlobalStats() {


		return ['success' => true];
	}

	public function getWikilStats() {


		return ['success' => true];
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
