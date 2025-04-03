<?php

namespace Cheevos;

use Cheevos\Templates\TemplateAchievements;
use Exception;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Reverb\Notification\NotificationBroadcastFactory;
use Wikimedia\ObjectCache\WANObjectCache;

class AchievementService {
	private const CACHE_VERSION = 'v2';
	private const TTL_5_MIN = 300;

	public function __construct(
		private readonly CheevosClient $cheevosClient,
		private readonly WANObjectCache $cache,
		private readonly NotificationBroadcastFactory $notificationBroadcastFactory,
		private readonly UserFactory $userFactory,
		private readonly UserIdentityLookup $userIdentityLookup
	) {
	}

	public function broadcastAchievement( CheevosAchievement $achievement, string $siteKey, int $userId ): void {
		if ( empty( $siteKey ) || $userId < 0 ) {
			return;
		}

		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( !$userIdentity || !$userIdentity->isRegistered() ) {
			return;
		}

		$html = TemplateAchievements::achievementBlockPopUp( $achievement, $siteKey );

		$broadcast = $this->notificationBroadcastFactory->newSystemSingle(
			'user-interest-achievement-earned',
			$this->userFactory->newFromUserIdentity( $userIdentity ),
			[
				'url' => SpecialPage::getTitleFor( 'Achievements' )->getFullURL(),
				'message' => [ [ 'user_note', $html ] ],
			]
		);

		$broadcast?->transmit();
	}

	/** Invalidate API Cache
	 *
	 * @throws Exception
	 */
	public function invalidateCache(): void {
		// NOTE: WANObjectCache doesn't support key wildcard deletion.
	}

	/**
	 * Get all achievements with caching.
	 *
	 * @return CheevosAchievement[]
	 *
	 * @throws CheevosException
	 * @throws Exception
	 */
	public function getAchievements( ?string $siteKey = null ): array {
		$cacheKey = $this->cache->makeKey( 'cheevos', 'apicache', 'getAchievements', self::CACHE_VERSION, $siteKey ?: 'all' );
		$cachedValue = $this->cache->get( $cacheKey );

		if ( !empty( $cachedValue ) ) {
			return $this->cheevosClient->parse(
				$cachedValue,
				'achievements',
				CheevosAchievement::class
			);
		}

		$response = $this->cheevosClient->get( 'achievements/all', [ 'site_key' => $siteKey, 'limit' => 0 ] );
		if ( isset( $response['achievements'] ) ) {
			$this->cache->set( $cacheKey, $response, self::TTL_5_MIN );
		}

		return $this->cheevosClient->parse( $response, 'achievements', CheevosAchievement::class );
	}

	/** Get achievement by database ID with caching.
	 *
	 * @throws Exception
	 */
	public function getAchievement( int $id ): ?CheevosAchievement {
		$cacheKey = $this->cache->makeKey( 'cheevos', 'apicache', 'getAchievement', self::CACHE_VERSION, $id );
		$cachedValue = $this->cache->get( $cacheKey );

		if ( !empty( $cachedValue ) ) {
			return $this->cheevosClient->parse(
				[ $cachedValue ],
				'achievements',
				CheevosAchievement::class,
				true
			);
		}

		$response = $this->cheevosClient->get( "achievement/$id" );
		$this->cache->set( $cacheKey, $response, self::TTL_5_MIN );

		return $this->cheevosClient->parse( [ $response ], 'achievements', CheevosAchievement::class, true );
	}

	/** Soft delete an achievement from the service.
	 *
	 * @throws CheevosException
	 */
	public function deleteAchievement( int $id, int $authorId ): array {
		return $this->cheevosClient->delete( "achievement/$id", [ "author_id" => $authorId ] );
	}

	/** Update an existing achievement on the service.
	 *
	 * @throws CheevosException
	 */
	public function updateAchievement( int $id, array $body ): void {
		$this->cheevosClient->put(
			$id ? "achievement/$id" : 'achievement',
			$body
		);
	}

	/** Create Achievement
	 *
	 * @throws CheevosException
	 */
	public function createAchievement( array $body ): void {
		$this->cheevosClient->put( 'achievement', $body );
	}

	/**
	 * Get achievement status for a user.
	 *
	 * @return CheevosAchievementStatus[]
	 *
	 * @throws CheevosException
	 */
	public function getAchievementStatus( int $userId, string $siteKey ): array {
		$response = $this->cheevosClient->get(
			'achievements/status',
			[
				'limit' => 0,
				'user_id' => $userId,
				'site_key' => $siteKey,
			]
		);
		return $this->cheevosClient->parse( $response, 'status', CheevosAchievementStatus::class );
	}

