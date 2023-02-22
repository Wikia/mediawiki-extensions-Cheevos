<?php
/**
 * Cheevos
 * Cheevos Class
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use MediaWiki\MediaWikiServices;
use RedisCache;
use RedisException;
use User;

class Cheevos {
	/**
	 * Main Request cURL wrapper.
	 *
	 * @param string $type
	 * @param string $path
	 * @param array $data
	 *
	 * @return array
	 */
	private static function request( string $type, string $path, array $data = [] ): array {
		global $wgCheevosHost, $wgCheevosClientId, $wgCheevosEnvoySocketPath;

		if ( empty( $wgCheevosHost ) ) {
			throw new CheevosException( '$wgCheevosHost is not configured.' );
		}
		if ( empty( $wgCheevosClientId ) ) {
			throw new CheevosException( '$wgCheevosClientId is not configured.' );
		}

		$host = $wgCheevosHost;
		$type = strtoupper( $type );

		$url = "{$host}/{$path}";
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Client-ID: ' . $wgCheevosClientId
		];

		$curlOpts = [
			CURLOPT_RETURNTRANSFER		=> 1,
			CURLOPT_URL					=> $url,
			CURLOPT_SSL_VERIFYHOST		=> false,
			CURLOPT_SSL_VERIFYPEER		=> false,
			CURLOPT_CUSTOMREQUEST		=> $type,
			CURLOPT_CONNECTTIMEOUT		=> 1,
			CURLOPT_TIMEOUT				=> 10,
			CURLOPT_ENCODING			=> 'gzip'
		];

		if ( !empty( $wgCheevosEnvoySocketPath ) ) {
			$curlOpts[CURLOPT_UNIX_SOCKET_PATH] = $wgCheevosEnvoySocketPath;
		}

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			$curlOpts
		);
		if ( in_array( $type, [ 'DELETE', 'GET' ] ) && !empty( $data ) ) {
			$url = $url . "/?" . http_build_query( $data );
		} else {
			$postData = json_encode( $data );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $postData );
			$headers[] = 'Content-Length: ' . strlen( $postData );
		}
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		$result = curl_exec( $ch );
		curl_close( $ch );
		$result = json_decode( $result, true );

		return $result;
	}

	/**
	 * Wrapper for Request Function for GET method.
	 *
	 * @param string $path
	 * @param array $data
	 *
	 * @return array
	 */
	private static function get( string $path, $data = [] ): array {
		return self::request( 'GET', $path, $data );
	}

	/**
	 * Wrapper for Request Function for PUT method.
	 *
	 * @param string $path
	 * @param array $data
	 *
	 * @return array
	 */
	private static function put( $path, $data = [] ) {
		return self::request( 'PUT', $path, $data );
	}

	/**
	 * Wrapper for Request Function for POST method.
	 *
	 * @param string $path
	 * @param array $data
	 *
	 * @return array
	 */
	private static function post( $path, $data = [] ) {
		return self::request( 'POST', $path, $data );
	}

	/**
	 * Wrapper for Request Function for DELETE method.
	 *
	 * @param string $path
	 * @param array $data
	 *
	 * @return array
	 */
	private static function delete( $path, $data = [] ) {
		return self::request( 'DELETE', $path, $data );
	}

	/**
	 * Handle the return from a CURL request.
	 *
	 * @param array $return - Return from CURL request.
	 * @param string|null $expected - Expected array key to return.
	 * @param string|null $class - Class to initialize with returned data.
	 * @param bool $single - Only return the first request of an initialized class.
	 *
	 * @return mixed
	 */
	private static function return( $return, $expected = null, $class = null, $single = false ) {
		// Throw Errors if we have API errors.
		if ( $return === null ) {
			throw new CheevosException( 'Cheevos Service Unavailable', 503 );
		}
		if ( isset( $return['code'] ) && $return['code'] !== 200 ) {
			throw new CheevosException( $return['message'], $return['code'] );
		}

		// Handles getting only the data we want
		if ( $expected && isset( $return[$expected] ) ) {
			$return = $return[$expected];
		}

		// Return data as classes instead of arrays.
		if ( $class && class_exists( $class ) ) {
			$holder = [];
			foreach ( $return as $classme ) {
				if ( is_array( $classme ) ) {
					$object = new $class( $classme );
					if ( $object->hasId() ) {
						$holder[$object->getId()] = $object;
					} else {
						$holder[] = $object;
					}
				}
				if ( $single ) {
					break;
				}
			}
			$return = $holder;

			// If we classify things, single will only return the first.
			if ( $single ) {
				reset( $return );
				$return = current( $return );
			}
		}
		return $return;
	}

	/**
	 * Validate data recieved from Cheevos
	 *
	 * @param array $body
	 */
	private static function validateBody( $body ) {
		if ( !is_array( $body ) ) {
			$body = json_decode( $body, true );
			if ( $body === null ) {
				return false;
			} else {
				return $body;
			}
		} else {
			return $body;
		}
	}

	/**
	 * Invalid API Cache
	 *
	 * @return bool Success
	 */
	public static function invalidateCache() {
		global $wgRedisServers;

		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )->getConnection( 'cache' );

		if ( $redis === false ) {
			return false;
		}

		$redisKey = 'cheevos:apicache:*';
		$prefix = $wgRedisServers['cache']['options']['prefix'] ?? "";

		try {
			$cache = $redis->getKeys( $redisKey );
			foreach ( $cache as $key ) {
				// remove prefix if exists, because weird.
				$key = str_replace( $prefix . "cheevos", "cheevos", $key );
				$redis->del( $key );
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			return false;
		}

		return true;
	}

	/**
	 * Returns all relationships for a user by global id
	 *
	 * @param User $user
	 *
	 * @return array
	 */
	public static function getFriends( User $user ): array {
		$globalId = self::getUserIdForService( $user );
		$friendTypes = self::return( self::get( "friends/{$globalId}" ) );
		if ( is_array( $friendTypes ) ) {
			foreach ( $friendTypes as $category => $serviceUserIds ) {
				if ( is_array( $serviceUserIds ) ) {
					foreach ( $serviceUserIds as $key => $serviceUserId ) {
						$user = self::getUserForServiceUserId( $serviceUserId );
						if ( !$user ) {
							unset( $friendTypes[$category][$key] );
						} else {
							$friendTypes[$category][$key] = $user;
						}
					}
				}
			}
		} else {
			$friendTypes = [];
		}

		return $friendTypes;
	}

	/**
	 * Return friendship status
	 *
	 * @param User $from
	 * @param User $to
	 *
	 * @return array
	 */
	public static function getFriendStatus( User $from, User $to ): mixed {
		$fromGlobalId = self::getUserIdForService( $from );
		$toGlobalId = self::getUserIdForService( $to );
		$return = self::get( "friends/{$fromGlobalId}/{$toGlobalId}" );
		return self::return( $return );
	}

	/**
	 * Create a frienship request
	 *
	 * @param User $from
	 * @param User $to
	 *
	 * @return array
	 */
	public static function createFriendRequest( User $from, User $to ) {
		$fromGlobalId = self::getUserIdForService( $from );
		$toGlobalId = self::getUserIdForService( $to );
		$return = self::put( "friends/{$fromGlobalId}/{$toGlobalId}" );
		return self::return( $return );
	}

	/**
	 * Accept a friendship request (by creating a request the oposite direction!)
	 *
	 * @param User $from
	 * @param User $to
	 *
	 * @return array
	 */
	public static function acceptFriendRequest( User $from, User $to ) {
		return self::createFriendRequest( $from, $to );
	}

	/**
	 * Remove a friendship association between 2 users.
	 *
	 * @param User $from
	 * @param User $to
	 *
	 * @return array
	 */
	public static function removeFriend( User $from, User $to ) {
		$fromGlobalId = self::getUserIdForService( $from );
		$toGlobalId = self::getUserIdForService( $to );
		$return = self::delete( "friends/{$fromGlobalId}/{$toGlobalId}" );
		return self::return( $return );
	}

	/**
	 * Cancel friend request by removing assosiation.
	 *
	 * @param User $from
	 * @param User $to
	 *
	 * @return array
	 */
	public static function cancelFriendRequest( User $from, User $to ) {
		return self::removeFriend( $from, $to );
	}

	/**
	 * Get all achievements with caching.
	 *
	 * @param string|null $siteKey MD5 Hash Site Key
	 *
	 * @return mixed Ouput of self::return.
	 */
	public static function getAchievements( $siteKey = null ) {
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )->getConnection( 'cache' );
		$cache = false;
		$redisKey = 'cheevos:apicache:getAchievements:' . ( $siteKey ? $siteKey : 'all' );

		if ( $redis !== false ) {
			try {
				$cache = $redis->get( $redisKey );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		$return = unserialize( $cache, [ false ] );
		if ( !$cache || !$return ) {
			$return = self::get(
				'achievements/all',
				[
					'site_key' => $siteKey,
					'limit'	=> 0
				]
			);

			try {
				if ( $redis !== false && isset( $return['achievements'] ) ) {
					$redis->setEx( $redisKey, 300, serialize( $return ) );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		return self::return( $return, 'achievements', '\Cheevos\CheevosAchievement' );
	}

	/**
	 * Get achievement by database ID with caching.
	 *
	 * @param int $id Achievement ID
	 *
	 * @return mixed Ouput of self::return.
	 */
	public static function getAchievement( int $id ): mixed {
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )
			->getConnection( 'cache' );
		$cache = false;
		$redisKey = 'cheevos:apicache:getAchievement:' . $id;

		if ( $redis !== false ) {
			try {
				$cache = $redis->get( $redisKey );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		if ( !$cache || !unserialize( $cache ) ) {
			$return = self::get( "achievement/{$id}" );
			try {
				if ( $redis !== false ) {
					$redis->setEx( $redisKey, 300, serialize( $return ) );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		} else {
			$return = unserialize( $cache );
		}

		// The return function expects an array of results.
		$return = [ $return ];
		return self::return( $return, 'achievements', '\Cheevos\CheevosAchievement', true );
	}

	/**
	 * Soft delete an achievement from the service.
	 *
	 * @param int $id Achievement ID
	 * @param int $globalId Global ID
	 *
	 * @return mixed Array
	 */
	public static function deleteAchievement( int $id, int $globalId ): mixed {
		$return = self::delete(
			"achievement/{$id}",
			[
				"author_id" => $globalId
			]
		);
		return self::return( $return );
	}

	/**
	 * PUT Achievement into Cheevos
	 *
	 * @param array $body
	 * @param int|null $id
	 *
	 * @return false
	 */
	private static function putAchievement( array $body, int $id = null ): mixed {
		$body = self::validateBody( $body );
		if ( !$body ) {
			return false;
		}

		$path = ( $id ) ? "achievement/{$id}" : "achievement";
		$return = self::put( $path, $body );
		return self::return( $return );
	}

	/**
	 * Update an existing achievement on the service.
	 *
	 * @param int $id Achievement ID
	 * @param array $body
	 *
	 * @return void
	 */
	public static function updateAchievement( int $id, array $body ) {
		return self::putAchievement( $body, $id );
	}

	/**
	 * Create Achievement
	 *
	 * @param array $body
	 *
	 * @return void
	 */
	public static function createAchievement( array $body ) {
		return self::putAchievement( $body );
	}

	/**
	 * Get all categories.
	 *
	 * @param bool $skipCache [Optional] Skip pulling data from the local cache. Will still update the local cache.
	 *
	 * @return mixed
	 */
	public static function getCategories( bool $skipCache = false ): mixed {
		$cache = false;
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )
			->getConnection( 'cache' );
		$redisKey = 'cheevos:apicache:getCategories';

		if ( !$skipCache && $redis !== false ) {
			try {
				$cache = $redis->get( $redisKey );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		if ( !$cache || !unserialize( $cache ) ) {
			$return = self::get(
				'achievement_categories/all',
				[
					'limit'	=> 0
				]
			);
			try {
				if ( $redis !== false ) {
					$redis->setEx( $redisKey, 300, serialize( $return ) );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		} else {
			$return = unserialize( $cache );
		}

		return self::return( $return, 'categories', '\Cheevos\CheevosAchievementCategory' );
	}

	/**
	 * Get Category by ID
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public static function getCategory( int $id ): mixed {
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )
			->getConnection( 'cache' );
		$cache = false;
		$redisKey = 'cheevos:apicache:getCategory:' . $id;

		if ( $redis !== false ) {
			try {
				$cache = $redis->get( $redisKey );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		if ( !$cache || !unserialize( $cache ) ) {
			$return = self::get( "achievement_category/{$id}" );
			try {
				if ( $redis !== false ) {
					$redis->setEx( $redisKey, 300, serialize( $return ) );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		} else {
			$return = unserialize( $cache );
		}

		// return expect array of results. fake it.
		$return = [ $return ];
		return self::return( $return, 'categories', '\Cheevos\CheevosAchievementCategory', true );
	}

	/**
	 * Delete Category by ID (with optional user_id for user that deleted the category)
	 *
	 * @param int $id
	 * @param int $userId
	 *
	 * @return void
	 */
	public static function deleteCategory( int $id, int $userId = 0 ) {
		$return = self::delete( "achievement_category/{$id}", [
			"author_id" => $userId
		] );
		return self::return( $return );
	}

	/**
	 * Create a Category
	 *
	 * @param array $body
	 * @param int|null $id
	 *
	 * @return mixed
	 */
	private static function putCategory( array $body, int $id = null ): mixed {
		$body = self::validateBody( $body );
		if ( !$body ) {
			return false;
		}

		$path = ( $id ) ? "achievement_category/{$id}" : "achievement_category";
		$return = self::put( $path, $body );
		return self::return( $return );
	}

	/**
	 * Update Category by ID
	 *
	 * @param int $id
	 * @param array $body
	 */
	public static function updateCategory( int $id, array $body ) {
		return self::putCategory( $body, $id );
	}

	/**
	 * Create Category
	 *
	 * @param array $body
	 */
	public static function createCategory( array $body ) {
		return self::putCategory( $body );
	}

	/**
	 * Call the increment end point on the API.
	 *
	 * @param array $body Post Request Body to be converted into JSON.
	 *
	 * @return mixed Array of return status including earned achievements or false on error.
	 */
	public static function increment( array $body ): mixed {
		$body = self::validateBody( $body );
		if ( !$body ) {
			return false;
		}

		$return = self::post( 'increment', $body );

		return self::return( $return );
	}

	/**
	 * Call increment to check for any unnotified achievement rewards.
	 *
	 * @param int $globalId
	 * @param string $siteKey
	 * @param bool $forceRecalculate
	 */
	public static function checkUnnotified( int $globalId, string $siteKey, bool $forceRecalculate = false ) {
		$globalId = intval( $globalId );
		if ( empty( $globalId ) || empty( $siteKey ) ) {
			return null;
		}

		$data = [
			'user_id' => $globalId,
			'site_key' => $siteKey,
			'recalculate' => $forceRecalculate,
			'deltas' => []
		];
		return self::increment( $data );
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
	 * @param User|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return mixed
	 */
	public static function getStatProgress( array $filters = [], ?User $user = null ): mixed {
		if ( $user !== null ) {
			$filters['user_id'] = self::getUserIdForService( $user );
		}

		foreach ( [ 'user_id', 'start_time', 'end_time', 'limit', 'offset' ] as $key ) {
			if ( isset( $filter[$key] ) && !is_int( $filter[$key] ) ) {
				$filter[$key] = intval( $filter[$key] );
			}
		}
		$filters['limit'] = ( $filters['limit'] ?? 200 );

		$return = self::get( 'stats', $filters );

		return self::return( $return, 'stats', '\Cheevos\CheevosStatProgress' );
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
	 * @param User|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return mixed
	 */
	public static function getWikiPointLog( array $filters = [], ?User $user = null ): mixed {
		if ( $user !== null ) {
			$filters['user_id'] = self::getUserIdForService( $user );
		}

		foreach ( [ 'user_id', 'limit', 'offset' ] as $key ) {
			if ( isset( $filter[$key] ) && !is_int( $filter[$key] ) ) {
				$filter[$key] = intval( $filter[$key] );
			}
		}
		$filters['limit'] = ( $filters['limit'] ?? 25 );

		$return = self::get( 'points/user', $filters );

		return self::return( $return, 'points', '\Cheevos\CheevosWikiPointLog' );
	}

	/**
	 * Return stats/user_site_count for selected filters.
	 *
	 * @param User $user Global User ID
	 * @param string|null $siteKey [Optional] Filter by site key.
	 *
	 * @return mixed
	 */
	public static function getUserPointRank( User $user, ?string $siteKey = null ): mixed {
		$return = self::get(
			'points/user_rank',
			[
				'user_id'	=> self::getUserIdForService( $user ),
				'site_key'	=> $siteKey
			]
		);

		return self::return( $return, 'rank' );
	}

	/**
	 * Return StatDailyCount for selected filters.
	 *
	 * @param array	$filters Limit Filters - All filters are optional and can omitted from the array.
	 *                       This is an array since the amount of filter parameters is expected to be reasonably
	 * 						 volatile over the life span of the product.
	 *                       This function does minimum validation of the filters.
	 * 						 For example, sending a numeric string when the service is expecting an integer will
	 * 						 result in an exception being thrown.
	 *                       - $filters = [
	 *                       -     'site_key' => 'example', //Limit by site key.
	 *                       -     'stat' => 'example', //Filter by a specific stat name.
	 *                       -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                       -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                       - ];
	 *
	 * @return mixed
	 */
	public static function getStatDailyCount( array $filters = [] ): mixed {
		foreach ( [ 'limit', 'offset' ] as $key ) {
			if ( isset( $filter[$key] ) && !is_int( $filter[$key] ) ) {
				$filter[$key] = intval( $filter[$key] );
			}
		}
		$filters['limit'] = ( $filters['limit'] ?? 200 );

		$return = self::get( 'stats/daily', $filters );

		return self::return( $return, 'stats', '\Cheevos\CheevosStatDailyCount' );
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
	 * @param User|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return mixed
	 */
	public static function getStatMonthlyCount( array $filters = [], ?User $user = null ): mixed {
		if ( $user !== null ) {
			$filters['user_id'] = self::getUserIdForService( $user );
		}

		foreach ( [ 'user_id', 'limit', 'offset' ] as $key ) {
			if ( isset( $filter[$key] ) && !is_int( $filter[$key] ) ) {
				$filter[$key] = intval( $filter[$key] );
			}
		}
		$filters['limit'] = ( $filters['limit'] ?? 200 );

		$return = self::get( 'stats/monthly', $filters );

		return self::return( $return, 'stats', '\Cheevos\CheevosStatMonthlyCount' );
	}

	/**
	 * Return stats/user_site_count for selected filters.
	 *
	 * @param User $user User
	 * @param string $stat Filter by stat name (Example: article_edit to get number of Wikis Edited)
	 *
	 * @return mixed
	 */
	public static function getUserSitesCountByStat( User $user, string $stat ): mixed {
		$return = self::get(
			'stats/user_sites_count',
			[
				'user_id'	=> self::getUserIdForService( $user ),
				'stat'		=> $stat
			]
		);

		return self::return( $return, 'count' );
	}

	/**
	 * Get achievement status for an user.
	 *
	 * @param int $globalId Global User ID
	 * @param string|null $siteKey Site Key
	 *
	 * @return mixed
	 */
	public static function getAchievementStatus( int $globalId, string $siteKey = null ): mixed {
		$return = self::get(
			'achievements/status',
			[
				'limit'	=> 0,
				'user_id' => intval( $globalId ),
				'site_key' => $siteKey
			]
		);

		return self::return( $return, 'status', '\Cheevos\CheevosAchievementStatus' );
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
	 * @param User|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return mixed
	 */
	public static function getAchievementProgress( array $filters = [], ?User $user = null ): mixed {
		if ( $user !== null ) {
			$filters['user_id'] = self::getUserIdForService( $user );
		}

		foreach ( [ 'user_id', 'achievement_id', 'category_id', 'limit', 'offset' ] as $key ) {
			if ( isset( $filter[$key] ) && !is_int( $filter[$key] ) ) {
				$filter[$key] = intval( $filter[$key] );
			}
		}

		$return = self::get( 'achievements/progress', $filters );

		return self::return( $return, 'progress', '\Cheevos\CheevosAchievementProgress' );
	}

	/**
	 * Get progress for an achievement
	 *
	 * @return mixed
	 */
	public static function getProgressCount( $site_key = null, $achievement_id = null ) {
		$return = self::get( "achievements/progress/count", [
			"achievement_id" => $achievement_id,
			"site_key"	=> $site_key
		] ); // return expect array of results. fake it.
		return self::return( $return );
	}

	public static function getProgressTop( $site_key = null, $ignore_users = [], $achievement_id = null, $limit = 1 ) {
		$return = self::get( "achievements/progress/top", [
			"ignore_users" => implode( ",", $ignore_users ),
			"site_key"	=> $site_key,
			"achievement_id" => $achievement_id,
			"limit"	=> $limit
		] ); // return expect array of results. fake it.
		return self::return( $return );
	}

	/**
	 * Get process for achievement
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public static function getProgress( $id ): mixed {
		$return = [ self::get( "achievements/progress/{$id}" ) ]; // return expect array of results. fake it.
		return self::return( $return, 'progress', '\Cheevos\CheevosAchievementProgress', true );
	}

	/**
	 * Delete progress towards an achievement.
	 *
	 * @param int $id Progress ID
	 *
	 * @return mixed
	 */
	public static function deleteProgress( int $id ) {
		$return = self::delete( "achievements/progress/{$id}" );
		return self::return( $return );
	}

	/**
	 * Put process for achievement. Either create or updates.
	 *
	 * @param array $body
	 * @param int|null $id
	 *
	 * @return false
	 */
	public static function putProgress( $body, $id = null ) {
		$body = self::validateBody( $body );
		if ( !$body ) {
			return false;
		}

		$path = ( $id ) ? "achievements/progress/{$id}" : "achievements/progress";
		$return = self::put( $path, $body );
		return self::return( $return );
	}

	/**
	 * Update progress
	 *
	 * @param int $id
	 * @param array $body
	 */
	public static function updateProgress( $id, $body ) {
		return self::putProgress( $body, $id );
	}

	/**
	 * Create Progress
	 *
	 * @param array $body
	 */
	public static function createProgress( $body ) {
		return self::putProgress( $body );
	}

	/**
	 * Return user_options/{id} for selected filters.
	 *
	 * @param int $globalId Global User ID
	 *
	 * @return mixed
	 */
	public static function getUserOptions( $globalId ) {
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )
			->getConnection( 'cache' );
		$cache = false;
		$redisKey = 'cheevos:apicache:useroptions:' . $globalId;

		if ( $redis !== false ) {
			try {
				$cache = $redis->get( $redisKey );
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		}

		if ( !$cache || !unserialize( $cache ) ) {
			$return = self::get( 'user_options/' . intval( $globalId ) );
			try {
				if ( $redis !== false ) {
					$redis->setEx( $redisKey, 86400, serialize( $return ) );
				}
			} catch ( RedisException $e ) {
				wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
			}
		} else {
			$return = unserialize( $cache );
		}

		return self::return( $return, 'useroptions' );
	}

	/**
	 * Put user options up to Cheevos.
	 *
	 * @param array $body POST Body
	 *
	 * @return mixed
	 */
	public static function setUserOptions( array $body ): mixed {
		$body = self::validateBody( $body );
		if ( !$body ) {
			return false;
		}

		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )
			->getConnection( 'cache' );
		$redisKey = 'cheevos:apicache:useroptions:' . $body['user_id'];
		try {
			if ( $redis !== false ) {
				$redis->del( $redisKey );
			}
		} catch ( RedisException $e ) {
			wfDebug( __METHOD__ . ": Caught RedisException - " . $e->getMessage() );
		}

		$path = "user_options/" . $body['user_id'];
		$return = self::put( $path, $body );
		return self::return( $return );
	}

	/**
	 * Revokes edit points for the provided revision IDs related to the page ID.
	 *
	 * @param int $pageId Page ID
	 * @param array $revisionIds Revision IDs
	 * @param string $siteKey Site Key
	 *
	 * @return mixed Array
	 */
	public static function revokeEditPoints( int $pageId, array $revisionIds, string $siteKey ): mixed {
		$revisionIds = array_map( 'intval', $revisionIds );
		$return = self::post(
			"points/revoke_revisions",
			[
				'page_id'		=> intval( $pageId ),
				'revision_ids'	=> $revisionIds,
				'site_key'		=> $siteKey
			]
		);
		return self::return( $return );
	}

	/**
	 * Get the user ID for this user in the Cheevos service.
	 *
	 * @param User $user
	 *
	 * @return int
	 */
	public static function getUserIdForService( User $user ): int {
		return $user->getId();
	}

	/**
	 * Get a local User object for this user ID in the Cheevos service.
	 *
	 * @param int $serviceUserId
	 *
	 * @return User|null
	 */
	public static function getUserForServiceUserId( int $serviceUserId ): ?User {
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $serviceUserId );
	}
}
