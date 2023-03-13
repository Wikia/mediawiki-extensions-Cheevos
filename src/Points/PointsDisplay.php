<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Display
 *
 * @package   Cheevos
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos\Points;

use Cheevos\AchievementService;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Cheevos\Templates\TemplateWikiPoints;
use Html;
use Linker;
use MediaWiki\MediaWikiServices;
use Parser;
use RedisCache;
use RequestContext;
use stdClass;
use Title;
use User;

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
	 * {{#GPScore: 50 | destiny,dota2,theorder1886}}
	 * displays top 50 scoring users with points from any of those three wikis
	 * {{#GPScore: User:Cathadan | destiny,dota2,theorder1886}} displays the sum of my scores from the given wikis
	 *
	 * TODO: break this function down, make it easier to read
	 *
	 * @param Parser $parser mediawiki Parser reference
	 * @param string $user ???????
	 * @param string|int $limit [Optional] Limit results.
	 * @param string $wikis [optional, default: ''] comma separated list of wiki namespaces,
	 * defaults to the current wiki
	 *					Special namespaces are:
	 *						'all' - Breaks down points per user per wiki.
	 *						'global' - Breaks down points per user across all wikis.
	 * @param string $markup [optional, default: 'table'] determines what type of markup is used for the output,
	 * 					'raw' returns an unformatted number for a single user and is ignored for multi-user results
	 *					'badged' returns the same as raw, but with the GP badge branding following it in an <img> tag
	 * 					'table' uses an unstyled HTML table
	 *
	 * @return array generated HTML string as element 0, followed by parser options
	 */
	public static function pointsBlock(
		Parser $parser,
		string $user = '',
		$limit = 25,
		string $wikis = '',
		string $markup = 'table'
	) {
		$dsSiteKey = CheevosHelper::getSiteKey();

		$limit = (int)$limit;
		if ( !$limit || $limit < 0 ) {
			$limit = 25;
		}

		$globalId = null;
		if ( !empty( $user ) ) {
			$userIdentity = MediaWikiServices::getInstance()->getUserIdentityLookup()
				->getUserIdentityByName( $user );
			if ( !$userIdentity || !$userIdentity->isRegistered() ) {
				return [
					wfMessage( 'user_not_found' )->escaped(),
					'isHTML' => true,
				];
			}

			$globalId = $userIdentity->getId();
		}

		$siteKey = $wikis !== 'all' && $wikis !== 'global' ? $dsSiteKey : null;
		$isSitesMode = false;

		$html = self::pointsBlockHtml( $siteKey, $globalId, $limit, 0, $isSitesMode, false, $markup );

		return [
			$html,
			'isHTML' => true,
		];
	}

	/**
	 * Get a standard points block HTML output.
	 *
	 * @param string|null $siteKey Limit by or override the site key used.
	 * @param int|null $globalId Global ID to filter by.
	 * @param int|null $itemsPerPage Items Per Page
	 * @param int|null $start Offset to start at.
	 * @param bool|null $isSitesMode Show individual wikis in the results instead of combining with 'global' => true.
	 * @param bool|null $isMonthly Show monthly totals.
	 * @param string $markup Determines what type of markup is used for the output.
	 * 					'raw' Returns an unformatted number for a single user and is ignored for multi-user results.
	 *					'badged' Returns the same as raw, but with the GP badge branding following it in an <img> tag.
	 * 					'table' Uses a standard wikitable class HTML table.
	 * @param Title|null $title Specify a Title to display pagination with.
	 * 					No pagination will be displayed if this is left as null.
	 *
	 * @return string HTML
	 */
	public static function pointsBlockHtml(
		string $siteKey = null,
		?int $globalId = null,
		?int $itemsPerPage = 25,
		?int $start = 0,
		?bool $isSitesMode = false,
		?bool $isMonthly = false,
		string $markup = 'table',
		Title $title = null
	) {
		global $wgExtensionAssetsPath;
		$dsSiteKey = CheevosHelper::getSiteKey();
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$itemsPerPage = max( 1, min( (int)$itemsPerPage, 200 ) );
		$start = (int)$start;
		$isSitesMode = (bool)$isSitesMode;
		$isMonthly = (bool)$isMonthly;

		$statProgress = self::getPoints( $siteKey, $globalId, $itemsPerPage, $start, $isSitesMode, $isMonthly );

		$userPoints = [];
		$siteKeys = [ $dsSiteKey ];

		foreach ( $statProgress as $progress ) {
			$globalId = $progress->getUser_Id();
			$lookupKey = $globalId . '-' . $progress->getSite_Key() . '-' .
						 ( $isMonthly ? $progress->getMonth() : null );
			if ( isset( $userPoints[$lookupKey] ) ) {
				continue;
			}

			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $globalId );
			if ( $globalId < 1 ) {
				continue;
			}

			$userPointsRow = new stdClass();
			if ( $user !== null ) {
				$userPointsRow->userName = $user->getName();
				if ( !$userNameUtils->isCreatable( $user->getName() ) || $user->isHidden() ) {
					continue;
				}
				$userPointsRow->userToolsLinks = Linker::userToolLinks( $user->getId(), $user->getName() );
				$userPointsRow->userLink = $linkRenderer->makeKnownLink(
					Title::newFromText( "User:" . $user->getName() ), $user->getName()
				);
				$userPointsRow->adminUrl = Title::newFromText( "Special:WikiPointsAdmin" )->getFullUrl(
					[ 'user' => $user->getName() ]
				);
			} else {
				$userPointsRow->userName = "GID: " . $progress->getUser_Id();
				$userPointsRow->userToolsLinks = $userPointsRow->userName;
				$userPointsRow->userLink = '';
			}
			if ( $isMonthly ) {
				$userPointsRow->yyyymm = gmdate( 'F Y', $progress->getMonth() );
			}
			$userPointsRow->score = $progress->getCount();
			$userPointsRow->siteKey = $progress->getSite_Key();
			$userPoints[$lookupKey] = $userPointsRow;
			if ( $isSitesMode ) {
				$siteKeys[] = $progress->getSite_Key();
			}
		}
		$siteKeys = array_unique( $siteKeys );

		$wikis = [];
		if ( $isSitesMode && !empty( $siteKeys ) ) {
			global $wgServer;
			$redis = MediaWikiServices::getInstance()
				->getService( RedisCache::class )
				->getConnection( 'cache' );
			if ( $redis !== false ) {
				foreach ( $siteKeys as $siteKey ) {
					if ( !empty( $siteKey ) ) {
						$wiki = CheevosHelper::getWikiInformation( $siteKey );
						if ( !empty( $wiki ) ) {
							$wikis[$siteKey] = $wiki;
						}
					}
				}
			}

			$localDomain = trim( $wgServer, '/' );
			foreach ( $userPoints as $key => $userPointsRow ) {
				if (
					$userPointsRow->siteKey != $dsSiteKey &&
					 !empty( $userPointsRow->userLink ) &&
					 isset( $wikis[$userPointsRow->siteKey] )
				) {
					$domain = parse_url( $wikis[$userPointsRow->siteKey]->getWikiUrl() )['host'];
					$userPoints[$key]->userToolsLinks = str_replace(
						$localDomain,
						$domain,
						$userPoints[$key]->userToolsLinks
					);
					$userPoints[$key]->userLink = str_replace(
						$localDomain,
						"https://" . $domain,
						$userPoints[$key]->userLink
					);
					$userPoints[$key]->userToolsLinks = str_replace(
						'href="/',
						'href="https://' . $domain . '/',
						$userPoints[$key]->userToolsLinks
					);
					$userPoints[$key]->userLink = str_replace(
						'href="/',
						'href="https://' . $domain . '/',
						$userPoints[$key]->userLink
					);
				}
			}
		}

		$user = RequestContext::getMain()->getUser();

		switch ( $markup ) {
			case 'badged':
			case 'raw':
				if ( empty( $userPoints ) ) {
					$userPointsRow = new stdClass();
					$userPointsRow->score = 0;
					$userPoints[] = $userPointsRow;
				}
				foreach ( $userPoints as $userPointsRow ) {
					$html = (
						isset( $userPointsRow->adminUrl ) &&
						$user->isAllowed( 'wiki_points_admin' ) ?
							"<a href='{$userPointsRow->adminUrl}'>{$userPointsRow->score}</a>" :
							$userPointsRow->score );
					if ( $markup == 'badged' ) {
						$html .= ' ' . Html::element(
							'img',
							[
								'src' => "$wgExtensionAssetsPath/Cheevos/images/gp30.png",
								'alt' => 'GP',
								'class' => 'GP-brand',
								'title' => wfMessage( 'pointsicon-tooltip' )
							]
						);
					}
					break;
				}
				break;
			case 'table':
			default:
				$pagination = '';
				if ( $title !== null ) {
					$pagination = TemplateWikiPoints::getSimplePagination( $title, $itemsPerPage, $start );
				}
				$html = TemplateWikiPoints::pointsBlockHtml(
					$userPoints,
					$pagination,
					$start,
					$wikis,
					$isSitesMode,
					$isMonthly
				);
				break;
		}

		return $html;
	}

	/**
	 * Get a list of earned wiki points grouped by criteria.
	 *
	 * @param string|null $siteKey Limit by or override the site key used.
	 * @param int|null $globalId Global ID to filter by.
	 * @param int|null $itemsPerPage Items Per Page
	 * @param int|null $start Offset to start at.
	 * @param bool|null $isSitesMode Show individual wikis in the results instead of combining with 'global' => true.
	 * @param bool|null $isMonthly Show monthly totals.
	 *
	 * @return array CheevosStatProgress Objects
	 */
	public static function getPoints(
		?string $siteKey = null,
		?int $globalId = null,
		?int $itemsPerPage = 25,
		?int $start = 0,
		?bool $isSitesMode = false,
		?bool $isMonthly = false
	): array {
		$itemsPerPage = max( 1, min( (int)$itemsPerPage, 200 ) );
		$start = (int)$start;
		$isSitesMode = (bool)$isSitesMode;
		$isMonthly = (bool)$isMonthly;

		$total = 0;

		$filters = [
			'stat'				=> 'wiki_points',
			'limit'				=> $itemsPerPage,
			'offset'			=> $start,
			'sort_direction'	=> 'desc'
		];

		if ( !$isSitesMode && empty( $siteKey ) ) {
			$filters['global'] = true;
		}

		if ( $siteKey !== null && !empty( $siteKey ) ) {
			$filters['site_key'] = $siteKey;
		}

		if ( $globalId > 0 ) {
			$filters['user_id'] = (int)$globalId;
		}

		$statProgress = [];
		if ( $isMonthly ) {
			try {
				$statProgress = MediaWikiServices::getInstance()->getService( AchievementService::class )
					->getStatMonthlyCount( $filters );
			} catch ( CheevosException $e ) {
				wfDebug( __METHOD__ . ": " . wfMessage( 'cheevos_api_error', $e->getMessage() ) );
			}
		} else {
			try {
				$statProgress = MediaWikiServices::getInstance()->getService( AchievementService::class )
					->getStatProgress( $filters );
			} catch ( CheevosException $e ) {
				wfDebug( __METHOD__ . ": " . wfMessage( 'cheevos_api_error', $e->getMessage() ) );
			}
		}
		return $statProgress;
	}

	/**
	 * Extracts a domain name from a fragment.  Does not guarantee the domain is real or valid.
	 * Example: //example.com/ => example.com
	 * Example: //sub.example.com/ => sub.example.com
	 * Example: //FakeDomain/ => FakeDomain
	 * Example: FakeDomain => FakeDomain
	 *
	 * @param string $fragment The domain to extract from a fragment.
	 * (e.g. http://fr.wowpedia.org, http://dota2.gamepedia.com)
	 *
	 * @return bool|string Bare host name extracted or false if unable to parse.
	 */
	public static function extractDomain( string $fragment ): bool|string {
		$fragment = mb_strtolower( $fragment, 'UTF-8' );

		$host = parse_url( $fragment, PHP_URL_HOST );
		if ( $host !== null ) {
			// If parse_url() went fine then return it.
			return $host;
		}

		$fragment = trim( trim( $fragment ), '/' );
		if ( preg_match( '#^([\w|\.]+?)$#', $fragment, $matches ) ) {
			return $matches[1];
		}

		return false;
	}

	/**
	 * Get wiki points for user by month.
	 *
	 * @param User $user User to look up.
	 * @param string|null $siteKey [Optional] Site Key
	 * @param int|null $monthsAgo [Optional] Aggregate months into the past.
	 *
	 * @return int Wiki Points
	 */
	public static function getWikiPointsForRange( User $user, string $siteKey = null, int $monthsAgo = null ): int {
		$globalId = $user->getId();

		if ( $globalId < 1 ) {
			return 0;
		}

		$filters = [
			'stat'		=> 'wiki_points',
			'site_key'	=> $siteKey,
			'user_id'	=> $globalId,
			'global'	=> ( $siteKey === null ? true : false )
		];

		$monthsAgo = (int)$monthsAgo;
		if ( $monthsAgo > 0 ) {
			$filters['start_time'] = strtotime(
				date( 'Y-m-d', strtotime( $monthsAgo . ' month ago' ) ) . 'T00:00:00+00:00'
			);
			$filters['end_time'] = strtotime( date( 'Y-m-d', strtotime( 'yesterday' ) ) . 'T23:59:59+00:00' );
		}

		$statProgress = [];
		try {
			$statProgress = MediaWikiServices::getInstance()->getService( AchievementService::class )
				->getStatProgress( $filters );
		} catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error {$e->getMessage()}\n" );
		}

		foreach ( $statProgress as $progress ) {
			if ( $progress->getStat() === 'wiki_points' ) {
				return (int)$progress->getCount();
			}
		}

		return 0;
	}
}
