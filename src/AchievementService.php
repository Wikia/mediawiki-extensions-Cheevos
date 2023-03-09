<?php

namespace Cheevos;

use Cheevos\Templates\TemplateAchievements;
use Config;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use RedisCache;
use RedisException;
use Reverb\Notification\NotificationBroadcastFactory;
use SpecialPage;

class AchievementService {
	private const REDIS_CONNECTION_GROUP = 'cache';
	private const CACHE_VERSION = 'v1';
	private const TTL_5_MIN = 300;

	public function __construct(
		private CheevosClient $cheevosClient,
		private RedisCache $redisCache,
		private Config $config,
		private NotificationBroadcastFactory $notificationBroadcastFactory,
		private UserFactory $userFactory,
		private UserIdentityLookup $userIdentityLookup
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

		if ( $broadcast ) {
			$broadcast->transmit();
		}
	}

	/** Invalidate API Cache */
	public function invalidateCache(): void {
		$redis = $this->redisCache->getConnection( self::REDIS_CONNECTION_GROUP );
		if ( $redis === false ) {
			return;
		}

		$redisServers = $this->config->has( 'RedisServers' ) ? $this->config->get( 'RedisServers' ) : [];
		$prefix = $redisServers['cache']['options']['prefix'] ?? '';

		try {
			$keys = $redis->getKeys( 'cheevos:apicache:*' );
			foreach ( $keys as $key ) {
				// remove prefix if exists, because weird.
				$key = str_replace( $prefix . 'cheevos', 'cheevos', $key );
				$redis->del( $key );
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}
	}

	/**
	 * Get all achievements with caching.
	 *
	 * @return CheevosAchievement[]
	 */
	public function getAchievements( ?string $siteKey = null ): array {
		$redis = $this->redisCache->getConnection( self::REDIS_CONNECTION_GROUP );
		if ( !$redis ) {
			return $this->cheevosClient->parse(
				$this->cheevosClient->get( 'achievements/all', [ 'site_key' => $siteKey, 'limit' => 0 ] ),
				'achievements',
				CheevosAchievement::class
			);
		}

		$redisKey = $this->makeRedisKey( 'getAchievements', self::CACHE_VERSION, $siteKey ?: 'all' );
		try {
			$cachedValue = json_decode( $redis->get( $redisKey ), true );
			if ( !empty( $cachedValue ) ) {
				return $this->cheevosClient->parse(
					$cachedValue,
					'achievements',
					CheevosAchievement::class
				);
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		$response = $this->cheevosClient->get( 'achievements/all', [ 'site_key' => $siteKey, 'limit' => 0 ] );
		try {
			if ( isset( $response['achievements'] ) ) {
				$redis->setEx( $redisKey, self::TTL_5_MIN, json_encode( $response ) );
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		return $this->cheevosClient->parse( $response, 'achievements', CheevosAchievement::class );
	}

	/** Get achievement by database ID with caching. */
	public function getAchievement( int $id ): ?CheevosAchievement {
		$redis = $this->redisCache->getConnection( self::REDIS_CONNECTION_GROUP );
		if ( !$redis ) {
			$response = $this->cheevosClient->get( "achievements/$id" );
			return $this->cheevosClient->parse(
				[ $response ],
				'achievements',
				CheevosAchievement::class,
				true
			);
		}

		$redisKey = $this->makeRedisKey( 'getAchievement', self::CACHE_VERSION, $id );
		try {
			$cachedValue = json_decode( $redis->get( $redisKey ), true );
			if ( !empty( $cachedValue ) ) {
				return $this->cheevosClient->parse(
					[ $cachedValue ],
					'achievements',
					CheevosAchievement::class,
					true
				);
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		$response = $this->cheevosClient->get( "achievements/$id" );
		try {
			$redis->setEx( $redisKey, self::TTL_5_MIN, json_encode( $response ) );
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		return $this->cheevosClient->parse( [ $response ], 'achievements', CheevosAchievement::class, true );
	}

	/** Soft delete an achievement from the service. */
	public function deleteAchievement( int $id, int $authorId ): array {
		return $this->cheevosClient->delete( "achievement/{$id}", [ "author_id" => $authorId ] );
	}

	/** Update an existing achievement on the service. */
	public function updateAchievement( int $id, array $body ): void {
		$this->cheevosClient->put(
			$id ? "achievement/{$id}" : 'achievement',
			$body
		);
	}

	/** Create Achievement */
	public function createAchievement( array $body ): void {
		$this->cheevosClient->put( 'achievement', $body );
	}

	/**
	 * Get achievement status for an user.
	 *
	 * @return CheevosAchievementStatus[]
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
	 */
	public function getAchievementProgress( array $filters = [], ?UserIdentity $user = null ): array {
		$parsedFilters = $this->parseFilters(
			$filters,
			[ 'user_id', 'achievement_id', 'category_id', 'limit', 'offset' ],
			$user
		);

		$response = $this->cheevosClient->get( 'achievements/progress', $parsedFilters );
		return $this->cheevosClient->parse( $response, 'progress', CheevosAchievementProgress::class );
	}

	/**
	 * Get process for achievement
	 *
	 * @param int $id
	 */
	public function getProgress( $id ): ?CheevosAchievementProgress {
		$response = $this->cheevosClient->get( "achievements/progress/$id" );
		return $this->cheevosClient->parse( [ $response ], 'progress', CheevosAchievementProgress::class, true );
	}

	/** Delete progress towards an achievement. */
	public function deleteProgress( int $id ): array {
		return $this->cheevosClient->get( "achievements/progress/$id" );
	}

	/**
	 * Put process for achievement. Either create or updates.
	 *
	 * @return array
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
	 */
	public function getCategories( bool $skipCache = false ): array {
		$redis = $this->redisCache->getConnection( self::REDIS_CONNECTION_GROUP );
		$redisKey = $this->makeRedisKey( 'getCategories', self::CACHE_VERSION );

		if ( !$skipCache && $redis ) {
			try {
				$cachedValue = json_decode( $redis->get( $redisKey ), true );
				if ( !empty( $cachedValue ) ) {
					return $this->cheevosClient->parse( $cachedValue, 'categories', CheevosAchievementCategory::class );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		$response = $this->cheevosClient->get( 'achievement_categories/all', [ 'limit' => 0 ] );
		if ( $redis ) {
			try {
				$redis->setEx( $redisKey, self::TTL_5_MIN, json_encode( $response ) );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}
		return $this->cheevosClient->parse( $response, 'categories', CheevosAchievementCategory::class );
	}

	/** Get Category by ID */
	public function getCategory( int $id ): ?CheevosAchievementCategory {
		$redis = $this->redisCache->getConnection( self::REDIS_CONNECTION_GROUP );

		if ( !$redis ) {
			$response = $this->cheevosClient->get( "achievement_category/$id" );
			return $this->cheevosClient->parse( $response, 'categories', CheevosAchievementCategory::class, true );
		}

		$redisKey = $this->makeRedisKey( 'getCategory', self::CACHE_VERSION, $id );
		try {
			$cachedValue = json_decode( $redis->get( $redisKey ), true );
			if ( !empty( $cachedValue ) ) {
				return $this->cheevosClient->parse(
					$cachedValue,
					'categories',
					CheevosAchievementCategory::class,
					true
				);
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		$response = $this->cheevosClient->get( "achievement_category/$id" );
		try {
			$redis->setEx( $redisKey, self::TTL_5_MIN, json_encode( $response ) );
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}
		return $this->cheevosClient->parse( $response, 'categories', CheevosAchievementCategory::class, true );
	}

	/** Delete Category by ID (with optional user_id for user that deleted the category) */
	public function deleteCategory( int $id, int $authorId ): void {
		$this->cheevosClient->delete( "achievement_category/$id", [ 'author_id' => $authorId ] );
	}

	/** Update Category by ID */
	public function updateCategory( int $id, array $body ): array {
		return $this->cheevosClient->put(
			$id ? "achievement_category/$id" : 'achievement_category',
			$body
		);
	}

	/** Create Category */
	public function createCategory( array $body ): array {
		return $this->cheevosClient->put( 'achievement_category', $body );
	}

	/** Call the increment end point on the API. */
	public function increment( array $body ): array {
		return $this->cheevosClient->post( 'increment', $body );
	}

	/** Call increment to check for any unnotified achievement rewards. */
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
	 * 						  volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 * 						  For example, sending a numeric string when the service is expecting an integer will
	 * 						  result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'global' => false, //Set to true to aggregate stats from all sites.
	 * 													(Also causes site_key to be ignored.)
	 *                        -     'stat' => 'example', //Filter by a specific stat name.
	 *                        -     'sort_direction' => 'asc' or 'desc', //If supplied, the result will be sorted
	 * 																	on the stats' count field.
	 *                        -     'start_time' => 'example', //If supplied, only stat deltas after this
	 * 															unix timestamp are considered.
	 *                        -     'end_time' => 'example', //If supplied, only stat deltas before this unix
	 * 															timestamp are considered.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 * @return CheevosStatProgress[]
	 */
	public function getStatProgress( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters(
			$filters,
			[ 'user_id', 'start_time', 'end_time', 'limit', 'offset' ],
			$userIdentity,
			200
		);

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
	 * 						  volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 * 						  For example, sending a numeric string when the service is expecting an integer will
	 * 						  result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 * @return CheevosWikiPointLog[]
	 */
	public function getWikiPointLog( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters(
			$filters,
			[ 'user_id', 'limit', 'offset' ],
			$userIdentity,
			25
		);

		return $this->cheevosClient->parse(
			$this->cheevosClient->get( 'points/user', $parsedFilters ),
			'points',
			CheevosWikiPointLog::class
		);
	}

	public function getUserPointRank( UserIdentity $userIdentity, ?string $siteKey = null ): mixed {
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
	 * 		                    - $filters = [
	 *                          -     'user_id' => 1, //Limit by service user ID.
	 *                          -     'site_key' => 'example', //Limit by site key.
	 *                          -     'stat' => 'example', //Filter by a specific stat name.
	 *                          -     'global' => true, //Overrides site_key to aggregate across all sites.
	 *                          -     'month' => 1601510400, //Limit to one month (starting timestamp).
	 *                          -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                          -     'offset' => 0, //Offset to start from the beginning of the result set.
	 * 		                    - ];
	 * @return CheevosStatMonthlyCount[]
	 */
	public function getStatMonthlyCount( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		$parsedFilters = $this->parseFilters(
			$filters,
			[ 'user_id', 'limit', 'offset' ],
			$userIdentity,
			200
		);
		$response = $this->cheevosClient->get( 'stats/monthly', $parsedFilters );
		return $this->cheevosClient->parse( $response, 'stats', CheevosStatMonthlyCount::class );
	}

	/** Return stats/user_site_count for selected filters. */
	public function getUserSitesCountByStat( UserIdentity $userIdentity, string $statName ): mixed {
		$response = $this->cheevosClient->get(
			'stats/user_sites_count',
			[ 'user_id' => $userIdentity->getId(), 'stat' => $statName ]
		);

		return $this->cheevosClient->parse( $response, 'count' );
	}

	/** Revokes edit points for the provided revision IDs related to the page ID. */
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

	private function parseFilters(
		array $filters,
		array $allowedKeys,
		?UserIdentity $userIdentity,
		?int $defaultLimit = null
	): array {
		$result = [];
		foreach ( $allowedKeys as $key ) {
			if ( isset( $filters[$key] ) ) {
				$filters[$key] = (int)$filters[ $key ];
			}
		}

		if ( $userIdentity !== null ) {
			$result['user_id'] = $userIdentity->getId();
		}

		if ( $defaultLimit !== null ) {
			$result['limit'] = $result['limit'] ?? $defaultLimit;
		}
		return $result;
	}

	// TODO--note on CR - I changed unserialize/servialize to json_decode/encode and bumped chache version
	private function makeRedisKey( ...$parts ): string {
		return 'cheevos:apicache:' . implode( ':', $parts );
	}
}
