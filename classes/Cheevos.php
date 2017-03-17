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
			CURLOPT_CUSTOMREQUEST		=> $type,
		));
		if (in_array($type,['DELETE','GET']) && !empty($data)) {
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
	
	private static function get($path, $data = []) {
		return self::request('GET',$path,$data);
	}

	private static function put($path, $data = []) {
		return self::request('PUT',$path,$data);
	}

	private static function post($path, $data = []) {
		return self::request('POST',$path,$data);
	}

	private static function delete($path, $data = []) {
		return self::request('DELETE',$path,$data);
	}


	private static function return($return, $expected = null, $class = null, $single = false) {
		// Throw Errors if we have API errors.
		if ($return === null) {
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
			}
			$return = $holder;

			// If we classify things, single will only return the first.	
			if ($single) {
				$return = $return[0];
			}
		}
		return $return;
	}

	private static function validateBody($body) {
		if (!is_array($body)) {
			$body = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement_category passed.
			} else {
				return $body;
			}
		} else {
			return $body;
		}
	}


	public static function getAchievements($site_key = null) {
		$return = self::get('achievements/all',[
			'site_key' => $site_key,
			'limit'	=> 0
		]);
		return self::return($return, 'achievements', 'Cheevos\CheevosAchievement');
	}


	public static function getAchievement($id) {
		$return = [ self::get("achievement/{$id}") ]; // return expect array of results. fake it.
		return self::return($return, 'achievements', 'Cheevos\CheevosAchievement',true);
	}


	public static function deleteAchievement($id, $userId = null) {
		if (!$userId) {
			global $wgUser;
			$userId = $wgUser->getId();
		}
		$return = self::delete("achievement/{$id}",[
			"author_id" => $userId
		]);
		return self::return($return);;
	}

	private static function putAchievement($body ,$id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievement/{$id}" : "achievement";
		$return = self::put($path,$body);
		return self::return($return);
	}

	public static function updateAchievement($id, $body) {
		return self::putAchievement($body, $id);
	}

	public static function createAchievement($body) {
		return self::putAchievement($body);
	}


	public static function getCategories() {
		$return = self::get('achievement_categories/all',[
			'limit'	=> 0
		]);

		return self::return($return, 'categories', 'Cheevos\CheevosAchievementCategory');
	}

	public static function getCategory($id) {
		$return = [ self::get("achievement_category/{$id}") ]; // return expect array of results. fake it.
		return self::return($return, 'categories', 'Cheevos\CheevosAchievementCategory', true);
	}

	public static function deleteCategory($id, $userId = 0) {
		$return = self::delete("achievement_category/{$id}",[
			"author_id" => $userId
		]);
		return self::return($return);;
	}

	private static function putCategory($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievement_category/{$id}" : "achievement_category";
		$return = self::put($path,$body);
		return self::return($return);
	}

	public static function updateCategory($id, $body) {
		return self::putCategory($body, $id);
	}

	public static function createCategory($body) {
		return self::putCategory($body);
	}

	public static function increment($body) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$return = self::post('increment',$body);	
		return self::return($result);
	}

	public static function stats($data = []) {
		$data['limit'] = isset($data['limit']) ? $data['limit'] : 200;
		$return = self::get('stats',$data);
		return self::return($return,'stats');
	}

	public static function getUserProgress($user_id, $category_id = null, $site_key = null) {
		$return = self::get('achievements/progress',[
			'limit'	=> 0,
			'user_id' => $user_id,
			'category_id' => $category_id,
			'site_key' => $site_key
		]);

		return self::return($return, 'progress', 'Cheevos\CheevosAchievementProgress');
	}

	public static function getProgress($id) {
		$return = [ self::get("achievements/progress/{$id}") ]; // return expect array of results. fake it.
		return self::return($return, 'progress', 'Cheevos\CheevosAchievementProgress', true);
	}


	public static function deleteProgress($id, $userId = 0) {
		$return = self::delete("achievements/progress/{$id}",[
			"author_id" => $userId
		]);
		return self::return($return);;
	}

	public static function putProgress($body, $id = null) {
		$body = self::validateBody($body);
		if (!$body) return false;

		$path = ($id) ? "achievements/progress/{$id}" : "achievements/progress";
		$return = self::put($path,$body);
		return self::return($return);
	}

	public static function updateProgress($id, $body) {
		return self::putProgress($body, $id);
	}

	public static function createProgress($body) {
		return self::putProgress($body);
	}

	// WUT IN TARNATION
	public static function getKnownHooks() {
		return [];
	}
}
