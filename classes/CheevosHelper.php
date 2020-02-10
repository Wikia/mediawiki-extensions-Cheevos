<?php
/**
 * Cheevos
 * Cheevos Helper Functions
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

namespace Cheevos;

use Exception;
use RedisCache;
use RequestContext;

class CheevosHelper {
	/**
	 * Return the language code the current user.
	 *
	 * @return string	Language Code
	 */
	public static function getUserLanguage() {
		try {
			$user = RequestContext::getMain()->getUser();
			$code = $user->getOption('language');
		} catch (Exception $e) {
			$code = "en"; // "faulure? English is best anyway."  --Cameron Chunn, 2017-03-02 15:37:33 -0600
		}
		return $code;
	}

	/**
	 * Return the language for the wiki.
	 *
	 * @return string	Language Code
	 */
	public static function getWikiLanuage() {
		global $wgLanguageCode;
		return $wgLanguageCode;
	}

	/**
	 * Turns an array of CheevosStatProgress objects into an array that is easier to consume.
	 *
	 * @param array	Flat array.
	 *
	 * @return array	Nice array.
	 */
	public static function makeNiceStatProgressArray($stats) {
		$nice = [];
		$users = [];

		foreach ($stats as $stat) {
			$_data = [
				'stat_id' => $stat['stat_id'],
				'count' => $stat['count'],
				'last_incremented' => $stat['last_incremented'],
			];
			if (!isset($users[$stat['user_id']])) {

			}
			$users[$stat['user_id']] = Cheevos::getUserForServiceUserId($stat['user_id']);
			if (isset($stat['site_key']) && !empty($stat['site_key'])) {
				$nice[$stat['site_key']][$users[$stat['user_id']]->getId()][$stat['stat']] = $_data;
			} else {
				$nice[$users[$stat['user_id']]->getId()][$stat['stat']] = $_data;
			}
		}
		return $nice;
	}

	/**
	 * Get a site name for a site key.
	 *
	 * @param string	Site Key
	 *
	 * @return string	Site Name with Language
	 */
	public static function getSiteName($siteKey) {
		global $dsSiteKey, $wgSitename, $wgLanguageCode;

		$sitename = '';
		if (!empty($siteKey) && $siteKey !== $dsSiteKey) {
			try {
				$redis = RedisCache::getClient('cache');
				if ($redis !== false) {
					$info = $redis->hGetAll('dynamicsettings:siteInfo:' . $siteKey);
					if (!empty($info)) {
						foreach ($info as $field => $value) {
							$info[$field] = unserialize($value);
						}
					}
					if (isset($info['wiki_name'])) {
						$sitename = $info['wiki_name'] . " (" . strtoupper($info['wiki_language']) . ")";
					}
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__ . ": Caught RedisException - " . $e->getMessage());
			}
		}

		if (empty($sitename)) {
			$sitename = $wgSitename . " (" . strtoupper($wgLanguageCode) . ")";
			;
		}
		return $sitename;
	}
}
