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
 */

namespace Cheevos;

use Cheevos\Job\CheevosIncrementJob;
use Config;
use Exception;
use Fandom\Includes\Article\GlobalTitleLookup;
use Fandom\WikiConfig\WikiVariablesDataService;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use RequestContext;
use WikiDomain\WikiConfigData;
use WikiDomain\WikiConfigDataService;

class CheevosHelper {

	private static array $increments = [];
	private static bool $shutdownRegistered = false;
	private static bool $shutdownRan = false;

	public function __construct(
		private AchievementService $achievementService,
		private Config $config,
		private GlobalTitleLookup $globalTitleLookup,
		private WikiConfigDataService $wikiConfigDataService
	) {
	}

	public function increment( string $stat, int $delta, UserIdentity $user, array $edits = [] ): void {
		// Register shutdown function to actually save increments
		if ( !self::$shutdownRegistered && PHP_SAPI !== 'cli' ) {
			self::$shutdownRegistered = true;
			register_shutdown_function( fn() => $this->doIncrements() );
		}

		$siteKey = self::getSiteKey();
		if ( !$siteKey || !$user->isRegistered() ) {
			return;
		}

		$userId = $user->getId();
		$timestamp = time();
		self::$increments[$userId]['user_id'] = $userId;
		self::$increments[$userId]['user_name'] = $user->getName();
		self::$increments[$userId]['site_key'] = $siteKey;
		self::$increments[$userId]['deltas'][] = [ 'stat' => $stat, 'delta' => $delta ];
		self::$increments[$userId]['timestamp'] = $timestamp;
		self::$increments[$userId]['request_uuid'] = sha1( $userId . $siteKey . $timestamp . random_bytes( 4 ) );
		if ( !empty( $edits ) ) {
			if ( !isset( self::$increments[$userId]['edits'] ) ||
				!is_array( self::$increments[$userId]['edits'] ) ) {
				self::$increments[$userId]['edits'] = [];
			}
			self::$increments[$userId]['edits'] = array_merge( self::$increments[$userId]['edits'], $edits );
		}

		if ( self::$shutdownRan ) {
			$this->doIncrements();
		}
	}

	private function doIncrements() {
		// Attempt to do it NOW. If we get an error, fall back to the SyncService job.
		try {
			self::$shutdownRan = true;
			foreach ( self::$increments as $userId => $increment ) {
				$return = $this->achievementService->increment( $increment );
				unset( self::$increments[$userId] );
				if ( isset( $return['earned'] ) ) {
					foreach ( $return['earned'] as $achievement ) {
						$achievement = new CheevosAchievement( $achievement );
						$this->achievementService->broadcastAchievement(
							$achievement,
							$increment['site_key'],
							$increment['user_id']
						);
					}
				}
			}
		} catch ( CheevosException $e ) {
			foreach ( self::$increments as $userId => $increment ) {
				CheevosIncrementJob::queue( $increment );
				unset( self::$increments[$userId] );
			}
		}
	}

	public function getUrlOnCheevosCentralWiki( LinkTarget $target ): string {
		$centralWikiId = $this->config->get( 'CheevosCentralWikiId' );
		return $this->globalTitleLookup->getForeignPageURL(
			$this->wikiConfigDataService->getWikiDataById( $centralWikiId ),
			$target
		);
	}

	public function isCheevosCentralWiki(): bool {
		return (bool)$this->config->get( 'CheevosIsCentral' );
	}

