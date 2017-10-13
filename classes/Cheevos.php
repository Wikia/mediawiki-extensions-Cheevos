<?php
/**
 * Cheevos
 * Cheevos Class
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Achievements
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class Cheevos {
	/**
	 * Main Request cURL wrapper.
	 *
	 * @param string $type
	 * @param string $path
	 * @param array $data
	 * @return void
	 */
	private static function request($type, $path, $data = []) {
		global $wgCheevosHost, $wgCheevosClientId;

		if (empty($wgCheevosHost)) {
			throw new CheevosException('$wgCheevosHost is not configured.');
		}
		if (empty($wgCheevosClientId)) {
			throw new CheevosException('$wgCheevosClientId is not configured.');
		}

		$host = $wgCheevosHost;
		$type = strtoupper($type);

		$url = "{$host}/{$path}";
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Client-ID: '.$wgCheevosClientId
		];


		$ch = curl_init();
		curl_setopt_array(
			$ch,
			[
				CURLOPT_RETURNTRANSFER		=> 1,
				CURLOPT_URL					=> $url,
				CURLOPT_SSL_VERIFYHOST		=> false,
				CURLOPT_SSL_VERIFYPEER		=> false,
				CURLOPT_CUSTOMREQUEST		=> $type,
				CURLOPT_CONNECTTIMEOUT		=> 1,
				CURLOPT_TIMEOUT				=> 6
			]
		);
		if (in_array($type, ['DELETE', 'GET']) && !empty($data)) {
			$url = $url . "/?" . http_build_query($data);
		} else {
			$postData = json_encode($data);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			$headers[] = 'Content-Length: ' . strlen($postData);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		try {
			$result = curl_exec($ch);
			curl_close($ch);
			$result = json_decode($result, true);
		} catch (Exception $e) {
			// don't fail hard on hard failures.
			$result = null;
		}

		return $result;
	}

	/**
	 * Wrapper for Request Function for GET method.
	 *
	 * @param string $path
	 * @param array $data
	 * @return void
	 */
	private static function get($path, $data = []) {
		return self::request('GET', $path, $data);
	}

	/**
	 * Wrapper for Request Function for PUT method.
	 *
	 * @param string $path
	 * @param array $data
	 * @return void
	 */
	private static function put($path, $data = []) {
		return self::request('PUT', $path, $data);
	}

	/**
	 * Wrapper for Request Function for POST method.
	 *
	 * @param string $path
	 * @param array $data
	 * @return void
	 */
	private static function post($path, $data = []) {
		return self::request('POST', $path, $data);
	}

	/**
	 * Wrapper for Request Function for DELETE method.
	 *
	 * @param string $path
	 * @param array $data
	 * @return void
	 */
	private static function delete($path, $data = []) {
		return self::request('DELETE', $path, $data);
	}

	/**
	 * Handle the return from a CURL request.
	 *
	 * @access	private
	 * @param	array	$return - Return from CURL request.
	 * @param	string	$expected - Expected array key to return.
	 * @param	string	$class - Class to initialize with returned data.
	 * @param	boolean	$single - Only return the first request of an initialized class.
	 * @return	mixed
	 */
	private static function return($return, $expected = null, $class = null, $single = false) {
		// Throw Errors if we have API errors.
		if ($return === null || $return === false) {
			throw new CheevosException('Cheevos Service Unavailable', 503);
		}
		if (isset($return['code']) && $return['code'] !== 200) {
			throw new CheevosException($return['message'], $return['code']);
		}

		// Handles getting only the data we want
		if ($expected && isset($return[$expected])) {
			$return = $return[$expected];
		}

		// Return data as classes instead of arrays.
		if ($class && class_exists($class)) {
			$holder = [];
			foreach ($return as $classme) {
				if (is_array($classme)) {
					$object = new $class($classme);
					if ($object->hasId()) {
						$holder[$object->getId()] = $object;
					} else {
						$holder[] = $object;
					}
				}
				if ($single) {
					break;
				}
			}
			$return = $holder;

			// If we classify things, single will only return the first.
			if ($single) {
				reset($return);
				$return = current($return);
			}
		}
		return $return;
	}

	/**
	 * Validate data recieved from Cheevos
	 *
	 * @param array $body
	 * @return void
	 */
	static private function validateBody($body) {
		if (!is_array($body)) {
			$body = json_decode($body, 1);
			if (is_null($body)) {
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
	 * @access	public
	 * @return	boolean	Success
	 */
	static public function invalidateCache() {
		global $wgRedisServers;

		$redis = \RedisCache::getClient('cache');

		if ($redis === false) {
			return false;
		}

		$cache = false;
		$redisKey = 'cheevos:apicache:*';
		$prefix = isset( $wgRedisServers['cache']['options']['prefix'] ) ?  $wgRedisServers['cache']['options']['prefix'] : "";

		try {
			$cache = $redis->getKeys($redisKey);
			foreach ($cache as $key) {
				$key = str_replace($prefix."cheevos", "cheevos", $key); // remove prefix if exists, because weird.
				$redis->del($key);
			}
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Returns all relationships for a user by global id
	 *
	 * @param int $globalId
	 * @return array
	 */
	public static function getFriends($globalId) {
		$return = self::get("friends/{$globalId}");
		return self::return($return);
	}

	/**
	 * Return friendship status
	 *
	 * @param from user, int $user1
	 * @param to user, int $user2
	 * @return array
	 */
	public static function getFriendStatus($user1, $user2) {
		$return = self::get("friends/{$user1}/{$user2}");
		return self::return($return);
	}

	/**
	 * Create a frienship request
	 *
	 * @param from user, int $user1
	 * @param to user, int $user2
	 * @return array
	 */
	public static function createFriendRequest($user1, $user2) {
		$return = self::put("friends/{$user1}/{$user2}");
		return self::return($return);
	}

	/**
	 * Accept a friendship request (by creating a request the oposite direction!)
	 *
	 * @param from user, int $user1
	 * @param to user, int $user2
	 * @return array
	 */
	public static function acceptFriendRequest($user1, $user2) {
		return self::createFriendRequest($user2, $user1);
	}

	/**
	 * Remove a friendship association between 2 users.
	 *
	 * @param from user, int $user1
	 * @param to user, int $user2
	 * @return array
	 */
	public static function removeFriend($user1, $user2) {
		$return = self::delete("friends/{$user1}/{$user2}");
		return self::return($return);
	}

	/**
	 * Cancel friend request by removing assosiation.
	 *
	 * @param from user, int $user1
	 * @param to user, int $user2
	 * @return array
	 */
	public static function cancelFriendRequest($user1, $user2) {
		return self::removeFriend($user1, $user2);
	}

	/**
	 * Get all achievements with caching.
	 *
	 * @access	public
	 * @param 	string	MD5 Hash Site Key
	 * @return	mixed	Ouput of self::return.
	 */
	public static function getAchievements($siteKey = null) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getAchievements:' . ( $siteKey ? $siteKey : 'all' );

		if ($redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		$return = unserialize($cache, [false]);
		if (!$cache || !$return) {
			$return = self::get(
				'achievements/all',
				[
					'site_key' => $siteKey,
					'limit'	=> 0
				]
			);

			try {
				if ($redis !== false && isset($return['achievements'])) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		return self::return($return, 'achievements', '\Cheevos\CheevosAchievement');
	}

	/**
	 * Get achievement by database ID with caching.
	 *
	 * @access	public
	 * @param 	integer	Achievement ID
	 * @return	mixed	Ouput of self::return.
	 */
	public static function getAchievement($id) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getAchievement:' . $id;

		if ($redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get("achievement/{$id}");
			try {
				if ($redis !== false) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		$return = [ $return ]; //The return function expects an array of results.
		return self::return($return, 'achievements', '\Cheevos\CheevosAchievement', true);
	}

	/**
	 * Soft delete an achievement from the service.
	 *
	 * @access	public
	 * @param	integer	Achievement ID
	 * @param	integer	Global ID
	 * @return	mixed	Array
	 */
	public static function deleteAchievement($id, $globalId) {
		$return = self::delete(
			"achievement/{$id}",
			[
				"author_id" => intval($globalId)
			]
		);
		return self::return($return);
	}

	/**
	 * PUT Achievement into Cheevos
	 *
	 * @param array $body
	 * @param int $id
	 * @return void
	 */
	private static function putAchievement($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) {
			return false;
		}

		$path = ($id) ? "achievement/{$id}" : "achievement";
		$return = self::put($path, $body);
		return self::return($return);
	}

	/**
	 * Update an existing achievement on the service.
	 *
	 * @access	public
	 * @param	integer	Achievement ID
	 * @param	array	$body
	 * @return	void
	 */
	public static function updateAchievement($id, $body) {
		return self::putAchievement($body, $id);
	}

	/**
	 * Create Achievement
	 *
	 * @param array $body
	 * @return void
	 */
	public static function createAchievement($body) {
		return self::putAchievement($body);
	}

	/**
	 * Get all categories.
	 *
	 * @acess	public
	 * @param	boolean	[Optional] Skip pulling data from the local cache.  Will still update the local cache.
	 * @return	void
	 */
	static public function getCategories($skipCache = false) {
		$cache = false;
		$redis = \RedisCache::getClient('cache');
		$redisKey = 'cheevos:apicache:getCategories';

		if (!$skipCache && $redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get(
				'achievement_categories/all',
				[
					'limit'	=> 0
				]
			);
			try {
				if ($redis !== false) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		return self::return($return, 'categories', '\Cheevos\CheevosAchievementCategory');
	}

	/**
	 * Get Category by ID
	 *
	 * @param int $id
	 * @return void
	 */
	public static function getCategory($id) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getCategory:' . $id;

		if ($redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get("achievement_category/{$id}");
			try {
				if ($redis !== false) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		$return = [ $return ]; // return expect array of results. fake it.
		return self::return($return, 'categories', '\Cheevos\CheevosAchievementCategory', true);
	}

	/**
	 * Delete Category by ID (with optional user_id for user that deleted the category)
	 *
	 * @param int $id
	 * @param int $userId
	 * @return void
	 */
	public static function deleteCategory($id, $userId = 0) {
		$return = self::delete("achievement_category/{$id}", [
			"author_id" => $userId
		]);
		return self::return($return);;
	}

	/**
	 * Create a Category
	 *
	 * @param array $body
	 * @param int $id
	 * @return void
	 */
	private static function putCategory($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievement_category/{$id}" : "achievement_category";
		$return = self::put($path, $body);
		return self::return($return);
	}

	/**
	 * Update Category by ID
	 *
	 * @param int $id
	 * @param array $body
	 * @return void
	 */
	public static function updateCategory($id, $body) {
		return self::putCategory($body, $id);
	}

	/**
	 * Create Category
	 *
	 * @param array $body
	 * @return void
	 */
	public static function createCategory($body) {
		return self::putCategory($body);
	}

	/**
	 * Call the increment end point on the API.
	 *
	 * @acecss	public
	 * @param	array	Post Request Body to be converted into JSON.
	 * @return	mixed	Array of return status including earned achievements or false on error.
	 */
	public static function increment($body) {
		$body = self::validateBody($body);
		if (!$body) {
			return false;
		}

		$return = self::post('increment', $body);

		return self::return($return);
	}

	/**
	 * Call increment to check for any unnotified achievement rewards.
	 *
	 * @param int $globalId
	 * @param string $siteKey
	 * @param boolean $forceRecalculate
	 * @return void
	 */
	static public function checkUnnotified($globalId, $siteKey, $forceRecalculate = false) {
		$globalId = intval($globalId);
		if (empty($globalId) || empty($siteKey)) {
			return;
		}

		$data = [
			'user_id' => $globalId,
			'site_key' => $siteKey,
			'recalculate' => $forceRecalculate,
			'deltas' => []
		];
		return self::increment($data);
	}

	/**
	 * Return StatProgress for selected filters.
	 *
	 * @access	public
	 * @param	array	Limit Filters - All filters are optional and can omitted from the array.
	 * This is an array since the amount of filter parameters is expected to be reasonably volatile over the life span of the product.
	 * This function does minimum validation of the filters.  For example, sending a numeric string when the service is expecting an integer will result in an exception being thrown.
	 * 		$filters = [
	 * 			'user_id'			=> 0, //Limit by global user ID.
	 * 			'site_key'			=> 'example', //Limit by site key.
	 * 			'global'			=> false, //Set to true to aggregate stats from all sites.(Also causes site_key to be ignored.)
	 * 			'stat'				=> 'example', //Filter by a specific stat name.
	 * 			'sort_direction'	=> 'asc' or 'desc', //If supplied, the result will be sorted on the stats' count field.
	 * 			'start_time'		=> 'example', //If supplied, only stat deltas after this unix timestamp are considered.
	 * 			'end_time'			=> 'example', //If supplied, only stat deltas before this unix timestamp are considered.
	 * 			'limit'				=> 200, //Maximum number of results.  Defaults to 200.
	 * 			'offset'			=> 0, //Offset to start from the beginning of the result set.
	 * 		];
	 * @return	mixed
	 */
	public static function getStatProgress($filters = []) {
		foreach (['user_id', 'start_time', 'end_time', 'limit', 'offset'] as $key) {
			if (isset($filter[$key]) && !is_int($filter[$key])) {
				$filter[$key] = intval($filter[$key]);
			}
		}
		$filters['limit'] = (isset($filters['limit']) ? $filters['limit'] : 200);

		$return = self::get('stats', $filters);

		return self::return($return, 'stats', '\Cheevos\CheevosStatProgress');
	}

	/**
	 * Return WikiPointLog for selected filters.
	 *
	 * @access	public
	 * @param	array	Limit Filters - All filters are optional and can omitted from the array.
	 * This is an array since the amount of filter parameters is expected to be reasonably volatile over the life span of the product.
	 * This function does minimum validation of the filters.  For example, sending a numeric string when the service is expecting an integer will result in an exception being thrown.
	 * 		$filters = [
	 * 			'user_id'			=> 0, //Limit by global user ID.
	 * 			'site_key'			=> 'example', //Limit by site key.
	 * 			'limit'				=> 200, //Maximum number of results.  Defaults to 200.
	 * 			'offset'			=> 0, //Offset to start from the beginning of the result set.
	 * 		];
	 * @return	mixed
	 */
	public static function getWikiPointLog($filters = []) {
		foreach (['user_id', 'limit', 'offset'] as $key) {
			if (isset($filter[$key]) && !is_int($filter[$key])) {
				$filter[$key] = intval($filter[$key]);
			}
		}
		$filters['limit'] = (isset($filters['limit']) ? $filters['limit'] : 25);

		$return = self::get('points/user', $filters);

		return self::return($return, 'points', '\Cheevos\CheevosWikiPointLog');
	}

	/**
	 * Return stats/user_site_count for selected filters.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	string	[Optional] Filter by site key.
	 * @return	mixed
	 */
	public static function getUserPointRank($globalId, $siteKey = null) {
		$return = self::get(
			'points/user_rank',
			[
				'user_id'	=> $globalId,
				'site_key'	=> $siteKey
			]
		);

		return self::return($return, 'rank');
	}

	/**
	 * Return StatMonthlyCount for selected filters.
	 *
	 * @access	public
	 * @param	array	Limit Filters - All filters are optional and can omitted from the array.
	 * This is an array since the amount of filter parameters is expected to be reasonably volatile over the life span of the product.
	 * This function does minimum validation of the filters.  For example, sending a numeric string when the service is expecting an integer will result in an exception being thrown.
	 * 		$filters = [
	 * 			'site_key'	=> 'example', //Limit by site key.
	 * 			'stat'		=> 'example', //Filter by a specific stat name.
	 * 			'limit'		=> 200, //Maximum number of results.  Defaults to 200.
	 * 			'offset'	=> 0, //Offset to start from the beginning of the result set.
	 * 		];
	 * @return	mixed
	 */
	public static function getStatMonthlyCount($filters = []) {
		foreach (['limit', 'offset'] as $key) {
			if (isset($filter[$key]) && !is_int($filter[$key])) {
				$filter[$key] = intval($filter[$key]);
			}
		}
		$filters['limit'] = (isset($filters['limit']) ? $filters['limit'] : 200);

		$return = self::get('stats/monthly', $filters);

		return self::return($return, 'stats', '\Cheevos\CheevosStatMonthlyCount');
	}

	/**
	 * Return stats/user_site_count for selected filters.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	string	Filter by stat name (Example: article_edit to get number of Wikis Edited)
	 * @return	mixed
	 */
	public static function getUserSitesCountByStat($globalId, $stat) {
		$return = self::get(
			'stats/user_sites_count',
			[
				'user_id'	=> $globalId,
				'stat'		=> $stat
			]
		);

		return self::return($return, 'count');
	}

	/**
	 * Get achievement status for an user.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	string	Site Key - From DynamicSettings
	 * @return	mixed
	 */
	public static function getAchievementStatus($globalId, $siteKey = null) {
		$return = self::get(
			'achievements/status',
			[
				'limit'	=> 0,
				'user_id' => intval($globalId),
				'site_key' => $siteKey
			]
		);

		return self::return($return, 'status', '\Cheevos\CheevosAchievementStatus');
	}

	/**
	 * Return AchievementProgress for selected filters.
	 *
	 * @access	public
	 * @param	array	Limit Filters - All filters are optional and can omitted from the array.
	 * 		$filters = [
	 * 			'site_key'			=> 'example', //Limit by site key.
	 * 			'achievement_id'	=> 0, //Limit by achievement ID.
	 * 			'user_id'			=> 0, //Limit by global user ID.
	 * 			'category_id'		=> 0, //Limit by category ID.
	 * 			'earned'			=> false, //Only get progress for earned achievements.
	 * 			'limit'				=> 100, //Maximum number of results.
	 * 			'offset'			=> 0, //Offset to start from the beginning of the result set.
	 * 		];
	 * @return	mixed
	 */
	public static function getAchievementProgress($filters = []) {
		foreach (['user_id', 'achievement_id', 'category_id', 'limit', 'offset'] as $key) {
			if (isset($filter[$key]) && !is_int($filter[$key])) {
				$filter[$key] = intval($filter[$key]);
			}
		}

		$return = self::get('achievements/progress', $filters);

		return self::return($return, 'progress', '\Cheevos\CheevosAchievementProgress');
	}

	/**
	 * Get progress for an achievement
	 *
	 * @param int $id
	 * @return	mixed
	 */
	public static function getProgressCount($site_key = null, $achievement_id = null) {
		$return = self::get("achievements/progress/count",[
			"achievement_id" => $achievement_id,
			"site_key"	=> $site_key
		]); // return expect array of results. fake it.
		return self::return($return);
	}



	public static function getProgressTop($site_key = null, $ingore_users = [], $achievement_id = null, $limit = 1) {
		$return = self::get("achievements/progress/top",[
			"ignore_users" => implode(",",$ingore_users),
			"site_key"	=> $site_key,
			"achievement_id" => $achievement_id,
			"limit"	=> $limit
		]); // return expect array of results. fake it.
		return self::return($return);
	}

	/**
	 * Get process for achievement
	 *
	 * @param int $id
	 * @return	mixed
	 */
	public static function getProgress($id) {
		$return = [ self::get("achievements/progress/{$id}") ]; // return expect array of results. fake it.
		return self::return($return, 'progress', '\Cheevos\CheevosAchievementProgress', true);
	}

	/**
	 * Delete progress towards an achievement.
	 *
	 * @access	public
	 * @param	integer	Progress ID
	 * @return	mixed
	 */
	public static function deleteProgress($id) {
		$return = self::delete("achievements/progress/{$id}");
		return self::return($return);
	}

	/**
	 * Put process for achievement. Either create or updates.
	 *
	 * @param array $body
	 * @param int $id
	 * @return void
	 */
	public static function putProgress($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievements/progress/{$id}" : "achievements/progress";
		$return = self::put($path, $body);
		return self::return($return);
	}

	/**
	 * Update progress
	 *
	 * @param int $id
	 * @param array $body
	 * @return void
	 */
	public static function updateProgress($id, $body) {
		return self::putProgress($body, $id);
	}

	/**
	 * Create Progress
	 *
	 * @param array $body
	 * @return void
	 */
	public static function createProgress($body) {
		return self::putProgress($body);
	}

	/**
	 * Get all points promotions with caching.
	 *
	 * @access	public
	 * @param 	string	[Optional] Site Key
	 * @param 	boolean	[Optional] Skip Cache Look Up.
	 * @return	mixed	Ouput of self::return.
	 */
	static public function getPointsPromotions($siteKey = null, $skipCache = false) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getPointsPromotions:' . ( $siteKey ? $siteKey : 'all' );

		if (!$skipCache && $redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
			$return = unserialize($cache, [false]);
		}

		if (!$cache || !$return) {
			$return = self::get(
				'points/promotions',
				[
					'site_key' => $siteKey,
					'limit'	=> 0
				]
			);

			try {
				if ($redis !== false && isset($return['promotions'])) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		return self::return($return, 'promotions', '\Cheevos\CheevosSiteEditPointsPromotion');
	}

	/**
	 * Get points promotion by database ID with caching.
	 *
	 * @access	public
	 * @param 	integer	SiteEditPointsPromotion ID
	 * @return	mixed	Ouput of self::return.
	 */
	static public function getPointsPromotion($id) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getPointsPromotion:'.$id;

		if ($redis !== false) {
			try {
				$cache = $redis->get($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get("points/promotions/{$id}");
			try {
				if ($redis !== false) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		$return = [ $return ]; //The return function expects an array of results.
		return self::return($return, 'promotions', '\Cheevos\CheevosSiteEditPointsPromotion', true);
	}

	/**
	 * Soft delete an points promotion from the service.
	 *
	 * @access	public
	 * @param	integer	SiteEditPointsPromotion ID
	 * @param	integer	Global ID
	 * @return	mixed	Array
	 */
	static public function deletePointsPromotion($id) {
		$redis = \RedisCache::getClient('cache');
		$redisKey = 'cheevos:apicache:getPointsPromotion:'.$id;

		if ($redis !== false) {
			try {
				$redis->del($redisKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		}

		$return = self::delete(
			"points/promotions/{$id}"
		);
		return self::return($return);
	}

	/**
	 * PUT PointsPromotion into Cheevos
	 *
	 * @access	public
	 * @param	array	$body
	 * @param	integer	$id
	 * @return	mixed	Output of self::return.
	 */
	public static function putPointsPromotion($body, $id = null) {
		$id = intval($id);
		$body = self::validateBody($body);
		if (!$body) {
			return false;
		}

		$path = ($id > 0 ? "points/promotions/{$id}" : "points/promotions");
		$return = self::put($path, $body);

		if ($id > 0) {
			$redis = \RedisCache::getClient('cache');
			$redisKey = 'cheevos:apicache:getPointsPromotion:'.$id;
			if ($redis !== false) {
				try {
					$redis->del($redisKey);
				} catch (RedisException $e) {
					wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
				}
			}
		}

		return self::return($return);
	}

	/**
	 * Update an existing points promotion on the service.
	 *
	 * @access	public
	 * @param	integer	SiteEditPointsPromotion ID
	 * @param	array	$body
	 * @return	void
	 */
	static public function updatePointsPromotion($id, $body) {
		return self::putPointsPromotion($body, $id);
	}

	/**
	 * Create PointsPromotion
	 *
	 * @param array $body
	 * @return void
	 */
	static public function createPointsPromotion($body) {
		return self::putPointsPromotion($body);
	}

	/**
	 * Revokes edit points for the provided revision IDs related to the page ID.
	 *
	 * @access	public
	 * @param	integer	Page ID
	 * @param	array	Revision IDs
	 * @param	string	Site Key
	 * @return	mixed	Array
	 */
	static public function revokeEditPoints($pageId, $revisionIds, $siteKey) {
		$revisionIds = array_map('intval', $revisionIds);
		$return = self::post(
			"points/revoke_revisions",
			[
				'page_id'		=> intval($pageId),
				'revision_ids'	=> $revisionIds,
				'site_key'		=> $siteKey
			]
		);
		return self::return($return);
	}
}
