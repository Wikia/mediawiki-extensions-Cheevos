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

	const DEFAULT_LIMIT = 200;

	private static function request($type, $path, $data = []) {
		global $wgCheevosHost;

		$host = $wgCheevosHost;
		$type = strtoupper($type);

		$url = "{$host}/{$path}";

		$ch = curl_init();
		curl_setopt_array($ch, array(
		    CURLOPT_RETURNTRANSFER		=> 1,
		    CURLOPT_URL					=> $url,
			CURLOPT_SSL_VERIFYHOST		=> false,
			CURLOPT_SSL_VERIFYPEER		=> false,
			CURLOPT_CUSTOMREQUEST		=> $type,
			CURLOPT_HTTPHEADER			=> [
				'Accept: application/json',
				'Content-Type: application/json'
			]
		));
		if (in_array($type,['DELETE','GET']) && !empty($data)) {
			$url = $url . "/?" . http_build_query($data);
		} else {
			$postData = json_encode($data);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($result, 1);
		return $result;
	}

	private static function get($path, $data = []) {
		return self::request('GET',$path,$data);
	}

	private static function put($path, $data = []) {
		return self::request('PUT',$path,$data);
	}

	private static function delete($path, $data = []) {
		return self::request('DELETE',$path,$data);
	}


	private static function return($return,$expected=null,$class=null) {
		if (isset($return['code']) && $return['code'] !== 200) {
			throw new CheevosException($return['message'], $return['code']);
		}

		if ($expected && isset($return[$expected])) {
			$return = $return[$expected];
		}

		if ($class && class_exists($class)) {
			$holder = [];
			foreach ($return as $classme) {
				$holder[] = new $class($classme);
			}
			$return = $holder;
		}

		return $return;
	}


	public static function getAchievements($siteId=0) {
		$return = self::get('achievements/all',[
			'siteId' => $siteId,
			'limit'	=> 0
		]);

		return self::return($return, 'achievements', 'Cheevos\Achievement');
	}


	public static function getAchievement($id) {
		$return = self::get("achievement/{$id}");
		return self::return($return, 'achievements', 'Cheevos\Achievement');
	}


	public static function deleteAchievement($id, $userId=0) {
		$return = self::delete("achievement/{$id}",[
			"authorId" => $userId
		]);
		return self::return($return);;
	}

	private static function putAchievement($body,$id=null) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$achievement = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement passed.
			}
		}

		$path = ($id) ? "achievement/{$id}" : "achievement";
		$return = self::put($path,$body);
		return self::return($return);;
	}


	public static function updateAchievement($id,$body) {
		return self::putAchievement($body, $id);
	}


	public static function createAchievement($body) {
		return self::putAchievement($body);
	}


	public static function getCategories() {
		$return = self::get('achievement_categories/all',[
			'limit'	=> 0
		]);

		return self::return($return, 'categories', 'Cheevos\AchievementCategory');
	}


	public static function getCategory($id) {
		$return = self::get("achievement_category/{$id}");
		return self::return($return, 'categories', 'Cheevos\AchievementCategory');
	}


	public static function deleteCategory($id, $userId=0) {
		$return = self::delete("achievement_category/{$id}",[
			"authorId" => $userId
		]);
		return self::return($return);;
	}

	private static function putCategory($body,$id=null) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$achievement_category = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement_category passed.
			}
		}

		$path = ($id) ? "achievement_category/{$id}" : "achievement_category";
		$return = self::put($path,$body);
		return self::return($return);;
	}


	public static function updateCategory($id,$body) {
		return self::putCategory($body, $id);
	}


	public static function createCategory($body) {
		return self::putCategory($body);
	}


	/*


	public static function increment($body) {
		if (!is_array($body)) {
			// Valid JSON strings probably acceptable as well, right?
			$achievement = json_decode($body,1);
			if (is_null($body)) {
				return false; // cant decode, no valid achievement passed.
			}
		}

		$result = false;
		try {
			$result = $this->api->incrementPost($body);
		} catch (Exception $e) {
			wfErrorLog('Exception in increment: '. $e->getMessage(). PHP_EOL);
		}
		return $result;
	}

	public static function stats($userId, $siteId, $global, $stat, $limit=null, $offset=null) {
		$result = false;
		try {
		    $result = $this->api->statsGet($userId, $siteId, $global, $stat, $limit, $offset);
		} catch (Exception $e) {
		    echo 'Exception when stats: ', $e->getMessage(), PHP_EOL;
		}
		return $result;
	}

	*/

}

class CheevosException extends \Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