	/**
	 * Return the language code the current user.
	 *
	 * @return string Language Code
	 */
	public static function getUserLanguage() {
		try {
			$user = RequestContext::getMain()->getUser();
			$code = MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'language' );
		} catch ( Exception $e ) {
			// "failure? English is the best anyway."  --Cameron Chunn, 2017-03-02 15:37:33 -0600
			$code = "en";
		}
		return $code;
	}

	/**
	 * Turns an array of CheevosStatProgress objects into an array that is easier to consume.
	 *
	 * @param array $stats Flat array.
	 *
	 * @return array Nice array.
	 */
	public static function makeNiceStatProgressArray( array $stats ): array {
		$nice = [];
		$users = [];

		foreach ( $stats as $stat ) {
			$_data = [
				'stat_id' => $stat['stat_id'],
				'count' => $stat['count'],
				'last_incremented' => $stat['last_incremented'],
			];
			if ( !isset( $users[$stat['user_id']] ) ) {
				$users[$stat['user_id']] =
					MediaWikiServices::getInstance()->getUserFactory()->newFromId( $stat['user_id'] );
			}
			if ( isset( $stat['site_key'] ) && !empty( $stat['site_key'] ) ) {
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
	 * @param string $siteKey Site Key
	 * @param WikiConfigData|null $wiki Provide already retrieved wiki object.
	 *
	 * @return string Site Name with Language
	 */
	public function getSiteName( string $siteKey, ?WikiConfigData $wiki = null ): string {
		$sitename = $this->config->get( 'Sitename' );
		$languageCode = $this->config->get( 'LanguageCode' );

		$dsSiteKey = self::getSiteKey();

		if ( !empty( $siteKey ) && $siteKey !== $dsSiteKey ) {
			if ( empty( $wiki ) ) {
				$wiki = self::getWikiInformation( $siteKey );
			}
			if ( !empty( $wiki ) ) {
				$sitename = $wiki->getTitle();
				$languageCode = $wiki->getLangCode();
			}
		}

		return sprintf( '%s (%s)', $sitename, mb_strtoupper( $languageCode, 'UTF-8' ) );
	}

	/**
	 * Get wiki information based on the provided site identifier.($dsSiteKey or $cityId)
	 *
	 * @param string $siteKey
	 *
	 * @return WikiConfigData|null
	 */
	public static function getWikiInformation( string $siteKey ): ?WikiConfigData {
		$services = MediaWikiServices::getInstance();
		$wikiConfigDataService = $services->getService( WikiConfigDataService::class );
		if ( strlen( $siteKey ) === 32 ) {
			// Handle legecy $dsSiteKey MD5 hash.
			$wikiVariablesService = $services->getService( WikiVariablesDataService::class );
			$variableId = $wikiVariablesService->getVarIdByName( 'dsSiteKey' );
			if ( !$variableId ) {
				return null;
			}
			// JSON encoding the $siteKey has a potential performance benefit over LIKE.
			$listOfWikisWithVar = $wikiVariablesService->getListOfWikisWithVar(
				$variableId,
				'=',
				json_encode( $siteKey ),
				'$',
				0,
				1
			);
			if ( $listOfWikisWithVar['total_count'] === 1 ) {
				$cityId = key( $listOfWikisWithVar['result'] );
				$wiki = $wikiConfigDataService->getWikiDataById( (int)$cityId );
			}
		} else {
			$wiki = $wikiConfigDataService->getWikiDataById( (int)$siteKey );
		}
		if ( empty( $wiki ) ) {
			$wiki = null;
		}

		return $wiki;
	}

	/**
	 * Get site key.
	 *
	 * @return string|null Site key string or null if empty.
	 */
	public static function getSiteKey(): ?string {
		global $dsSiteKey; // phpcs:ignore
		if ( empty( $dsSiteKey ) ) {
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$cityId = $config->get( 'CityId' );

			if ( empty( $cityId ) ) {
				return null;
			}
			$dsSiteKey = $cityId;
		}

		return (string)$dsSiteKey;
	}

	/**
	 * @deprecated
	 *
	 * Return if we are operating in the context of the central wiki.
	 *
	 * @return bool
	 */
	public static function isCentralWiki(): bool {
		return MediaWikiServices::getInstance()->getService( CheevosHelper::class )->isCheevosCentralWiki();
	}
}
