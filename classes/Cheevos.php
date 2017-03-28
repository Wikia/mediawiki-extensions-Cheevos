<?php
/**
 * Achievements
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
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER		=> 1,
			CURLOPT_URL					=> $url,
			CURLOPT_SSL_VERIFYHOST		=> false,
			CURLOPT_SSL_VERIFYPEER		=> false,
			CURLOPT_CUSTOMREQUEST		=> $type
		));
		if (in_array($type, ['DELETE', 'GET']) && !empty($data)) {
			$url = $url . "/?" . http_build_query($data);
		} else {
			$postData = json_encode($data);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			$headers[] = 'Content-Length: ' . strlen($postData);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($result, true);

		return $result;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $path
	 * @param array $data
	 * @return void
	 */
	private static function get($path, $data = []) {
		return self::request('GET', $path, $data);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $path
	 * @param array $data
	 * @return void
	 */
	private static function put($path, $data = []) {
		return self::request('PUT', $path, $data);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $path
	 * @param array $data
	 * @return void
	 */
	private static function post($path, $data = []) {
		return self::request('POST', $path, $data);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $path
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
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @return void
	 */
	private static function validateBody($body) {
		if (!is_array($body)) {
			$body = json_decode($body, 1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement_category passed.
			} else {
				return $body;
			}
		} else {
			return $body;
		}
	}

	public static function invalidateCache() {
		global $wgRedisServers;

		$redis = \RedisCache::getClient('cache');
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
		}
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

		try {
			$cache = $redis->get($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get('achievements/all', [
				'site_key' => $siteKey,
				'limit'	=> 0
			]);

			if (!empty($siteKey) && isset($return['achievements'])) {
				$removeParents = [];
				foreach ($return['achievements'] as $key => $achievement) {
					if (isset($achievement['parent_id']) && $achievement['parent_id'] > 0) {
						$removeParents[] = $achievement['parent_id'];
					}
				}
				if (count($removeParents)) {
					foreach ($return['achievements'] as $key => $achievement) {
						if (isset($achievement['id']) && in_array($achievement['id'], $removeParents)) {
							unset($return['achievements'][$key]);
						}
					}
				}
			}

			try {
				if (isset($return['achievements'])) {
					$redis->setEx($redisKey, 300, serialize($return));
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		return self::return($return, 'achievements', '\Cheevos\CheevosAchievement');
	}

	/**
	 * Get all achievement by database ID with caching.
	 *
	 * @access	public
	 * @param 	integer	Achievement ID
	 * @return	mixed	Ouput of self::return.
	 */
	public static function getAchievement($id) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getAchievement:' . $id;

		try {
			$cache = $redis->get($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get("achievement/{$id}");
			try {
				$redis->setEx($redisKey, 300, serialize($return));
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		$return = [ $return ]; //The return function expects an array of results.
		return self::return($return, 'achievements', 'Cheevos\CheevosAchievement', true);
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
		return self::return($return);;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @param [type] $id
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
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @return void
	 */
	public static function createAchievement($body) {
		return self::putAchievement($body);
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function getCategories() {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getCategories';

		try {
			$cache = $redis->get($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get('achievement_categories/all', [
				'limit'	=> 0
			]);
			try {
				$redis->setEx($redisKey, 300, serialize($return));
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		return self::return($return, 'categories', 'Cheevos\CheevosAchievementCategory');
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @return void
	 */
	public static function getCategory($id) {
		$redis = \RedisCache::getClient('cache');
		$cache = false;
		$redisKey = 'cheevos:apicache:getCategory:' . $id;

		try {
			$cache = $redis->get($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		if (!$cache || !unserialize($cache)) {
			$return = self::get("achievement_category/{$id}");
			try {
				$redis->setEx($redisKey, 300, serialize($return));
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		$return = [ $return ]; // return expect array of results. fake it.
		return self::return($return, 'categories', 'Cheevos\CheevosAchievementCategory', true);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
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
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @param [type] $id
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
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @param [type] $body
	 * @return void
	 */
	public static function updateCategory($id, $body) {
		return self::putCategory($body, $id);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $body
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
	 * Undocumented function
	 *
	 * @param [type] $globalId
	 * @param [type] $siteKey
	 * @param boolean $forceRecalculate
	 * @return void
	 */
	static public function checkUnnotified($globalId, $siteKey, $forceRecalculate = false) {
		if (empty($globalId) || empty($siteKey)) {
			return;
		}

		$data = [
			'user_id' => intval($globalId),
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
	 * 			'user_id'	=> 0, //Limit by global user ID.
	 * 			'site_key'	=> 'example', //Limit by site key.
	 * 			'global'	=> false, //Set to true to aggregate stats from all sites.(Also causes site_key to be ignored.)
	 * 			'stat'		=> 'example', //Filter by a specific stat name.
	 * 			'limit'		=> 200, //Maximum number of results.  Defaults to 200.
	 * 			'offset'	=> 0, //Offset to start from the beginning of the result set.
	 * 		];
	 * @return	mixed
	 */
	public static function getStatProgress($filters = []) {
		foreach (['user_id', 'limit', 'offset'] as $key) {
			if (isset($filter[$key]) && !is_int($filter[$key])) {
				$filter[$key] = intval($filter[$key]);
			}
		}
		$filters['limit'] = (isset($filters['limit']) ? $filters['limit'] : 200);

		$return = self::get('stats', $filters);

		return self::return($return, 'stats');
	}

	/**
	 * Undocumented function
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	string	Site Key - From DynamicSettings
	 * @return	mixed
	 */
	public static function getUserStatus($globalId, $siteKey = null) {
		$return = self::get(
			'achievements/status',
			[
				'limit'	=> 0,
				'user_id' => intval($globalId),
				'site_key' => $siteKey
			]
		);

		return self::return($return, 'status', 'Cheevos\CheevosAchievementStatus');
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

		return self::return($return, 'progress', 'Cheevos\CheevosAchievementProgress');
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
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
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @return	mixed
	 */
	public static function getProgress($id) {
		$return = [ self::get("achievements/progress/{$id}") ]; // return expect array of results. fake it.
		return self::return($return, 'progress', 'Cheevos\CheevosAchievementProgress', true);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @param int $globalId
	 * @return void
	 */
	public static function deleteProgress($id, $globalId = 0) {
		$return = self::delete("achievements/progress/{$id}", [
			"author_id" => $globalId
		]);
		return self::return($return);;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @param [type] $id
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
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @param [type] $body
	 * @return void
	 */
	public static function updateProgress($id, $body) {
		return self::putProgress($body, $id);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $body
	 * @return void
	 */
	public static function createProgress($body) {
		return self::putProgress($body);
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function getKnownHooks() {
		return [];
	}
}
