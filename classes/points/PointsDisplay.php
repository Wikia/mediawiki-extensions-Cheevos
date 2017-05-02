<?php
/**
 * Curse Inc.
 * Wiki Points
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos\Points;

/**
 * Class containing some business and display logic for points blocks
 */
class PointsDisplay {
	/**
	 * Displays scores of wiki editors in an HTML table
	 * {{#GPScore}} displays top 25 scoring users from this wiki
	 * {{#GPScore: 10}} displays top 10 scoring users from this wiki
	 * {{#GPScore: 10 | all}} displays top 10 scoring users from all wikis
	 * {{#GPScore: User:Cathadan}} displays my score from this wiki
	 * {{#GPScore: 50 | destiny,dota2,theorder1886}} displays top 50 scoring users with points from any of those three wikis
	 * {{#GPScore: User:Cathadan | destiny,dota2,theorder1886}} displays the sum of my scores from the given wikis
	 *
	 * TODO: break this function down, make it easier to read
	 *
	 * @param	Parser	mediawiki Parser reference
	 * @param	limit	[Optional] Limit results.
	 * @param	string	[optional, default: ''] comma separated list of wiki namespaces, defaults to the current wiki
	 *					Special namespaces are:
	 *						'all' - Breaks down points per user per wiki.
	 *						'global' - Breaks down points per user across all wikis.
	 * @param	string	[optional, default: 'table'] determines what type of markup is used for the output,
	 * 					'raw' returns an unformatted number for a single user and is ignored for multi-user results
	 *					'badged' returns the same as raw, but with the GP badge branding following it in an <img> tag
	 * 					'table' uses an unstyled HTML table
	 * @return	array	generated HTML string as element 0, followed by parser options
	 */
	public static function pointsBlock(&$parser, $user = '', $limit = 25, $wikis = '', $markup = 'table') {
		global $dsSiteKey;

		$limit = intval($limit);
		if (!$limit || $limit < 0) {
			$limit = 25;
		}

		$globalId = null;
		if (!empty($user)) {
			$user = \User::newFromName($user);
			if (!$user || !$user->getId()) {
				return [
					wfMessage('user_not_found')->escaped(),
					'isHTML' => true,
				];
			}
			$lookup = \CentralIdLookup::factory();
			$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);
			if (!$globalId) {
				return [
					wfMessage('global_user_not_found')->escaped(),
					'isHTML' => true,
				];
			}
		}

		$siteKey = null;
		if ($wikis !== 'all' && $wikis !== 'global') {
			$siteKey = $dsSiteKey;
		}
		$isSitesMode = false;
		if ($wikis === 'all' && $wikis !== 'global') {
			$isSitesMode = true;
		}

		$html = self::pointsBlockHtml($siteKey, $globalId, $limit, 0, $isSitesMode, $isMonthly, $markup);

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	string	[Optional] Limit by or override the site key used.
	 * @param	integer	[Optional] Global ID to filter by.
	 * @param	integer	[Optional] Items Per Page
	 * @param	integer	[Optional] Offset to start at.
	 * @param	boolean	[Optional] Show individual wikis in the results instead of combining with 'global' => true.
	 * @param	boolean	[Optional] Showing monthly totals.
	 * @param	string	[Optional] Determines what type of markup is used for the output.
	 * 					'raw' Returns an unformatted number for a single user and is ignored for multi-user results.
	 *					'badged' Returns the same as raw, but with the GP badge branding following it in an <img> tag.
	 * 					'table' Uses an unstyled HTML table.
	 * @return	string	HTML
	 */
	static public function pointsBlockHtml($siteKey = null, $globalId = null, $itemsPerPage = 25, $start = 0, $isSitesMode = false, $isMonthly = false, $markup = 'table') {
		global $dsSiteKey;

		$lookup = \CentralIdLookup::factory();

		$itemsPerPage = max(1, min(intval($itemsPerPage), 200));
		$start = intval($start);
		$isSitesMode = boolval($isSitesMode);
		$isMonthly = boolval($isMonthly);

		$total = 0;

		$filters = [
			'stat'				=> 'wiki_points',
			'limit'				=> $itemsPerPage,
			'offset'			=> $start,
			'sort_direction'	=> 'desc'
		];

		if (!$isSitesMode && empty($siteKey)) {
			$filters['global'] = true;
		}

		if ($siteKey !== null && !empty($siteKey)) {
			$isSitesMode = false;
			$filters['site_key'] = $siteKey;
		}

		if ($globalId > 0) {
			$filters['user_id'] = intval($globalId);
		}

		$statProgress = [];
		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new \ErrorPageError("Encountered Cheevos API error {$e->getMessage()}\n");
		}

		$userPoints = [];
		$siteKeys = [$dsSiteKey];
		foreach ($statProgress as $progress) {
			$globalId = $progress->getUser_Id();
			$lookupKey = $globalId.'-'.$progress->getSite_Key();
			if (isset($userPoints[$lookupKey])) {
				continue;
			}
			$user = $lookup->localUserFromCentralId($globalId);
			if ($globalId < 1) {
				continue;
			}
			$userPointsRow = new \stdClass();
			if ($user !== null) {
				$userPointsRow->userName = $user->getName();
				$userPointsRow->userToolsLinks = \Linker::userToolLinks($user->getId(), $user->getName());
				$userPointsRow->userLink = \Linker::link(\Title::newFromText("User:".$user->getName()), $user->getName(), [], [], ['https']);
			} else {
				$userPointsRow->userName = "GID: ".$progress->getUser_Id();
				$userPointsRow->userToolsLinks = $userPointsRow->userName;
				$userPointsRow->userLink = '';
			}
			$userPointsRow->score = $progress->getCount();
			$userPointsRow->siteKey = $progress->getSite_Key();
			$userPoints[$lookupKey] = $userPointsRow;
			if ($isSitesMode) {
				$siteKeys[] = $progress->getSite_Key();
			}
		}
		$siteKeys = array_unique($siteKeys);

