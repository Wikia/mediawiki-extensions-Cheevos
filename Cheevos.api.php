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

class CheevosAPI extends ApiBase {
	/**
	 * Main Executor
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute() {
		$this->params = $this->extractRequestParams();

		switch ($this->params['do']) {
			case 'acknowledgeAwards':
				$response = $this->acknowledgeAwards();
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
	 * @return array	Merged array of parameter requirements.
	 */
	public function getAllowedParams() {
		return [
			'do' => [
				ApiBase::PARAM_TYPE		=> 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'hashes' => [
				ApiBase::PARAM_TYPE		=> 'string', // Actually a JSON string of a single dimensional array.
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
			'hashes'	=> 'Hashes to acknowledge awards for.'
		];
	}

	/**
	 * Acknowledge achievement awards.
	 *
	 * @return array
	 */
	public function acknowledgeAwards() {
		$success = false;

		$redis = RedisCache::getClient('cache');

		if ($redis !== false) {
			$lookup = CentralIdLookup::factory();
			$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);
			if ($this->getUser()->getId() < 1 || User::isIP($this->getUser()->getName()) || !$globalId) {
				$this->dieUsageMsg(['notloggedin', $this->params['do']]);
			}

			$redisKey = 'cheevos:display:' . $globalId;
			$hashes = $this->getRequest()->getVal('hashes');
			$hashes = @json_decode($hashes, true);

			if (count($hashes)) {
				array_unshift($hashes, $redisKey);

				$success = (bool)call_user_func_array([$redis, 'hDel'], $hashes);
			}
		}

		return ['success' => $success];
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
