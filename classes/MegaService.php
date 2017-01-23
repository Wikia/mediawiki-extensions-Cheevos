<?php
/**
 * Cheevos
 * Cheevos Mega Service
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class MegaService {
	/**
	 * List of Auth Sites from the service.
	 *
	 * @var		array
	 */
	private $authSites = [];

	/**
	 * Initialized Properly
	 *
	 * @var		boolean
	 */
	private $initialized = false;

	/**
	 * Existing MegaService Instance
	 *
	 * @var		object
	 */
	static private $instance = false;

	/**
	 * Callable functions that exist on the service.
	 *
	 * @access	protected
	 * @var		array
	 */
	protected $magicFunctions = [
		//Get Achievements
		'getAllAchievements'			=> 'getAllAchievements',
		'getAchievementByID'			=> 'getAchievementByID',
		'getAchievementsBySiteID'		=> 'getAchievementsBySiteID',
		//Get Earned Achievements
		'getSiteAchievementsByUserID'	=> 'getSiteAchievementsByUserID',
		'getAllAchievementsByUserID'	=> 'getAllAchievementsByUserID',
		//Achievement Management
		'addAchievement'				=> 'addAchievement',
		'deleteAchievement'				=> 'deleteAchievement',
		//Achievement Earning
		'awardAchievement'				=> 'awardAchievement',
		'unawardAchievement'			=> 'unawardAchievement',
		//Site Management
		'getAllSites'					=> 'getAllSites',
		'addSite'						=> 'addSite',
		//Achievement Mapping
		'getSitesByAchievementId'		=> 'getSitesByAchievementId',
		'addSiteAchievementMap'			=> 'addSiteAchievementMap',
		'removeSiteAchievementMap'		=> 'removeSiteAchievementMap'
	];

	/**
	 * Array key equivalents.
	 *
	 * @access	protected
	 * @var		array
	 */
	protected $arrayKeys = [
		'id'			=> 'global_id',
		'name'			=> 'name',
		'description'	=> 'description',
		'imagePath'		=> 'image_url',
		'rules'			=> 'rules',
		'requires'		=> 'requires',
		'subSiteKey'	=> 'site_key'
	];

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	Configuration
	 * @return	void
	 */
	public function __construct($config) {
		$this->config = $config;
		if (empty($this->config['service_url']) || empty($this->config['site_id']) || empty($this->config['site_key'])) {
			throw new \MWException('Achievement System Service Error: Missing `service_url`, `site_id`, or `site_key`.');
		}
		$this->initialized = true;

		$authSites = $this->getAllSites();

		if (is_array($authSites)) {
			foreach ($authSites as $authSite) {
				if ($authSite['externalID'] != $this->config['site_id']) {
					//We only care our sites that fall under the CurseAuth site ID.
					continue;
				}
				$uniqueKey = $this->_makeUniqueSiteKey($authSite['externalID'], $authSite['subSiteKey']);
				$this->authSites[$uniqueKey] = [
					'remote_id'	=> $authSite['id'], //The primary ID in the achievement service database.
					'site_id'	=> $authSite['externalID'], //The seven digit site ID.
					'site_key'	=> $authSite['subSiteKey'], //32 digit MD5, this is set by the sites typically.
					'host_name'	=> $authSite['hostName'] //Host name of the site.
				];
			}

			if (!array_key_exists($this->_getUniqueSiteKey(), $this->authSites)) {
				//Add our key.
				$this->addSite(['externalID' => $this->config['site_id'], 'hostName' => $this->config['host_name'], 'subSiteKey' => $this->config['site_key']]);
			}
		} else {
			$this->initialized = false;
		}
	}

	/**
	 * Return a singleton instance of this object.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function getInstance() {
		if (!self::$instance instanceOf self) {
			global $achServiceConfig;

			self::$instance = new self($achServiceConfig);
		}
		return self::$instance;
	}

	/**
	 * Magic function for handling service calls.
	 *
	 * @access	public
	 * @param	string	Called magic function name.
	 * @param	array	[Optional] Array of arguments.
	 * @return	object	Parsed JSON object
	 */
	public function __call($function, $arguments = []) {
		if (!$this->initialized) {
			return false;
		}

		$data = (isset($arguments[0]) ? $arguments[0] : null);

		$isPost = false;
		if (isset($arguments[1]) && $arguments[1] == true) {
			$isPost = true;
		}

		if (array_key_exists($function, $this->magicFunctions)) {
			$location = $this->config['service_url'].$this->magicFunctions[$function];

			$get = [];
			$post = [];
			if (is_array($data) && count($data)) {
				if (!array_key_exists('siteID', $data)) {
					$data['siteID'] = $this->config['site_id'];
				} elseif ($data['siteID'] === false) {
					unset($data['siteID']);
				}
				if (!array_key_exists('subSiteKey', $data)) {
					$data['subSiteKey'] = $this->config['site_key'];
				} elseif ($data['subSiteKey'] === false) {
					unset($data['subSiteKey']);
				}
				foreach ($data as $key => $data) {
					if ($isPost) {
						$post[$key] = $data;
					} else {
						$get[] = $key.'='.urlencode(trim($data));
					}
				}
			}

			if (count($get)) {
				$location .= '?'.implode('&', $get);
			}
			if ($isPost) {
				$result = $this->fetch($location, json_encode($post), ['headers' => ['Content-Type: application/json']], false);
			} else {
				$result = $this->fetch($location);
			}
			$parsedJSON = $this->jsonConvert($result);

			return $parsedJSON;
		}
		return false;
	}

	/**
	 * Return the unique site key for this site.(Site ID plus Site Key)
	 *
	 * @access	public
	 * @return	string	Unique Site ID plus Site Key
	 */
	public function _getUniqueSiteKey() {
		return $this->_makeUniqueSiteKey($this->config['site_id'], $this->config['site_key']);
	}

	/**
	 * Creates a unique site key from the site ID and site key.
	 *
	 * @access	public
	 * @param	integer	Seven digit site ID.
	 * @param	string	32 character MD5 hash.
	 * @return	string	Unique Site ID plus Site Key
	 */
	public function _makeUniqueSiteKey($siteId, $siteKey) {
		return $siteId.'-'.$siteKey;
	}

	/**
	 * Return the site remote ID(database ID in the service).
	 * This is used for getAchievementsBySiteID().
	 *
	 * @access	public
	 * @return	void
	 */
	public function _getSiteRemoteId($uniqueKey) {
		return $this->authSites[$uniqueKey]['remote_id'];
	}

	/**
	 * Return all achievements for Hydra.
	 *
	 * @access	public
	 * @return	array	Mega Achievements from Service
	 */
	public function _getAchievementsForHydra() {
		$achievements = [];
		foreach ($this->authSites as $uniqueKey => $authSite) {
			if ($authSite['site_id'] != $this->config['site_id']) {
				continue;
			}
			$_achievements = $this->getAchievementsBySiteID(['siteID' => $authSite['remote_id'], 'subSiteKey' => false]);
			$achievements = array_merge($achievements, $_achievements);
		}
		return $achievements;
	}

	/**
	 * Add Achievement
	 *
	 * @access	public
	 * @param	array	Array of data.
	 * @return	string	Parsed JSON
	 */
	public function addAchievement($achievement) {
		$achievement = $this->translateArrayKeys($achievement, 'toService');
		$uniqueSiteKey = $this->_makeUniqueSiteKey($this->config['site_id'], $achievement['subSiteKey']);

		if (!array_key_exists($uniqueSiteKey, $this->authSites)) {
			//Add our key.
			$authSiteAdded = $this->addSite(['externalID' => $this->config['site_id'], 'hostName' => $achievement['hostName'], 'subSiteKey' => $achievement['subSiteKey']]);
			if (!$authSiteAdded) {
				throw new \MWException(__METHOD__.": Failed to add new auth site {$achievement['hostName']} {$achievement['subSiteKey']} while adding an achievement.");
			}
		}

		unset($achievement['hostName']);
		$location = $this->config['service_url'].'addAchievement';

		$result = $this->fetch($location, json_encode($achievement), ['headers' => ['Content-Type: application/json']], true);
		$return = $this->jsonConvert($result);

		if (!$return['errorCode'] && $return['id'] > 0) {
			$siteMap = $this->addSiteAchievementMap(['siteID' => intval($this->authSites[$uniqueSiteKey]['remote_id']), 'achievementID' => $return['id'], 'subSiteKey' => false], true);
		} else {
			throw new \MWException(__METHOD__.": Failed to add a site achievement map for {$achievement['hostName']} {$achievement['subSiteKey']} to mega achievement ID {$return['id']} while adding an achievement.");
		}

		return $return;
	}

	/**
	 * Update Achievement
	 *
	 * @access	public
	 * @param	array	Array of achievement data.
	 * @param	array	Array of old achievement data.
	 * @return	string	Parsed JSON
	 */
	public function updateAchievement($achievement, $oldAchievement) {
		$achievement = $this->translateArrayKeys($achievement, 'toService');
		$location = $this->config['service_url'].'updateAchievement';
		unset($achievement['hostName']);
		$result = $this->fetch($location, json_encode($achievement), ['headers' => ['Content-Type: application/json']], true);
		$return = $this->jsonConvert($result);

		if ($return['returnCode'] == true) {
			if ($oldAchievement['site_key'] != $achievement['subSiteKey']) {
				//Get the old mapping ID and update it with the new site key.
				$mapping = $this->getSitesByAchievementId(
					[
						'achievementID'	=> $achievement['achievementID']
					]
				);

				if (is_array($mapping) && count($mapping)) {
					foreach ($mapping as $map) {
						$removed = $this->removeSiteAchievementMap(
							[
								'siteID'		=> $map['id'],
								'achievementID'	=> $achievement['achievementID']
							],
							true
						);
					}
				}

				$uniqueSiteKey = $this->_makeUniqueSiteKey($this->config['site_id'], $achievement['subSiteKey']);
				if (!array_key_exists($uniqueSiteKey, $this->authSites)) {
					//Add our key.
					$authSiteAdded = $this->addSite(['externalID' => $this->config['site_id'], 'hostName' => $achievement['hostName'], 'subSiteKey' => $achievement['subSiteKey']]);
					if (!$authSiteAdded) {
						throw new \MWException(__METHOD__.": Failed to add new auth site {$achievement['hostName']} {$achievement['subSiteKey']} while adding an achievement.");
					}
				}
				$siteMap = $this->addSiteAchievementMap(['siteID' => intval($this->authSites[$uniqueSiteKey]['remote_id']), 'achievementID' => $achievement['achievementID'], 'subSiteKey' => false], true);
			}
			$return['id'] = $achievement['achievementID'];
		}

		return $return;
	}

	/**
	 * Add Auth Site
	 *
	 * @access	public
	 * @param	array	Array of data.
	 * @return	string	Parsed JSON
	 */
	public function addSite($authSite) {
		$uniqueKey = $this->_makeUniqueSiteKey($authSite['externalID'], $authSite['subSiteKey']);

		if (!array_key_exists($uniqueKey, $this->authSites)) {
			$location = $this->config['service_url'].'addSite';
			$result = $this->fetch($location, json_encode($authSite), ['headers' => ['Content-Type: application/json']], true);
			$return = $this->jsonConvert($result);
		}

		if (!$return['errorCode'] && $return['id'] > 0) {
			$this->authSites[$uniqueKey] = [
				'remote_id'	=> $return['id'], //The primary ID in the achievement service database.
				'site_id'	=> $return['externalID'], //The seven digit site ID.
				'site_key'	=> $return['subSiteKey'], //32 digit MD5, this is set by the sites typically.
				'host_name'	=> $return['hostName'] //Host name of the site.
			];
			return true;
		}

		return false;
	}

	/**
	 * JSON Parser wrapper and error handling
	 *
	 * @access	public
	 * @param	string	Raw JSON
	 * @return	object	Processed JSON
	 */
	private function jsonConvert($json) {
		$json = @json_decode($json, true);
		return $json;
	}

	/**
	 * Translates array keys back and forth from the Achievement Service equivalents.
	 *
	 * @access	public
	 * @param	array	Array to translate.
	 * @param	string	[Optional] Which direction to translate keys.  Use toLocal to get keys in local format and toService to get keys in service format.
	 * @return	void
	 */
	public function translateArrayKeys($array, $direction = 'toLocal') {
		if (!is_array($array)) {
			return $array;
		}

		if ($direction == 'toService') {
			$arrayKeys = array_flip($this->arrayKeys);
		} else {
			$arrayKeys = $this->arrayKeys;
		}
		foreach ($array as $key => $value) {
			if ($direction == 'toLocal') {
				$key = lcfirst($key);
			}
			if (array_key_exists($key, $arrayKeys)) {
				$returnArray[$arrayKeys[$key]] = $value;
			} else {
				$returnArray[$key] = $value;
			}
		}
		return $returnArray;
	}

	/**
	 * Convert a .NET style date format to a standard UNIX timestamp.
	 *
	 * @access	public
	 * @param	string	.NET style date format
	 * @return	mixed	Converted timestamp or false on error.
	 */
	static public function convertDotNetDate($date) {
		$regex = "#/Date\((\d{13})([-|+])(\d{4})\)/#"; //This will break on Sat, 20 Nov 2286 17:46:40 GMT.
		$timestamp = false;
		if (preg_match($regex, $date, $matches)) {
			$timestamp = $matches[1] / 1000;
			$offset = $matches[3] / 100;
			if ($matches[2] == '-') {
				$timestamp = $timestamp + ($offset * 60 * 60);
			} else {
				$timestamp = $timestamp - ($offset * 60 * 60);
			}
		}
		return intval(round($timestamp));
	}

	/**
	 * CURL wrapper for get and post functionality. Some options are only configured by settings at load time:
	 * array('interface' => eth1, 'useragent' => 'Custom Agent/1.0') interface: Physical interface to use on the hardware level.  useragent: Replace the default Mouse Framework user agent string.
	 *
	 * @access	public
	 * @param	string	URL to CURL
	 * @param	array	[Optional] Post Data
	 * @param	array	[Optional] Options array('headers' => array('cs-api-key: abcd123'))  headers: Array of http header strings
	 * @param	boolean	Turn on various debug functionality such as saving information with the CURLINFO_HEADER_OUT option.
	 * @return	mixed	Raw page text/HTML or false for a 404/503 response.
	 */
	public function fetch($location, $postFields = null, $options = [], $debug = false) {
		$ch = curl_init();
		$timeout = 10;

		$useragent = "ASS/2.0 (Hydra)";
		$cookieFileHash = md5($useragent);

		$dateTime = gmdate("D, d M Y H:i:s", time())." GMT";
		$headers = ['Date: '.$dateTime, 'Accept: application/json'];
		if (isset($options['headers']) && is_array($options['headers']) && count($options['headers'])) {
			$headers = array_merge($headers, $options['headers']);
		}

		$curl_options = [
			CURLOPT_TIMEOUT			=> $timeout,
			CURLOPT_USERAGENT		=> $useragent,
			CURLOPT_URL				=> $location,
			CURLOPT_CONNECTTIMEOUT	=> $timeout,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_MAXREDIRS		=> 4,
			CURLOPT_COOKIEFILE		=> '/tmp/'.$cookieFileHash,
			CURLOPT_COOKIEJAR		=> '/tmp/'.$cookieFileHash,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_HTTPHEADER		=> $headers
		];

		if (!empty($postFields)) {
			$curl_options[CURLOPT_POST]			= true;
			$curl_options[CURLOPT_POSTFIELDS]	= $postFields;
		}

		if (isset($this->settings['interface']) && $this->settings['interface']) {
			$curl_options[CURLOPT_INTERFACE]	= $this->settings['interface'];
		}

		curl_setopt_array($ch, $curl_options);

		if ($debug === true) {
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		}

		$page = curl_exec($ch);

		if ($debug === true) {
			$this->lastRequestInfo = curl_getinfo($ch);
		}

		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($response_code == 503 || $response_code == 404) {
			return false;
		}

		curl_close($ch);

		return $page;
	}
}