	/**
	 * Return AchievementProgress for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can be omitted from the array.
	 *                           - $filters = [
	 *                           -     'site_key' => 'example', //Limit by site key.
	 *                           -     'achievement_id' => 0, //Limit by achievement ID.
	 *                           -     'user_id' => 0, //Limit by global user ID.
	 *                           -     'category_id' => 0, //Limit by category ID.
	 *                           -     'earned' => false, //Only get progress for earned achievements.
	 *                           -     'limit' => 100, //Maximum number of results.
	 *                           -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                           - ];
	 * @param UserIdentity|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return CheevosAchievementProgress[]
	 *
	 * @throws CheevosException
	 */
	public function getAchievementProgress( array $filters = [], ?UserIdentity $user = null ): array {
		$parsedFilters = $this->parseFilters( $filters, $user );

		$response = $this->cheevosClient->get( 'achievements/progress', $parsedFilters );
		return $this->cheevosClient->parse( $response, 'progress', CheevosAchievementProgress::class );
	}

	/**
	 * Get process for achievement
	 *
	 * @throws CheevosException
	 */
	public function getProgress( int $id ): ?CheevosAchievementProgress {
		$response = $this->cheevosClient->get( "achievements/progress/$id" );
		return $this->cheevosClient->parse( [ $response ], 'progress', CheevosAchievementProgress::class, true );
	}

	/** Delete progress towards an achievement.
	 *
	 * @throws CheevosException
	 */
	public function deleteProgress( int $id ): array {
		return $this->cheevosClient->delete( "achievements/progress/$id" );
	}

	/**
	 * Put process for achievement. Either create or updates.
	 *
	 * @throws CheevosException
	 */
	public function putProgress( array $body ): array {
		return $this->cheevosClient->put( 'achievements/progress', $body );
	}

	/**
	 * Get all categories.
	 *
	 * @param bool $skipCache Skip pulling data from the local cache. Will still update the local cache.
	 *
	 * @return CheevosAchievementCategory[]
	 *
	 * @throws Exception
	 */
	public function getCategories( bool $skipCache = false ): array {
		$cacheKey = $this->cache->makeKey( 'cheevos', 'apicache', 'getCategories', self::CACHE_VERSION );

		if ( !$skipCache ) {
			$cachedValue = $this->cache->get( $cacheKey );
			if ( !empty( $cachedValue ) ) {
				return $this->cheevosClient->parse( $cachedValue, 'categories', CheevosAchievementCategory::class );
			}
		}

		$response = $this->cheevosClient->get( 'achievement_categories/all', [ 'limit' => 0 ] );
		$this->cache->set( $cacheKey, $response, self::TTL_5_MIN );

		return $this->cheevosClient->parse( $response, 'categories', CheevosAchievementCategory::class );
	}

	/** Get Category by ID
	 *
	 * @throws Exception
	 */
	public function getCategory( int $id ): ?CheevosAchievementCategory {
		$cacheKey = $this->cache->makeKey( 'cheevos', 'apicache', 'getCategory', self::CACHE_VERSION, $id );
		$cachedValue = $this->cache->get( $cacheKey );

		if ( !empty( $cachedValue ) ) {
			return $this->cheevosClient->parse(
				$cachedValue,
				'categories',
				CheevosAchievementCategory::class,
				true
			);
		}

		$response = $this->cheevosClient->get( "achievement_category/$id" );
		$this->cache->set( $cacheKey, $response, self::TTL_5_MIN );

		return $this->cheevosClient->parse( $response, 'categories', CheevosAchievementCategory::class, true );
	}

	/** Delete Category by ID (with optional user_id for user that deleted the category)
	 *
	 * @throws CheevosException
	 */
	public function deleteCategory( int $id, int $authorId ): void {
		$this->cheevosClient->delete( "achievement_category/$id", [ 'author_id' => $authorId ] );
	}

	/** Update Category by ID
	 *
	 * @throws CheevosException
	 */
	public function updateCategory( int $id, array $body ): array {
		return $this->cheevosClient->put(
			$id ? "achievement_category/$id" : 'achievement_category',
			$body
		);
	}

	/** Create Category
	 *
	 * @throws CheevosException
	 */
	public function createCategory( array $body ): array {
		return $this->cheevosClient->put( 'achievement_category', $body );
	}

	/** Call the increment end point on the API.
	 *
	 * @throws CheevosException
	 */
	public function increment( array $body ): array {
		return $this->cheevosClient->post( 'increment', $body );
	}

	/** Call increment to check for any unnotified achievement rewards.
	 *
	 * @throws CheevosException
	 */
	public function checkUnnotified( int $globalId, string $siteKey, bool $forceRecalculate ): array {
		if ( empty( $globalId ) || empty( $siteKey ) ) {
			return [];
		}

		$data = [
			'user_id' => $globalId,
			'site_key' => $siteKey,
			'recalculate' => $forceRecalculate,
			'deltas' => []
		];
		return $this->increment( $data );
	}