		$wikis = [];
		if ($isSitesMode && !empty($siteKeys)) {
			global $wgServer;
			$wikis = \DynamicSettings\Wiki::loadFromHash($siteKeys);
			$localDomain = trim($wgServer, '/');
			foreach ($userPoints as $key => $userPointsRow) {
				if ($userPointsRow->siteKey != $dsSiteKey && !empty($userPointsRow->userLink) && isset($wikis[$userPointsRow->siteKey])) {
					$userPoints[$key]->userToolsLinks = str_replace($localDomain, $wikis[$userPointsRow->siteKey]->getDomains()->getDomain(), $userPoints[$key]->userToolsLinks);
					$userPoints[$key]->userLink = str_replace($localDomain, $wikis[$userPointsRow->siteKey]->getDomains()->getDomain(), $userPoints[$key]->userLink);
					$userPoints[$key]->userToolsLinks = str_replace('href="/', 'href="https://'.$wikis[$userPointsRow->siteKey]->getDomains()->getDomain().'/', $userPoints[$key]->userToolsLinks);
					$userPoints[$key]->userLink = str_replace('href="/', 'href="https://'.$wikis[$userPointsRow->siteKey]->getDomains()->getDomain().'/', $userPoints[$key]->userLink);
				}
			}
		}

		switch ($markup) {
			case 'badged':
			case 'raw':
				foreach ($userPoints as $userPointsRow) {
					$html = $userPointsRow->score;
					if ($markup == 'badged') {
						$html .= ' '.\Html::element(
							'img',
							[
								'src' => '/extensions/WikiPoints/images/gp30.png',
								'alt' => 'GP',
								'class' => 'GP-brand',
								'title' => wfMessage('pointsicon-tooltip')
							]
						);
					}
					break;
				}
				break;
			case 'table':
			default:
				$html = \TemplateWikiPoints::pointsBlockHtml($userPoints, $pagination, $wikis, $isSitesMode, $isMonthly);
				break;
		}

		return $html;
	}

	/**
	 * Extracts a domain name from a fragment.  Does not guarantee the domain is real or valid.
	 * Example: //example.com/ => example.com
	 * Example: //sub.example.com/ => sub.example.com
	 * Example: //FakeDomain/ => FakeDomain
	 * Example: FakeDomain => FakeDomain
	 *
	 * @param	string	The domain to extract from a fragment. (e.g. http://fr.wowpedia.org, http://dota2.gamepedia.com)
	 * @return	string	Bare host name extracted or false if unable to parse.
	 */
	static public function extractDomain($fragment) {
		$fragment = mb_strtolower($fragment, 'UTF-8');

		$host = parse_url($fragment, PHP_URL_HOST);
		if ($host !== null) {
			//If parse_url() went fine then return it.
			return $host;
		}

		$fragment = trim(trim($fragment), '/');
		if (preg_match('#^([\w|\.]+?)$#', $fragment, $matches)) {
			return $matches[1];
		}

		return false;
	}

	/**
	 * Get wiki points for user by month.
	 *
	 * @param	integer Global ID
	 * @param	integer Aggregate months into the past.
	 * @return	integer Wiki Points
	 */
	static public function getWikiPointsForRange($globalId, $siteKey = null, $months = null) {
		if ($globalId < 1) {
			return 0;
		}

		$filters = [
			'stat'		=> 'wiki_points',
			'site_key'	=> $siteKey,
			'user_id'	=> $globalId,
			'global'	=> ($siteKey === null ? true : false)
		];

		$statProgress = [];
		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new \MWException("Encountered Cheevos API error {$e->getMessage()}\n");
		}

		$userPoints = [];
		$siteKeys = [];
		foreach ($statProgress as $progress) {
			if ($progress->getStat() === 'wiki_points') {
				return intval($progress->getCount());
			}
		}

		return 0;
	}

	/**
	 * Returns the number of points and rank of any given user on any specific wiki (global by default).
	 * Use WikiPoints::extractDomain($wgServer) to look up points for the local wiki.
	 *
	 * @param	integer	Global User ID for a user
	 * @param	string	[Optional] String indicating a wiki that should be looked up.
	 * @return	array	Keys for "score" and "rank"
	 */
	public static function ppointsForGlobalId($globalId, $wiki = 'all') {
		$result = [
			'score' => '0',
			'rank' => ''
		];

		//@TODO: Tuesday morning - Combine all of this into one function to get wiki points for a global ID.
		self::getWikiPointsForRange($globalId);

		if ($redis !== false) {
			$result['score'] = $redis->zScore($redisKey, $globalId);
			$result['rank'] = 1 + $redis->zRevRank($redisKey, $globalId);
		}

		return $result;
	}
}
