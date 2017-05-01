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
	 * @param	string	[optional, default: 'table'] determines what type of markup is used for the output,
	 * 					'raw' returns an unformatted number for a single user and is ignored for multi-user results
	 *					'badged' returns the same as raw, but with the GP badge branding following it in an <img> tag
	 * 					'table' uses an unstyled HTML table
	 * @return	array	generated HTML string as element 0, followed by parser options
	 */
	public static function pointsBlock(&$parser, $limit = 25, $wikis = '', $markup = 'table') {
		global $wgServer;

		$limit = intval($limit);
		if (!$limit || $limit < 0) {
			$limit = 25;
		}

		$isSitesMode = false;
		if ($wikis === 'all') {
			$isSitesMode = true;
		}

		$html = self::pointsBlockHtml($limit, 0, $isSitesMode, $isMonthly, $markup);

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	integer	[Optional] Items Per Page
	 * @param	integer	[Optional] Offset to start at.
	 * @param	boolean	[Optional] Including all wikis or not.
	 * @param	boolean	[Optional] Showing monthly totals.
	 * @param	string	[Optional] Determines what type of markup is used for the output.
	 * 					'raw' Returns an unformatted number for a single user and is ignored for multi-user results.
	 *					'badged' Returns the same as raw, but with the GP badge branding following it in an <img> tag.
	 * 					'table' Uses an unstyled HTML table.
	 * @return	string	HTML
	 */
	static public function pointsBlockHtml($itemsPerPage = 25, $start = 0, $isSitesMode = false, $isMonthly = false, $markup = 'table') {
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

		if (!$isSitesMode) {
			$filters['site_key'] = $dsSiteKey;
		}

		$statProgress = [];
		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new \ErrorPageError("Encountered Cheevos API error {$e->getMessage()}\n");
		}

		$userPoints = [];
		$siteKeys = [];
		foreach ($statProgress as $progress) {
			$globalId = $progress->getUser_Id();
			if (isset($userPoints[$globalId])) {
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
				$userPointsRow->userLink = \Linker::link(\Title::newFromText("User:".$user->getName()));
			} else {
				$userPointsRow->userName = "GID: ".$progress->getUser_Id();
				$userPointsRow->userToolsLinks = $userPointsRow->userName;
				$userPointsRow->userLink = '';
			}
			$userPointsRow->score = $progress->getCount();
			$userPointsRow->siteKey = $progress->getSite_Key();
			$userPoints[$globalId] = $userPointsRow;
			if ($isSitesMode) {
				$siteKeys[] = $progress->getSite_Key();
			}
		}
		$siteKeys = array_unique($siteKeys);

		$wikis = [];
		if ($isSitesMode && !empty($siteKeys)) {
			$wikis = \DynamicSettings\Wiki::loadFromHash($siteKeys);
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
	public static function getWikiPointsForRange($globalId, $months = null) {
		if ($globalId < 1) {
			return 0;
		}

		$filters = [
			'stat'		=> 'wiki_points',
			'limit'		=> $itemsPerPage,
			'offset'	=> $start,
			'site_key'	=> $dsSiteKey,
			'user_id'	=> $globalId,
			'global'	=> true
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
}
