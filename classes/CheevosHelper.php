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
use MediaWiki\MediaWikiServices;
use RequestContext;
use WikiConfig\WikiVariablesDataService;
use WikiDomain\WikiConfigData;
use WikiDomain\WikiConfigDataService;

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
				$users[$stat['user_id']] = Cheevos::getUserForServiceUserId($stat['user_id']);
			}
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
	 * @param string	     Site Key
	 * @param WikiConfigData [Optional] Provide already retrieved wiki object.
	 *
	 * @return string	Site Name with Language
	 */
	public static function getSiteName(string $siteKey, ?WikiConfigData $wiki = null): string {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$sitename = $config->get('Sitename');
		$languageCode = $config->get('LanguageCode');

		$dsSiteKey = self::getSiteKey();

		if (!empty($siteKey) && $siteKey !== $dsSiteKey) {
			if (empty($wiki)) {
				$wiki = self::getWikiInformation($siteKey);
			}
			if (!empty($wiki)) {
				$sitename = $wiki->getTitle();
				$languageCode = $wiki->getLangCode();
			}
		}

		$sitename = sprintf('%s (%s)', $sitename, mb_strtoupper($languageCode, 'UTF-8'));

		return $sitename;
	}

	/**
	 * Get wiki information based on the provided site identifier.($dsSiteKey or $cityId)
	 *
	 * @param string $siteKey
	 *
	 * @return WikiConfigData|null
	 */
	public static function getWikiInformation(string $siteKey): ?WikiConfigData {
		$services = MediaWikiServices::getInstance();
		$wikiConfigDataService = $services->getService(WikiConfigDataService::class);
		if (strlen($siteKey) === 32) {
			// Handle legecy $dsSiteKey MD5 hash.
			$wikiVariablesService = $services->getService(WikiVariablesDataService::class);
			$variableId = $wikiVariablesService->getVarIdByName('dsSiteKey');
			if (!$variableId) {
				return null;
			}
			// JSON encoding the $siteKey has a potential performance benefit over LIKE.
			$listOfWikisWithVar = $wikiVariablesService->getListOfWikisWithVar(
				$variableId,
				'=',
				json_encode($siteKey),
				'$',
				0,
				1
			);
			if ($listOfWikisWithVar['total_count'] === 1) {
				$cityId = key($listOfWikisWithVar['result']);
				$wiki = $wikiConfigDataService->getWikiDataById((int)$cityId);
			}
		} else {
			$wiki = $wikiConfigDataService->getWikiDataById((int)$siteKey);
		}
		if (empty($wiki)) {
			$wiki = null;
		}

		return $wiki;
	}

	/**
	 * Get site key.
	 *
	 * @return mixed	Site key string or null if empty.
	 */
	public static function getSiteKey(): ?string {
		global $dsSiteKey;
		if (!$dsSiteKey || empty($dsSiteKey)) {
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$cityId = $config->get('CityId');

			if (empty($cityId)) {
				return null;
			}
			$dsSiteKey = $cityId;
		}

		return (string)$dsSiteKey;
	}

	/**
	 * Return if we are operating in the context of the central wiki.
	 *
	 * @return boolean
	 */
	public static function isCentralWiki(): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		return (bool)$config->get('CheevosIsCentral', false);
	}
}