	/**
	 * Return StatProgress for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can be omitted from the array.
	 *                        This is an array since the amount of filter parameters is expected to be reasonably
	 *                          volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 *                          For example, sending a numeric string when the service is expecting an integer will
	 *                          result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'global' => false, //Set to true to aggregate stats from all sites.
	 *                                                    (Also causes site_key to be ignored.)
	 *                        -     'stat' => 'example', //Filter by a specific stat name.
	 *                        -     'sort_direction' => 'asc' or 'desc', //If supplied, the result will be sorted
	 *                                                                    on the stats' count field.
	 *                        -     'start_time' => 'example', //If supplied, only stat deltas after this
	 *                                                            unix timestamp are considered.
	 *                        -     'end_time' => 'example', //If supplied, only stat deltas before this unix
	 *                                                            timestamp are considered.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 *
	 * @return CheevosStatProgress[]
	 *
	 * @throws CheevosException
	 */
	public function getStatProgress( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters( $filters, $userIdentity, 200 );

		return $this->cheevosClient->parse(
			$this->cheevosClient->get( 'stats', $parsedFilters ),
			'stats',
			CheevosStatProgress::class
		);
	}

	/**
	 * Return WikiPointLog for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can omitted from the array.
	 *                        This is an array since the amount of filter parameters is expected to be reasonably
	 *                          volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 *                          For example, sending a numeric string when the service is expecting an integer will
	 *                          result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 *
	 * @return CheevosWikiPointLog[]
	 *
	 * @throws CheevosException
	 */
	public function getWikiPointLog( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters( $filters, $userIdentity, 25 );

		return $this->cheevosClient->parse(
			$this->cheevosClient->get( 'points/user', $parsedFilters ),
			'points',
			CheevosWikiPointLog::class
		);
	}

	/**
	 * @throws CheevosException
	 */
	public function getUserPointRank(
		UserIdentity $userIdentity,
		?string $siteKey = null
	): mixed {
		$response = $this->cheevosClient->get(
			'points/user_rank',
			[ 'user_id' => $userIdentity->getId(), 'site_key' => $siteKey ]
		);
		return $this->cheevosClient->parse( $response, 'rank' );
	}

	/**
	 * Return StatMonthlyCount for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can omitted from the array.
	 *                          This is an array since the amount of filter parameters is expected to be
	 *                          reasonably volatile over the life span of the product. This function
	 *                          does minimum validation of the filters.  For example, sending a numeric
	 *                          string when the service is expecting an integer will result in an
	 *                          exception being thrown.
	 *                          - $filters = [
	 *                          -     'user_id' => 1, //Limit by service user ID.
	 *                          -     'site_key' => 'example', //Limit by site key.
	 *                          -     'stat' => 'example', //Filter by a specific stat name.
	 *                          -     'global' => true, //Overrides site_key to aggregate across all sites.
	 *                          -     'month' => 1601510400, //Limit to one month (starting timestamp).
	 *                          -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                          -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                          - ];
	 *
	 * @return CheevosStatMonthlyCount[]
	 *
	 * @throws CheevosException
	 */
	public function getStatMonthlyCount( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters( $filters, $userIdentity, 200 );
		$response = $this->cheevosClient->get( 'stats/monthly', $parsedFilters );
		return $this->cheevosClient->parse( $response, 'stats', CheevosStatMonthlyCount::class );
	}

	/** Return stats/user_site_count for selected filters.
	 *
	 * @throws CheevosException
	 */
	public function getUserSitesCountByStat(
		UserIdentity $userIdentity,
		string $statName
	): mixed {
		$response = $this->cheevosClient->get(
			'stats/user_sites_count',
			[ 'user_id' => $userIdentity->getId(), 'stat' => $statName ]
		);

		return $this->cheevosClient->parse( $response, 'count' );
	}

	/** Revokes edit points for the provided revision IDs related to the page ID.
	 *
	 * @throws CheevosException
	 */
	public function revokeEditPoints( int $pageId, array $revisionIds, string $siteKey ): array {
		return $this->cheevosClient->post(
			'points/revoke_revisions',
			[
				'page_id' => $pageId,
				'revision_ids' => array_map( static fn ( $id ) => (int)$id, $revisionIds ),
				'site_key' => $siteKey,
			]
		);
	}

	private function parseFilters( array $filters, ?UserIdentity $userIdentity, ?int $defaultLimit = null ): array {
		if ( $userIdentity !== null ) {
			$filters['user_id'] = $userIdentity->getId();
		}

		if ( $defaultLimit !== null ) {
			$filters['limit'] ??= $defaultLimit;
		}
		return $filters;
	}

}
