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
					$holder[] = new $class($classme);
				}
				if ($single) {
					break;
				}
			}
			$return = $holder;

			// If we classify things, single will only return the first.
			if ($single) {
				$return = $return[0];
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

	/**
	 * Get all achievements with caching.
	 *
	 * @access	public
	 * @param 	string	MD5 Hash Site Key
	 * @return	object
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

			try {
				$redis->setEx($redisKey, 300, serialize($return));
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
		} else {
			$return = unserialize($cache);
		}

		return self::return($return, 'achievements', 'Cheevos\CheevosAchievement');
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @return void
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

		$return = [ $return ]; // return expect array of results. fake it.
		return self::return($return, 'achievements', 'Cheevos\CheevosAchievement', true);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @param [type] $userId
	 * @return void
	 */
	public static function deleteAchievement($id, $userId = null) {
		if (!$userId) {
			global $wgUser;
			$userId = $wgUser->getId();
		}
		$return = self::delete("achievement/{$id}", [
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
	private static function putAchievement($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievement/{$id}" : "achievement";
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
	 * Undocumented function
	 *
	 * @param array $data
	 * @return void
	 */
	public static function stats($data = []) {
		$data['limit'] = isset($data['limit']) ? $data['limit'] : 200;
		$return = self::get('stats', $data);
		return self::return($return, 'stats');
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $globalId
	 * @param [type] $categoryId
	 * @param [type] $siteKey
	 * @return void
	 */
	public static function getUserProgress($globalId, $categoryId = null, $siteKey = null) {
		$return = self::get('achievements/progress', [
			'limit'	=> 0,
			'user_id' => $globalId,
			'category_id' => $categoryId,
			'site_key' => $siteKey
		]);

		return self::return($return, 'progress', 'Cheevos\CheevosAchievementProgress');
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $id
	 * @return void
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
