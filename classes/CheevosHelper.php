<?php
/**
 * Cheevos
 * Cheevos Helper Functions
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
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
			if (isset($stat['site_key']) && !empty($stat['site_key'])) {
				$nice[$stat['site_key']][$stat['user_id']][$stat['stat']] = $_data;
			} else {
				$nice[$stat['user_id']][$stat['stat']] = $_data;
			}
		}
		return $nice;
	}

	/**
	 * Get a site name for a site key.
	 *
	 * @access	public
	 * @param	string	Site Key
	 * @return	string	Site Name with Language
	 */
	static public function getSiteName($siteKey) {
		global $dsSiteKey, $wgSitename, $wgLanguageCode;

		$sitename = '';
		if (!empty($siteKey) && $siteKey !== $dsSiteKey) {
			try {
				$redis = \RedisCache::getClient('cache');
				$info = $redis->hGetAll('dynamicsettings:siteInfo:'.$siteKey);
				if (!empty($info)) {
					foreach ($info as $field => $value) {
						$info[$field] = unserialize($value);
					}
				}
				$sitename = $info['wiki_name']." (".strtoupper($info['wiki_language']).")";
			} catch (\RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		if (empty($sitename)) {
			$sitename = $wgSitename." (".strtoupper($wgLanguageCode).")";;
		}
		return $sitename;
	}
}
